<?php

namespace Gigabait93\Extensions\Commands;

use Gigabait93\Extensions\Actions\AddStubsAction;
use Gigabait93\Extensions\Actions\GenerateStubsAction;
use Illuminate\Support\Str;
use Gigabait93\Extensions\Commands\Concerns\HandlesStubs;
use function Laravel\Prompts\select;

class StubCommand extends AbstractCommand
{
    use HandlesStubs;

    protected $signature = 'extension:stub {name?} {path?} {--stub=* : Stub groups to generate}';
    protected $description = 'Generate additional stubs for an existing extension';

    public function handle(): void
    {
        $basePath = $this->selectBasePath($this->argument('path'));
        if (! $basePath) {
            return;
        }

        $extensions = array_map(fn($p) => basename($p), $this->files->directories($basePath));

        $name = $this->argument('name');
        if (! ($name && in_array($name, $extensions, true))) {
            if (empty($extensions)) {
                $this->error(trans('extensions::commands.no_extensions_found_in_path'));
                return;
            }
            $name = select(trans('extensions::commands.select_extension'), $extensions);
        }
        if (! $name) {
            $this->error(trans('extensions::commands.extension_name_required'));
            return;
        }
        $name = Str::studly($name);

        $stubRoot = config('extensions.stubs.path');
        $available = $this->availableStubs($stubRoot);
        $stubs = $this->resolveStubs($available);

        $generator = new GenerateStubsAction($this->files, $stubRoot);
        $action = new AddStubsAction($this->files, $generator);
        $type = basename($basePath);
        try {
            $action->execute($name, $basePath, $type, $stubs);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return;
        }

        $this->info(trans('extensions::commands.stubs_generated'));
    }
}
