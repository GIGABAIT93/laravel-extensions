<?php

namespace Gigabait93\Extensions\Commands;

use Illuminate\Console\Command;
use Gigabait93\Extensions\Facades\Extensions;

class InstallCommand extends Command
{
    protected $signature = 'extension:install {extension?} {--force}';
    protected $description = 'Set up a new extension (migrate + seed)';

    public function handle(): void
    {
        $name = $this->argument('extension');
        $force = $this->option('force');

        $result = Extensions::install($name, $force);
        $this->info($result);
    }
}
