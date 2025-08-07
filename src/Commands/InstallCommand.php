<?php

namespace Gigabait93\Extensions\Commands;

use Illuminate\Console\Command;
use Gigabait93\Extensions\Facades\Extensions;

class InstallCommand extends Command
{
    protected $signature = 'extension:install {extension?} {--force}';
    protected $description = 'Set up a new extension (migrate + seed)';

    public function handle(): void
    {
        $name = $this->argument('extension');
        $force = $this->option('force');

        if (! $name) {
            $list = Extensions::all()->map(fn($e) => $e->getName())->toArray();
            if ($this->input->isInteractive()) {
                $name = $this->choice(trans('extensions::commands.select_extension_install'), $list);
            } else {
                $this->error(trans('extensions::commands.extension_name_required'));
                return;
            }
        }

        $result = Extensions::install($name, $force);
        $this->info($result);
    }
}
