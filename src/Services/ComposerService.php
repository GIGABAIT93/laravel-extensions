<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Services;

use Composer\InstalledVersions;
use Gigabait93\Extensions\Support\JsonFileReader;
use Gigabait93\Extensions\Support\ManifestValue;
use Gigabait93\Extensions\Support\PathResolver;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ComposerService
{
    /**
     * @param string[] $packages
     */
    public function installDependencies(array $packages = []): bool
    {
        $packages = $this->normalizePackages($packages);
        $message = empty($packages)
            ? 'Starting composer update with merge-plugin to install new dependencies'
            : 'Starting targeted composer update with merge-plugin to install missing extension dependencies';

        return $this->runComposerUpdate($packages, $message, 'dependencies installed');
    }

    /**
     * @param string[] $packages
     */
    public function updateDependencies(array $packages = []): bool
    {
        return $this->runComposerUpdate(
            $this->normalizePackages($packages),
            'Starting composer update with merge-plugin',
            null
        );
    }

    public function extensionHasComposerFile(ManifestValue $manifest): bool
    {
        return PathResolver::hasComposerFile($manifest->path);
    }

    public function getExtensionComposerData(ManifestValue $manifest): ?array
    {
        return JsonFileReader::read(PathResolver::getComposerPath($manifest->path));
    }

    public function isMergePluginInstalled(): bool
    {
        return class_exists(InstalledVersions::class)
            && InstalledVersions::isInstalled('wikimedia/composer-merge-plugin');
    }

    public function isMergePluginAllowed(): bool
    {
        $rootComposer = $this->readRootComposerData();
        if (!is_array($rootComposer)) {
            return false;
        }

        $allow = $rootComposer['config']['allow-plugins']['wikimedia/composer-merge-plugin'] ?? null;

        return $allow === true || $allow === 1 || $allow === 'true';
    }

    /** @return string[] */
    public function getMergePluginIncludes(): array
    {
        $rootComposer = $this->readRootComposerData();
        if (!is_array($rootComposer)) {
            return [];
        }

        $includes = $rootComposer['extra']['merge-plugin']['include'] ?? [];
        if (!is_array($includes)) {
            return [];
        }

        return array_values(array_filter($includes, static fn ($pattern) => is_string($pattern) && trim($pattern) !== ''));
    }

    public function hasMergePluginIncludes(): bool
    {
        return !empty($this->getMergePluginIncludes());
    }

    public function isExtensionComposerIncluded(ManifestValue $manifest): bool
    {
        $includes = $this->getMergePluginIncludes();
        if (empty($includes)) {
            return false;
        }

        $target = $this->normalizePathSeparators($manifest->path);
        $target = rtrim($target, '/') . '/composer.json';

        $base = $this->normalizePathSeparators(base_path());
        $relative = $target;
        if (str_starts_with(strtolower($target), strtolower($base . '/'))) {
            $relative = ltrim(substr($target, strlen($base)), '/');
        }

        foreach ($includes as $pattern) {
            $normalizedPattern = $this->normalizePathSeparators($pattern);

            if (fnmatch(strtolower($normalizedPattern), strtolower($relative))) {
                return true;
            }

            if (fnmatch(strtolower($normalizedPattern), strtolower($target))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string[] $packages
     */
    private function runComposerUpdate(array $packages, string $startMessage, ?string $successSuffix = null): bool
    {
        try {
            Log::info($startMessage);

            $command = $this->buildComposerUpdateCommand($packages);
            $timeout = (int) config('extensions.composer.timeout', 300);
            $waitSeconds = (int) config('extensions.composer.lock_wait_seconds', 15);
            $lockSeconds = (int) config('extensions.composer.lock_seconds', max($timeout + 30, 330));

            $run = function () use ($command, $timeout) {
                return Process::path(base_path())
                    ->timeout($timeout)
                    ->run($command);
            };

            $result = $this->runWithLock($run, $waitSeconds, $lockSeconds);

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
                'command' => $command,
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

    /**
     * @param string[] $packages
     */
    private function buildComposerUpdateCommand(array $packages): string
    {
        $composerCommand = trim((string) config('extensions.composer.command', 'composer'));
        if ($composerCommand === '') {
            $composerCommand = 'composer';
        }

        $command = $composerCommand . ' update';

        foreach ($packages as $package) {
            $command .= ' ' . escapeshellarg($package);
        }

        $command .= ' --no-interaction --optimize-autoloader --with-all-dependencies';

        if ((bool) config('extensions.composer.prefer_dist', true)) {
            $command .= ' --prefer-dist';
        }

        if ((bool) config('extensions.composer.no_dev', false)) {
            $command .= ' --no-dev';
        }

        return $command;
    }

    private function runWithLock(callable $run, int $waitSeconds, int $lockSeconds): mixed
    {
        if ($waitSeconds <= 0 || $lockSeconds <= 0) {
            return $run();
        }

        try {
            $lock = Cache::lock('extensions:composer:update', $lockSeconds);

            return $lock->block($waitSeconds, $run);
        } catch (LockTimeoutException) {
            Log::warning('Timed out waiting for composer update lock.');

            return Process::result('', 'Timed out waiting for composer lock.', 1);
        } catch (\BadMethodCallException) {
            // Cache driver does not support locks; continue without lock.
            return $run();
        }
    }

    /** @return string[] */
    private function normalizePackages(array $packages): array
    {
        $normalized = [];

        foreach ($packages as $package) {
            if (!is_string($package)) {
                continue;
            }

            $package = strtolower(trim($package));
            if ($package === '' || str_contains($package, ' ')) {
                continue;
            }

            $normalized[] = $package;
        }

        return array_values(array_unique($normalized));
    }

    private function readRootComposerData(): ?array
    {
        $path = (string) config('extensions.composer.root_json', base_path('composer.json'));
        if ($path === '') {
            return null;
        }

        return JsonFileReader::read($path);
    }

    private function normalizePathSeparators(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
