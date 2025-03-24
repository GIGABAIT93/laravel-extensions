<?php

namespace Gigabait93\Extensions\Commands;

use Illuminate\Console\Command;
use Gigabait93\Extensions\Services\Extensions;

class InstallCommand extends Command
{
    protected $signature = 'extension:install {extension}';
    protected $description = 'Set up a new extension';

    public function handle(): void
    {
        $extension = $this->argument('extension');
        $manager = new Extensions();
        $result = $manager->install($extension);
        $this->info($result);
    }
}
