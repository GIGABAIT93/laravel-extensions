<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Services;

use Gigabait93\Extensions\Contracts\ActivatorContract;
use Gigabait93\Extensions\Entities\Extension;
use Gigabait93\Extensions\Events\ExtensionDeletedEvent;
use Gigabait93\Extensions\Events\ExtensionDepsInstalledEvent;
use Gigabait93\Extensions\Events\ExtensionDisabledEvent;
use Gigabait93\Extensions\Events\ExtensionEnabledEvent;
use Gigabait93\Extensions\Jobs\ExtensionDisableJob;
use Gigabait93\Extensions\Jobs\ExtensionEnableJob;
use Gigabait93\Extensions\Jobs\ExtensionInstallDepsJob;
use Gigabait93\Extensions\Support\ManifestValue;
use Gigabait93\Extensions\Support\OpResult;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Core service managing discovery and lifecycle of extensions.
 */
class ExtensionService
{
    private ?array $cachedStatuses = null;

    private ?Collection $cachedManifests = null;

    public function __construct(
        private ActivatorContract $activator,
        private RegistryService $registry,
        private BootstrapService $bootstrapper,
        private DependencyService $deps,
        private MigratorService $migrator,
        private Container $app
    ) {
    }

    /** Get cached statuses or retrieve from activator */
    private function getStatuses(): array
    {
        return $this->cachedStatuses ??= $this->activator->statuses();
    }

    /** Get cached manifests or retrieve from registry */
    private function getAllManifests(): Collection
    {
        return $this->cachedManifests ??= $this->registry->all();
    }

    /** Clear internal caches */
    private function clearCache(): void
    {
        $this->cachedStatuses = null;
        $this->cachedManifests = null;
        $this->registry->clearCache();
    }

    /** Ensure manifests are up-to-date and purge activator entries for missing extensions */
    public function discover(): void
    {
        $this->clearCache();
        $this->registry->discover();

        $knownIds = array_flip(
            $this->getAllManifests()->map(static fn ($m) => $m->id)->all()
        );

        foreach (array_keys($this->getStatuses()) as $id) {
            if (!isset($knownIds[$id])) {
                $this->activator->remove($id);
                $this->clearCache(); // Clear cache after removing status
            }
        }
    }

    /** @return Collection<int, Extension> */
    public function all(): Collection
    {
        $statuses = $this->getStatuses();

        return $this->getAllManifests()->map(
            fn (ManifestValue $m) => $this->makeExtension($m, $statuses)
        );
    }

    public function get(string $id): ?Extension
    {
        $manifest = $this->registry->find($id);
        if (!$manifest) {
            return null;
        }

        $statuses = $this->getStatuses();

        return $this->makeExtension($manifest, $statuses);
    }

    public function findByName(string $name): ?Extension
    {
        $needle = strtolower($name);
        $statuses = $this->getStatuses();

        foreach ($this->getAllManifests() as $m) {
            if (strtolower($m->name) === $needle) {
                return $this->makeExtension($m, $statuses);
            }
        }

        return null;
    }

    public function find(string $idOrName): ?Extension
    {
        return $this->get($idOrName) ?? $this->findByName($idOrName);
    }

    public function findByNameAndType(string $name, ?string $type = null): ?Extension
    {
        $needleName = strtolower($name);
        $needleType = $type !== null ? strtolower($type) : null;
        $statuses = $this->getStatuses();

        foreach ($this->getAllManifests() as $m) {
            if (strtolower($m->name) !== $needleName) {
                continue;
            }
            if ($needleType !== null && strtolower($m->type) !== $needleType) {
                continue;
            }

            return $this->makeExtension($m, $statuses);
        }

        return null;
    }

    public function one(string $idOrName, ?string $type = null): ?Extension
    {
        return $this->get($idOrName) ?? $this->findByNameAndType($idOrName, $type);
    }

    /** @return array<string,string> canonicalType => path */
    public function typedPaths(): array
    {
        return $this->registry->typedPaths();
    }

    public function pathForType(string $type): ?string
    {
        return $this->registry->pathForType($type);
    }

    /** @return string[] */
    public function types(): array
    {
        return $this->registry->types();
    }

