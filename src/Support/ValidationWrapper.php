<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Support;

/**
 * Utility trait for common validation patterns that return OpResult.
 */
trait ValidationWrapper
{
    /**
     * Wrap a validation callable with standard existence check.
     *
     * @param string $id Extension ID
     * @param callable $validationCallback Function that performs specific validation
     * @return OpResult
     */
    protected function wrapValidation(string $id, callable $validationCallback): OpResult
    {
        $existsResult = $this->validateExtensionExists($id);
        if ($existsResult->isFailure()) {
            return $existsResult;
        }

        return $validationCallback($id);
    }

    /**
     * Execute an operation with consistent error handling and logging.
     *
     * @param callable $operation The operation to execute
     * @param string $operationName Name for logging purposes
     * @return OpResult
     */
    protected function executeWithLogging(callable $operation, string $operationName): OpResult
    {
        try {
            return $operation();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("$operationName failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return OpResult::failure("$operationName failed: " . $e->getMessage());
        }
    }
}
