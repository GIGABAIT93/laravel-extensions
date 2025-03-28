<?php

namespace Gigabait93\Extensions\Commands;

use Illuminate\Console\Command;
use Gigabait93\Extensions\Services\Extensions;

class DisableCommand extends Command
{
    protected $signature = 'extension:disable {extension}';
    protected $description = 'Disable the extension';

    public function handle(): void
    {
        $extension = $this->argument('extension');
        $manager = Extensions::getInstance();
        $result = $manager->disable($extension);
        $this->info($result);
    }
}
