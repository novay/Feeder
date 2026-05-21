<?php

namespace Novay\Feeder;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Novay\Feeder\Exceptions\FeederException;

class FeederClient
{
    /**
     * Mengembalikan langsung isi "data" dari response Feeder.
     */
    public function post(string $act, array $payload = []): array
    {
        return data_get($this->response($act, $payload), 'data', []);
    }

    /**
     * Mengembalikan response penuh dari Feeder:
     * [
     *   "error_code" => 0,
     *   "error_desc" => "",
     *   "data" => [...]
     * ]
     */
    public function response(string $act, array $payload = []): array
    {
        $response = $this->sendWithToken($act, $payload);

        if ($this->shouldRefreshToken($response)) {
            $this->clearToken();

            $response = $this->sendWithToken($act, $payload, forceNewToken: true);
        }

        $this->throwIfFeederError($response);

        return $response;
    }

    /**
     * Ambil token dari cache.
     * Bila tidak ada, ambil token baru dari Feeder.
     */
    public function token(bool $force = false): string
    {
        if ($force) {
            $this->clearToken();
        }

        $cacheKey = $this->tokenCacheKey();

        if (! $force && filled(Cache::get($cacheKey))) {
            return Cache::get($cacheKey);
        }

        return Cache::lock(
            $this->tokenLockKey(),
            $this->tokenLockSeconds()
        )->block($this->tokenLockWaitSeconds(), function () use ($force, $cacheKey) {
            if (! $force && filled(Cache::get($cacheKey))) {
                return Cache::get($cacheKey);
            }

            return $this->requestNewToken();
        });
    }

    public function clearToken(): void
    {
        Cache::forget($this->tokenCacheKey());
    }

    protected function sendWithToken(string $act, array $payload = [], bool $forceNewToken = false): array
    {
        $token = $this->token($forceNewToken);

        /*
         * act dan token sengaja diletakkan terakhir
         * agar tidak tertimpa oleh payload dari pemanggil.
         */
        $body = array_merge($payload, [
            'act' => $act,
            'token' => $token,
        ]);

        $response = $this->http()->post($this->endpoint(), $body);

        if ($response->failed()) {
            throw new FeederException(
                message: 'Gagal menghubungi API Feeder. HTTP Status: ' . $response->status(),
                response: $response->json(),
                code: $response->status(),
            );
        }

        return $this->jsonResponse($response->json());
    }

    protected function requestNewToken(): string
    {
        $this->validateCredential();

        $response = $this->http()->post($this->endpoint(), [
            'act' => 'getToken',
            'username' => $this->username(),
            'password' => $this->password(),
        ]);

        if ($response->failed()) {
            throw new FeederException(
                message: 'Gagal mengambil token Feeder. HTTP Status: ' . $response->status(),
                response: $response->json(),
                code: $response->status(),
            );
        }

        $json = $this->jsonResponse($response->json());

        $this->throwIfFeederError($json);

        $token = data_get($json, 'data.token');

        if (blank($token) || ! is_string($token)) {
            throw new FeederException(
                message: 'Token tidak ditemukan pada response Feeder.',
                response: $json,
            );
        }

        Cache::put(
            $this->tokenCacheKey(),
            $token,
            now()->addSeconds($this->resolveTokenTtl($token))
        );

        return $token;
    }

    protected function http(): PendingRequest
    {
        $request = Http::acceptJson()
            ->timeout($this->httpTimeout())
            ->connectTimeout($this->httpConnectTimeout());

        if ($this->httpAsForm()) {
            $request = $request->asForm();
        }

        return $request;
    }

    protected function jsonResponse(mixed $json): array
    {
        if (! is_array($json)) {
            throw new FeederException(
                message: 'Response API Feeder tidak valid atau bukan JSON.'
            );
        }

        return $json;
    }

    protected function throwIfFeederError(array $response): void
    {
        $errorCode = (int) data_get($response, 'error_code', 0);

        if ($errorCode === 0) {
            return;
        }

        throw new FeederException(
            message: data_get($response, 'error_desc') ?: 'API Feeder mengembalikan error.',
            feederErrorCode: $errorCode,
            response: $response,
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
        if (blank($this->username()) || blank($this->password())) {
            throw new FeederException(
                message: 'Username atau password Feeder belum dikonfigurasi.'
            );
        }
    }

    protected function endpoint(): string
    {
        return (string) config('feeder.endpoint');
    }

    protected function username(): ?string
    {
        return config('feeder.username');
    }

    protected function password(): ?string
    {
        return config('feeder.password');
    }

    protected function tokenCacheKey(): string
    {
        return (string) config('feeder.token.cache_key', 'feeder:token');
    }

    protected function tokenLockKey(): string
    {
        return (string) config('feeder.token.lock_key', 'feeder:token:lock');
    }

    protected function tokenLockSeconds(): int
    {
        return (int) config('feeder.token.lock_seconds', 10);
    }

    protected function tokenLockWaitSeconds(): int
    {
        return (int) config('feeder.token.lock_wait_seconds', 5);
    }

    protected function fallbackTokenTtl(): int
    {
        return (int) config('feeder.token.ttl', 3600);
    }

    protected function tokenLeeway(): int
    {
        return (int) config('feeder.token.leeway', 60);
    }

    protected function httpTimeout(): int
    {
        return (int) config('feeder.http.timeout', 30);
    }

    protected function httpConnectTimeout(): int
    {
        return (int) config('feeder.http.connect_timeout', 10);
    }

    protected function httpAsForm(): bool
    {
        return (bool) config('feeder.http.as_form', true);
    }

    protected function autoRefreshToken(): bool
    {
        return (bool) config('feeder.auto_refresh_token', true);
    }

    protected function tokenErrorKeywords(): array
    {
        return (array) config('feeder.token_error_keywords', []);
    }
}
