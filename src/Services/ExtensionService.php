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

    private array $cachedExtensions = [];

    private array $cachedProtected = [];

    private array $cachedSwitchTypes = [];

    private ?Collection $cachedStats = null;

    private ?int $cachedTotalSize = null;

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
        $this->cachedExtensions = [];
        $this->cachedProtected = [];
        $this->cachedSwitchTypes = [];
        $this->cachedStats = null;
        $this->cachedTotalSize = null;
        $this->registry->clearCache();
    }

    /** Ensure manifests are up-to-date and purge activator entries for missing extensions */
    public function discover(): OpResult
    {
        try {
            $this->clearCache();
            $this->registry->discover();

            $knownIds = array_flip(
                $this->getAllManifests()->map(static fn ($m) => $m->id)->all()
            );

            $removedExtensions = [];
            foreach (array_keys($this->getStatuses()) as $id) {
                if (!isset($knownIds[$id])) {
                    $this->activator->remove($id);
                    $removedExtensions[] = $id;
                    $this->clearCache(); // Clear cache after removing status
                }
            }

            $discoveredCount = $this->getAllManifests()->count();
            $message = __('extensions::lang.discovered_extensions', ['count' => $discoveredCount]);

            if (!empty($removedExtensions)) {
                $message .= ' ' . __('extensions::lang.removed_orphaned_extensions', ['count' => count($removedExtensions)]);
            }

            Log::info('Extensions discovered', [
                'discovered' => $discoveredCount,
                'removed_orphaned' => $removedExtensions,
            ]);

            return OpResult::success($message, [
                'discovered_count' => $discoveredCount,
                'removed_orphaned' => $removedExtensions,
            ]);
        } catch (\Throwable $e) {
            Log::error('Extension discovery failed', ['error' => $e->getMessage()]);

            return OpResult::failure(__('extensions::lang.discovery_failed', ['error' => $e->getMessage()]), 'exception');
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
        // Use cache if available
        if (isset($this->cachedExtensions[$id])) {
            return $this->cachedExtensions[$id];
        }

        $manifest = $this->registry->find($id);
        if (!$manifest) {
            return null;
        }

        $statuses = $this->getStatuses();
        $extension = $this->makeExtension($manifest, $statuses);

        // Cache the extension
        $this->cachedExtensions[$id] = $extension;

        return $extension;
    }

    public function findByName(string $name): ?Extension
    {
        return $this->findExtensionByCriteria(['name' => $name]);
    }

    public function find(string $idOrName): ?Extension
    {
        return $this->get($idOrName) ?? $this->findByName($idOrName);
    }

    public function findByNameAndType(string $name, ?string $type = null): ?Extension
    {
        $criteria = ['name' => $name];
        if ($type !== null) {
            $criteria['type'] = $type;
        }

        return $this->findExtensionByCriteria($criteria);
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
        // Check if already enabled
        if ($this->activator->isEnabled($id)) {
            return OpResult::success(__('extensions::lang.extension_already_enabled'));
        }

        // Use centralized installation logic with enable=true, migrations=true
        return $this->performInstallation($id, true, true);
    }

    public function disable(string $id): OpResult
    {
        try {
            $validation = $this->validateCanDisable($id);
            if ($validation->isFailure()) {
                // Handle special case for already disabled
                if ($validation->errorCode === 'already_disabled') {
                    return OpResult::success(__('extensions::lang.extension_already_disabled'));
                }

                return $validation;
            }

            $manifestResult = $this->getManifestOrFail($id);
            if ($manifestResult instanceof OpResult) {
                return $manifestResult;
            }
            $manifest = $manifestResult;

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
        $manifestResult = $this->getManifestOrFail($id);
        if ($manifestResult instanceof OpResult) {
            return [];
        }
        $manifest = $manifestResult;

        // Try include vendor to account for bundled deps
        $this->deps->includeExtensionVendor($manifest);

        return $this->deps->missingPackages($manifest);
    }

    public function installDependencies(string $id): OpResult
    {
        // Use centralized installation logic with enable=false, migrations=false
        return $this->performInstallation($id, false, false);
    }

    /** Install dependencies and run migrations without enabling extension */
    public function install(string $id): OpResult
    {
        // Use centralized installation logic with enable=false, migrations=true
        return $this->performInstallation($id, true, false);
    }

    /** Install dependencies then enable extension */
    public function installAndEnable(string $id): OpResult
    {
        // Use centralized installation logic with enable=true, migrations=true
        return $this->performInstallation($id, true, true);
    }

    public function migrate(string $id): bool
    {
        $manifestResult = $this->getManifestOrFail($id);
        if ($manifestResult instanceof OpResult) {
            return false;
        }
        $manifest = $manifestResult;

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

    /** Get extensions statistics */
    public function getStats(): array
    {
        $extensions = $this->all();

        return [
            'total' => $extensions->count(),
            'enabled' => $extensions->filter->isEnabled()->count(),
            'disabled' => $extensions->reject->isEnabled()->count(),
            'broken' => $extensions->filter->isBroken()->count(),
            'protected' => $extensions->filter(fn ($e) => $e->isProtected())->count(),
            'with_dependencies' => $extensions->filter->hasDependencies()->count(),
            'by_type' => $extensions->groupBy('type')->map->count()->toArray(),
        ];
    }

    /** Bulk operations */
    public function enableMultiple(array $ids): array
    {
        $results = [];
        foreach ($ids as $id) {
            $results[$id] = $this->enable($id);
        }

        return $results;
    }

    public function disableMultiple(array $ids): array
    {
        $results = [];
        foreach ($ids as $id) {
            $results[$id] = $this->disable($id);
        }

        return $results;
    }

    /** Get extensions with issues */
    public function getBroken(): Collection
    {
        return $this->all()->filter->isBroken();
    }

    public function getWithMissingDependencies(): Collection
    {
        return $this->all()->filter(function ($extension) {
            return !empty($extension->missingPackages()) || !empty($extension->missingExtensions());
        });
    }

    /** Search and filter methods */
    public function search(string $query): Collection
    {
        $query = strtolower($query);

        return $this->all()->filter(function ($extension) use ($query) {
            return str_contains(strtolower($extension->name), $query) ||
                   str_contains(strtolower($extension->id), $query) ||
                   str_contains(strtolower($extension->description), $query) ||
                   str_contains(strtolower($extension->author), $query);
        });
    }

    public function findByAuthor(string $author): Collection
    {
        return $this->all()->filter(function ($extension) use ($author) {
            return strtolower($extension->author) === strtolower($author);
        });
    }

    /** Get extensions that can be safely enabled */
    public function getReadyToEnable(): Collection
    {
        return $this->all()->filter->canEnable();
    }

    /** Check if extension can be updated (has newer version available) */
    public function checkUpdates(): array
    {
        // This would require external version checking - placeholder for now
        return [];
    }

    /** Get total size of all extensions */
    public function getTotalSize(): int
    {
        if ($this->cachedTotalSize !== null) {
            return $this->cachedTotalSize;
        }

        $this->cachedTotalSize = $this->all()->sum(function ($extension) {
            return $extension->getSize();
        });

        return $this->cachedTotalSize;
    }

    /** Get extensions sorted by size */
    public function getBySize(bool $desc = true): Collection
    {
        $sorted = $this->all()->sortBy(function ($extension) {
            return $extension->getSize();
        });

        return $desc ? $sorted->reverse()->values() : $sorted->values();
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

    public function isProtected(string $extensionId): bool
    {
        // Use cache if available
        if (array_key_exists($extensionId, $this->cachedProtected)) {
            return $this->cachedProtected[$extensionId];
        }

        $manifestResult = $this->getManifestOrFail($extensionId);
        if ($manifestResult instanceof OpResult) {
            $this->cachedProtected[$extensionId] = false;

            return false;
        }
        $manifest = $manifestResult;

        $result = $this->isProtectedManifest($manifest);
        $this->cachedProtected[$extensionId] = $result;

        return $result;
    }

    public function isSwitchType(string $extensionId): bool
    {
        // Use cache if available
        if (array_key_exists($extensionId, $this->cachedSwitchTypes)) {
            return $this->cachedSwitchTypes[$extensionId];
        }

        $manifestResult = $this->getManifestOrFail($extensionId);
        if ($manifestResult instanceof OpResult) {
            $this->cachedSwitchTypes[$extensionId] = false;

            return false;
        }
        $manifest = $manifestResult;

        $result = $this->isSwitchTypeManifest($manifest);
        $this->cachedSwitchTypes[$extensionId] = $result;

        return $result;
    }

    /** @return string[] ids of required extensions that are not enabled */
    public function missingExtensions(string $extensionId): array
    {
        $manifestResult = $this->getManifestOrFail($extensionId);
        if ($manifestResult instanceof OpResult) {
            return [];
        }
        $manifest = $manifestResult;

        return $this->getMissingExtensions($manifest);
    }

    /** @return string[] ids of enabled extensions that require this extension */
    public function requiredByEnabled(string $extensionId): array
    {
        return $this->getRequiredByEnabled($extensionId);
    }

    public function hasComposerFile(string $extensionId): bool
    {
        $manifestResult = $this->getManifestOrFail($extensionId);
        if ($manifestResult instanceof OpResult) {
            return false;
        }
        $manifest = $manifestResult;

        /** @var ComposerService $composerService */
        $composerService = $this->app->make(ComposerService::class);

        return $composerService->extensionHasComposerFile($manifest);
    }

    private function isExtensionProtected(string $extensionId): bool
    {
        return $this->isProtected($extensionId);
    }

    /**
     * Get manifest and validate extension exists.
     */
    private function getManifestOrFail(string $id): OpResult|ManifestValue
    {
        $manifest = $this->registry->find($id);
        if (!$manifest) {
            return OpResult::failure(__('extensions::lang.extension_not_found'), 'not_found');
        }

        return $manifest;
    }

    /**
     * Helper to find extension by various criteria - consolidated search logic.
     */
    private function findExtensionByCriteria(array $criteria): ?Extension
    {
        $statuses = $this->getStatuses();

        foreach ($this->getAllManifests() as $m) {
            $match = true;

            // Check ID match
            if (isset($criteria['id']) && $m->id !== $criteria['id']) {
                $match = false;
            }

            // Check name match (case insensitive)
            if (isset($criteria['name']) && strtolower($m->name) !== strtolower($criteria['name'])) {
                $match = false;
            }

            // Check type match (case insensitive)
            if (isset($criteria['type']) && strtolower($m->type) !== strtolower($criteria['type'])) {
                $match = false;
            }

            if ($match) {
                return $this->makeExtension($m, $statuses);
            }
        }

        return null;
    }

    /**
     * Centralized installation logic - performs all steps needed to install an extension.
     *
     * @param string $id Extension ID
     * @param bool $runMigrations Whether to run migrations
     * @param bool $enableExtension Whether to enable extension after install
     * @return OpResult
     */
    private function performInstallation(string $id, bool $runMigrations = true, bool $enableExtension = false): OpResult
    {
        try {
            $manifestResult = $this->getManifestOrFail($id);
            if ($manifestResult instanceof OpResult) {
                return $manifestResult;
            }
            $manifest = $manifestResult;

            // Include bundled vendor first (if any) to reduce false-positive "missing packages"
            $this->deps->includeExtensionVendor($manifest);

            // Check for missing extension dependencies
            $missingExt = $this->getMissingExtensions($manifest);
            if (!empty($missingExt)) {
                return OpResult::failure(
                    __('extensions::lang.missing_extensions', ['extensions' => implode(', ', $missingExt)]),
                    'missing_extensions',
                    ['extensions' => array_values($missingExt)]
                );
            }

            // Check for missing composer packages
            $missing = $this->deps->missingPackages($manifest);

            // Install composer dependencies if needed
            /** @var ComposerService $mergeService */
            $mergeService = $this->app->make(ComposerService::class);

            $installedPackages = [];

            // Check if extension has composer.json
            if (!$mergeService->extensionHasComposerFile($manifest)) {
                // No composer.json - just continue without installing packages
                $installedPackages = [];
            } else {
                $composerData = $mergeService->getExtensionComposerData($manifest);
                $dependencies = $composerData['require'] ?? [];

                if (empty($dependencies)) {
                    // No dependencies in composer.json - just continue
                    $installedPackages = [];
                } elseif (empty($missing)) {
                    // Dependencies already installed - just continue
                    $installedPackages = [];
                } else {
                    // Dependencies are missing - install them
                    $success = $mergeService->installDependencies();

                    if (!$success) {
                        return OpResult::failure(__('extensions::lang.deps_install_failed_merge'), 'install_failed', ['packages' => $missing]);
                    }

                    $installedPackages = $missing;
                    $extension = $this->get($id);
                    ExtensionDepsInstalledEvent::dispatch($extension, ['packages' => $missing, 'method' => 'composer-merge-plugin']);
                    Log::info('Dependencies installed via composer-merge-plugin', ['id' => $id, 'packages' => $missing]);
                }
            }

            $data = ['packages' => $installedPackages];

            // Run migrations if requested
            $migrationsRun = false;
            if ($runMigrations) {
                $migrationsRun = $this->migrator->migrate($manifest);
                $data['migrations_run'] = $migrationsRun;
            }

            // Enable extension if requested
            if ($enableExtension) {
                // Switch types logic: only one active per type
                $this->enforceSwitchTypesOnEnable($manifest);

                // Persist + bootstrap
                $this->activator->enable($id, $manifest->type);
                $this->clearCache(); // Clear cache after state change
                $this->bootstrapper->registerProvider($manifest);

                $extension = $this->get($id);
                ExtensionEnabledEvent::dispatch($extension);
                Log::info('Extension enabled', ['id' => $id]);

                return OpResult::success(__('extensions::lang.extension_enabled'), $data);
            }

            // Just installed, not enabled
            if ($runMigrations) {
                if ($migrationsRun) {
                    Log::info('Extension installed successfully (dependencies + migrations)', ['id' => $id]);

                    return OpResult::success(__('extensions::lang.extension_installed_with_migrations'), $data);
                } else {
                    Log::info('Extension installed (dependencies, no migrations needed)', ['id' => $id]);

                    return OpResult::success(__('extensions::lang.extension_installed_deps_only'), $data);
                }
            } else {
                // Only dependencies were requested (installDependencies case)
                if (!empty($installedPackages)) {
                    return OpResult::success(__('extensions::lang.deps_installed'), $data);
                } else {
                    // No packages to install or already installed
                    if (!$mergeService->extensionHasComposerFile($manifest)) {
                        return OpResult::success(__('extensions::lang.extension_no_composer'), $data);
                    } else {
                        $composerData = $mergeService->getExtensionComposerData($manifest);
                        $dependencies = $composerData['require'] ?? [];
                        if (empty($dependencies)) {
                            return OpResult::success(__('extensions::lang.extension_no_deps'), $data);
                        } else {
                            return OpResult::success(__('extensions::lang.deps_already_installed'), $data);
                        }
                    }
                }
            }

        } catch (\Throwable $e) {
            Log::error('Extension installation failed', ['id' => $id, 'error' => $e->getMessage()]);

            return OpResult::failure(__('extensions::lang.install_failed', ['error' => $e->getMessage()]), 'exception');
        }
    }

    /**
     * Centralized validation for extension operations.
     */
    public function validateExtensionExists(string $id): OpResult
    {
        $manifestResult = $this->getManifestOrFail($id);
        if ($manifestResult instanceof OpResult) {
            return $manifestResult;
        }

        return OpResult::success('Extension exists');
    }

    public function validateCanEnable(string $id): OpResult
    {
        $validationResult = $this->validateExtensionExists($id);
        if ($validationResult->isFailure()) {
            return $validationResult;
        }

        if ($this->activator->isEnabled($id)) {
            return OpResult::failure(__('extensions::lang.extension_already_enabled'), 'already_enabled');
        }

        // Dependencies validation is now handled in performInstallation()
        // This method only checks basic enable prerequisites
        return OpResult::success('Extension can be enabled');
    }

    public function validateCanDisable(string $id): OpResult
    {
        $validationResult = $this->validateExtensionExists($id);
        if ($validationResult->isFailure()) {
            return $validationResult;
        }

        if (!$this->activator->isEnabled($id)) {
            return OpResult::failure(__('extensions::lang.extension_already_disabled'), 'already_disabled');
        }

        $manifest = $this->registry->find($id);
        if ($this->isProtectedManifest($manifest) && !$this->isSwitchTypeManifest($manifest)) {
            return OpResult::failure(__('extensions::lang.extension_protected_disable'), 'protected');
        }

        $requiredBy = $this->getRequiredByEnabled($id);
        if (!empty($requiredBy)) {
            return OpResult::failure(
                __('extensions::lang.extension_required_by', ['extensions' => implode(', ', $requiredBy)]),
                'required_by',
                ['required_by' => $requiredBy]
            );
        }

        return OpResult::success('Extension can be disabled');
    }

    public function validateCanDelete(string $id): OpResult
    {
        $validationResult = $this->validateExtensionExists($id);
        if ($validationResult->isFailure()) {
            return $validationResult;
        }

        $manifest = $this->registry->find($id);
        if ($this->isProtectedManifest($manifest)) {
            return OpResult::failure(__('extensions::lang.extension_protected_delete'), 'protected');
        }

        return OpResult::success('Extension can be deleted');
    }

    private function isProtectedManifest(ManifestValue $manifest): bool
    {
        // Normalize protected list:
        // - ['core', 'billing'] -> both core and billing are protected
        // - ['core' => true, 'billing' => 1, 'other' => false] -> core and billing are protected
        // - ['Modules' => 'Sample'] -> Sample of type Modules is protected
        // - mixed variants with string values
        $raw = (array) config('extensions.protected', []);
        $list = [];

        foreach ($raw as $k => $v) {
            if (is_string($v)) {
                $list[] = $v;
            } elseif (is_string($k) && ($v === true || $v === 1)) {
                $list[] = $k;
            } elseif (is_string($k) && is_string($v)) {
                // Format like ['Modules' => 'Sample'] means extension 'Sample' of type 'Modules' is protected
                if (strtolower($manifest->type) === strtolower($k)) {
                    $list[] = $v;
                }
            }
        }

        $set = array_flip(array_map('strtolower', $list));

        $idLower = strtolower($manifest->id);
        $nameLower = strtolower($manifest->name);

        return isset($set[$idLower]) || isset($set[$nameLower]);
    }

    private function isSwitchTypeManifest(ManifestValue $manifest): bool
    {
        $switch = array_map('strtolower', (array) config('extensions.switch_types', []));

        return in_array(strtolower($manifest->type), $switch, true);
    }

    /** @return string[] ids of enabled extensions that require $id */
    private function getRequiredByEnabled(string $id): array
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
    private function getMissingExtensions(ManifestValue $manifest): array
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
            if ($this->isProtectedManifest($m)) {
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
            $validation = $this->validateCanDelete($id);
            if ($validation->isFailure()) {
                return $validation;
            }

            $manifest = $this->registry->find($id);

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
