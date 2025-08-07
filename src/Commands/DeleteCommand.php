<?php

namespace Gigabait93\Extensions\Commands;

use Gigabait93\Extensions\Facades\Extensions;
use Illuminate\Console\Command;

class DeleteCommand extends Command
{
    protected $signature = 'extension:delete {extension}';
    protected $description = 'Remove the extension';

    public function handle(): void
    {
        $name = $this->argument('extension');
        $result = Extensions::delete($name);

        $this->info($result);
    }
}
