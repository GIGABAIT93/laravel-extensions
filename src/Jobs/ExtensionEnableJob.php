<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Jobs;

use Gigabait93\Extensions\Services\ExtensionService;
use Gigabait93\Extensions\Services\TrackerService;
use Gigabait93\Extensions\Support\OpResult;

class ExtensionEnableJob extends BaseJob
{
    protected function executeOperation(ExtensionService $extensions, TrackerService $tracker): OpResult
    {
        $operation = $tracker->getOperation($this->operationId);
        $autoInstallDeps = $operation['context']['auto_install_deps'] ?? false;

        // Check if dependencies need to be installed first
        if ($autoInstallDeps) {
            $missing = $extensions->missingPackages($this->extensionId);
            if (!empty($missing)) {
                $tracker->updateProgress($this->operationId, 20, 'Installing missing dependencies...');

                $depsResult = $extensions->installDependencies($this->extensionId);
                if ($depsResult->isFailure()) {
                    return $depsResult;
                }
            }
        }

        $tracker->updateProgress($this->operationId, 70, 'Enabling extension...');

        return $extensions->enable($this->extensionId);
    }

    protected function getStartingMessage(): string
    {
        return 'Checking extension status...';
    }

    protected function getSuccessMessage(): string
    {
        return 'Extension enabled successfully';
    }

    protected function getFailureMessage(): string
    {
        return 'Failed to enable extension';
    }

    protected function getExceptionMessage(): string
    {
        return 'Exception occurred during extension enable';
    }

    protected function getResultKey(): string
    {
        return 'enable_result';
    }

    protected function getLogPrefix(): string
    {
        return 'EnableJob';
    }
}
