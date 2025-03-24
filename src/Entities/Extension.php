<?php

namespace Gigabait93\Extensions\Entities;

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
}
