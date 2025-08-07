<?php

namespace Gigabait93\Extensions\Services;

use Gigabait93\Extensions\Actions\CreateExtensionAction;
use Gigabait93\Extensions\Actions\GenerateStubsAction;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;

class ExtensionBuilder
{
    protected ?string $name = null;

    protected ?string $basePath = null;

    protected ?array $stubs = null;

    protected ?string $stubRoot = null;

    public function __construct(protected Filesystem $files)
    {
    }

    /**
     * Specify extension name.
     */
    public function name(string $name): self
    {
        $this->name = Str::studly($name);

        return $this;
    }

    /**
     * Specify base path where extension will be created.
     */
    public function in(string $basePath): self
    {
        $this->basePath = $basePath;
        if (! $this->files->isDirectory($basePath)) {
            $this->files->makeDirectory($basePath, 0755, true, true);
        }

        return $this;
    }

    /**
     * Specify stub groups to generate.
     */
    public function stubs(array $groups): self
    {
        $this->stubs = $groups;

        return $this;
    }

    /**
     * Add a stub group to be generated.
     */
    public function addStub(string $group): self
    {
        $this->stubs[] = $group;

        return $this;
    }

    /**
     * Override the stub root path.
     */
    public function stubRoot(string $path): self
    {
        $this->stubRoot = $path;

        return $this;
    }

    /**
     * Build a new extension using configured stubs.
     *
     * Any parameters passed directly will override previously configured values.
     */
    public function build(?string $name = null, ?string $basePath = null, ?array $stubs = null): string
    {
        $name = $name ? Str::studly($name) : $this->name;
        $basePath = $basePath ?: $this->basePath ?: $this->resolveBasePath();
        $stubRoot = $this->stubRoot ?: $this->resolveStubRoot();
        $stubs = $stubs ?? $this->stubs ?? config('extensions.stubs.default', []);

        if (! $name) {
            throw new RuntimeException('Extension name is required.');
        }

        $generator = new GenerateStubsAction($this->files, $stubRoot);
        $action = new CreateExtensionAction($this->files, $generator);
        $type = basename($basePath);
        $action->execute($name, $basePath, $type, $stubs);

        return rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . $name;
    }

    /**
     * Retrieve configured extension paths.
     */
    public function paths(): array
    {
        return config('extensions.paths', []);
    }

    /**
     * Retrieve available stub groups from the stub root.
     */
    public function stubGroups(): array
    {
        $root = $this->stubRoot ?: $this->resolveStubRoot();

        $groups = [];
        foreach ($this->files->allFiles($root) as $file) {
            $rel = Str::after($file->getPathname(), $root . DIRECTORY_SEPARATOR);
            $rel = str_replace('\\', '/', $rel);
            $group = Str::before($rel, '/');
            $group = Str::before($group, '.');
            $groups[] = Str::lower($group);
        }

        return array_values(array_unique($groups));
    }

    /**
     * Resolve the stub root path currently in use.
     */
    public function stubPath(): string
    {
        return $this->stubRoot ?: $this->resolveStubRoot();
    }

    protected function resolveBasePath(): string
    {
        $paths = $this->paths();
        if (empty($paths)) {
            throw new RuntimeException(trans('extensions::commands.paths_required'));
        }
        $basePath = $paths[0];
        if (! $this->files->isDirectory($basePath)) {
            throw new RuntimeException(trans('extensions::commands.base_path_required'));
        }
        return $basePath;
    }

    protected function resolveStubRoot(): string
    {
        $stubRoot = config('extensions.stubs.path')
            ?: dirname(__DIR__, 2) . '/stubs/Extension';
        if (! $this->files->isDirectory($stubRoot)) {
            throw new RuntimeException(trans('extensions::commands.stubs_path_required'));
        }
        return $stubRoot;
    }
}