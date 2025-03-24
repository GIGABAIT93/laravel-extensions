<?php

namespace Gigabait93\Extensions\Entities;

use Gigabait93\Extensions\Services\Extensions;

class Extension
{
    protected string $name;
    protected string $type;
    protected bool $active;
    protected array $meta;

    public function __construct(array $data)
    {
        $this->name   = $data['name'] ?? '';
        $this->type   = $data['type'] ?? '';
        $this->active = $data['active'] ?? false;
        $this->meta   = $data;
    }

    /**
     * Returns the extension name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the extension type.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Checks if the extension is active.
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Returns the raw extension data.
     */
    public function getData(): array
    {
        return $this->meta;
    }

    /**
     * Installs the extension via the Extensions service.
     */
    public function install(): string
    {
        return Extensions::getInstance()->install($this->getName());
    }

    /**
     * Enables the extension via the Extensions service.
     */
    public function enable(): string
    {
        return Extensions::getInstance()->enable($this->getName());
    }

    /**
     * Disables the extension via the Extensions service.
     */
    public function disable(): string
    {
        return Extensions::getInstance()->disable($this->getName());
    }

    /**
     * Deletes the extension via the Extensions service.
     */
    public function delete(): string
    {
        return Extensions::getInstance()->delete($this->getName());
    }
}
