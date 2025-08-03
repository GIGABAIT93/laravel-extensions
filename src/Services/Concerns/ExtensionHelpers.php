<?php

namespace Gigabait93\Extensions\Services\Concerns;

use Illuminate\Support\Str;

trait ExtensionHelpers
{
    /**
     * Find the installation directory for a given extension name.
     *
     * @param  string      $name
     * @return string|null
     */
    protected function findDirByName(string $name): ?string
    {
        foreach ($this->paths as $base) {
            $dir = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . $name;
            if ($this->fs->isDirectory($dir) && $this->fs->exists("$dir/extension.json")) {
                return $dir;
            }
        }
        return null;
    }

    /**
     * Check if an extension is in the protected list.
     */
    protected function isProtected(string $name): bool
    {
        return in_array($name, config('extensions.protected', []), true);
    }

    /**
     * Determine if an extension can be disabled.
     *
     * Protected extensions can only be disabled when their type is listed
     * in the "switch_types" configuration.
     */
    protected function canDisable(string $name): bool
    {
        if (! $this->isProtected($name)) {
            return true;
        }

        $ext        = $this->get($name);
        $switch     = config('extensions.switch_types', []);
        $type       = $ext?->getType();

        return $type && in_array($type, $switch, true);
    }

    /**
     * Clear the internal cache so next call to all() rescans statuses.
     */
    protected function invalidateCache(): void
    {
        $this->cache = null;
    }

    /**
     * Check whether a directory contains any PHP files.
     */
    protected function hasPhp(string $dir): bool
    {
        foreach ($this->fs->files($dir) as $file) {
            if (Str::endsWith($file->getFilename(), '.php')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Convert an absolute path to a path relative to basePath.
     */
    protected function relative(string $path): string
    {
        return ltrim(Str::after($path, $this->basePath), DIRECTORY_SEPARATOR);
    }
}
