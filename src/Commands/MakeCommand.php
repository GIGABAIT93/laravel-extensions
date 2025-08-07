<?php

namespace Gigabait93\Extensions\Commands;

use Gigabait93\Extensions\Actions\CreateExtensionAction;
use Gigabait93\Extensions\Actions\GenerateStubsAction;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeCommand extends Command
{
    protected $signature = 'extension:make {name?} {path?} {--stub=* : Stub groups to generate}';
    protected $description = 'Scaffold a new extension from stub files';

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
            $this->error(trans('extensions::commands.paths_required'));
            return;
        }

        $name = $this->argument('name');
        if (! $name) {
            if ($this->input->isInteractive()) {
                $name = $this->ask(trans('extensions::commands.enter_extension_name'));
            }
            if (! $name) {
                $this->error(trans('extensions::commands.extension_name_required'));
                return;
            }
        }
        $name = Str::studly($name);

        $paths = array_values($this->bases);
        $basePath = $this->argument('path');
        if ($basePath && in_array($basePath, $paths, true)) {
            // ok
        } elseif ($this->input->isInteractive()) {
            $basePath = $this->choice(trans('extensions::commands.select_base_path'), $paths);
        } else {
            $this->error(trans('extensions::commands.base_path_required'));
            return;
        }

        $stubRoot = config('extensions.stubs.path')
            ?: dirname(__DIR__, 2) . '/stubs/Extension';
        if (! $this->files->isDirectory($stubRoot)) {
            $this->error(trans('extensions::commands.stubs_path_required'));
            return;
        }

        $available = $this->availableStubs($stubRoot);
        $stubs     = $this->option('stub');
        if (empty($stubs)) {
            if ($this->input->isInteractive()) {
                $stubs = $this->choice(trans('extensions::commands.select_stubs'), $available, null, null, true);
            } else {
                $stubs = config('extensions.stubs.default') ?: $available;
            }
        }

        $generator = new GenerateStubsAction($this->files, $stubRoot);
        $action = new CreateExtensionAction($this->files, $generator);
        $type = basename($basePath);
        try {
            $action->execute($name, $basePath, $type, $stubs);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return;
        }

        $namespace = ucfirst(basename($basePath));
        $destination = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . $name;
        $this->info(trans('extensions::commands.extension_created', compact('name', 'namespace', 'destination')));
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
