<?php

namespace Gigabait93\Extensions\Commands;

use Illuminate\Console\Command;
use Gigabait93\Extensions\Services\ExtensionManager;

class EnableCommand extends Command
{
    protected $signature = 'extension:enable {extension}';
    protected $description = 'Enable extension';

    public function handle(): void
    {
        $extension = $this->argument('extension');
        $manager = new ExtensionManager();
        $result = $manager->enable($extension);
        $this->info($result);
    }
}
