<?php

namespace Tests\Feature\Services\Fdc;

use App\Services\Fdc\FdcCircuitBreaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FdcCircuitBreakerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_allows_requests_while_closed(): void
    {
        $breaker = new FdcCircuitBreaker(failureThreshold: 3, cooldownSeconds: 60);

        $this->assertTrue($breaker->allowsRequest());
        $this->assertFalse($breaker->isOpen());
    }

    public function test_trips_open_after_threshold_failures(): void
    {
        $breaker = new FdcCircuitBreaker(failureThreshold: 3, cooldownSeconds: 60);

        $breaker->recordFailure();
        $breaker->recordFailure();
        $this->assertTrue($breaker->allowsRequest());

        $breaker->recordFailure();
        $this->assertTrue($breaker->isOpen());
        $this->assertFalse($breaker->allowsRequest());
    }

    public function test_success_resets_failure_count(): void
    {
        $breaker = new FdcCircuitBreaker(failureThreshold: 3, cooldownSeconds: 60);

        $breaker->recordFailure();
        $breaker->recordFailure();
        $breaker->recordSuccess();
        $breaker->recordFailure();
        $breaker->recordFailure();

        // Only 2 consecutive failures since the reset — still closed.
        $this->assertFalse($breaker->isOpen());
        $this->assertTrue($breaker->allowsRequest());
    }

    public function test_half_open_probe_failure_reopens_immediately(): void
    {
        $breaker = new FdcCircuitBreaker(failureThreshold: 1, cooldownSeconds: 1);

        $breaker->recordFailure(); // trips open
        $this->assertFalse($breaker->allowsRequest()); // still within cooldown

        sleep(2); // let cooldown elapse

        $this->assertTrue($breaker->allowsRequest()); // transitions to half-open, allows probe
        $breaker->recordFailure(); // probe failed -> re-opens immediately

        $this->assertTrue($breaker->isOpen());
    }

    public function test_half_open_probe_success_closes_breaker(): void
    {
        $breaker = new FdcCircuitBreaker(failureThreshold: 1, cooldownSeconds: 1);

        $breaker->recordFailure();
        sleep(2);
        $this->assertTrue($breaker->allowsRequest()); // half-open probe allowed
        $breaker->recordSuccess();

        $this->assertFalse($breaker->isOpen());
        $this->assertTrue($breaker->allowsRequest());
    }
}
