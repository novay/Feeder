<?php

namespace Novay\Feeder;

use Closure;
use Illuminate\Http\Client\ConnectionException as LaravelConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException as LaravelRequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Novay\Feeder\Exceptions\FeederAuthenticationException;
use Novay\Feeder\Exceptions\FeederConnectionException;
use Novay\Feeder\Exceptions\FeederException;
use Novay\Feeder\Exceptions\FeederResponseException;
use Novay\Feeder\Exceptions\FeederTokenException;
use Novay\Feeder\Testing\FeederFake;
use Throwable;

class FeederClient
{
    protected ?FeederFake $fake = null;

    protected array $recordedRequests = [];

    public function __construct(
        protected string $connection,
        protected array $config,
    ) {}

    public function post(string $act, array $payload = []): array
    {
        return data_get($this->response($act, $payload), 'data', []);
    }

    public function response(string $act, array $payload = []): array
    {
        if ($this->isFake()) {
            $response = $this->fakeResponse($act, $payload);

            $this->throwIfFeederError($response, $act);

            return $response;
        }

        $response = $this->sendWithToken($act, $payload);

        if ($this->shouldRefreshToken($response)) {
            $this->logWarning('Feeder token rejected. Refreshing token and retrying request.', [
                'connection' => $this->connection,
                'act' => $act,
                'error_code' => data_get($response, 'error_code'),
                'error_desc' => data_get($response, 'error_desc'),
            ]);

            $this->clearToken();

            $response = $this->sendWithToken($act, $payload, forceNewToken: true);
        }

        $this->throwIfFeederError($response, $act);

        return $response;
    }

    public function token(bool $force = false): string
    {
        if ($this->isFake()) {
            return "fake-token:{$this->connection}";
        }

        if ($force) {
            $this->clearToken();
        }

        $cacheKey = $this->tokenCacheKey();

        $cachedToken = Cache::get($cacheKey);

        if (! $force && filled($cachedToken)) {
            return $cachedToken;
        }

        return Cache::lock(
            $this->tokenLockKey(),
            $this->tokenLockSeconds()
        )->block($this->tokenLockWaitSeconds(), function () use ($force, $cacheKey) {
            $cachedToken = Cache::get($cacheKey);

            if (! $force && filled($cachedToken)) {
                return $cachedToken;
            }

            return $this->requestNewToken();
        });
    }

    public function clearToken(): void
    {
        if ($this->isFake()) {
            return;
        }

        Cache::forget($this->tokenCacheKey());

        $this->logInfo('Feeder cached token cleared.', [
            'connection' => $this->connection,
        ]);
    }

    public function fake(array|FeederFake $fake = []): static
    {
        $this->fake = $fake instanceof FeederFake
            ? $fake
            : new FeederFake($fake);

        $this->recordedRequests = [];

        return $this;
    }

    public function restoreFake(): static
    {
        $this->fake = null;
        $this->recordedRequests = [];

        return $this;
    }

    public function isFake(): bool
    {
        return $this->fake instanceof FeederFake;
    }

    public function recorded(): array
    {
        return $this->recordedRequests;
    }

    public function assertSent(string $act, ?Closure $callback = null): void
    {
        $sent = collect($this->recordedRequests)
            ->contains(function (array $request) use ($act, $callback) {
                if ($request['act'] !== $act) {
                    return false;
                }

                return $callback ? $callback($request) : true;
            });

        $this->assertTrue(
            $sent,
            "Failed asserting that Feeder act [{$act}] was sent on connection [{$this->connection}]."
        );
    }

    public function assertNotSent(string $act, ?Closure $callback = null): void
    {
        $sent = collect($this->recordedRequests)
            ->contains(function (array $request) use ($act, $callback) {
                if ($request['act'] !== $act) {
                    return false;
                }

                return $callback ? $callback($request) : true;
            });

        $this->assertFalse(
            $sent,
            "Failed asserting that Feeder act [{$act}] was not sent on connection [{$this->connection}]."
        );
    }

    public function assertSentTimes(string $act, int $times): void
    {
        $actual = collect($this->recordedRequests)
            ->where('act', $act)
            ->count();

        $this->assertSame(
            $times,
            $actual,
            "Failed asserting that Feeder act [{$act}] was sent {$times} times on connection [{$this->connection}]. Actually sent {$actual} times."
        );
    }

