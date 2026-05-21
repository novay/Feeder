<?php

namespace Novay\Feeder;

use Closure;
use Novay\Feeder\Exceptions\FeederException;
use Novay\Feeder\Testing\FeederFake;

class FeederManager
{
    /**
     * @var array<string, \Novay\Feeder\FeederClient>
     */
    protected array $clients = [];

    protected ?FeederFake $fake = null;

    public function connection(?string $name = null): FeederClient
    {
        $name = $name ?: $this->defaultConnection();

        if (isset($this->clients[$name])) {
            return $this->clients[$name];
        }

        $client = new FeederClient(
            connection: $name,
            config: $this->resolveConnectionConfig($name)
        );

        if ($this->fake) {
            $client->fake($this->fake);
        }

        $this->clients[$name] = $client;

        return $client;
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

    public function fake(array $responses = []): static
    {
        $this->fake = new FeederFake($responses);

        foreach ($this->clients as $client) {
            $client->fake($this->fake);
        }

        return $this;
    }

    public function fakeForConnection(string $connection, array $responses = []): static
    {
        $this->fake ??= new FeederFake();

        $this->fake->mergeForConnection($connection, $responses);

        foreach ($this->clients as $client) {
            $client->fake($this->fake);
        }

        return $this;
    }

    public function restoreFake(): static
    {
        $this->fake = null;

        foreach ($this->clients as $client) {
            $client->restoreFake();
        }

        return $this;
    }

    public function recorded(?string $connection = null): array
    {
        if ($connection) {
            return $this->connection($connection)->recorded();
        }

        return collect($this->clients)
            ->flatMap(fn(FeederClient $client) => $client->recorded())
            ->values()
            ->all();
    }

    public function assertSent(string $act, ?Closure $callback = null, ?string $connection = null): void
    {
        if ($connection) {
            $this->connection($connection)->assertSent($act, $callback);

            return;
        }

        $sent = collect($this->recorded())
            ->contains(function (array $request) use ($act, $callback) {
                if ($request['act'] !== $act) {
                    return false;
                }

                return $callback ? $callback($request) : true;
            });

        $this->assertTrue(
            $sent,
            "Failed asserting that Feeder act [{$act}] was sent."
        );
    }

    public function assertNotSent(string $act, ?Closure $callback = null, ?string $connection = null): void
    {
        if ($connection) {
            $this->connection($connection)->assertNotSent($act, $callback);

            return;
        }

        $sent = collect($this->recorded())
            ->contains(function (array $request) use ($act, $callback) {
                if ($request['act'] !== $act) {
                    return false;
                }

                return $callback ? $callback($request) : true;
            });

        $this->assertFalse(
            $sent,
            "Failed asserting that Feeder act [{$act}] was not sent."
        );
    }

    public function assertSentTimes(string $act, int $times, ?string $connection = null): void
    {
        if ($connection) {
            $this->connection($connection)->assertSentTimes($act, $times);

            return;
        }

        $actual = collect($this->recorded())
            ->where('act', $act)
            ->count();

        $this->assertSame(
            $times,
            $actual,
            "Failed asserting that Feeder act [{$act}] was sent {$times} times. Actually sent {$actual} times."
        );
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

    protected function assertTrue(bool $condition, string $message): void
    {
        if (class_exists(\PHPUnit\Framework\Assert::class)) {
            \PHPUnit\Framework\Assert::assertTrue($condition, $message);

            return;
        }

        if (! $condition) {
            throw new FeederException(message: $message);
        }
    }

    protected function assertFalse(bool $condition, string $message): void
    {
        if (class_exists(\PHPUnit\Framework\Assert::class)) {
            \PHPUnit\Framework\Assert::assertFalse($condition, $message);

            return;
        }

        if ($condition) {
            throw new FeederException(message: $message);
        }
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message): void
    {
        if (class_exists(\PHPUnit\Framework\Assert::class)) {
            \PHPUnit\Framework\Assert::assertSame($expected, $actual, $message);

            return;
        }

        if ($expected !== $actual) {
            throw new FeederException(message: $message);
        }
    }
}
