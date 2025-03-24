<?php

namespace Gigabait93\Extensions\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Gigabait93\Extensions\Entities\Extension;
use Gigabait93\Extensions\Activators\FileActivator;

class Extensions
{
    protected object $activator;
    protected array $extensionsPaths;

    public function __construct()
    {
        $config = config('extensions');
        // Instantiate the activator using the application container
        $activatorClass = $config['activator'] ?? FileActivator::class;
        $this->activator = app($activatorClass);
        $this->extensionsPaths = $config['extensions_paths'] ?? [base_path('modules')];
    }

    /**
     * Retrieves all extension status records and wraps them as Extension objects.
     */
    public function all(): Collection
    {
        $data = $this->activator->all();
        return $this->wrapExtensions($data);
    }

    /**
     * Wraps raw extension data into Extension objects.
     */
    public function wrapExtensions(array $data): Collection
    {
        return collect($data)->map(fn($item) => new Extension($item));
    }

    /**
     * Helper method: Returns the directory for a given extension if it exists.
     */
    protected function getExtensionDirectory(string $extension): ?string
    {
        foreach ($this->extensionsPaths as $path) {
            $dir = $path . DIRECTORY_SEPARATOR . $extension;
            if (File::isDirectory($dir) && File::exists($dir . DIRECTORY_SEPARATOR . 'extension.json')) {
                return $dir;
            }
        }
        return null;
    }

    /**
     * Reads the extension configuration from the extension.json file.
     */
    protected function getExtensionConfig(string $extension): ?array
    {
        $dir = $this->getExtensionDirectory($extension);
        if (!$dir) {
            return null;
        }
        $configPath = $dir . DIRECTORY_SEPARATOR . 'extension.json';
        $configData = json_decode(File::get($configPath), true);
        return is_array($configData) ? $configData : null;
    }

    /**
     * Installs a module.
     * Always creates or updates the module record with the discovered "type" value.
     */
    public function install(string $extension): string
    {
        $config = $this->getExtensionConfig($extension);
        if (!$config) {
            return "Extension '{$extension}' not found in the configured paths.";
        }
        $type = $config['type'] ?? 'module';
        $result = $this->activator->saveExtensionRecord($extension, ['type' => $type]);
        return $result
            ? "Extension '{$extension}' installed/updated successfully."
            : "Failed to install/update extension '{$extension}'.";
    }

    /**
     * Enables a module.
     */
    public function enable(string $extension): string
    {
        return $this->activator->setActive($extension, true)
            ? "Extension '{$extension}' enabled."
            : "Failed to enable extension '{$extension}'.";
    }

    /**
     * Disables a module.
     */
    public function disable(string $extension): string
    {
        return $this->activator->setActive($extension, false)
            ? "Extension '{$extension}' disabled."
            : "Failed to disable extension '{$extension}'.";
    }

    /**
     * Deletes a module by removing its status record and deleting its physical directory.
     */
    public function delete(string $extension): string
    {
        $deletedFromStorage = $this->activator->deleteExtension($extension);
        if (!$deletedFromStorage) {
            return "Extension '{$extension}' not found in storage.";
        }
        // Delete the physical module directory from all configured paths.
        foreach ($this->extensionsPaths as $path) {
            $dir = $path . DIRECTORY_SEPARATOR . $extension;
            if (File::isDirectory($dir)) {
                File::deleteDirectory($dir);
            }
        }
        return "Extension '{$extension}' deleted successfully.";
    }

    /**
     * Scans extension directories to find all modules (directories containing an extension.json file).
     */
    public function discoverExtensions(): array
    {
        $found = [];
        foreach ($this->extensionsPaths as $path) {
            if (File::isDirectory($path)) {
                foreach (File::directories($path) as $dir) {
                    if (File::exists($dir . DIRECTORY_SEPARATOR . 'extension.json')) {
                        $found[] = basename($dir);
                    }
                }
            }
        }
        return $found;
    }

    /**
     * Synchronizes storage by updating records for discovered modules and deleting records
     * for modules no longer present.
     */
    public function discoverAndSync(): array
    {
        $foundModules = $this->discoverExtensions();
        $updated = [];
        foreach ($foundModules as $moduleName) {
            $this->install($moduleName);
            $updated[] = $moduleName;
        }
        $storedModules = $this->activator->all();
        $storedModuleNames = array_map(fn($item) => $item['name'], $storedModules);
        $deleted = array_diff($storedModuleNames, $foundModules);
        foreach ($deleted as $moduleName) {
            $this->delete($moduleName);
        }
        return ['updated' => array_values($updated), 'deleted' => array_values($deleted)];
    }

    /**
     * Retrieves modules by type.
     */
    public function getByType(string $type): Collection
    {
        return $this->all()->filter(fn($e) => $e->getType() === $type);
    }

    /**
     * Retrieves modules by name.
     */
    public function getByName(string $name): Collection
    {
        return $this->all()->filter(fn($e) => $e->getName() === $name);
    }

    /**
     * Retrieves modules by active status.
     */
    public function getByActive(bool $active): Collection
    {
        return $this->all()->filter(fn($e) => ($e->getData()['active'] ?? false) === $active);
    }
}
