<?php

namespace Gigabait93\Extensions\Activators;

use Gigabait93\Extensions\Contracts\ActivatorInterface;
use Gigabait93\Extensions\Models\ExtensionStatus;

class DbActivator implements ActivatorInterface
{
    public function getStatuses(): array
    {
        return ExtensionStatus::all()
            ->pluck('enabled', 'name')
            ->map(fn ($v) => (bool) $v)
            ->toArray();
    }

    public function setStatus(string $extension, bool $status): bool
    {
        $model = ExtensionStatus::updateOrCreate(
            ['name' => $extension],
            ['enabled' => $status]
        );

        return (bool) $model;
    }
}
