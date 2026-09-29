<?php

namespace Tests\Feature\Services\Fdc;

use App\Services\Fdc\FdcRateLimiter;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FdcRateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_allows_requests_under_the_limit(): void
    {
        $limiter = new FdcRateLimiter(maxRequestsPerHour: 3);

        $this->assertTrue($limiter->attempt());
        $this->assertTrue($limiter->attempt());
        $this->assertTrue($limiter->attempt());
        $this->assertSame(0, $limiter->remaining());
    }

    public function test_blocks_requests_once_the_hourly_cap_is_hit(): void
    {
        $limiter = new FdcRateLimiter(maxRequestsPerHour: 2);

        $this->assertTrue($limiter->attempt());
        $this->assertTrue($limiter->attempt());
        $this->assertFalse($limiter->attempt());
    }

    public function test_remaining_reflects_consumed_budget(): void
    {
        $limiter = new FdcRateLimiter(maxRequestsPerHour: 1000);

        $this->assertSame(1000, $limiter->remaining());
        $limiter->attempt();
        $this->assertSame(999, $limiter->remaining());
    }

    public function test_default_matches_fdc_documented_cap(): void
    {
        $limiter = new FdcRateLimiter;

        $this->assertSame(1000, $limiter->remaining());
    }
}
