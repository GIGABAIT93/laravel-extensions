<?php

namespace Gigabait93\Extensions\Services;

use Gigabait93\Extensions\Contracts\ActivatorInterface;
use Gigabait93\Extensions\Entities\Extension;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class Extensions
{
    /** @var string[] Paths to directories with extensions */
    protected array $extensionsPaths;

    /** @var Collection<string, Extension>|null Cached collection of Extension objects */
    protected ?Collection $cachedExtensions = null;

    /**
     * @param ActivatorInterface $activator
     * @param string[] $extensionsPaths
     */
    public function __construct(
        protected ActivatorInterface $activator,
        array                        $extensionsPaths
    )
    {
        $this->extensionsPaths = $extensionsPaths;
    }

    /**
     * Clear the cache of Extension objects.
     * This is called when the status of an extension changes.
     */
    protected function invalidateCache(): void
    {
        $this->cachedExtensions = null;
    }

    /**
     * Download all expression.json, create Expression objects
     *
     * @return Collection<string, Extension>
     */
    public function all(): Collection
    {
        if ($this->cachedExtensions !== null) {
            return $this->cachedExtensions;
        }

        // We take statuses and forcibly turn on “Protected”
        $statuses = $this->activator->getStatuses();
        foreach (config('extensions.protected', []) as $p) {
            $statuses[$p] = true;
        }

        $items = [];
        foreach ($this->extensionsPaths as $path) {
            if (!is_dir($path)) continue;

            foreach (glob($path . '/*/extension.json') ?: [] as $file) {
                $data = json_decode(File::get($file), true);
                if (!is_array($data)) continue;

                $name = basename(dirname($file));
                $items[$name] = array_merge($data, [
                    'name' => $name,
                    'active' => $statuses[$name] ?? false,
                ]);
            }
        }

        $this->cachedExtensions = collect($items)
            ->map(fn(array $meta) => new Extension($meta));

        return $this->cachedExtensions;
    }

    public function install(string $name): string
    {
        $dir = $this->getDirectory($name);
        if (!$dir) {
            return "Extension '{$name}' not found.";
        }
        Artisan::call('extension:migrate', [
            'name' => $name,
            '--force' => true,
        ]);

        $this->invalidateCache();
        return "Extension '{$name}' has been successfully installed.";
    }

    public function enable(string $name): string
    {
        if ($this->isProtected($name)) {
            return "Extension '{$name}' is protected and cannot be disabled/enabled manually.";
        }

        $ok = $this->activator->setStatus($name, true);
        $this->invalidateCache();

        return $ok
            ? "Extension '{$name}' enabled."
            : "Failed to enable extension '{$name}'.";
    }

    public function disable(string $name): string
    {
        if ($this->isProtected($name)) {
            return "Extension '{$name}' is protected and cannot be disabled.";
        }

        $ok = $this->activator->setStatus($name, false);
        $this->invalidateCache();

        return $ok
            ? "Extension '{$name}' disabled."
            : "Failed to disable extension '{$name}'.";
    }

    public function delete(string $name): string
    {
        if ($this->isProtected($name)) {
            return "Extension '{$name}' is protected and cannot be deleted.";
        }

        $found = false;
        foreach ($this->extensionsPaths as $path) {
            $dir = "{$path}/{$name}";
            if (is_dir($dir)) {
                File::deleteDirectory($dir);
                $found = true;
            }
        }
        $this->invalidateCache();

        return $found
            ? "Extension '{$name}' removed."
            : "Extension '{$name}' not found.";
    }

    public function discover(): array
    {
        $found = [];
        foreach ($this->extensionsPaths as $path) {
            if (!is_dir($path)) continue;
            foreach (glob($path . '/*/extension.json') ?: [] as $file) {
                $found[] = basename(dirname($file));
            }
        }
        return array_unique($found);
    }

    public function getByName(string $name): ?Extension
    {
        return $this->all()->first(fn(Extension $e) => $e->getName() === $name);
    }

    public function getByType(string $type): Collection
    {
        return $this->all()->filter(fn(Extension $e) => $e->getType() === $type);
    }

    public function getByActive(bool $active): Collection
    {
        return $this->all()->filter(fn(Extension $e) => $e->isActive() === $active);
    }

    public function getActiveByType(string $type): Collection
    {
        return $this->all()
            ->filter(fn(Extension $e) => $e->isActive() && $e->getType() === $type);
    }

    /** We find a directory by name */
    protected function getDirectory(string $name): ?string
    {
        foreach ($this->extensionsPaths as $path) {
            $dir = "{$path}/{$name}";
            if (is_dir($dir) && file_exists("{$dir}/extension.json")) {
                return $dir;
            }
        }
        return null;
    }

    /** We check if Extension “protected” */
    protected function isProtected(string $name): bool
    {
        return in_array($name, config('extensions.protected', []), true);
    }
}