    public function enable(string $id): OpResult
    {
        try {
            $manifest = $this->registry->find($id);
            if (!$manifest) {
                return OpResult::failure(__('extensions::lang.extension_not_found'), 'not_found');
            }

            if ($this->activator->isEnabled($id)) {
                return OpResult::success(__('extensions::lang.extension_already_enabled'));
            }

            // Include bundled vendor first (if any) to reduce false-positive "missing packages"
            $this->deps->includeExtensionVendor($manifest);

            $missing = $this->deps->missingPackages($manifest);
            if (!empty($missing)) {
                return OpResult::failure(
                    __('extensions::lang.missing_dependencies', ['packages' => implode(', ', $missing)]),
                    'missing_packages',
                    ['packages' => array_values($missing)]
                );
            }

            $missingExt = $this->missingExtensions($manifest);
            if (!empty($missingExt)) {
                return OpResult::failure(
                    __('extensions::lang.missing_extensions', ['extensions' => implode(', ', $missingExt)]),
                    'missing_extensions',
                    ['extensions' => array_values($missingExt)]
                );
            }

            // Switch types logic: only one active per type
            $this->enforceSwitchTypesOnEnable($manifest);

            // Persist + bootstrap + migrate
            $this->activator->enable($id, $manifest->type);
            $this->clearCache(); // Clear cache after state change
            $this->bootstrapper->registerProvider($manifest);
            $this->migrator->migrate($manifest);

            $extension = $this->get($id);
            ExtensionEnabledEvent::dispatch($extension);
            Log::info('Extension enabled', ['id' => $id]);

            return OpResult::success(__('extensions::lang.extension_enabled'));
        } catch (\Throwable $e) {
            Log::error('Extension enable failed', ['id' => $id, 'error' => $e->getMessage()]);

            return OpResult::failure(__('extensions::lang.enable_failed', ['error' => $e->getMessage()]), 'exception');
        }
    }

    public function disable(string $id): OpResult
    {
        try {
            $manifest = $this->registry->find($id);
            if (!$manifest) {
                return OpResult::failure(__('extensions::lang.extension_not_found'), 'not_found');
            }

            if (!$this->activator->isEnabled($id)) {
                return OpResult::success(__('extensions::lang.extension_already_disabled'));
            }

            if ($this->isProtected($manifest) && !$this->isSwitchType($manifest)) {
                return OpResult::failure(__('extensions::lang.extension_protected_disable'), 'protected');
            }

            $requiredBy = $this->requiredByEnabled($id);
            if (!empty($requiredBy)) {
                return OpResult::failure(
                    __('extensions::lang.extension_required_by', ['extensions' => implode(', ', $requiredBy)]),
                    'required_by',
                    ['required_by' => $requiredBy]
                );
            }

            $extension = $this->get($id);
            $this->activator->disable($id, $manifest->type);
            $this->clearCache(); // Clear cache after state change

            ExtensionDisabledEvent::dispatch($extension);
            Log::info('Extension disabled', ['id' => $id]);

            return OpResult::success(__('extensions::lang.extension_disabled'));
        } catch (\Throwable $e) {
            Log::error('Extension disable failed', ['id' => $id, 'error' => $e->getMessage()]);

            return OpResult::failure(__('extensions::lang.disable_failed', ['error' => $e->getMessage()]), 'exception');
        }
    }

    /** @return Collection<int, Extension> */
    public function enabled(): Collection
    {
        return $this->all()->filter->isEnabled();
    }

    /** @return Collection<int, Extension> */
    public function disabled(): Collection
    {
        return $this->all()->reject->isEnabled();
    }

    /** @return Collection<int, Extension> */
    public function allByType(?string $type = null): Collection
    {
        $all = $this->all();
        if ($type === null || $type === '') {
            return $all;
        }
        $lt = strtolower($type);

        return $all->filter(static fn (Extension $e) => strtolower($e->type()) === $lt);
    }

    /** @return Collection<int, Extension> */
    public function enabledByType(?string $type = null): Collection
    {
        $coll = $this->enabled();
        if ($type === null || $type === '') {
            return $coll;
        }
        $lt = strtolower($type);

        return $coll->filter(static fn (Extension $e) => strtolower($e->type()) === $lt);
    }

