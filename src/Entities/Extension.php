<?php

namespace Gigabait93\Extensions\Entities;

use Gigabait93\Extensions\Services\Extensions;
use Illuminate\Support\Facades\App;

class Extension
{
    protected string $name;
    protected string $type;
    protected bool $active;
    protected array $meta;

    /**
     * @param array{name?: string, type?: string, active?: bool, ...} $data
     */
    public function __construct(array $data)
    {
        $this->name   = $data['name']   ?? '';
        $this->type   = $data['type']   ?? '';
        $this->active = $data['active'] ?? false;
        $this->meta   = $data;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Checking on “Active”
     */
    public function isActive(): bool
    {
        if ($this->isProtected()) {
            return true;
        }
        return $this->active;
    }

    /** Checking on “Protected” */
    public function isProtected(): bool
    {
        return in_array($this->name, config('extensions.protected', []), true);
    }

    public function getData(): array
    {
        return $this->meta;
    }

    public function install(): string
    {
        return App::make(Extensions::class)
            ->install($this->name);
    }

    public function enable(): string
    {
        return App::make(Extensions::class)
            ->enable($this->name);
    }

    public function disable(): string
    {
        return App::make(Extensions::class)
            ->disable($this->name);
    }

    public function delete(): string
    {
        return App::make(Extensions::class)
            ->delete($this->name);
    }
}
