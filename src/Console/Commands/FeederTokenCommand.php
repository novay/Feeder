<?php

namespace Novay\Feeder\Console\Commands;

use Illuminate\Console\Command;
use Novay\Feeder\FeederClient;
use Throwable;

class FeederTokenCommand extends Command
{
    protected $signature = 'feeder:token 
                            {--force : Force refresh token}';

    protected $description = 'Get cached Feeder token or request a new one.';

    public function handle(FeederClient $feeder): int
    {
        try {
            $token = $feeder->token(force: (bool) $this->option('force'));

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