    /** @return Collection<int, Extension> */
    public function disabledByType(?string $type = null): Collection
    {
        $coll = $this->disabled();
        if ($type === null || $type === '') {
            return $coll;
        }
        $lt = strtolower($type);

        return $coll->filter(static fn (Extension $e) => strtolower($e->type()) === $lt);
    }

    /** @return string[] list of missing composer packages for the extension */
    public function missingPackages(string $id): array
    {
        $manifest = $this->registry->find($id);
        if (!$manifest) {
            return [];
        }

        // Try include vendor to account for bundled deps
        $this->deps->includeExtensionVendor($manifest);

        return $this->deps->missingPackages($manifest);
    }

    public function installDependencies(string $id): OpResult
    {
        try {
            $manifest = $this->registry->find($id);
            if (!$manifest) {
                return OpResult::failure(__('extensions::lang.extension_not_found'), 'not_found');
            }

            /** @var ComposerService $mergeService */
            $mergeService = $this->app->make(ComposerService::class);

            // Check if extension has composer.json
            if (!$mergeService->extensionHasComposerFile($manifest)) {
                return OpResult::success(__('extensions::lang.extension_no_composer'));
            }

            $composerData = $mergeService->getExtensionComposerData($manifest);
            $dependencies = $composerData['require'] ?? [];

            if (empty($dependencies)) {
                return OpResult::success(__('extensions::lang.extension_no_deps'));
            }

            // Check if dependencies are missing
            $missing = $this->deps->missingPackages($manifest);
            if (empty($missing)) {
                return OpResult::success(__('extensions::lang.deps_already_installed'));
            }

            // Use composer install with merge-plugin
            $success = $mergeService->installDependencies();

            if ($success) {
                $extension = $this->get($id);
                ExtensionDepsInstalledEvent::dispatch($extension, ['packages' => $missing, 'method' => 'composer-merge-plugin']);
                Log::info('Dependencies installed via composer-merge-plugin', ['id' => $id, 'packages' => $missing]);

                return OpResult::success(__('extensions::lang.deps_installed'), ['packages' => $missing]);
            }

            return OpResult::failure(__('extensions::lang.deps_install_failed_merge'), 'install_failed', ['packages' => $missing]);
        } catch (\Throwable $e) {
            Log::error('Dependencies install failed', ['id' => $id, 'error' => $e->getMessage()]);

            return OpResult::failure(__('extensions::lang.deps_install_failed', ['error' => $e->getMessage()]), 'exception');
        }
    }

    /** Install dependencies then enable extension */
    public function installAndEnable(string $id): OpResult
    {
        $install = $this->installDependencies($id);
        if ($install->isFailure()) {
            return $install;
        }

        return $this->enable($id);
    }

    public function migrate(string $id): bool
    {
        $manifest = $this->registry->find($id);
        if (!$manifest) {
            return false;
        }

        return $this->migrator->migrate($manifest);
    }

    public function reloadActive(): void
    {
        $this->bootstrapper->warmup();
    }

    // Async operations with tracking support
    public function enableAsync(string $id, bool $autoInstallDeps = false): string
    {
        $tracker = $this->app->make(TrackerService::class);
        $operationId = $tracker->createOperation('enable', $id, ['auto_install_deps' => $autoInstallDeps]);

        ExtensionEnableJob::dispatch($id, $operationId);

        return $operationId;
    }

    public function disableAsync(string $id): string
    {
        $tracker = $this->app->make(TrackerService::class);
        $operationId = $tracker->createOperation('disable', $id);

        ExtensionDisableJob::dispatch($id, $operationId);

        return $operationId;
    }

    public function installDepsAsync(string $id, bool $autoEnable = false): string
    {
        $tracker = $this->app->make(TrackerService::class);
        $operationId = $tracker->createOperation('install_deps', $id, ['auto_enable' => $autoEnable]);

        ExtensionInstallDepsJob::dispatch($id, $operationId);

        return $operationId;
    }

    public function getOperationStatus(string $operationId): ?array
    {
        $tracker = $this->app->make(TrackerService::class);

        return $tracker->getOperation($operationId);
    }

