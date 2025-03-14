<?php

namespace Gigabait93\Extensions\Commands;

use Illuminate\Console\Command;
use Gigabait93\Extensions\Services\ExtensionsManager;

class InstallCommand extends Command
{
    protected $signature = 'extension:install {extension} {--type=}';
    protected $description = 'Set up a new extension';

    public function handle(): void
    {
        $extension = $this->argument('extension');
        $type = $this->option('type') ?: null;
        $manager = new ExtensionsManager();
        $result = $manager->install($extension, $type);
        $this->info($result);
    }
}
