<?php

namespace Novay\Feeder\Console\Commands;

use Illuminate\Console\Command;
use Novay\Feeder\Exceptions\FeederException;
use Novay\Feeder\FeederManager;
use Throwable;

class FeederTestCommand extends Command
{
    protected $signature = 'feeder:test
                            {--connection= : Feeder connection name}
                            {--act=GetProfilPT : Feeder act to test}';

    protected $description = 'Test Feeder connection, token retrieval, and a sample Feeder act.';

    public function handle(FeederManager $feeder): int
    {
        $connection = $this->option('connection');
        $act = (string) $this->option('act');

        $this->components->info('Testing Feeder connection...');

        try {
            $client = $feeder->connection($connection ?: null);

            $this->components->twoColumnDetail('Connection', $client->getConnectionName());

            $token = $client->token();

            $this->components->twoColumnDetail('Token', $this->maskToken($token));

            $response = $client->response($act);

            $this->components->twoColumnDetail('Act', $act);
            $this->components->twoColumnDetail('Error Code', (string) data_get($response, 'error_code', '-'));
            $this->components->twoColumnDetail('Error Desc', data_get($response, 'error_desc') ?: '-');
            $this->components->twoColumnDetail('Data', is_array(data_get($response, 'data')) ? 'OK' : 'EMPTY');

            $this->components->info('Feeder test completed successfully.');

            return self::SUCCESS;
        } catch (FeederException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
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
