<?php

namespace Gigabait93\Extensions\Services;

use Gigabait93\Extensions\Contracts\ActivatorInterface;
use Gigabait93\Extensions\Entities\Extension;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ExtensionsService
{
    protected Filesystem $fs;
    protected string $basePath;
    protected array $paths;
    protected ?Collection $cache = null;
    protected ActivatorInterface $activator;

    /**
     * Constructor.
     *
     * @param ActivatorInterface $activator
     * @param string[]           $paths       Directories to scan for extensions
     */
    public function __construct(ActivatorInterface $activator, array $paths)
    {
        $this->activator = $activator;
        $this->paths     = $paths;
        $this->fs        = new Filesystem;
        $this->basePath  = rtrim(app()->basePath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Scan the filesystem for new extensions, register them (disabled by default),
     * and return the list of newly discovered names.
     *
     * @return string[]
     */
    public function discover(): array
    {
        $found = [];
        foreach ($this->paths as $base) {
            if (! $this->fs->isDirectory($base)) {
                continue;
            }
            foreach ($this->fs->directories($base) as $dir) {
                if ($this->fs->exists("$dir/extension.json")) {
                    $found[] = basename($dir);
                }
            }
        }
        $found = array_unique($found);

        $statuses = $this->activator->getStatuses();
        $new      = [];

        foreach ($found as $name) {
            if (! array_key_exists($name, $statuses)) {
                // register new extension as disabled
                $this->activator->setStatus($name, false);
                $new[] = $name;
            }
        }

        if (! empty($new)) {
            $this->invalidateCache();
        }

        return $new;
    }

    /**
     * Return a Collection of all extensions as Extension entities,
     * based solely on Activator statuses (no filesystem scan).
     *
     * @return Collection<string, Extension>
     */
    public function all(): Collection
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $statuses = $this->activator->getStatuses();

        // ensure protected extensions are always active
        foreach (config('extensions.protected', []) as $name) {
            $statuses[$name] = true;
        }

        $items = [];
        foreach ($statuses as $name => $isActive) {
            $dir = $this->findDirByName($name);
            if (! $dir) {
                continue; // directory removed or missing
            }

            $metaFile = "$dir/extension.json";
            $meta     = json_decode($this->fs->get($metaFile), true) ?: [];

            $migDir  = "$dir/Database/Migrations";
            $seedDir = "$dir/Database/Seeders";

            $items[$name] = array_merge($meta, [
                'name'          => $name,
                'type'          => $meta['type'] ?? '',
                'active'        => (bool) $isActive,
                'path'          => $dir,
                'migrationPath' => $this->fs->isDirectory($migDir)  && $this->hasPhp($migDir)  ? $migDir  : null,
                'seederPath'    => $this->fs->isDirectory($seedDir) && $this->hasPhp($seedDir) ? $seedDir : null,
            ]);
        }

        $this->cache = collect($items)
            ->map(fn(array $m) => new Extension($m, $this));

        return $this->cache;
    }

    /**
     * Get a single Extension by name.
     *
     * @param  string         $name
     * @return Extension|null
     */
    public function get(string $name): ?Extension
    {
        return $this->all()->get($name);
    }

    /**
     * Return only active extensions.
     *
     * @return Collection<string, Extension>
     */
    public function active(): Collection
    {
        return $this->all()->filter(fn(Extension $e) => $e->isActive());
    }

    /**
     * Alias for get().
     */
    public function getByName(string $name): ?Extension
    {
        return $this->get($name);
    }

    /**
     * Find extension by its declared type.
     *
     * @param  string         $type
     * @return Extension|null
     */
    public function getByType(string $type): ?Extension
    {
        return $this->all()->first(fn(Extension $e) => $e->getType() === $type);
    }

    /**
     * Find extension by its filesystem path.
     *
     * @param  string         $path
     * @return Extension|null
     */
    public function getByPath(string $path): ?Extension
    {
        return $this->all()->first(fn(Extension $e) => $e->getPath() === $path);
    }

    /**
     * Find extension by its base namespace.
     *
     * @param  string         $namespace
     * @return Extension|null
     */
    public function getByNamespace(string $namespace): ?Extension
    {
        return $this->all()->first(fn(Extension $e) => $e->getNamespace() === $namespace);
    }

    /**
     * Find extension by its main class (non-existent, placeholder).
     *
     * @param  string         $class
     * @return Extension|null
     */
    public function getByClass(string $class): ?Extension
    {
        // Placeholder: Extension::getClass() not implemented yet
        return $this->all()->first(fn(Extension $e) => method_exists($e, 'getClass') && $e->getClass() === $class);
    }

    /**
     * Find extension by both type and name.
     *
     * @param  string         $type
     * @param  string         $name
     * @return Extension|null
     */
    public function getByTypeAndName(string $type, string $name): ?Extension
    {
        return $this->all()->first(fn(Extension $e) => $e->getType() === $type && $e->getName() === $name);
    }

    /**
     * Install an extension: run migrations and seeders.
     *
     * @param  string $name
     * @param  bool   $force
     * @return string Result message
     */
    public function install(string $name, bool $force = false): string
    {
        $ext = $this->get($name);
        if (! $ext) {
            return "Extension '{$name}' not found.";
        }

        if ($mp = $ext->getMigrationPath()) {
            Artisan::call('migrate', ['--path' => $this->relative($mp), '--force' => $force]);
        }

        if ($sp = $ext->getSeederPath()) {
            $ns = $ext->getNamespace();
            if ($ns && class_exists($class = "{$ns}\\Database\\Seeders\\DatabaseSeeder")) {
                Artisan::call('db:seed', ['--class' => $class, '--force' => $force]);
            } else {
                Artisan::call('db:seed', ['--path' => $this->relative($sp), '--force' => $force]);
            }
        }

        $this->invalidateCache();
        return "Extension '{$name}' installed.";
    }

    /**
     * Enable an extension (unless protected).
     *
     * @param  string $name
     * @return string
     */
    public function enable(string $name): string
    {
        $ok = $this->activator->setStatus($name, true);
        $this->invalidateCache();
        return $ok ? "Extension enabled." : "Enable failed.";
    }

    /**
     * Disable an extension (unless protected).
     *
     * @param  string $name
     * @return string
     */
    public function disable(string $name): string
    {
        if ($this->isProtected($name)) {
            return "Extension '{$name}' is protected.";
        }
        $ok = $this->activator->setStatus($name, false);
        $this->invalidateCache();
        return $ok ? "Extension disabled." : "Disable failed.";
    }

    /**
     * Delete an extension directory (unless protected).
     *
     * @param  string $name
     * @return string
     */
    public function delete(string $name): string
    {
        if ($this->isProtected($name)) {
            return "Extension '{$name}' is protected.";
        }

        $deleted = false;
        foreach ($this->paths as $base) {
            $dir = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . $name;
            if ($this->fs->isDirectory($dir)) {
                $this->fs->deleteDirectory($dir);
                $deleted = true;
            }
        }

        $this->invalidateCache();
        return $deleted ? "Extension '{$name}' deleted." : "Extension '{$name}' not found.";
    }

    /**
     * Check if an extension has a migrations folder.
     *
     * @param  string $name
     * @return bool
     */
    public function hasMigrations(string $name): bool
    {
        return (bool) $this->get($name)?->getMigrationPath();
    }

    /**
     * Check if an extension has a seeders folder.
     *
     * @param  string $name
     * @return bool
     */
    public function hasSeeders(string $name): bool
    {
        return (bool) $this->get($name)?->getSeederPath();
    }

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
     *
     * @param  string $name
     * @return bool
     */
    protected function isProtected(string $name): bool
    {
        return in_array($name, config('extensions.protected', []), true);
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
     *
     * @param  string $dir
     * @return bool
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
     *
     * @param  string $path
     * @return string
     */
    protected function relative(string $path): string
    {
        return ltrim(Str::after($path, $this->basePath), DIRECTORY_SEPARATOR);
    }
}
