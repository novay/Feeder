<?php

namespace Novay\Feeder\Exceptions;

use RuntimeException;
use Throwable;

class FeederException extends RuntimeException
{
    public function __construct(
        string $message = 'Terjadi kesalahan pada API Feeder.',
        public readonly ?int $feederErrorCode = null,
        public readonly ?array $response = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
