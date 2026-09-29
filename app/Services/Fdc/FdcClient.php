<?php

namespace App\Services\Fdc;

use App\Services\Fdc\Exceptions\FdcCircuitOpenException;
use App\Services\Fdc\Exceptions\FdcRateLimitExceededException;
use App\Services\Fdc\Exceptions\FdcRequestFailedException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client for USDA FoodData Central (FDC) — https://fdc.nal.usda.gov/api-guide.html
 *
 * Responsibilities (architecture doc §1, §5):
 *  - wrap FDC's REST API (search + food-detail lookup) behind a small typed
 *    interface so callers never touch Http::/config directly;
 *  - cache successful responses so repeated lookups (e.g. re-importing a
 *    common ingredient across many recipes) don't re-hit the network or the
 *    1,000 req/hr/IP budget;
 *  - retry transient failures with exponential backoff;
 *  - trip a circuit breaker on sustained failure so a bad FDC outage
 *    degrades gracefully (stale nutrition data) instead of failing imports
 *    outright;
 *  - enforce the job-level rate limit up front so we never even attempt a
 *    call that would blow the hourly budget.
 *
 * This class does not decide *what* to do when FDC is unavailable — that's
 * the calling job's job (e.g. MatchIngredientsJob marks nutrition `stale`
 * and moves on). It only reports failures via typed exceptions so callers
 * can make that decision deliberately.
 */
class FdcClient
{
    private const BASE_URL = 'https://api.nal.usda.gov/fdc/v1';

    private const CACHE_TTL_SECONDS = 60 * 60 * 24 * 7; // 7 days: FDC nutrient data changes rarely.

    public function __construct(
        private readonly FdcRateLimiter $rateLimiter,
        private readonly FdcCircuitBreaker $circuitBreaker,
        private readonly ?string $apiKey = null,
        private readonly int $maxAttempts = 3,
        private readonly int $baseBackoffMs = 250,
        private readonly int $timeoutSeconds = 10,
    ) {}

    public static function make(): self
    {
        return new self(
            rateLimiter: new FdcRateLimiter(
                maxRequestsPerHour: (int) config('services.fdc.rate_limit_per_hour', 1000),
            ),
            circuitBreaker: new FdcCircuitBreaker(
                failureThreshold: (int) config('services.fdc.circuit_failure_threshold', 5),
                cooldownSeconds: (int) config('services.fdc.circuit_cooldown_seconds', 60),
            ),
            apiKey: config('services.fdc.api_key'),
            maxAttempts: (int) config('services.fdc.max_attempts', 3),
            baseBackoffMs: (int) config('services.fdc.base_backoff_ms', 250),
            timeoutSeconds: (int) config('services.fdc.timeout_seconds', 10),
        );
    }

    /**
     * Search FDC for foods matching a free-text query (e.g. a raw ingredient
     * name pulled from a recipe line). Returns the raw decoded JSON `foods`
     * array (each item has at least `fdcId`, `description`, `dataType`,
     * `foodCategory`).
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchFoods(string $query, int $pageSize = 10): array
    {
        $cacheKey = $this->cacheKey('search', $query, $pageSize);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($query, $pageSize) {
            $response = $this->request('GET', '/foods/search', [
                'query' => $query,
                'pageSize' => $pageSize,
                // NOTE: "Survey (FNDDS)" is a valid FDC dataType but its
                // parentheses trip FDC's edge WAF into a blanket 400 on this
                // endpoint (confirmed via manual curl testing 2026-09-29) —
                // deliberately excluded here. Foundation + SR Legacy +
                // Branded cover our raw/whole-food and packaged-food needs.
                'dataType' => ['Foundation', 'SR Legacy', 'Branded'],
            ]);

            return $response->json('foods', []);
        });
    }

    /**
     * Fetch full detail (including nutrient breakdown) for a single FDC food
     * by its fdcId.
     *
     * @return array<string, mixed>
     */
    public function getFood(int|string $fdcId): array
    {
        $cacheKey = $this->cacheKey('food', (string) $fdcId);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($fdcId) {
            $response = $this->request('GET', "/food/{$fdcId}");

            return $response->json() ?? [];
        });
    }

    /**
     * Core request wrapper: enforces rate limit + circuit breaker, retries
     * transient failures with exponential backoff, and never throws for
     * business-as-usual "no results" — only for actual failure to get a
     * usable response.
     *
     * @param  array<string, mixed>  $params
     *
     * @throws FdcRateLimitExceededException
     * @throws FdcCircuitOpenException
     * @throws FdcRequestFailedException
     */
    private function request(string $method, string $path, array $params = []): Response
    {
        if (! $this->circuitBreaker->allowsRequest()) {
            throw new FdcCircuitOpenException(
                "FDC circuit breaker is open; skipping {$method} {$path} to avoid piling onto a failing dependency."
            );
        }

        if (! $this->rateLimiter->attempt()) {
            throw new FdcRateLimitExceededException(
                "FDC job-level rate limit (per-hour) exhausted; retry after {$this->rateLimiter->secondsUntilReset()}s."
            );
        }

        $lastException = null;

        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            try {
                $response = $this->pendingRequest()->{strtolower($method)}($path, $params);

                if ($response->successful()) {
                    $this->circuitBreaker->recordSuccess();

                    return $response;
                }

                // 429 (rate limited by FDC itself) and 5xx are worth retrying;
                // 4xx other than 429 (bad request/key) is not.
                if ($response->status() === 429 || $response->serverError()) {
                    $lastException = new FdcRequestFailedException(
                        "FDC responded {$response->status()} for {$method} {$path} (attempt {$attempt}/{$this->maxAttempts})"
                    );

                    $this->sleepBackoff($attempt);

                    continue;
                }

                // Non-retryable client error: fail fast, still counts against
                // the circuit breaker since it's an unexpected condition.
                $this->circuitBreaker->recordFailure();

                throw new FdcRequestFailedException(
                    "FDC responded {$response->status()} for {$method} {$path}: ".$response->body()
                );
            } catch (ConnectionException $e) {
                $lastException = new FdcRequestFailedException(
                    "FDC connection error for {$method} {$path} (attempt {$attempt}/{$this->maxAttempts}): ".$e->getMessage(),
                    previous: $e,
                );

                $this->sleepBackoff($attempt);
            }
        }

        $this->circuitBreaker->recordFailure();

        Log::warning('fdc.request_failed_after_retries', [
            'method' => $method,
            'path' => $path,
            'attempts' => $this->maxAttempts,
        ]);

        throw $lastException ?? new FdcRequestFailedException("FDC request failed for {$method} {$path}");
    }

    private function pendingRequest(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->timeout($this->timeoutSeconds)
            ->withQueryParameters(['api_key' => $this->apiKey ?: 'DEMO_KEY'])
            ->acceptJson();
    }

    private function sleepBackoff(int $attempt): void
    {
        if ($attempt >= $this->maxAttempts) {
            return;
        }

        // Exponential backoff with a little jitter: base * 2^(attempt-1) +/- 20%.
        $delayMs = $this->baseBackoffMs * (2 ** ($attempt - 1));
        $jitterMs = (int) ($delayMs * (mt_rand(-20, 20) / 100));

        usleep(max(0, $delayMs + $jitterMs) * 1000);
    }

    private function cacheKey(string ...$parts): string
    {
        return 'fdc:'.implode(':', array_map(
            fn (string $p) => str_replace([' ', ':'], '_', strtolower($p)),
            $parts,
        ));
    }
}
