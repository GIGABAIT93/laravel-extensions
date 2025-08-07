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
                $this->error('Extension name is required');
                return;
            }
            $name = $this->ask('Enter extension name');
        }
        $name = Str::studly($name);

        $paths = array_values($this->bases);
        $inputPath = $this->argument('path');
        if ($inputPath && in_array($inputPath, $paths, true)) {
            $basePath = $inputPath;
        } elseif ($interactive) {
            $basePath = $this->choice('Select base path for the extension', $paths);
        } else {
            $this->error('Base path is required');
            return;
        }

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
        $this->info("Extension {$name} created in namespace {$namespace} at {$destination}");
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
