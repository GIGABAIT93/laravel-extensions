<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Activators;

use Gigabait93\Extensions\Contracts\ActivatorContract;
use Gigabait93\Extensions\Models\ExtensionStatus;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DbActivator implements ActivatorContract
{
    public function __construct()
    {
        if (!Schema::hasTable('extensions')) {
            throw new RuntimeException(
                "The 'extensions' table is missing. Publish and run migrations to use the database activator."
            );
        }
    }

    public function enable(string $id, ?string $type = null): void
    {
        ExtensionStatus::updateOrCreate(['name' => $id], ['enabled' => true, 'type' => $type]);
    }

    public function disable(string $id, ?string $type = null): void
    {
        ExtensionStatus::updateOrCreate(['name' => $id], ['enabled' => false, 'type' => $type]);
    }

    public function isEnabled(string $id): bool
    {
        return (bool) optional(ExtensionStatus::query()->find($id))->enabled;
    }

    public function remove(string $id): void
    {
        ExtensionStatus::query()->where('name', $id)->delete();
    }

    public function set(string $id, bool $enabled, ?string $type = null): void
    {
        ExtensionStatus::updateOrCreate(
            ['name' => $id],
            ['enabled' => $enabled, 'type' => $type]
        );
    }

    public function statuses(): array
    {
        return ExtensionStatus::query()
            ->get(['name', 'enabled', 'type'])
            ->keyBy('name')
            ->map(fn ($row) => ['enabled' => (bool) $row->enabled, 'type' => $row->type])
            ->all();
    }
}
