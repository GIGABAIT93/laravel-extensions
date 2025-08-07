<?php

namespace Gigabait93\Extensions\Commands;

use Gigabait93\Extensions\Facades\Extensions;
use Illuminate\Console\Command;

class DiscoverCommand extends Command
{
    protected $signature = 'extension:discover';
    protected $description = 'Scan extensions catalog and synchronize them with repository';

    public function handle(): void
    {
        $names = Extensions::discover();

        if (empty($names)) {
            $this->info('No new extensions found.');
        } else {
            $this->info('Synchronized extensions: ' . implode(', ', $names));
        }
    }
}
