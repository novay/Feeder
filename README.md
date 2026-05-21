# Feeder SDK

Laravel package for interacting with Feeder API using automatic token retrieval, token caching, token refresh, retry, safe logging, Artisan commands, multi connection, testing fake, and typed exceptions.

```php
use Novay\Feeder\Facades\Feeder;

$profil = Feeder::post('GetProfilPT');
```

---

## Features

- Simple facade API
- Automatic token retrieval & refresh
- Token caching
- Safe HTTP timeout configuration
- Full response or data-only response
- Artisan commands
- Multi connection
- Typed exceptions
- Testing fake
- Laravel package auto-discovery

---

## Requirements

- PHP 8.2 or higher
- Laravel 10, 11, 12, or 13

---

## Installation

Install the package via Composer:

```bash
composer require novay/feeder
```

---

## Publish Config

```bash
php artisan vendor:publish --tag=feeder-config
php artisan optimize:clear
```

---

## Environment

```env
FEEDER_CONNECTION=live

FEEDER_LIVE_ENDPOINT=http://IP:PORT/ws/live2.php
FEEDER_LIVE_USERNAME=your_live_username
FEEDER_LIVE_PASSWORD=your_live_password
```

Available env:
```env
FEEDER_SANDBOX_ENDPOINT=http://sandbox-feeder.test/ws/live2.php
FEEDER_SANDBOX_USERNAME=your_sandbox_username
FEEDER_SANDBOX_PASSWORD=your_sandbox_password

FEEDER_TOKEN_TTL=3600
FEEDER_TOKEN_LEEWAY=60

FEEDER_TIMEOUT=30
FEEDER_CONNECT_TIMEOUT=10
FEEDER_AS_FORM=true

FEEDER_RETRY_TIMES=2
FEEDER_RETRY_SLEEP=300

FEEDER_LOGGING_ENABLED=true
FEEDER_LOG_CHANNEL=stack

FEEDER_AUTO_REFRESH_TOKEN=true
```

---

## Basic Usage

```php
use Novay\Feeder\Facades\Feeder;

$profil = Feeder::post('GetProfilPT');
```

`post()` returns only the `data` value.

---

## Full Response

```php
$response = Feeder::response('GetProfilPT');
```

Returned structure:

```php
[
    'error_code' => 0,
    'error_desc' => '',
    'data' => [],
]
```

---

## Sending Payload

```php
$mahasiswa = Feeder::post('GetListMahasiswa', [
    'filter' => "id_prodi='xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'",
    'limit' => 20,
    'offset' => 0,
]);
```

The package automatically appends:

```php
[
    'act' => 'GetListMahasiswa',
    'token' => '...',
]
```

---

## Token

```php
$token = Feeder::token();
```

Force refresh:

```php
$token = Feeder::token(force: true);
```

Clear cached token:

```php
Feeder::clearToken();
```

---

## Multi Connection

Use default connection:

```php
$profil = Feeder::post('GetProfilPT');
```

Use specific connection:

```php
$profil = Feeder::connection('live')->post('GetProfilPT');

$profilSandbox = Feeder::connection('sandbox')->post('GetProfilPT');
```

Token per connection:

```php
$liveToken = Feeder::connection('live')->token();

$sandboxToken = Feeder::connection('sandbox')->token();
```

Clear token per connection:

```php
Feeder::connection('live')->clearToken();

Feeder::connection('sandbox')->clearToken();
```

---

## Artisan Commands

Test Feeder:

```bash
php artisan feeder:test
php artisan feeder:test --connection=live
php artisan feeder:test --connection=sandbox
php artisan feeder:test --connection=sandbox --act=GetProfilPT
```

Show masked token:

```bash
php artisan feeder:token
php artisan feeder:token --connection=live
php artisan feeder:token --connection=sandbox
php artisan feeder:token --connection=sandbox --force
```

Clear cached token:

```bash
php artisan feeder:clear-token
php artisan feeder:clear-token --connection=live
php artisan feeder:clear-token --connection=sandbox
```

---

## Logging

The package logs:

- connection
- act
- HTTP status
- duration
- exception class
- safe error message

Sensitive values are redacted:

- token
- username
- password
- authorization
- body
- payload

---

## Testing Fake