    public function getExtensionOperations(string $extensionId): array
    {
        $tracker = $this->app->make(TrackerService::class);

        return $tracker->getOperationsByExtension($extensionId);
    }

    public function isOperationPending(string $extensionId, string $type): bool
    {
        $tracker = $this->app->make(TrackerService::class);

        return $tracker->isOperationPending($extensionId, $type);
    }

    // Composite operation: install deps then enable
    public function installAndEnableAsync(string $id): string
    {
        return $this->installDepsAsync($id, true);
    }

    // Legacy methods for backward compatibility
    public function enableQueued(string $id, ?string $onSuccess = null, ?string $onFailure = null): string
    {
        return $this->enableAsync($id);
    }

    public function disableQueued(string $id, ?string $onSuccess = null, ?string $onFailure = null): string
    {
        return $this->disableAsync($id);
    }

    public function installDepsQueued(string $id, ?string $onSuccess = null, ?string $onFailure = null): string
    {
        return $this->installDepsAsync($id);
    }

    // Visualization-ready methods for developers
    public function getExtensionWithOperations(string $id): ?array
    {
        $extension = $this->get($id);
        if (!$extension) {
            return null;
        }

        $operations = $this->getExtensionOperations($id);
        $missing = $this->missingPackages($id);

        return [
            'extension' => [
                'id' => $extension->id(),
                'name' => $extension->name(),
                'type' => $extension->type(),
                'version' => $extension->version(),
                'enabled' => $extension->isEnabled(),
                'broken' => $extension->isBroken(),
                'issue' => $extension->issue(),
                'path' => $extension->path(),
            ],
            'status' => [
                'can_enable' => empty($missing) && !$extension->isEnabled(),
                'can_disable' => $extension->isEnabled() && !$this->isExtensionProtected($extension->id()),
                'missing_packages' => $missing,
                'has_pending_operations' => !empty(array_filter($operations, fn ($op) => in_array($op['status'], ['queued', 'running']))),
            ],
            'operations' => $operations,
        ];
    }

    public function getAllWithOperations(): array
    {
        $extensions = $this->all();
        $result = [];

        foreach ($extensions as $extension) {
            $result[] = $this->getExtensionWithOperations($extension->id());
        }

        return $result;
    }

    public function getOperationsSummary(): array
    {
        $allExtensions = $this->all();
        $summary = [
            'total_extensions' => $allExtensions->count(),
            'enabled_extensions' => $allExtensions->filter->isEnabled()->count(),
            'broken_extensions' => $allExtensions->filter->isBroken()->count(),
            'operations' => [
                'queued' => 0,
                'running' => 0,
                'completed' => 0,
                'failed' => 0,
            ],
            'recent_operations' => [],
        ];

        foreach ($allExtensions as $extension) {
            $operations = $this->getExtensionOperations($extension->id());
            foreach ($operations as $op) {
                $summary['operations'][$op['status']] = ($summary['operations'][$op['status']] ?? 0) + 1;
                $summary['recent_operations'][] = $op;
            }
        }

        // Sort recent operations by creation time (most recent first)
        usort($summary['recent_operations'], fn ($a, $b) => $b['created_at'] <=> $a['created_at']);
        $summary['recent_operations'] = array_slice($summary['recent_operations'], 0, 10);

        return $summary;
    }

    private function isExtensionProtected(string $extensionId): bool
    {
        $manifest = $this->registry->find($extensionId);
        if (!$manifest) {
            return false;
        }

        return $this->isProtected($manifest);
    }

    private function isProtected(ManifestValue $manifest): bool
    {
        // Normalize protected list:
        // - ['core', 'billing']
        // - ['core' => true, 'billing' => 1, 'other' => false]
        // - mixed variants with string values
        $raw = (array) config('extensions.protected', []);
        $list = [];

        foreach ($raw as $k => $v) {
            if (is_string($v)) {
                $list[] = $v;
            } elseif (is_string($k) && ($v === true || $v === 1)) {
                $list[] = $k;
            }
        }

        $set = array_flip(array_map('strtolower', $list));

        $idLower = strtolower($manifest->id);
        $nameLower = strtolower($manifest->name);

        return isset($set[$idLower]) || isset($set[$nameLower]);
    }

