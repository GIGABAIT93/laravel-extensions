<?php

namespace Gigabait93\Extensions\Entities;

use Gigabait93\Extensions\Services\ExtensionsService;
use Illuminate\Support\Str;

/**
 * Value object representing a discovered or configured extension.
 */
class Extension
{
    protected string     $name;
    protected string     $type;
    protected string     $version;
    protected bool       $active;
    protected string     $path;
    protected ?string    $migrationPath;
    protected ?string    $seederPath;
    protected ExtensionsService $service;
    protected array      $meta;

    /**
     * @param array $data Extension metadata array
     * @param ExtensionsService $service Service used to manage lifecycle
     */
    public function __construct(array $data, ExtensionsService $service)
    {
        $this->service       = $service;
        $this->name          = $data['name'];
        $this->type          = $data['type']          ?? '';
        $this->version       = $data['version']       ?? '1.0.0';
        $this->active        = $data['active'];
        $this->path          = $data['path'];
        $this->migrationPath = $data['migrationPath'] ?? null;
        $this->seederPath    = $data['seederPath']    ?? null;
        $this->meta          = $data;
    }

    /** Get extension name. */
    public function getName(): string
    {
        return $this->name;
    }

    /** Get normalized extension type. */
    public function getType(): string
    {
        return strtolower($this->type);
    }

    /** Get version string. */
    public function getVersion(): string
    {
        return $this->version;
    }

    /** Whether extension is currently active. */
    public function isActive(): bool
    {
        return $this->active;
    }

    /** Whether extension is listed as protected in config. */
    public function isProtected(): bool
    {
        return in_array($this->name, config('extensions.protected', []), true);
    }

    /** Get filesystem path to the extension root. */
    public function getPath(): string
    {
        return $this->path;
    }

    /** Get migrations path if defined in metadata. */
    public function getMigrationPath(): ?string
    {
        return $this->migrationPath;
    }

    /** Get seeders path if defined in metadata. */
    public function getSeederPath(): ?string
    {
        return $this->seederPath;
    }

    /**
     * Get raw metadata or a specific key with default.
     *
     * @return array|string
     */
    public function getMeta(?string $key = '', ?string $default = ''): array|string
    {
        if ($key) {
            return $this->meta[$key] ?? $default;
        }

        return $this->meta;
    }

    /** Run extension installation (migrate + seed). */
    public function install(bool $force = false): string
    {
        return $this->service->install($this->name, $force);
    }

    /** Enable the extension. */
    public function enable(): string
    {
        return $this->service->enable($this->name);
    }

    /** Disable the extension. */
    public function disable(): string
    {
        return $this->service->disable($this->name);
    }

    /** Delete the extension. */
    public function delete(): string
    {
        return $this->service->delete($this->name);
    }

    /**
     * Resolve root namespace for the extension (based on provider FQCN).
     */
    public function getNamespace(): ?string
    {
        $prov = $this->meta['provider'] ?? null;
        if (! $prov || ! class_exists($prov)) {
            return null;
        }

        return Str::beforeLast($prov, '\\Providers');
    }
}
