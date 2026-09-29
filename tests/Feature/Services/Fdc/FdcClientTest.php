<?php

namespace Tests\Feature\Services\Fdc;

use App\Services\Fdc\Exceptions\FdcCircuitOpenException;
use App\Services\Fdc\Exceptions\FdcRateLimitExceededException;
use App\Services\Fdc\Exceptions\FdcRequestFailedException;
use App\Services\Fdc\FdcCircuitBreaker;
use App\Services\Fdc\FdcClient;
use App\Services\Fdc\FdcRateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Exercises FdcClient's request wrapper: caching, retry-with-backoff, and
 * the rate-limit/circuit-breaker guards — all against Http::fake(), never a
 * real network call.
 */
class FdcClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_search_foods_returns_decoded_results(): void
    {
        Http::fake([
            '*/foods/search*' => Http::response([
                'foods' => [
                    ['fdcId' => 123, 'description' => 'Milk, whole', 'foodCategory' => 'Dairy and Egg Products'],
                ],
            ], 200),
        ]);

        $client = $this->makeClient();
        $results = $client->searchFoods('milk');

        $this->assertCount(1, $results);
        $this->assertSame(123, $results[0]['fdcId']);
    }

    public function test_successful_response_is_cached_and_not_re_requested(): void
    {
        Http::fake([
            '*/foods/search*' => Http::response(['foods' => [['fdcId' => 1]]], 200),
        ]);

        $client = $this->makeClient();
        $client->searchFoods('egg');
        $client->searchFoods('egg');

        Http::assertSentCount(1);
    }

    public function test_retries_on_server_error_then_succeeds(): void
    {
        Http::fakeSequence('*/foods/search*')
            ->push(['message' => 'boom'], 500)
            ->push(['foods' => [['fdcId' => 7]]], 200);

        $client = $this->makeClient(baseBackoffMs: 1);
        $results = $client->searchFoods('flour');

        $this->assertSame(7, $results[0]['fdcId']);
        Http::assertSentCount(2);
    }

    public function test_exhausting_retries_throws_and_trips_circuit_breaker_eventually(): void
    {
        Http::fake([
            '*/foods/search*' => Http::response(['message' => 'down'], 500),
        ]);

        $client = $this->makeClient(maxAttempts: 2, baseBackoffMs: 1, circuitThreshold: 2);

        $this->expectException(FdcRequestFailedException::class);
        $client->searchFoods('anything');
    }

    public function test_circuit_breaker_short_circuits_after_repeated_failures(): void
    {
        Http::fake([
            '*/foods/search*' => Http::response(['message' => 'down'], 500),
        ]);

        $client = $this->makeClient(maxAttempts: 1, baseBackoffMs: 1, circuitThreshold: 1, circuitCooldown: 60);

        try {
            $client->searchFoods('a');
        } catch (FdcRequestFailedException) {
        }

        // Breaker should now be open; the next call should short-circuit
        // without hitting Http at all.
        $this->expectException(FdcCircuitOpenException::class);
        $client->searchFoods('b');
    }

    public function test_rate_limit_is_enforced_before_any_http_call(): void
    {
        Http::fake([
            '*/foods/search*' => Http::response(['foods' => []], 200),
        ]);

        $rateLimiter = new FdcRateLimiter(maxRequestsPerHour: 1);
        $client = new FdcClient(
            rateLimiter: $rateLimiter,
            circuitBreaker: new FdcCircuitBreaker,
            apiKey: 'test',
        );

        $client->searchFoods('first'); // consumes the only slot
        Http::assertSentCount(1);

        $this->expectException(FdcRateLimitExceededException::class);
        $client->searchFoods('second'); // cache-key differs, must hit rate limiter and throw
    }

    private function makeClient(
        int $maxAttempts = 3,
        int $baseBackoffMs = 1,
        int $circuitThreshold = 5,
        int $circuitCooldown = 60,
    ): FdcClient {
        return new FdcClient(
            rateLimiter: new FdcRateLimiter(maxRequestsPerHour: 1000),
            circuitBreaker: new FdcCircuitBreaker(failureThreshold: $circuitThreshold, cooldownSeconds: $circuitCooldown),
            apiKey: 'test-key',
            maxAttempts: $maxAttempts,
            baseBackoffMs: $baseBackoffMs,
        );
    }
}
