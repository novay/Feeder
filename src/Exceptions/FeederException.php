<?php

namespace Novay\Feeder\Exceptions;

use RuntimeException;
use Throwable;

class FeederException extends RuntimeException
{
    public function __construct(
        string $message = 'Terjadi kesalahan pada API Feeder.',
        public readonly ?string $connection = null,
        public readonly ?string $act = null,
        public readonly ?int $feederErrorCode = null,
        public readonly ?array $response = null,
        public readonly array $context = [],
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function context(): array
    {
        return [
            'connection' => $this->connection,
            'act' => $this->act,
            'feeder_error_code' => $this->feederErrorCode,
            'response' => $this->response,
            'context' => $this->context,
        ];
    }
}
