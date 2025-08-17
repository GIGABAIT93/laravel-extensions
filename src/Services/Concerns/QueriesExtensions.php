<?php

namespace Gigabait93\Extensions\Services\Concerns;

use Gigabait93\Extensions\Entities\Extension;
use Illuminate\Support\Collection;

trait QueriesExtensions
{
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
        foreach (config('extensions.protected', []) as $name) {
            $statuses[$name] = $statuses[$name] ?? true;
        }

        $items = [];
        foreach ($statuses as $name => $isActive) {
            $dir = $this->findDirByName($name);
            if (! $dir) {
                continue; // directory removed or missing
            }

            $metaFile = "$dir/extension.json";
            $meta     = json_decode($this->fs->get($metaFile), true) ?: [];

            $type    = $meta['type'] ?? '';
            $migDir  = "$dir/Database/Migrations";
            $seedDir = "$dir/Database/Seeders";

            $active = (bool) $isActive;
            if (in_array($name, config('extensions.protected', []), true)
                && ! ($type && in_array($type, config('extensions.switch_types', []), true))) {
                $active = true;
            }

            $items[$name] = array_merge($meta, [
                'name'          => $name,
                'type'          => $type,
                'active'        => $active,
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
     */
    public function get(string $name): ?Extension
    {
        return $this->all()->get($name);
    }

    /**
     * Return only active extensions.
     *
     * @param  string|null  $type
     * @return Collection<string, Extension>
     */
    public function active(?string $type = null): Collection
    {
        return $this->all()->filter(
            fn (Extension $e) => $e->isActive() && ($type === null || $e->getType() === $type)
        );
    }

    /**
     * Alias for get().
     */
    public function getByName(string $name): ?Extension
    {
        return $this->get($name);
    }

    /**
     * Filter extensions using a callback.
     *
     * @param callable $callback
     * @return Collection<string, Extension>
     */
    protected function filterExtensions(callable $callback): Collection
    {
        return $this->all()->filter($callback);
    }

    /**
     * Find extensions by their declared type.
     *
     * @return Collection<string, Extension>
     */
    public function getByType(string $type): Collection
    {
        return $this->filterExtensions(fn(Extension $e) => $e->getType() === $type);
    }

    /**
     * Find extensions by their filesystem path.
     *
     * @return Collection<string, Extension>
     */
    public function getByPath(string $path): Collection
    {
        return $this->filterExtensions(fn(Extension $e) => $e->getPath() === $path);
    }

    /**
     * Find extensions by their base namespace.
     *
     * @return Collection<string, Extension>
     */
    public function getByNamespace(string $namespace): Collection
    {
        return $this->filterExtensions(fn(Extension $e) => $e->getNamespace() === $namespace);
    }

    /**
     * Find extensions by their main class (non-existent, placeholder).
     *
     * @return Collection<string, Extension>
     */
    public function getByClass(string $class): Collection
    {
        return $this->filterExtensions(fn(Extension $e) => method_exists($e, 'getClass') && $e->getClass() === $class);
    }

    /**
     * Find extensions by both type and name.
     *
     * @return Collection<string, Extension>
     */
    public function getByTypeAndName(string $type, string $name): Collection
    {
        return $this->filterExtensions(fn(Extension $e) => $e->getType() === $type && $e->getName() === $name);
    }
}
