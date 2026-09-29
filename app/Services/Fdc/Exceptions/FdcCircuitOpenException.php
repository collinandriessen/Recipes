<?php

namespace App\Services\Fdc\Exceptions;

/**
 * Thrown when the circuit breaker is open (FDC has been failing repeatedly)
 * and the client short-circuited the call rather than hitting the network.
 */
class FdcCircuitOpenException extends FdcException {}
