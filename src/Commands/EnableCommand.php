<?php

namespace Gigabait93\Extensions\Commands;

use Illuminate\Console\Command;
use Gigabait93\Extensions\Services\Extensions;

class EnableCommand extends Command
{
    protected $signature = 'extension:enable {extension}';
    protected $description = 'Enable extension';

    public function handle(): void
    {
        $extension = $this->argument('extension');
        $manager = Extensions::getInstance();
        $result = $manager->enable($extension);
        $this->info($result);
    }
}