    public function getConnectionName(): string
    {
        return $this->connection;
    }

    protected function fakeResponse(string $act, array $payload = []): array
    {
        $this->recordFakeRequest($act, $payload);

        return $this->fake->response(
            connection: $this->connection,
            act: $act,
            payload: $payload
        );
    }

    protected function recordFakeRequest(string $act, array $payload = []): void
    {
        $this->recordedRequests[] = [
            'connection' => $this->connection,
            'act' => $act,
            'payload' => $payload,
            'recorded_at' => now(),
        ];
    }

    protected function sendWithToken(string $act, array $payload = [], bool $forceNewToken = false): array
    {
        $startedAt = microtime(true);

        $token = $this->token($forceNewToken);

        $body = array_merge($payload, [
            'act' => $act,
            'token' => $token,
        ]);

        try {
            $response = $this->http()->post($this->endpoint(), $body);

            $this->logInfo('Feeder request completed.', [
                'connection' => $this->connection,
                'act' => $act,
                'http_status' => $response->status(),
                'duration_ms' => $this->durationMs($startedAt),
                'force_new_token' => $forceNewToken,
            ]);

            if ($response->failed()) {
                throw new FeederConnectionException(
                    message: 'Gagal menghubungi API Feeder. HTTP Status: ' . $response->status(),
                    connection: $this->connection,
                    act: $act,
                    response: $this->safeJson($response->json()),
                    code: $response->status(),
                );
            }

            return $this->jsonResponse($response->json(), $act);
        } catch (FeederException $e) {
            $this->logWarning('Feeder request failed.', [
                'connection' => $this->connection,
                'act' => $act,
                'duration_ms' => $this->durationMs($startedAt),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        } catch (LaravelConnectionException | LaravelRequestException $e) {
            $this->logWarning('Feeder HTTP client failed.', [
                'connection' => $this->connection,
                'act' => $act,
                'duration_ms' => $this->durationMs($startedAt),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw new FeederConnectionException(
                message: 'Gagal terhubung ke API Feeder: ' . $e->getMessage(),
                connection: $this->connection,
                act: $act,
                context: [
                    'endpoint' => $this->endpoint(),
                    'exception' => $e::class,
                ],
                previous: $e,
            );
        } catch (Throwable $e) {
            $this->logWarning('Unexpected Feeder request failure.', [
                'connection' => $this->connection,
                'act' => $act,
                'duration_ms' => $this->durationMs($startedAt),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw new FeederConnectionException(
                message: 'Terjadi kesalahan koneksi saat menghubungi API Feeder: ' . $e->getMessage(),
                connection: $this->connection,
                act: $act,
                context: [
                    'endpoint' => $this->endpoint(),
                    'exception' => $e::class,
                ],
                previous: $e,
            );
        }
    }

    protected function requestNewToken(): string
    {
        $this->validateCredential();

        $startedAt = microtime(true);

        try {
            $response = $this->http()->post($this->endpoint(), [
                'act' => 'GetToken',
                'username' => $this->username(),
                'password' => $this->password(),
            ]);

            $this->logInfo('Feeder token request completed.', [
                'connection' => $this->connection,
                'act' => 'GetToken',
                'http_status' => $response->status(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            if ($response->failed()) {
                throw new FeederConnectionException(
                    message: 'Gagal mengambil token Feeder. HTTP Status: ' . $response->status(),
                    connection: $this->connection,
                    act: 'GetToken',
                    response: $this->safeJson($response->json()),
                    code: $response->status(),
                );
            }

            $json = $this->jsonResponse($response->json(), 'GetToken');

            $this->throwIfFeederError($json, 'GetToken');

            $token = data_get($json, 'data.token');

            if (blank($token) || ! is_string($token)) {
                throw new FeederTokenException(
                    message: 'Token tidak ditemukan pada response Feeder.',
                    connection: $this->connection,
                    act: 'GetToken',
                    response: $this->safeJson($json),
                );
            }

            $ttl = $this->resolveTokenTtl($token);

            Cache::put(
                $this->tokenCacheKey(),
                $token,
                now()->addSeconds($ttl)
            );

            $this->logInfo('Feeder token cached.', [
                'connection' => $this->connection,
                'ttl_seconds' => $ttl,
            ]);

            return $token;
        } catch (FeederException $e) {
            $this->logWarning('Feeder token request failed.', [
                'connection' => $this->connection,
                'act' => 'GetToken',
                'duration_ms' => $this->durationMs($startedAt),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        } catch (LaravelConnectionException | LaravelRequestException $e) {
            $this->logWarning('Feeder token HTTP client failed.', [
                'connection' => $this->connection,
                'act' => 'GetToken',
                'duration_ms' => $this->durationMs($startedAt),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw new FeederConnectionException(
                message: 'Gagal terhubung ke API Feeder saat mengambil token: ' . $e->getMessage(),
                connection: $this->connection,
                act: 'GetToken',
                context: [
                    'endpoint' => $this->endpoint(),
                    'exception' => $e::class,
                ],
                previous: $e,
            );
        } catch (Throwable $e) {
            $this->logWarning('Unexpected Feeder token failure.', [
                'connection' => $this->connection,
                'act' => 'GetToken',
                'duration_ms' => $this->durationMs($startedAt),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw new FeederTokenException(
                message: 'Terjadi kesalahan saat mengambil token Feeder: ' . $e->getMessage(),
                connection: $this->connection,
                act: 'GetToken',
                context: [
                    'endpoint' => $this->endpoint(),
                    'exception' => $e::class,
                ],
                previous: $e,
            );
        }
    }

    protected function http(): PendingRequest
    {
        $request = Http::acceptJson()
            ->timeout($this->httpTimeout())
            ->connectTimeout($this->httpConnectTimeout());

        if ($this->httpRetryTimes() > 0) {
            $request = $request->retry(
                $this->httpRetryTimes(),
                $this->httpRetrySleep()
            );
        }

        if ($this->httpAsForm()) {
            $request = $request->asForm();
        }

        return $request;
    }

    protected function jsonResponse(mixed $json, ?string $act = null): array
    {
        if (! is_array($json)) {
            throw new FeederResponseException(
                message: 'Response API Feeder tidak valid atau bukan JSON.',
                connection: $this->connection,
                act: $act,
            );
        }

        return $json;
    }

    protected function safeJson(mixed $json): ?array
    {
        if (! is_array($json)) {
            return null;
        }

        return $this->sanitizeLogContext($json);
    }

    protected function throwIfFeederError(array $response, ?string $act = null): void
    {
        $errorCode = (int) data_get($response, 'error_code', 0);

        if ($errorCode === 0) {
            return;
        }

        $message = data_get($response, 'error_desc') ?: 'API Feeder mengembalikan error.';

        if ($act === 'GetToken' || $this->isAuthenticationErrorResponse($response)) {
            throw new FeederAuthenticationException(
                message: $message,
                connection: $this->connection,
                act: $act,
                feederErrorCode: $errorCode,
                response: $this->safeJson($response),
            );
        }

        if ($this->isTokenErrorResponse($response)) {
            throw new FeederTokenException(
                message: $message,
                connection: $this->connection,
                act: $act,
                feederErrorCode: $errorCode,
                response: $this->safeJson($response),
            );
        }

        throw new FeederResponseException(
            message: $message,
            connection: $this->connection,
            act: $act,
            feederErrorCode: $errorCode,
            response: $this->safeJson($response),
        );
    }

    protected function shouldRefreshToken(array $response): bool
    {
        if (! $this->autoRefreshToken()) {
            return false;
        }

        $errorCode = (int) data_get($response, 'error_code', 0);

        if ($errorCode === 0) {
            return false;
        }

        return $this->isTokenErrorResponse($response);
    }

    protected function isAuthenticationErrorResponse(array $response): bool
    {
        $errorDesc = Str::of((string) data_get($response, 'error_desc', ''))->lower();

        return $errorDesc->contains([
            'auth',
            'authentication',
            'unauthorized',
            'credential',
            'username',
            'password',
            'login',
            'user tidak ditemukan',
            'password salah',
            'akses ditolak',
        ]);
    }

    protected function isTokenErrorResponse(array $response): bool
    {
        $errorDesc = Str::of((string) data_get($response, 'error_desc', ''))->lower();

        foreach ($this->tokenErrorKeywords() as $keyword) {
            if ($errorDesc->contains(Str::lower($keyword))) {
                return true;
            }
        }

        return false;
    }

    protected function resolveTokenTtl(string $token): int
    {
        $fallbackTtl = max(60, $this->fallbackTokenTtl());

        $parts = explode('.', $token);

        if (count($parts) < 2) {
            return $fallbackTtl;
        }

        $payload = json_decode($this->base64UrlDecode($parts[1]), true);

        if (! is_array($payload)) {
            return $fallbackTtl;
        }

        $exp = data_get($payload, 'exp');

        if (! is_numeric($exp)) {
            return $fallbackTtl;
        }

        $ttl = ((int) $exp) - now()->timestamp - $this->tokenLeeway();

        return max(60, $ttl);
    }

    protected function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;

        if ($remainder) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($value, '-_', '+/')) ?: '';
    }

    protected function validateCredential(): void
    {
        if (blank($this->endpoint())) {
            throw new FeederConnectionException(
                message: "Endpoint Feeder untuk connection [{$this->connection}] belum dikonfigurasi.",
                connection: $this->connection,
                act: 'GetToken',
            );
        }

        if (blank($this->username()) || blank($this->password())) {
            throw new FeederAuthenticationException(
                message: "Username atau password Feeder untuk connection [{$this->connection}] belum dikonfigurasi.",
                connection: $this->connection,
                act: 'GetToken',
            );
        }
    }

    protected function durationMs(float $startedAt): float
    {
        return round((microtime(true) - $startedAt) * 1000, 2);
    }

    protected function logInfo(string $message, array $context = []): void
    {
        if (! $this->loggingEnabled()) {
            return;
        }

        Log::channel($this->logChannel())->info(
            $message,
            $this->sanitizeLogContext($context)
        );
    }

    protected function logWarning(string $message, array $context = []): void
    {
        if (! $this->loggingEnabled()) {
            return;
        }

        Log::channel($this->logChannel())->warning(
            $message,
            $this->sanitizeLogContext($context)
        );
    }

    protected function sanitizeLogContext(array $context): array
    {
        $sensitiveKeys = [
            'token',
            'username',
            'password',
            'authorization',
            'body',
            'payload',
        ];

        foreach ($context as $key => $value) {
            if (in_array(Str::lower((string) $key), $sensitiveKeys, true)) {
                $context[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $context[$key] = $this->sanitizeLogContext($value);
            }
        }

        return $context;
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

    protected function cfg(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    protected function replaceConnectionPlaceholder(string $value): string
    {
        return str_replace('{connection}', $this->connection, $value);
    }

    protected function endpoint(): ?string
    {
        return $this->cfg('endpoint');
    }

    protected function username(): ?string
    {
        return $this->cfg('username');
    }

    protected function password(): ?string
    {
        return $this->cfg('password');
    }

    protected function tokenCacheKey(): string
    {
        return $this->replaceConnectionPlaceholder(
            (string) $this->cfg('token.cache_key', 'feeder:{connection}:token')
        );
    }

    protected function tokenLockKey(): string
    {
        return $this->replaceConnectionPlaceholder(
            (string) $this->cfg('token.lock_key', 'feeder:{connection}:token:lock')
        );
    }

    protected function tokenLockSeconds(): int
    {
        return (int) $this->cfg('token.lock_seconds', 10);
    }

    protected function tokenLockWaitSeconds(): int
    {
        return (int) $this->cfg('token.lock_wait_seconds', 5);
    }

    protected function fallbackTokenTtl(): int
    {
        return (int) $this->cfg('token.ttl', 3600);
    }

    protected function tokenLeeway(): int
    {
        return (int) $this->cfg('token.leeway', 60);
    }

    protected function httpTimeout(): int
    {
        return (int) $this->cfg('http.timeout', 30);
    }

    protected function httpConnectTimeout(): int
    {
        return (int) $this->cfg('http.connect_timeout', 10);
    }

    protected function httpAsForm(): bool
    {
        return (bool) $this->cfg('http.as_form', true);
    }

    protected function httpRetryTimes(): int
    {
        return (int) $this->cfg('http.retry.times', 2);
    }

    protected function httpRetrySleep(): int
    {
        return (int) $this->cfg('http.retry.sleep', 300);
    }

    protected function loggingEnabled(): bool
    {
        return (bool) $this->cfg('logging.enabled', true);
    }

    protected function logChannel(): string
    {
        return (string) $this->cfg('logging.channel', config('logging.default', 'stack'));
    }

    protected function autoRefreshToken(): bool
    {
        return (bool) $this->cfg('auto_refresh_token', true);
    }

    protected function tokenErrorKeywords(): array
    {
        return (array) $this->cfg('token_error_keywords', []);
    }
}
