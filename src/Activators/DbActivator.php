<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Activators;

use Gigabait93\Extensions\Contracts\ActivatorContract;
use Gigabait93\Extensions\Models\ExtensionStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class DbActivator implements ActivatorContract
{
    public function enable(string $id, ?string $type = null): void
    {
        $this->runAgainstTable(fn () => ExtensionStatus::updateOrCreate(['name' => $id], ['enabled' => true, 'type' => $type]));
        $this->flushStatusesCache();
    }

    public function disable(string $id, ?string $type = null): void
    {
        $this->runAgainstTable(fn () => ExtensionStatus::updateOrCreate(['name' => $id], ['enabled' => false, 'type' => $type]));
        $this->flushStatusesCache();
    }

    public function isEnabled(string $id): bool
    {
        return (bool) ($this->statuses()[$id]['enabled'] ?? false);
    }

    public function remove(string $id): void
    {
        $this->runAgainstTable(fn () => ExtensionStatus::query()->where('name', $id)->delete());
        $this->flushStatusesCache();
    }

    public function set(string $id, bool $enabled, ?string $type = null): void
    {
        $this->runAgainstTable(fn () => ExtensionStatus::updateOrCreate(
            ['name' => $id],
            ['enabled' => $enabled, 'type' => $type]
        ));
        $this->flushStatusesCache();
    }

    public function statuses(): array
    {
        if (!(bool) config('extensions.activator_cache.enabled', true)) {
            return $this->loadStatuses();
        }

        return Cache::remember(
            $this->statusesCacheKey(),
            max(1, (int) config('extensions.activator_cache.ttl', 300)),
            fn (): array => $this->loadStatuses(),
        );
    }

    private function loadStatuses(): array
    {
        return $this->runAgainstTable(fn (): array => ExtensionStatus::query()
            ->get(['name', 'enabled', 'type'])
            ->keyBy('name')
            ->map(fn ($row) => ['enabled' => (bool) $row->enabled, 'type' => $row->type])
            ->all(), [], false);
    }

    private function flushStatusesCache(): void
    {
        Cache::forget($this->statusesCacheKey());
    }

    private function statusesCacheKey(): string
    {
        return (string) config('extensions.activator_cache.key', 'extensions:activator:statuses');
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
                if (!$throwOnMissingTable) {
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
