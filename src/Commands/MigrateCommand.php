<?php

namespace Gigabait93\Extensions\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use ReflectionClass;
use Gigabait93\Extensions\Services\Extensions;

class MigrateCommand extends Command
{
    protected $signature = 'extension:migrate
                            {name? : Extension name (default - all)}
                            {--force : Forcibly perform in the sold}';
    protected $description = 'Run migration for one or all extensions';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(Extensions $extService): void
    {
        $force = $this->option('force');
        $extensions = $extService->all();

        if ($name = $this->argument('name')) {
            $extensions = $extensions->filter(fn($e) => $e->getName() === $name);
        }

        foreach ($extensions as $extension) {
            $providerClass = $extension->getData()['provider'] ?? null;

            // We define the extension root through the provider's reflection
            if (!class_exists($providerClass)) {
                continue;
            }

            $ref = new ReflectionClass($providerClass);
            $moduleDir = dirname($ref->getFileName(), 2);
            $migDir = $moduleDir . '/Database/migrations';

            if (!is_dir($migDir)) {
                continue;
            }

            // Let's check if there is at least one file *.php
            $files = glob($migDir . '/*.php');
            if (empty($files)) {
                continue;
            }

            // Relative path for artisan
            $relPath = ltrim(str_replace(base_path(), '', $migDir), '/');

            $this->info(" → Run the module migration '{$extension->getName()}': {$relPath}");
            Artisan::call('migrate', [
                '--path' => $relPath,
                '--force' => $force,
            ]);
            $this->line(Artisan::output());
        }
    }
}
