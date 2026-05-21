<?php

namespace Novay\Feeder\Console\Commands;

use Illuminate\Console\Command;
use Novay\Feeder\FeederManager;
use Throwable;

class FeederTokenCommand extends Command
{
    protected $signature = 'feeder:token 
                            {--connection= : Feeder connection name}
                            {--force : Force refresh token}';

    protected $description = 'Get cached Feeder token or request a new one.';

    public function handle(FeederManager $feeder): int
    {
        try {
            $client = $feeder->connection($this->option('connection') ?: null);

            $token = $client->token(force: (bool) $this->option('force'));

            $this->components->twoColumnDetail('Connection', $client->getConnectionName());
            $this->components->twoColumnDetail('Token', $this->maskToken($token));
            $this->components->info('Token is available.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }

    protected function maskToken(string $token): string
    {
        if (strlen($token) <= 16) {
            return str_repeat('*', strlen($token));
        }

        return substr($token, 0, 8) . '...' . substr($token, -8);
    }
}
