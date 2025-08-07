<?php

namespace Gigabait93\Extensions\Commands;

use Illuminate\Console\Command;
use Gigabait93\Extensions\Facades\Extensions;

class DiscoverCommand extends Command
{
    protected $signature   = 'extension:discover';
    protected $description = 'Scan extensions catalog and synchronize them with repository';

    public function handle(): void
    {
        $names = Extensions::discover();

        if (empty($names)) {
            $this->info(trans('extensions::commands.no_new_extensions'));
        } else {
            $this->info(trans('extensions::commands.synchronized_extensions', ['names' => implode(', ', $names)]));
        }
    }
}
