<?php

namespace Gigabait93\Extensions\Commands;

use Gigabait93\Extensions\Actions\CreateExtensionAction;
use Gigabait93\Extensions\Actions\GenerateStubsAction;
use Illuminate\Support\Str;

class MakeCommand extends BaseExtensionCommand
{
    protected $signature = 'extension:make {name?} {path?} {--stub=* : Stub groups to generate} {--interactive : Ask for missing values}';
    protected $description = 'Scaffold a new extension from stub files';

    public function handle(): void
    {
        $interactive = $this->option('interactive');

        $name = $this->argument('name');
        if (! $name) {
            if (! $interactive) {
                $this->error('Extension name is required');

                return;
            }

            $name = $this->ask('Enter extension name');
        }
        $name = Str::studly($name);

        $basePath = $this->resolveBasePath($interactive, $this->argument('path'));
        if (! $basePath) {
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
}
