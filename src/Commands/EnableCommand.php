<?php

namespace Gigabait93\Extensions\Commands;

use Gigabait93\Extensions\Facades\Extensions;
use Illuminate\Console\Command;

class EnableCommand extends Command
{
    protected $signature = 'extension:enable {extension}';
    protected $description = 'Enable the extension';

    public function handle(): void
    {
        $name = $this->argument('extension');
        $result = Extensions::enable($name);

        $this->info($result);
    }
}
