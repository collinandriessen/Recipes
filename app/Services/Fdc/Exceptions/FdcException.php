<?php

namespace App\Services\Fdc\Exceptions;

use Exception;

/**
 * Base exception for all FDC client failures. Callers (jobs) should catch
 * this specifically rather than a generic Throwable so that unrelated bugs
 * don't get silently swallowed by the "don't fail the import" policy.
 */
class FdcException extends Exception {}
