<?php

namespace App\Exceptions;

use RuntimeException;

class BoatRentalException extends RuntimeException
{
    public const SLOT_UNAVAILABLE = 'SLOT_UNAVAILABLE';

    public const HOLD_EXPIRED = 'HOLD_EXPIRED';

    public const HOLD_USED = 'HOLD_USED';

    public const HOLD_INVALID = 'HOLD_INVALID';

    public const DUPLICATE_REQUEST = 'DUPLICATE_REQUEST';

    public function __construct(
        public readonly string $errorCode,
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
