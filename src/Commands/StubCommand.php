<?php

namespace Gigabait93\Extensions\Commands;

use Gigabait93\Extensions\Actions\AddStubsAction;
use Gigabait93\Extensions\Actions\GenerateStubsAction;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class StubCommand extends Command
{
    protected $signature = 'extension:stub {name?} {path?} {--stub=* : Stub groups to generate} {--interactive : Ask for missing values}';
    protected $description = 'Generate additional stubs for an existing extension';

    protected Filesystem $files;
    protected array $bases;

    public function __construct()
    {
        parent::__construct();
        $this->files = new Filesystem;
        $this->bases = config('extensions.paths', []);
    }

    public function handle(): void
    {
        if (empty($this->bases)) {
            $this->error("Please configure at least one entry in config('extensions.paths')");
            return;
        }

        $interactive = $this->option('interactive');
        $paths = array_values($this->bases);
        $basePath = $this->argument('path');
        if ($basePath && in_array($basePath, $paths, true)) {
            // ok
        } elseif ($interactive) {
            $basePath = $this->choice('Select base path for the extension', $paths);
        } else {
            $this->error('Base path is required');
            return;
        }

        $extensions = array_map(fn($p) => basename($p), $this->files->directories($basePath));

        $name = $this->argument('name');
        if ($name && in_array($name, $extensions, true)) {
            // ok
        } elseif ($interactive) {
            if (empty($extensions)) {
                $this->error('No extensions found in selected path');
                return;
            }
            $name = $this->choice('Select extension', $extensions);
        } else {
            $this->error('Extension name is required');
            return;
        }
        $name = Str::studly($name);

        $stubRoot = config('extensions.stubs.path');
        $available = $this->availableStubs($stubRoot);
        $stubs = $this->option('stub');
        if (empty($stubs)) {
            if ($interactive) {
                $stubs = $this->choice('Select stubs to generate', $available, null, null, true);
            } else {
                $stubs = config('extensions.stubs.default', $available);
            }
        }

        $generator = new GenerateStubsAction($this->files, $stubRoot);
        $action = new AddStubsAction($this->files, $generator);
        $type = basename($basePath);
        try {
            $action->execute($name, $basePath, $type, $stubs);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return;
        }

        $this->info('Stubs generated successfully');
    }

    protected function availableStubs(string $stubRoot): array
    {
        $groups = [];
        foreach ($this->files->allFiles($stubRoot) as $file) {
            $rel = Str::after($file->getPathname(), $stubRoot . DIRECTORY_SEPARATOR);
            $rel = str_replace('\\', '/', $rel);
            $group = Str::before($rel, '/');
            $group = Str::before($group, '.');
            $groups[] = Str::lower($group);
        }
        return array_values(array_unique($groups));
    }
}