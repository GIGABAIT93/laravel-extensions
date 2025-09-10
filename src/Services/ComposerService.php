<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Services;

use Gigabait93\Extensions\Support\ManifestValue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ComposerService
{
    public function installDependencies(): bool
    {
        try {
            Log::info('Starting composer update with merge-plugin to install new dependencies');

            // When new dependencies are added via merge-plugin, we need to update
            // the lock file to include them. composer install won't work.
            $result = Process::path(base_path())
                ->timeout(300) // 5 minutes timeout
                ->run('composer update --no-interaction --optimize-autoloader');

            if ($result->successful()) {
                Log::info('Composer update completed successfully - dependencies installed');

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

    public function updateDependencies(): bool
    {
        try {
            Log::info('Starting composer update with merge-plugin');

            $result = Process::path(base_path())
                ->timeout(300)
                ->run('composer update --no-interaction --optimize-autoloader');

            if ($result->successful()) {
                Log::info('Composer update completed successfully');

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

    public function extensionHasComposerFile(ManifestValue $manifest): bool
    {
        $composerPath = rtrim($manifest->path, DIRECTORY_SEPARATOR) . '/composer.json';

        return file_exists($composerPath);
    }

    public function getExtensionComposerData(ManifestValue $manifest): ?array
    {
        $composerPath = rtrim($manifest->path, DIRECTORY_SEPARATOR) . '/composer.json';

        if (!file_exists($composerPath)) {
            return null;
        }

        $content = file_get_contents($composerPath);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);

        return is_array($data) ? $data : null;
    }
}
