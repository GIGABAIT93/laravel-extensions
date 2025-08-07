<?php

namespace Gigabait93\Extensions\Commands;

use Gigabait93\Extensions\Facades\Extensions;
use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'extension:install {extension} {--force}';
    protected $description = 'Set up a new extension (migrate + seed)';

    public function handle(): void
    {
        $name = $this->argument('extension');
        $force = $this->option('force');

        $result = Extensions::install($name, $force);
        $this->info($result);
    }
}