```php
use Novay\Feeder\Facades\Feeder;

Feeder::fake([
    'GetProfilPT' => [
        'kode_perguruan_tinggi' => '123456',
        'nama_perguruan_tinggi' => 'STIKES Mutiara Mahakam Samarinda',
    ],
]);

$profil = Feeder::post('GetProfilPT');

expect($profil['nama_perguruan_tinggi'])
    ->toBe('STIKES Mutiara Mahakam Samarinda');

Feeder::assertSent('GetProfilPT');
Feeder::assertSentTimes('GetProfilPT', 1);
```

### Fake Full Response

```php
Feeder::fake([
    'GetProfilPT' => [
        'error_code' => 0,
        'error_desc' => '',
        'data' => [
            'nama_perguruan_tinggi' => 'STIKES Mutiara Mahakam Samarinda',
        ],
    ],
]);
```

### Fake Error

```php
use Novay\Feeder\Exceptions\FeederTokenException;

Feeder::fake([
    'GetProfilPT' => [
        'error_code' => 100,
        'error_desc' => 'Token tidak valid',
        'data' => [],
    ],
]);

expect(fn () => Feeder::post('GetProfilPT'))
    ->toThrow(FeederTokenException::class);
```

### Fake With Callback

```php
Feeder::fake([
    'GetListMahasiswa' => function (string $act, array $payload, string $connection) {
        return [
            [
                'nim' => '2024001',
                'nama_mahasiswa' => 'Mahasiswa Demo',
                'connection' => $connection,
                'filter' => $payload['filter'] ?? null,
            ],
        ];
    },
]);
```

### Assert Payload

```php
Feeder::fake([
    'GetListMahasiswa' => [],
]);

Feeder::post('GetListMahasiswa', [
    'filter' => "id_prodi='abc'",
    'limit' => 10,
    'offset' => 0,
]);

Feeder::assertSent('GetListMahasiswa', function (array $request) {
    return $request['payload']['limit'] === 10
        && $request['payload']['offset'] === 0;
});
```

### Multi Connection Fake

```php
Feeder::fake([
    'live.GetProfilPT' => [
        'nama_perguruan_tinggi' => 'Kampus Live',
    ],
    'sandbox.GetProfilPT' => [
        'nama_perguruan_tinggi' => 'Kampus Sandbox',
    ],
]);
```

Or:

```php
Feeder::fakeForConnection('sandbox', [
    'GetProfilPT' => [
        'nama_perguruan_tinggi' => 'Kampus Sandbox',
    ],
]);
```

Assertions:

```php
Feeder::assertSent('GetProfilPT');
Feeder::assertNotSent('GetListMahasiswa');
Feeder::assertSentTimes('GetProfilPT', 1);

Feeder::assertSent('GetProfilPT', connection: 'sandbox');
```

Restore real client:

```php
Feeder::restoreFake();
```

---

## Typed Exceptions

All exceptions extend:

```php
Novay\Feeder\Exceptions\FeederException
```

Available typed exceptions:

```php
Novay\Feeder\Exceptions\FeederAuthenticationException;
Novay\Feeder\Exceptions\FeederTokenException;
Novay\Feeder\Exceptions\FeederConnectionException;
Novay\Feeder\Exceptions\FeederResponseException;
```

Catch all:

```php
use Novay\Feeder\Exceptions\FeederException;
use Novay\Feeder\Facades\Feeder;

try {
    $profil = Feeder::post('GetProfilPT');
} catch (FeederException $e) {
    report($e);

    return $e->getMessage();
}
```

Catch specific:

```php
use Novay\Feeder\Exceptions\FeederAuthenticationException;
use Novay\Feeder\Exceptions\FeederConnectionException;
use Novay\Feeder\Exceptions\FeederResponseException;
use Novay\Feeder\Exceptions\FeederTokenException;
use Novay\Feeder\Facades\Feeder;

try {
    $profil = Feeder::post('GetProfilPT');
} catch (FeederAuthenticationException $e) {
    // Invalid or missing username/password.
} catch (FeederTokenException $e) {
    // Token missing, invalid, expired, or rejected.
} catch (FeederConnectionException $e) {
    // HTTP error, timeout, connection problem, endpoint not configured.
} catch (FeederResponseException $e) {
    // Invalid JSON, unexpected response, or non-token Feeder error.
}
```

Exception context:

```php
try {
    $profil = Feeder::post('GetProfilPT');
} catch (FeederException $e) {
    logger()->warning('Feeder failed.', $e->context());
}
```

---

## Security Notes

Do not hardcode Feeder username, password, or token in Livewire components, controllers, or Blade files.

Use `.env`.

If your endpoint still uses HTTP instead of HTTPS, access it only from a trusted server, internal network, VPN, or secure reverse proxy.

---

## License

MIT
