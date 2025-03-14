<?php

namespace Gigabait93\Extensions\Commands;

use Illuminate\Console\Command;
use Gigabait93\Extensions\Services\ExtensionManager;

class DeleteCommand extends Command
{
    protected $signature = 'extension:delete {extension}';
    protected $description = 'Remove the extension';

    public function handle(): void
    {
        $extension = $this->argument('extension');
        $manager = new ExtensionManager();
        $result = $manager->delete($extension);
        $this->info($result);
    }
}
