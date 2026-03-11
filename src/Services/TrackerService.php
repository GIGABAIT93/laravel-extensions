<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Services;

use DateTimeInterface;
use Gigabait93\Extensions\Models\ExtensionOperation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TrackerService
{
    private ?bool $databaseAvailable = null;

    private static ?int $lastPrunedAt = null;

    public function createOperation(string $type, string $extensionId, array $context = []): string
    {
        $operationId = (string) Str::uuid();

        $this->updateOperation($operationId, [
            'id' => $operationId,
            'type' => $type,
            'extension_id' => $extensionId,
            'status' => 'queued',
            'progress' => 0,
            'message' => 'Operation queued',
            'context' => $context,
            'created_at' => now()->toISOString(),
            'started_at' => null,
            'completed_at' => null,
            'error' => null,
            'result' => null,
        ]);

        $this->pruneExpiredOperations();

        return $operationId;
    }

    public function updateOperation(string $operationId, array $data): void
    {
        if ($this->shouldUseDatabase()) {
            $this->updateOperationInDatabase($operationId, $data);

            return;
        }

        $this->updateOperationInCache($operationId, $data);
    }

    public function getOperation(string $operationId): ?array
    {
        if ($this->shouldUseDatabase()) {
            $operation = ExtensionOperation::query()->find($operationId);

            return $operation ? $this->toPayload($operation) : null;
        }

        return Cache::get($this->cacheOperationKey($operationId));
    }

    public function markAsStarted(string $operationId): void
    {
        $this->updateOperation($operationId, [
            'status' => 'running',
            'started_at' => now()->toISOString(),
            'message' => 'Operation started',
        ]);
    }

    public function markAsCompleted(string $operationId, array $result = [], string $message = 'Completed successfully'): void
    {
        $this->updateOperation($operationId, [
            'status' => 'completed',
            'progress' => 100,
            'message' => $message,
            'completed_at' => now()->toISOString(),
            'result' => $result,
        ]);
    }

    public function markAsFailed(string $operationId, string $error, string $message = 'Operation failed'): void
    {
        $this->updateOperation($operationId, [
            'status' => 'failed',
            'message' => $message,
            'completed_at' => now()->toISOString(),
            'error' => $error,
        ]);
    }

    public function updateProgress(string $operationId, int $progress, string $message): void
    {
        $this->updateOperation($operationId, [
            'progress' => min(100, max(0, $progress)),
            'message' => $message,
        ]);
    }

    public function getOperationsByExtension(string $extensionId): array
    {
        if ($this->shouldUseDatabase()) {
            return ExtensionOperation::query()
                ->where('extension_id', $extensionId)
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (ExtensionOperation $operation) => $this->toPayload($operation))
                ->all();
        }

        return $this->getOperationsByExtensionFromCache($extensionId);
    }

    /**
     * @param string[] $extensionIds
     * @return array<string, array<int, array<string,mixed>>>
     */
    public function getOperationsByExtensions(array $extensionIds): array
    {
        $ids = array_values(array_unique(array_filter($extensionIds, static fn ($id) => is_string($id) && trim($id) !== '')));

        if (empty($ids)) {
            return [];
        }

        if ($this->shouldUseDatabase()) {
            $grouped = array_fill_keys($ids, []);

            $operations = ExtensionOperation::query()
                ->whereIn('extension_id', $ids)
                ->orderByDesc('created_at')
                ->get();

            foreach ($operations as $operation) {
                $grouped[$operation->extension_id][] = $this->toPayload($operation);
            }

            return $grouped;
        }

        $grouped = [];
        foreach ($ids as $id) {
            $grouped[$id] = $this->getOperationsByExtensionFromCache($id);
        }

        return $grouped;
    }

    public function getPendingOperationId(string $extensionId, string $type): ?string
    {
        if ($this->shouldUseDatabase()) {
            $operation = ExtensionOperation::query()
                ->where('extension_id', $extensionId)
                ->where('type', $type)
                ->whereIn('status', ['queued', 'running'])
                ->orderByDesc('created_at')
                ->first();

            return $operation?->id;
        }

        $pending = collect($this->getOperationsByExtensionFromCache($extensionId))
            ->filter(static function (array $op) use ($type): bool {
                return ($op['type'] ?? null) === $type
                    && in_array(($op['status'] ?? null), ['queued', 'running'], true);
            })
            ->sortByDesc(static fn (array $op): string => (string) ($op['created_at'] ?? ''))
            ->first();

        return is_array($pending) ? ($pending['id'] ?? null) : null;
    }

    public function isOperationPending(string $extensionId, string $type): bool
    {
        if ($this->shouldUseDatabase()) {
            return ExtensionOperation::query()
                ->where('extension_id', $extensionId)
                ->where('type', $type)
                ->whereIn('status', ['queued', 'running'])
                ->exists();
        }

        $operations = $this->getOperationsByExtensionFromCache($extensionId);

        foreach ($operations as $op) {
            if (($op['type'] ?? null) === $type && in_array(($op['status'] ?? null), ['queued', 'running'], true)) {
                return true;
            }
        }

        return false;
    }

    private function shouldUseDatabase(): bool
    {
        if ((string) config('extensions.operations.store', 'database') !== 'database') {
            return false;
        }

        if ($this->databaseAvailable !== null) {
            return $this->databaseAvailable;
        }

        try {
            $this->databaseAvailable = Schema::hasTable('extension_operations');
        } catch (\Throwable) {
            $this->databaseAvailable = false;
        }

        return $this->databaseAvailable;
    }

    private function updateOperationInDatabase(string $operationId, array $data): void
    {
        $operation = ExtensionOperation::query()->find($operationId);
        $attributes = $this->normalizeDatabaseAttributes($data);

        if ($operation) {
            $operation->fill($attributes);
            $operation->save();

            return;
        }

        if (!isset($attributes['extension_id']) || !isset($attributes['type'])) {
            // Ignore partial updates for unknown operations.
            return;
        }

        $defaults = [
            'id' => $operationId,
            'status' => 'queued',
            'progress' => 0,
            'message' => 'Operation queued',
            'context' => [],
        ];

        ExtensionOperation::query()->create(array_merge($defaults, $attributes, ['id' => $operationId]));
    }

    private function updateOperationInCache(string $operationId, array $data): void
    {
        $key = $this->cacheOperationKey($operationId);
        $current = Cache::get($key, []);
        $updated = array_merge($current, $data);

        Cache::put($key, $updated, now()->addHours((int) config('extensions.operations.cache_ttl_hours', 2)));

        if (!empty($updated['extension_id'])) {
            $this->addToCacheRegistry((string) $updated['extension_id'], $operationId);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function normalizeDatabaseAttributes(array $data): array
    {
        $allowed = [
            'id',
            'type',
            'extension_id',
            'status',
            'progress',
            'message',
            'context',
            'result',
            'error',
            'created_at',
            'started_at',
            'completed_at',
            'updated_at',
        ];

        $attributes = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $attributes[$key] = $data[$key];
        }

        if (array_key_exists('progress', $attributes)) {
            $attributes['progress'] = (int) $attributes['progress'];
        }

        foreach (['created_at', 'started_at', 'completed_at', 'updated_at'] as $dateKey) {
            if (!array_key_exists($dateKey, $attributes)) {
                continue;
            }

            $attributes[$dateKey] = $this->parseTimestamp($attributes[$dateKey]);
        }

        return $attributes;
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value)) {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * @return array<string,mixed>
     */
    private function toPayload(ExtensionOperation $operation): array
    {
        return [
            'id' => $operation->id,
            'type' => $operation->type,
            'extension_id' => $operation->extension_id,
            'status' => $operation->status,
            'progress' => (int) $operation->progress,
            'message' => $operation->message,
            'context' => $operation->context ?? [],
            'created_at' => $operation->created_at?->toISOString(),
            'started_at' => $operation->started_at?->toISOString(),
            'completed_at' => $operation->completed_at?->toISOString(),
            'error' => $operation->error,
            'result' => $operation->result,
        ];
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function getOperationsByExtensionFromCache(string $extensionId): array
    {
        $registryKey = $this->cacheRegistryKey($extensionId);
        $operationIds = Cache::get($registryKey, []);

        $operations = [];
        foreach ($operationIds as $operationId) {
            if (!is_string($operationId)) {
                continue;
            }

            $operation = Cache::get($this->cacheOperationKey($operationId));
            if (is_array($operation)) {
                $operations[] = $operation;
            }
        }

        usort(
            $operations,
            static fn (array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''))
        );

        return $operations;
    }

    private function addToCacheRegistry(string $extensionId, string $operationId): void
    {
        $registryKey = $this->cacheRegistryKey($extensionId);
        $operationIds = Cache::get($registryKey, []);

        if (!in_array($operationId, $operationIds, true)) {
            $operationIds[] = $operationId;
            Cache::put($registryKey, $operationIds, now()->addHours((int) config('extensions.operations.cache_ttl_hours', 2)));
        }
    }

    private function cacheOperationKey(string $operationId): string
    {
        return "ext:operation:{$operationId}";
    }

    private function cacheRegistryKey(string $extensionId): string
    {
        return "ext:operations_registry:{$extensionId}";
    }

    private function pruneExpiredOperations(): void
    {
        if (!$this->shouldUseDatabase()) {
            return;
        }

        $retentionHours = (int) config('extensions.operations.retention_hours', 168);
        if ($retentionHours <= 0) {
            return;
        }

        $interval = max(1, (int) config('extensions.operations.prune_interval_seconds', 300));
        $nowTs = time();

        if (self::$lastPrunedAt !== null && ($nowTs - self::$lastPrunedAt) < $interval) {
            return;
        }

        self::$lastPrunedAt = $nowTs;

        ExtensionOperation::query()
            ->where('created_at', '<', now()->subHours($retentionHours))
            ->delete();
    }
}
