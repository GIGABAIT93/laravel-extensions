<?php

namespace Gigabait93\Extensions\Commands;

use Illuminate\Console\Command;
use Gigabait93\Extensions\Facades\Extensions;

class EnableCommand extends Command
{
    protected $signature = 'extension:enable {extension?}';
    protected $description = 'Enable the extension';

    public function handle(): void
    {
        $name = $this->argument('extension');
        $result = Extensions::enable($name);
        $this->info($result);
    }
}
