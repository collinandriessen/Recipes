<?php

namespace App\Services\Fdc\Exceptions;

/**
 * Thrown when the job-level rate limiter has no remaining budget for the
 * current hourly window. Callers should treat this as "try again later",
 * not as a permanent failure.
 */
class FdcRateLimitExceededException extends FdcException {}
