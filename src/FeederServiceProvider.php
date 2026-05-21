<?php

namespace Novay\Feeder;

use Illuminate\Support\ServiceProvider;
use Novay\Feeder\Console\Commands\FeederClearTokenCommand;
use Novay\Feeder\Console\Commands\FeederTestCommand;
use Novay\Feeder\Console\Commands\FeederTokenCommand;

class FeederServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/feeder.php',
            'feeder'
        );

        $this->app->singleton(FeederClient::class, function () {
            return new FeederClient();
        });

        $this->app->alias(FeederClient::class, 'feeder');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/feeder.php' => config_path('feeder.php'),
        ], 'feeder-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                FeederTestCommand::class,
                FeederTokenCommand::class,
                FeederClearTokenCommand::class,
            ]);
        }
    }
}
