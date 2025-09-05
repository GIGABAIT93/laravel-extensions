<?php

namespace Gigabait93\Extensions\Commands;

use Gigabait93\Extensions\Actions\CreateExtensionAction;
use Gigabait93\Extensions\Actions\GenerateStubsAction;
use Gigabait93\Extensions\Commands\Concerns\HandlesStubs;
use Illuminate\Support\Str;

use function Laravel\Prompts\text;

/**
 * Command: scaffold a new extension from stub files.
 */
class MakeCommand extends AbstractCommand
{
    use HandlesStubs;

    protected $signature = 'extension:make {name?} {path?} {--stub=* : Stub groups to generate}';
    protected $description = 'Scaffold a new extension from stub files';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $name = $this->argument('name')
            ?: text(trans('extensions::commands.enter_extension_name'));
        if (! $name) {
            $this->error(trans('extensions::commands.extension_name_required'));

            return;
        }
        $name = Str::studly($name);

        $basePath = $this->selectBasePath($this->argument('path'));
        if (! $basePath) {
            return;
        }

        $stubRoot = config('extensions.stubs.path')
            ?: dirname(__DIR__, 2) . '/stubs/Extension';
        if (! $this->files->isDirectory($stubRoot)) {
            $this->error(trans('extensions::commands.stubs_path_required'));

            return;
        }

        $available = $this->availableStubs($stubRoot);
        $stubs = $this->resolveStubs($available);

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
}
