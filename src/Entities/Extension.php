<?php

namespace Gigabait93\Extensions\Entities;

use Gigabait93\Extensions\Services\ExtensionsService;
use Illuminate\Support\Str;

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

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isProtected(): bool
    {
        return in_array($this->name, config('extensions.protected', []), true);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMigrationPath(): ?string
    {
        return $this->migrationPath;
    }

    public function getSeederPath(): ?string
    {
        return $this->seederPath;
    }

    public function getMeta(?string $key = '', ?string $default = ''): array|string
    {
        if ($key) {
            return $this->meta[$key] ?? $default;
        }
        return $this->meta;
    }

    public function install(bool $force = false): string
    {
        return $this->service->install($this->name, $force);
    }

    public function enable(): string
    {
        return $this->service->enable($this->name);
    }

    public function disable(): string
    {
        return $this->service->disable($this->name);
    }

    public function delete(): string
    {
        return $this->service->delete($this->name);
    }

    public function getNamespace(): ?string
    {
        $prov = $this->meta['provider'] ?? null;
        if (! $prov || ! class_exists($prov)) {
            return null;
        }
        return Str::beforeLast($prov, '\\Providers');
    }
}
