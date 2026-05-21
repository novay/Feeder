# Feeder SDK

Laravel package for interacting with Feeder API using automatic token retrieval, token caching, and token refresh.

```php
use Novay\Feeder\Facades\Feeder;

$profil = Feeder::post('GetProfilPT');
```

---

## Features

- Automatic token retrieval & refresh
- Token caching
- Safe HTTP timeout configuration
- Full response or data-only response
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
```

This will publish the config file to:

```txt
config/feeder.php
```

---

## Environment Variables

Add these variables to your `.env` file:

```env
FEEDER_ENDPOINT=http://IP.ADDRESS/ws/live2.php
FEEDER_USERNAME=your_username
FEEDER_PASSWORD=your_password

FEEDER_TOKEN_TTL=3600
FEEDER_TOKEN_LEEWAY=60

FEEDER_TIMEOUT=30
FEEDER_CONNECT_TIMEOUT=10
FEEDER_AS_FORM=true
FEEDER_AUTO_REFRESH_TOKEN=true
```

After changing configuration in production, run:

```bash
php artisan optimize:clear
```

Or:

```bash
php artisan config:cache
```

---

## Basic Usage

### Get Profil PT

```php
use Novay\Feeder\Facades\Feeder;

$profil = Feeder::post('GetProfilPT');
```

The `post` method returns only the `data` value from Feeder response.

Example Feeder response:

```json
{
    "error_code": 0,
    "error_desc": "",
    "data": {
        "kode_perguruan_tinggi": "000000",
        "nama_perguruan_tinggi": "Nama Kampus"
    }
}
```

Returned value:

```php
[
    'kode_perguruan_tinggi' => '000000',
    'nama_perguruan_tinggi' => 'Nama Kampus',
]
```

---

## Get Full Response

If you need the full response including `error_code` and `error_desc`, use:

```php
$response = Feeder::response('GetProfilPT');
```

Returned value:

```php
[
    'error_code' => 0,
    'error_desc' => '',
    'data' => [
        //
    ],
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

The package will automatically append the token:

```php
[
    'act' => 'GetListMahasiswa',
    'token' => '...',
    'filter' => "...",
    'limit' => 20,
    'offset' => 0,
]
```

---

## Token Handling

The package handles token automatically.

```php
$token = Feeder::token();
```

Force refresh token:

```php
$token = Feeder::token(force: true);
```

Clear cached token:

```php
Feeder::clearToken();
```

Token is stored using Laravel cache. If the token is a JWT and contains an `exp` claim, the package will use the JWT expiration time. Otherwise, it will use `FEEDER_TOKEN_TTL`.

---

## Usage in Livewire

```php
<?php

use Livewire\Component;
use Novay\Feeder\Facades\Feeder;
use Novay\Feeder\Exceptions\FeederException;

new class extends Component
{
    public array $profil = [];

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->loadProfil();
    }

    public function loadProfil(): void
    {
        $this->reset('errorMessage');

        try {
            $this->profil = Feeder::post('GetProfilPT');
        } catch (FeederException $e) {
            $this->errorMessage = $e->getMessage();
            $this->profil = [];
        }
    }
};
?>

<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">
            Profil Perguruan Tinggi
        </h2>

        <flux:button wire:click="loadProfil" icon="arrow-path">
            Muat Ulang
        </flux:button>
    </div>

    @if ($errorMessage)
        <flux:callout variant="danger" icon="exclamation-triangle">
            {{ $errorMessage }}
        </flux:callout>
    @endif

    @if ($profil)
        <div class="rounded-xl border p-4">
            <pre class="overflow-auto text-xs">{{ json_encode($profil, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
    @endif
</div>
```

---

## Configuration

The published config file:

```php
return [
    'endpoint' => env('FEEDER_ENDPOINT', 'http://IP.ADDRESS/ws/live2.php'),

    'username' => env('FEEDER_USERNAME'),
    'password' => env('FEEDER_PASSWORD'),

    'token' => [
        'cache_key' => env('FEEDER_TOKEN_CACHE_KEY', 'feeder:token'),
        'ttl' => (int) env('FEEDER_TOKEN_TTL', 3600),
        'leeway' => (int) env('FEEDER_TOKEN_LEEWAY', 60),
        'lock_key' => env('FEEDER_TOKEN_LOCK_KEY', 'feeder:token:lock'),
        'lock_seconds' => (int) env('FEEDER_TOKEN_LOCK_SECONDS', 10),
        'lock_wait_seconds' => (int) env('FEEDER_TOKEN_LOCK_WAIT_SECONDS', 5),
    ],

    'http' => [
        'timeout' => (int) env('FEEDER_TIMEOUT', 30),
        'connect_timeout' => (int) env('FEEDER_CONNECT_TIMEOUT', 10),
        'as_form' => env('FEEDER_AS_FORM', true),
    ],

    'auto_refresh_token' => env('FEEDER_AUTO_REFRESH_TOKEN', true),

    'token_error_keywords' => [
        'token',
        'expired',
        'expire',
        'invalid',
        'kadaluarsa',
        'kedaluwarsa',
        'tidak valid',
    ],
];
```

---

## Error Handling

The package throws `FeederException` when:

- Feeder credential is missing
- Token cannot be retrieved
- Feeder returns non-zero `error_code`
- Feeder returns invalid JSON
- HTTP connection fails
- Token is missing from token response

Example:

```php
use Novay\Feeder\Facades\Feeder;
use Novay\Feeder\Exceptions\FeederException;

try {
    $data = Feeder::post('GetProfilPT');
} catch (FeederException $e) {
    report($e);

    return back()->with('error', $e->getMessage());
}
```

---

## Security Notes

Do not hardcode Feeder username, password, or token inside Livewire components, controllers, or Blade files.

Use `.env`:

```env
FEEDER_USERNAME=your_username
FEEDER_PASSWORD=your_password
```

If your Feeder endpoint still uses HTTP instead of HTTPS, avoid calling it from public or untrusted networks. Prefer server-to-server access from a trusted network, VPN, internal network, or reverse proxy.

Never log:

- Feeder password
- Feeder username
- Feeder token
- Full request body containing token

---

## Recommended Production Cache Driver

For production, Redis or database cache is recommended.

If the application receives concurrent requests, the package uses Laravel cache lock to prevent multiple token requests from being sent at the same time.

---

## Roadmap

- [ ] `php artisan feeder:test`
- [ ] `php artisan feeder:token`
- [ ] `php artisan feeder:clear-token`
- [ ] Request logging with sensitive data masking
- [ ] Retry and backoff configuration
- [ ] Multi connection support
- [ ] Feeder fake for tests
- [ ] Pagination helper
- [ ] Typed exceptions
- [ ] Laravel events for request success/failure/token refresh

---

## Testing

```bash
composer test
```

Example future testing API:

```php
Feeder::fake([
    'GetProfilPT' => [
        'kode_perguruan_tinggi' => '000000',
        'nama_perguruan_tinggi' => 'Kampus Demo',
    ],
]);

$profil = Feeder::post('GetProfilPT');

expect($profil['nama_perguruan_tinggi'])->toBe('Kampus Demo');
```

---

## License

MIT