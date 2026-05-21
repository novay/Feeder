<?php

namespace Novay\Feeder;

use Novay\Feeder\Exceptions\FeederException;

class FeederManager
{
    /**
     * @var array<string, \Novay\Feeder\FeederClient>
     */
    protected array $clients = [];

    public function connection(?string $name = null): FeederClient
    {
        $name = $name ?: $this->defaultConnection();

        if (isset($this->clients[$name])) {
            return $this->clients[$name];
        }

        $this->clients[$name] = new FeederClient(
            connection: $name,
            config: $this->resolveConnectionConfig($name)
        );

        return $this->clients[$name];
    }

    public function post(string $act, array $payload = []): array
    {
        return $this->connection()->post($act, $payload);
    }

    public function response(string $act, array $payload = []): array
    {
        return $this->connection()->response($act, $payload);
    }

    public function token(bool $force = false): string
    {
        return $this->connection()->token($force);
    }

    public function clearToken(): void
    {
        $this->connection()->clearToken();
    }

    public function defaultConnection(): string
    {
        return (string) config('feeder.default', 'live');
    }

    protected function resolveConnectionConfig(string $name): array
    {
        $connection = config("feeder.connections.{$name}");

        if (! is_array($connection)) {
            throw new FeederException(
                message: "Feeder connection [{$name}] tidak ditemukan."
            );
        }

        $base = [
            'token' => config('feeder.token', []),
            'http' => config('feeder.http', []),
            'logging' => config('feeder.logging', []),
            'auto_refresh_token' => config('feeder.auto_refresh_token', true),
            'token_error_keywords' => config('feeder.token_error_keywords', []),
        ];

        return array_replace_recursive($base, $connection);
    }
}
