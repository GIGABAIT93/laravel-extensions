<?php

namespace Gigabait93\Extensions\Entities;

class Extension
{
    public string $name;
    public string $type;
    public bool $active;
    public ?string $created_at;
    public ?string $updated_at;
    public array $attributes;

    public function __construct(array $data)
    {
        $this->name       = $data['name'] ?? '';
        $this->type       = $data['type'] ?? '';
        $this->active     = $data['active'] ?? false;
        $this->created_at = $data['created_at'] ?? null;
        $this->updated_at = $data['updated_at'] ?? null;
        $this->attributes = $data;
    }
    
    /**
     * Get the extension name.
     */
    public function getName(): string
    {
        return $this->attributes['name'] ?? '';
    }

    /**
     * Get the extension type.
     */
    public function getType(): ?string
    {
        return $this->attributes['type'] ?? null;
    }

    /**
     * Check if the extension is active.
     */
    public function isActive(): bool
    {
        return $this->attributes['active'] ?? false;
    }

    /**
     * Get the raw extension data.
     */
    public function getData(): array
    {
        return $this->attributes;
    }
}
