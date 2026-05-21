<?php

namespace Novay\Feeder\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Novay\Feeder\FeederClient connection(?string $name = null)
 * @method static array post(string $act, array $payload = [])
 * @method static array response(string $act, array $payload = [])
 * @method static string token(bool $force = false)
 * @method static void clearToken()
 *
 * @method static \Novay\Feeder\FeederManager fake(array $responses = [])
 * @method static \Novay\Feeder\FeederManager fakeForConnection(string $connection, array $responses = [])
 * @method static \Novay\Feeder\FeederManager restoreFake()
 * @method static array recorded(?string $connection = null)
 * @method static void assertSent(string $act, ?Closure $callback = null, ?string $connection = null)
 * @method static void assertNotSent(string $act, ?Closure $callback = null, ?string $connection = null)
 * @method static void assertSentTimes(string $act, int $times, ?string $connection = null)
 *
 * @see \Novay\Feeder\FeederManager
 */
class Feeder extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'feeder';
    }
}
