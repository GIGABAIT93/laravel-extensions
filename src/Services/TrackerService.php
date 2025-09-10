<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TrackerService
{
    private const int TTL_HOURS = 2;

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

        $this->addToRegistry($extensionId, $operationId);

        return $operationId;
    }

    public function updateOperation(string $operationId, array $data): void
    {
        $key = "ext:operation:{$operationId}";
        $current = Cache::get($key, []);
        $updated = array_merge($current, $data);

        Cache::put($key, $updated, now()->addHours(self::TTL_HOURS));
    }

    public function getOperation(string $operationId): ?array
    {
        return Cache::get("ext:operation:{$operationId}");
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
        // Since we can't easily scan all cache keys without Redis,
        // we'll store a registry of operations per extension
        $registryKey = "ext:operations_registry:{$extensionId}";
        $operationIds = Cache::get($registryKey, []);

        $operations = [];
        foreach ($operationIds as $operationId) {
            $operation = $this->getOperation($operationId);
            if ($operation) {
                $operations[] = $operation;
            }
        }

        return $operations;
    }

    private function addToRegistry(string $extensionId, string $operationId): void
    {
        $registryKey = "ext:operations_registry:{$extensionId}";
        $operationIds = Cache::get($registryKey, []);

        if (!in_array($operationId, $operationIds)) {
            $operationIds[] = $operationId;
            Cache::put($registryKey, $operationIds, now()->addHours(self::TTL_HOURS));
        }
    }

    public function isOperationPending(string $extensionId, string $type): bool
    {
        $operations = $this->getOperationsByExtension($extensionId);

        foreach ($operations as $op) {
            if ($op['type'] === $type && in_array($op['status'], ['queued', 'running'])) {
                return true;
            }
        }

        return false;
    }
}
