<?php

namespace Novay\Feeder\Console\Commands;

use Illuminate\Console\Command;
use Novay\Feeder\FeederClient;

class FeederClearTokenCommand extends Command
{
    protected $signature = 'feeder:clear-token';

    protected $description = 'Clear cached Feeder token.';

    public function handle(FeederClient $feeder): int
    {
        $feeder->clearToken();

        $this->components->info('Cached Feeder token cleared.');

        return self::SUCCESS;
    }
}
