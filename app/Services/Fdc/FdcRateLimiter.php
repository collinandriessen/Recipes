<?php

namespace App\Services\Fdc;

use Illuminate\Support\Facades\Cache;

/**
 * Job-level rate limiter for USDA FoodData Central.
 *
 * FDC enforces 1,000 requests/hour per API key/IP. This limiter is a sliding
 * fixed-window counter backed by the cache store so it works correctly across
 * multiple queue workers (all FDC calls happen inside queued jobs, never
 * inline in a web request). Callers should check {@see self::attempt()}
 * before making a request; when it returns false the job should release
 * itself back onto the queue with a delay rather than failing.
 */
class FdcRateLimiter
{
    private const CACHE_PREFIX = 'fdc_rate_limit';

    public function __construct(
        private readonly int $maxRequestsPerHour = 1000,
    ) {}

    /**
     * Attempt to consume one request slot for the current hour window.
     * Returns true if the request is allowed to proceed, false if the
     * caller should back off.
     */
    public function attempt(): bool
    {
        $key = $this->windowKey();

        $current = Cache::get($key, 0);

        if ($current >= $this->maxRequestsPerHour) {
            return false;
        }

        // Cache::add would be ideal for the first increment, but we need an
        // atomic increment either way; Cache::increment works across the
        // shared stores we support (redis/database/array) and we set the
        // window's expiry only when we're the one creating it.
        $new = Cache::increment($key);

        if ($new === 1) {
            Cache::put($key, 1, now()->addHour());
        }

        if ($new > $this->maxRequestsPerHour) {
            return false;
        }

        return true;
    }

    public function remaining(): int
    {
        $used = Cache::get($this->windowKey(), 0);

        return max(0, $this->maxRequestsPerHour - $used);
    }

    /**
     * Seconds until the current rate-limit window resets. Used by callers to
     * decide how long to delay a re-queued job.
     */
    public function secondsUntilReset(): int
    {
        return 3600 - (time() % 3600);
    }

    private function windowKey(): string
    {
        // Fixed hourly window keyed by the calendar hour, matching FDC's
        // "requests per hour" semantics closely enough for our volume.
        return self::CACHE_PREFIX.':'.now()->format('Y-m-d-H');
    }
}
