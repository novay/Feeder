<?php

namespace Novay\Feeder\Console\Commands;

use Illuminate\Console\Command;
use Novay\Feeder\Exceptions\FeederException;
use Novay\Feeder\FeederClient;
use Throwable;

class FeederTestCommand extends Command
{
    protected $signature = 'feeder:test 
                            {--act=GetProfilPT : Feeder act to test}';

    protected $description = 'Test Feeder connection, token retrieval, and a sample Feeder act.';

    public function handle(FeederClient $feeder): int
    {
        $act = (string) $this->option('act');

        $this->components->info('Testing Feeder connection...');

        try {
            $token = $feeder->token();

            $this->components->twoColumnDetail('Token', $this->maskToken($token));

            $response = $feeder->response($act);

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
