<?php

namespace App\Services\Fdc\Exceptions;

/**
 * Thrown after retries are exhausted for a request that ultimately failed
 * (network error or non-2xx response from FDC).
 */
class FdcRequestFailedException extends FdcException {}
