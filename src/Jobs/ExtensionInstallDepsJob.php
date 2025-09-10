<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Jobs;

use Gigabait93\Extensions\Services\ExtensionService;
use Gigabait93\Extensions\Services\TrackerService;
use Gigabait93\Extensions\Support\OpResult;
use Illuminate\Support\Facades\Log;

class ExtensionInstallDepsJob extends BaseJob
{
    protected function executeJob(ExtensionService $extensions, TrackerService $tracker): void
    {
        $operation = $tracker->getOperation($this->operationId);
        $autoEnable = $operation['context']['auto_enable'] ?? false;

        $result = $extensions->installDependencies($this->extensionId);

        if ($result->isSuccess()) {
            $tracker->updateProgress($this->operationId, 80, 'Dependencies installed successfully');

            // Auto-enable extension if requested
            if ($autoEnable) {
                $tracker->updateProgress($this->operationId, 90, 'Enabling extension...');

                $enableResult = $extensions->enable($this->extensionId);
                if ($enableResult->isSuccess()) {
                    $tracker->markAsCompleted($this->operationId, [
                        'dependencies_result' => $result->toArray(),
                        'enable_result' => $enableResult->toArray(),
                    ], 'Dependencies installed and extension enabled successfully');
                } else {
                    $tracker->markAsCompleted($this->operationId, [
                        'dependencies_result' => $result->toArray(),
                        'enable_result' => $enableResult->toArray(),
                        'warning' => 'Dependencies installed but extension enable failed',
                    ], 'Dependencies installed but failed to enable extension');
                }
            } else {
                $tracker->markAsCompleted($this->operationId, [
                    'dependencies_result' => $result->toArray(),
                ], 'Dependencies installed successfully');
            }

            Log::info('InstallDepsJob completed successfully', [
                'extension_id' => $this->extensionId,
                'operation_id' => $this->operationId,
                'auto_enable' => $autoEnable,
                'result' => $result->toArray(),
            ]);
        } else {
            $tracker->markAsFailed($this->operationId, $result->message, 'Failed to install dependencies');

            Log::warning('InstallDepsJob failed', [
                'extension_id' => $this->extensionId,
                'operation_id' => $this->operationId,
                'result' => $result->toArray(),
            ]);
        }
    }

    protected function executeOperation(ExtensionService $extensions, TrackerService $tracker): OpResult
    {
        // This method is not used in this job as we override executeJob
        return $extensions->installDependencies($this->extensionId);
    }

    protected function getStartingMessage(): string
    {
        return 'Checking dependencies...';
    }

    protected function getSuccessMessage(): string
    {
        return 'Dependencies installed successfully';
    }

    protected function getFailureMessage(): string
    {
        return 'Failed to install dependencies';
    }

    protected function getExceptionMessage(): string
    {
        return 'Exception occurred during dependency installation';
    }

    protected function getResultKey(): string
    {
        return 'dependencies_result';
    }

    protected function getLogPrefix(): string
    {
        return 'InstallDepsJob';
    }
}
