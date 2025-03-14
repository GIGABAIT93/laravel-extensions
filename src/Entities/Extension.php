<?php
// src/Entities/Extension.php

namespace Gigabait93\Modules\Entities;

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
}
