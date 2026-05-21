<?php

namespace Novay\Feeder\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array post(string $act, array $payload = [])
 * @method static array response(string $act, array $payload = [])
 * @method static string token(bool $force = false)
 * @method static void clearToken()
 *
 * @see \Novay\Feeder\FeederClient
 */
class Feeder extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'feeder';
    }
}
