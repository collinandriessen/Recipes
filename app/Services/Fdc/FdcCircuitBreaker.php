<?php

namespace App\Services\Fdc;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Simple cache-backed circuit breaker for the FDC client.
 *
 * When FDC calls are failing repeatedly, we want the rest of the import
 * pipeline to keep working (per architecture §5: "FDC calls must not fail
 * imports outright"). This breaker trips after a run of consecutive
 * failures, short-circuits further calls for a cooldown window, and lets a
 * single "probe" call through afterwards to test recovery (half-open state).
 */
class FdcCircuitBreaker
{
    private const STATE_CLOSED = 'closed';

    private const STATE_OPEN = 'open';

    private const STATE_HALF_OPEN = 'half_open';

    public function __construct(
        private readonly int $failureThreshold = 5,
        private readonly int $cooldownSeconds = 60,
    ) {}

    /**
     * Whether a call is currently allowed to go out to FDC.
     */
    public function allowsRequest(): bool
    {
        $state = $this->state();

        if ($state === self::STATE_OPEN) {
            if ($this->openedAt() !== null && (time() - $this->openedAt()) >= $this->cooldownSeconds) {
                $this->transitionTo(self::STATE_HALF_OPEN);

                return true;
            }

            return false;
        }

        // Closed and half-open both allow the request; half-open allows
        // exactly one probe at a time by design of recordSuccess/recordFailure.
        return true;
    }

    public function recordSuccess(): void
    {
        if ($this->state() !== self::STATE_CLOSED) {
            Log::info('fdc.circuit_breaker.closed', ['previous_state' => $this->state()]);
        }

        Cache::forget($this->key('failures'));
        Cache::forget($this->key('opened_at'));
        $this->transitionTo(self::STATE_CLOSED);
    }

    public function recordFailure(): void
    {
        $state = $this->state();

        if ($state === self::STATE_HALF_OPEN) {
            // Probe failed: re-open immediately, reset cooldown clock.
            $this->trip();

            return;
        }

        $failures = Cache::increment($this->key('failures'));

        if ($failures === 1) {
            // Ensure the counter itself doesn't live forever if it never
            // reaches threshold.
            Cache::put($this->key('failures'), 1, now()->addMinutes(10));
        }

        if ($failures >= $this->failureThreshold) {
            $this->trip();
        }
    }

    public function isOpen(): bool
    {
        return $this->state() === self::STATE_OPEN
            && ! ($this->openedAt() !== null && (time() - $this->openedAt()) >= $this->cooldownSeconds);
    }

    private function trip(): void
    {
        Log::warning('fdc.circuit_breaker.opened', ['cooldown_seconds' => $this->cooldownSeconds]);
        Cache::put($this->key('opened_at'), time(), now()->addMinutes(30));
        $this->transitionTo(self::STATE_OPEN);
    }

    private function state(): string
    {
        return Cache::get($this->key('state'), self::STATE_CLOSED);
    }

    private function openedAt(): ?int
    {
        return Cache::get($this->key('opened_at'));
    }

    private function transitionTo(string $state): void
    {
        Cache::put($this->key('state'), $state, now()->addMinutes(30));
    }

    private function key(string $suffix): string
    {
        return "fdc_circuit_breaker:{$suffix}";
    }
}
