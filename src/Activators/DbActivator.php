<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Activators;

use Gigabait93\Extensions\Contracts\ActivatorContract;
use Gigabait93\Extensions\Models\ExtensionStatus;
use Illuminate\Database\QueryException;
use RuntimeException;

class DbActivator implements ActivatorContract
{
    public function enable(string $id, ?string $type = null): void
    {
        $this->runAgainstTable(fn () => ExtensionStatus::updateOrCreate(['name' => $id], ['enabled' => true, 'type' => $type]));
    }

    public function disable(string $id, ?string $type = null): void
    {
        $this->runAgainstTable(fn () => ExtensionStatus::updateOrCreate(['name' => $id], ['enabled' => false, 'type' => $type]));
    }

    public function isEnabled(string $id): bool
    {
        return (bool) optional($this->runAgainstTable(
            fn () => ExtensionStatus::query()->find($id),
            missingTableFallback: null,
            throwOnMissingTable: false,
        ))->enabled;
    }

    public function remove(string $id): void
    {
        $this->runAgainstTable(fn () => ExtensionStatus::query()->where('name', $id)->delete());
    }

    public function set(string $id, bool $enabled, ?string $type = null): void
    {
        $this->runAgainstTable(fn () => ExtensionStatus::updateOrCreate(
            ['name' => $id],
            ['enabled' => $enabled, 'type' => $type]
        ));
    }

    public function statuses(): array
    {
        return $this->runAgainstTable(fn () => ExtensionStatus::query()
            ->get(['name', 'enabled', 'type'])
            ->keyBy('name')
            ->map(fn ($row) => ['enabled' => (bool) $row->enabled, 'type' => $row->type])
            ->all(), [], false);
    }

    private function runAgainstTable(
        callable $callback,
        mixed $missingTableFallback = null,
        bool $throwOnMissingTable = true,
    ): mixed {
        try {
            return $callback();
        } catch (QueryException $e) {
            if ($this->isMissingTableException($e)) {
                if (! $throwOnMissingTable) {
                    return $missingTableFallback;
                }

                throw new RuntimeException(
                    "The 'extensions' table is missing. Publish and run migrations to use the database activator.",
                    0,
                    $e
                );
            }

            throw $e;
        }
    }

    private function isMissingTableException(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'no such table')
            || str_contains($message, 'base table or view not found')
            || str_contains($message, 'relation "extensions" does not exist');
    }
}
