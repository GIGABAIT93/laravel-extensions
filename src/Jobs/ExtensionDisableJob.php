<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Jobs;

use Gigabait93\Extensions\Services\ExtensionService;
use Gigabait93\Extensions\Services\TrackerService;
use Gigabait93\Extensions\Support\OpResult;

class ExtensionDisableJob extends BaseJob
{
    protected function executeOperation(ExtensionService $extensions, TrackerService $tracker): OpResult
    {
        $tracker->updateProgress($this->operationId, 50, 'Disabling extension...');

        return $extensions->disable($this->extensionId);
    }

    protected function getStartingMessage(): string
    {
        return 'Checking extension status...';
    }

    protected function getSuccessMessage(): string
    {
        return 'Extension disabled successfully';
    }

    protected function getFailureMessage(): string
    {
        return 'Failed to disable extension';
    }

    protected function getExceptionMessage(): string
    {
        return 'Exception occurred during extension disable';
    }

    protected function getResultKey(): string
    {
        return 'disable_result';
    }

    protected function getLogPrefix(): string
    {
        return 'DisableJob';
    }
}
