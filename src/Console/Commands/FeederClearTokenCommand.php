<?php

namespace Novay\Feeder\Console\Commands;

use Illuminate\Console\Command;
use Novay\Feeder\FeederManager;
use Throwable;

class FeederClearTokenCommand extends Command
{
    protected $signature = 'feeder:clear-token 
                            {--connection= : Feeder connection name}';

    protected $description = 'Clear cached Feeder token.';

    public function handle(FeederManager $feeder): int
    {
        try {
            $client = $feeder->connection($this->option('connection') ?: null);

            $client->clearToken();

            $this->components->twoColumnDetail('Connection', $client->getConnectionName());
            $this->components->info('Cached Feeder token cleared.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
