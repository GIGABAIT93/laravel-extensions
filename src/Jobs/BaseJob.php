<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Jobs;

use Gigabait93\Extensions\Services\ExtensionService;
use Gigabait93\Extensions\Services\TrackerService;
use Gigabait93\Extensions\Support\OpResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

abstract class BaseJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $extensionId,
        public readonly string $operationId,
    ) {
    }

    public function handle(ExtensionService $extensions, TrackerService $tracker): void
    {
        try {
            $tracker->markAsStarted($this->operationId);
            $tracker->updateProgress($this->operationId, 10, $this->getStartingMessage());

            $this->executeJob($extensions, $tracker);

        } catch (\Throwable $e) {
            $tracker->markAsFailed($this->operationId, $e->getMessage(), $this->getExceptionMessage());

            Log::error($this->getLogPrefix() . ' exception', [
                'extension_id' => $this->extensionId,
                'operation_id' => $this->operationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->fail($e);
        }
    }

    protected function executeJob(ExtensionService $extensions, TrackerService $tracker): void
    {
        $result = $this->executeOperation($extensions, $tracker);

        if ($result->isSuccess()) {
            $tracker->markAsCompleted($this->operationId, [
                $this->getResultKey() => $result->toArray(),
            ], $this->getSuccessMessage());

            Log::info($this->getLogPrefix() . ' completed successfully', [
                'extension_id' => $this->extensionId,
                'operation_id' => $this->operationId,
                'result' => $result->toArray(),
            ]);
        } else {
            $tracker->markAsFailed($this->operationId, $result->message, $this->getFailureMessage());

            Log::warning($this->getLogPrefix() . ' failed', [
                'extension_id' => $this->extensionId,
                'operation_id' => $this->operationId,
                'result' => $result->toArray(),
            ]);
        }
    }

    abstract protected function executeOperation(ExtensionService $extensions, TrackerService $tracker): OpResult;

    abstract protected function getStartingMessage(): string;

    abstract protected function getSuccessMessage(): string;

    abstract protected function getFailureMessage(): string;

    abstract protected function getExceptionMessage(): string;

    abstract protected function getResultKey(): string;

    abstract protected function getLogPrefix(): string;
}
