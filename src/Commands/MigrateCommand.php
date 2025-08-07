<?php

namespace Gigabait93\Extensions\Commands;

use Illuminate\Console\Command;
use Gigabait93\Extensions\Facades\Extensions;

class MigrateCommand extends Command
{
    protected $signature   = 'extension:migrate
                            {name? : Extension name (default - all)}
                            {--force : Force the operation without confirmation}';
    protected $description = 'Run migrations and seeders for one or all extensions';

    public function handle(): void
    {
        $force = $this->option('force');
        $name  = $this->argument('name');

        $list = Extensions::all();

        if ($name) {
            $list = $list->filter(fn($e) => $e->getName() === $name);
        } elseif ($this->input->isInteractive()) {
            $choices = $list->map(fn($e) => $e->getName())->toArray();
            $choices[] = trans('extensions::commands.option_all');
            $choice = $this->choice(trans('extensions::commands.select_extension_migrate'), $choices);
            if ($choice !== trans('extensions::commands.option_all')) {
                $list = $list->filter(fn($e) => $e->getName() === $choice);
            }
        }

        if ($list->isEmpty()) {
            $this->warn(trans('extensions::commands.no_extensions_to_process'));
            return;
        }

        $list->each(function ($ext) use ($force) {
            $extName = $ext->getName();
            $this->info(trans('extensions::commands.processing_extension', ['name' => $extName]));

            // Install wraps migrate + seed
            $result = Extensions::install($extName, $force);
            $this->info(trans('extensions::commands.processed_extension', ['result' => $result]));
            $this->line('');
        });

        $this->info(trans('extensions::commands.all_processed'));
    }
}
