<?php

namespace Gigabait93\Extensions\Commands;

use Gigabait93\Extensions\Facades\Extensions;

use function Laravel\Prompts\select;

class MigrateCommand extends AbstractCommand
{
    protected $signature   = 'extension:migrate
                            {name? : Extension name (default - all)}
                            {--all : Run migrations for all extensions}
                            {--force : Force the operation without confirmation}';
    protected $description = 'Run migrations and seeders for one or all extensions';

    public function handle(): void
    {
        $force = $this->option('force');
        $all   = $this->option('all');
        $name  = $this->argument('name');

        $list = Extensions::all();

        if ($name and !$all) {
            $list = $list->filter(fn ($e) => $e->getName() === $name);
        } elseif ($this->input->isInteractive() and !$all) {
            $choices = $list->map(fn ($e) => $e->getName())->toArray();
            $allLabel = trans('extensions::commands.option_all');
            $choices['all'] = $allLabel;

            $default = (array_values($choices)[0] ?? null);
            $choice = select(
                trans('extensions::commands.select_extension_migrate'),
                $choices,
                $default
            );

            if ($choice !== 'all') {
                $list = $list->filter(fn ($e) => $e->getName() === $choice);
            }
        }

        if ($list->isEmpty()) {
            $this->warn(trans('extensions::commands.no_extensions_to_process'));

            return;
        }

        $list->each(function ($ext) use ($force) {
            if (!$ext->isActive()) {
                return;
            }
            $extName = $ext->getName();
            $this->info(trans('extensions::commands.processing_extension', ['name' => $extName]));

            $result = Extensions::install($extName, $force);
            $this->info(trans('extensions::commands.processed_extension', ['result' => $result]));
            $this->line('');
        });

        $this->info(trans('extensions::commands.all_processed'));
    }
}
