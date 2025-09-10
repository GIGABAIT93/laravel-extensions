<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Services;

use Gigabait93\Extensions\Support\JsonFileReader;
use Gigabait93\Extensions\Support\ManifestValue;
use Gigabait93\Extensions\Support\PathResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ComposerService
{
    public function installDependencies(): bool
    {
        return $this->runComposerUpdate('Starting composer update with merge-plugin to install new dependencies', 'dependencies installed');
    }

    public function updateDependencies(): bool
    {
        return $this->runComposerUpdate('Starting composer update with merge-plugin', null);
    }

    public function extensionHasComposerFile(ManifestValue $manifest): bool
    {
        return PathResolver::hasComposerFile($manifest->path);
    }

    public function getExtensionComposerData(ManifestValue $manifest): ?array
    {
        return JsonFileReader::read(PathResolver::getComposerPath($manifest->path));
    }

    private function runComposerUpdate(string $startMessage, ?string $successSuffix = null): bool
    {
        try {
            Log::info($startMessage);

            $result = Process::path(base_path())
                ->timeout(300)
                ->run('composer update --no-interaction --optimize-autoloader');

            if ($result->successful()) {
                $message = 'Composer update completed successfully';
                if ($successSuffix) {
                    $message .= ' - ' . $successSuffix;
                }
                Log::info($message);

                return true;
            }

            Log::error('Composer update failed', [
                'exit_code' => $result->exitCode(),
                'output' => $result->output(),
                'error' => $result->errorOutput(),
            ]);

            return false;

        } catch (\Throwable $e) {
            Log::error('Composer update exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }
}
