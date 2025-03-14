<?php

namespace Gigabait93\Extensions\Commands;

use Illuminate\Console\Command;
use Gigabait93\Extensions\Services\ExtensionManager;

class InstallCommand extends Command
{
    protected $signature = 'extension:install {extension}';
    protected $description = 'Set up a new extension';

    public function handle(): void
    {
        $extension = $this->argument('extension');
        $manager = new ExtensionManager();
        $result = $manager->install($extension);
        $this->info($result);
    }
}
