<?php

namespace Gigabait93\Extensions\Commands;

use Gigabait93\Extensions\Actions\AddStubsAction;
use Gigabait93\Extensions\Actions\GenerateStubsAction;
use Illuminate\Support\Str;

class StubCommand extends BaseExtensionCommand
{
    protected $signature = 'extension:stub {name?} {path?} {--stub=* : Stub groups to generate} {--interactive : Ask for missing values}';
    protected $description = 'Generate additional stubs for an existing extension';

    public function handle(): void
    {
        $interactive = $this->option('interactive');
        $basePath = $this->resolveBasePath($interactive, $this->argument('path'));
        if (! $basePath) {
            return;
        }

        $extensions = array_map(fn ($p) => basename($p), $this->files->directories($basePath));

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
}
