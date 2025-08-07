<?php

namespace Gigabait93\Extensions\Commands;

use Gigabait93\Extensions\Facades\Extensions;
use Illuminate\Console\Command;

class MigrateCommand extends Command
{
    protected $signature = 'extension:migrate
                            {name? : Extension name (default - all)}
                            {--force : Force the operation without confirmation}';
    protected $description = 'Run migrations and seeders for one or all extensions';

    public function handle(): void
    {
        $force = $this->option('force');
        $name = $this->argument('name');

        $list = Extensions::all();

        if ($name) {
            $list = $list->filter(fn ($e) => $e->getName() === $name);
        }

        if ($list->isEmpty()) {
            $this->warn('No extensions to process.');

            return;
        }

        $list->each(function ($ext) use ($force) {
            $extName = $ext->getName();
            $this->info("→ Processing extension '{$extName}'");

            // Install wraps migrate + seed
            $result = Extensions::install($extName, $force);
            $this->info("✔ {$result}");
            $this->line('');
        });

        $this->info('All requested extensions processed.');
    }
}
