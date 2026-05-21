<?php

namespace Novay\Feeder\Testing;

use Closure;
use Novay\Feeder\Exceptions\FeederException;

class FeederFake
{
    public function __construct(
        protected array $responses = [],
    ) {}

    public function merge(array $responses): static
    {
        $this->responses = array_replace($this->responses, $responses);

        return $this;
    }

    public function mergeForConnection(string $connection, array $responses): static
    {
        $prefixed = [];

        foreach ($responses as $act => $response) {
            $prefixed["{$connection}.{$act}"] = $response;
        }

        return $this->merge($prefixed);
    }

    public function response(string $connection, string $act, array $payload = []): array
    {
        $fake = $this->resolveFakeResponse($connection, $act);

        if ($fake instanceof Closure) {
            $fake = $fake($act, $payload, $connection);
        }

        return $this->normalizeResponse($fake);
    }

    protected function resolveFakeResponse(string $connection, string $act): mixed
    {
        $candidates = [
            "{$connection}.{$act}",
            "{$connection}.*",
            $act,
            '*',
        ];

        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $this->responses)) {
                return $this->responses[$candidate];
            }
        }

        throw new FeederException(
            message: "Fake response untuk act [{$act}] pada connection [{$connection}] belum didefinisikan."
        );
    }

    protected function normalizeResponse(mixed $response): array
    {
        if (
            is_array($response)
            && array_key_exists('error_code', $response)
        ) {
            return array_replace([
                'error_code' => 0,
                'error_desc' => '',
                'data' => [],
            ], $response);
        }

        return [
            'error_code' => 0,
            'error_desc' => '',
            'data' => $response,
        ];
    }
}