    private function isSwitchType(ManifestValue $manifest): bool
    {
        $switch = array_map('strtolower', (array) config('extensions.switch_types', []));

        return in_array(strtolower($manifest->type), $switch, true);
    }

    /** @return string[] ids of enabled extensions that require $id */
    private function requiredByEnabled(string $id): array
    {
        $result = [];
        $statuses = $this->getStatuses();

        foreach ($this->getAllManifests() as $m) {
            if (empty($statuses[$m->id]['enabled'])) {
                continue;
            }

            $requires = (array) ($m->requires_extensions ?? []);
            if (in_array($id, $requires, true)) {
                $result[] = $m->id;
            }
        }

        return $result;
    }

    /** @return string[] ids of required extensions that are not enabled */
    private function missingExtensions(ManifestValue $manifest): array
    {
        $result = [];
        foreach ((array) $manifest->requires_extensions as $req) {
            $m = $this->registry->find($req);
            if (!$m || !$this->activator->isEnabled($req)) {
                $result[] = $req;
            }
        }

        return $result;
    }

    private function enforceSwitchTypesOnEnable(ManifestValue $manifest): void
    {
        $switchable = array_map('strtolower', (array) config('extensions.switch_types', []));
        $type = strtolower($manifest->type);

        if (!in_array($type, $switchable, true)) {
            return;
        }

        foreach ($this->getAllManifests() as $m) {
            if ($m->id === $manifest->id) {
                continue;
            }
            if (strtolower($m->type) !== $type) {
                continue;
            }
            if ($this->isProtected($m)) {
                continue;
            }
            if ($this->activator->isEnabled($m->id)) {
                $this->activator->disable($m->id, $m->type);
                $this->clearCache(); // Clear cache after state change
            }
        }
    }

    public function delete(string $id): OpResult
    {
        try {
            $manifest = $this->registry->find($id);
            if (!$manifest) {
                return OpResult::failure(__('extensions::lang.extension_not_found'), 'not_found');
            }

            if ($this->isProtected($manifest)) {
                return OpResult::failure(__('extensions::lang.extension_protected_delete'), 'protected');
            }

            $extension = $this->get($id);

            // Disable first if enabled
            if ($this->activator->isEnabled($id)) {
                $disableResult = $this->disable($id);
                if ($disableResult->isFailure()) {
                    return $disableResult;
                }
            }

            $this->activator->remove($id);
            $this->clearCache(); // Clear cache after removing status

            $path = rtrim($manifest->path, DIRECTORY_SEPARATOR);
            $ok = $this->deleteDirectory($path);

            if (!$ok) {
                return OpResult::failure(__('extensions::lang.failed_delete_directory'), 'fs_error');
            }

            ExtensionDeletedEvent::dispatch($extension, ['path' => $path]);
            Log::info('Extension deleted', ['id' => $id, 'path' => $path]);

            return OpResult::success(__('extensions::lang.extension_deleted'));
        } catch (\Throwable $e) {
            Log::error('Extension delete failed', ['id' => $id, 'error' => $e->getMessage()]);

            return OpResult::failure(__('extensions::lang.delete_failed', ['error' => $e->getMessage()]), 'exception');
        }
    }

    private function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return true;
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        try {
            foreach ($it as $file) {
                if ($file->isDir()) {
                    if (!@rmdir($file->getPathname())) {
                        return false;
                    }
                } else {
                    if (!@unlink($file->getPathname())) {
                        return false;
                    }
                }
            }

            return @rmdir($dir);
        } catch (\Exception|\Error $e) {
            // Log specific error for debugging
            Log::warning('Failed to delete extension directory', [
                'directory' => $dir,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Build a consistent ExtensionEntity instance from a manifest + current statuses.
     * Centralizes "enabled/broken/issue" logic.
     */
    private function makeExtension(ManifestValue $m, array $statuses): Extension
    {
        $enabled = !empty($statuses[$m->id]['enabled']);
        $broken = !class_exists($m->provider);
        $issue = $broken ? __('extensions::lang.provider_not_found') : null;

        return Extension::fromManifest($m, $enabled, $broken, $issue);
    }
}
