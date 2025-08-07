<?php

namespace Gigabait93\Extensions\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

abstract class BaseExtensionCommand extends Command
{
    protected Filesystem $files;

    protected array $bases;

    public function __construct()
    {
        parent::__construct();

        $this->files = new Filesystem;
        $this->bases = config('extensions.paths', []);
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

    protected function resolveBasePath(bool $interactive, ?string $basePath): ?string
    {
        if (empty($this->bases)) {
            $this->error("Please configure at least one entry in config('extensions.paths')");

            return null;
        }

        $paths = array_values($this->bases);

        if ($basePath && in_array($basePath, $paths, true)) {
            return $basePath;
        }

        if ($interactive) {
            return $this->choice('Select base path for the extension', $paths);
        }

        $this->error('Base path is required');

        return null;
    }
}
