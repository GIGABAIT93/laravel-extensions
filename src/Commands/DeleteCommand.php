<?php

namespace Gigabait93\Extensions\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Gigabait93\Extensions\Services\Extensions;

class DeleteCommand extends Command
{
    protected $signature = 'extension:delete {extension}';
    protected $description = 'Remove the extension';

    public function handle(): void
    {
        $extension = $this->argument('extension');
        $manager = App::make(Extensions::class);
        $result = $manager->delete($extension);
        $this->info($result);
    }
}
