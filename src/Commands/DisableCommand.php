<?php

namespace Gigabait93\Extensions\Commands;

use Illuminate\Console\Command;
use Gigabait93\Extensions\Services\Extensions;
use Illuminate\Support\Facades\App;

class DisableCommand extends Command
{
    protected $signature = 'extension:disable {extension}';
    protected $description = 'Disable the extension';

    public function handle(): void
    {
        $extension = $this->argument('extension');
        $manager = App::make(Extensions::class);
        $result = $manager->disable($extension);
        $this->info($result);
    }
}
