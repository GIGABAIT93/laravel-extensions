<?php

namespace Gigabait93\Extensions\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeCommand extends Command
{
    protected $signature = 'extension:make
                            {name? : Extension name in StudlyCase}
                            {path? : Base path (choose from config extensions.paths)}';
    protected $description = 'Interactively scaffold a new extension from stub files';

    protected Filesystem $files;
    protected string $stubRoot;
    protected array $bases;

    public function __construct()
    {
        parent::__construct();
        $this->files = new Filesystem;
        $this->stubRoot = __DIR__ . '/../../stubs/Extension';
        $this->bases = config('extensions.paths', []);
    }

    public function handle(): void
    {
        if (empty($this->bases)) {
            $this->error("Please configure at least one entry in config('extensions.paths')");
            return;
        }

        $name = $this->argument('name');
        if (! $name) {
            $name = $this->ask('Enter extension name');
        }
        $name = Str::studly($name);

        $paths = array_values($this->bases);
        $inputPath = $this->argument('path');

        if ($inputPath && in_array($inputPath, $paths, true)) {
            $basePath = $inputPath;
        } else {
            $basePath = $this->choice('Select base path for the extension', $paths);
        }

        $namespace = ucfirst(basename($basePath));

        if ($namespace === false) {
            $this->error("Selected path is not defined in config('extensions.paths')");
            return;
        }

        $type = basename($basePath);

        $destination = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . $name;

        if ($this->files->exists($destination)) {
            $this->error("Extension {$name} already exists at {$destination}");
            return;
        }

        $this->copyStubs($name, $namespace, $destination, $type);

        $this->info("Extension {$name} created in namespace {$namespace} at {$destination}");
    }

    protected function copyStubs(string $name, string $namespace, string $dest, string $type): void
    {
        $snake = Str::snake($name);
        $snakePlural = Str::plural($snake);
        $camelLower = lcfirst(Str::camel($name));

        foreach ($this->files->allFiles($this->stubRoot) as $file) {
            $stubPath = $file->getPathname();
            $rel = Str::after($stubPath, $this->stubRoot . DIRECTORY_SEPARATOR);

            if (preg_match('#^Database/Migrations/migration_create_.*\\.stub$#', $rel)) {
                $timestamp = now()->format('Y_m_d_His');
                $rel = "Database/Migrations/{$timestamp}_create_{$snakePlural}_table.php";
            } else {
                $rel = str_replace(
                    ['{{name}}', '.stub'],
                    [$name, ''],
                    $rel
                );
            }

            $target = $dest . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            $this->files->ensureDirectoryExists(dirname($target));

            $stub = $this->files->get($stubPath);
            $content = str_replace(
                ['{{namespace}}', '{{name}}', '{{snake}}', '{{snakePlural}}', '{{camelLower}}', '{{type}}'],
                [$namespace, $name, $snake, $snakePlural, $camelLower, $type],
                $stub
            );

            $this->files->put($target, $content);
        }
    }
}
