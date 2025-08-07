<?php

namespace Gigabait93\Extensions\Commands;

use Gigabait93\Extensions\Facades\Extensions;
use Illuminate\Console\Command;

class DisableCommand extends Command
{
    protected $signature = 'extension:disable {extension}';
    protected $description = 'Disable the extension';

    public function handle(): void
    {
        $name = $this->argument('extension');
        $result = Extensions::disable($name);

        $this->info($result);
    }
}
