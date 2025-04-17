<?php

namespace Gigabait93\Extensions\Commands;

use Illuminate\Console\Command;
use Gigabait93\Extensions\Services\Extensions;
use Illuminate\Support\Facades\App;

class InstallCommand extends Command
{
    protected $signature = 'extension:install {extension}';
    protected $description = 'Set up a new extension';

    public function handle(): void
    {
        $extension = $this->argument('extension');
        $manager = App::make(Extensions::class);
        $result = $manager->install($extension);
        $this->info($result);
    }
}
