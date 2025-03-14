<?php
// src/Services/ExtensionManager.php

namespace Gigabait93\Extensions\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;
use Gigabait93\Extensions\Entities\Extension;

class ExtensionManager
{
    protected string $storage;
    protected string $jsonFile;
    protected string $extensionsTable;
    protected array $extensionsPaths;

    public function __construct()
    {
        $config = config('extensions');
        $this->storage = $config['storage'] ?? env('EXTENSIONS_ACTIVATOR', 'file');
        $this->jsonFile = $config['json_file'] ?? storage_path('extensions.json');
        $this->extensionsTable = $config['extensions_table'] ?? 'extensions';
        $this->extensionsPaths = $config['extensions_paths'] ?? [base_path('Extensions')];
    }

    /**
     * Check if the extensions table exists in the database.
     */
    protected function hasDatabase(): bool
    {
        return Schema::hasTable($this->extensionsTable);
    }

    /**
     * Retrieve the list of extensions from storage (database or file)
     * and wrap each item into an Extension object.
     *
     * @return Collection|Extension[]
     */
    public function all(): Collection
    {
        if ($this->storage === 'database' && $this->hasDatabase()) {
            $data = DB::table($this->extensionsTable)
                ->get()
                ->map(fn($item) => (array)$item)
                ->toArray();
        } else {
            $data = File::exists($this->jsonFile)
                ? json_decode(File::get($this->jsonFile), true)
                : [];
        }
        return $this->wrapExtensions($data);
    }

    /**
     * Save extensions data when using file storage.
     *
     * @param array $extensions
     * @return void
     */
    protected function saveExtensions(array $extensions): void
    {
        if ($this->storage === 'database' && $this->hasDatabase()) {
            // Database storage is handled via DB queries.
            return;
        }
        File::put($this->jsonFile, json_encode($extensions, JSON_PRETTY_PRINT));
    }

    /**
     * Wrap raw extension data into Extension objects.
     *
     * @param array $data
     * @return Collection|Extension[]
     */
    public function wrapExtensions(array $data): Collection
    {
        return collect($data)->map(fn($item) => new Extension($item));
    }

    /**
     * Install an extension.
     * Checks if at least one configured extension path contains the extension directory
     * with an extension.json file. If the extension type is not provided, it is read from extension.json.
     *
     * @param string $extension Extension name.
     * @return string Result message.
     */
    public function install(string $extension): string
    {
        $foundPath = null;
        foreach ($this->extensionsPaths as $path) {
            $extensionDir = $path . DIRECTORY_SEPARATOR . $extension;
            if (File::isDirectory($extensionDir) && File::exists($extensionDir . DIRECTORY_SEPARATOR . 'extension.json')) {
                $foundPath = $extensionDir;
                break;
            }
        }
        if (!$foundPath) {
            return "Extension '{$extension}' not found in the configured paths.";
        }

        // Read the extension configuration and use the 'type' from file (default to 'extension' if not present)
        $extensionJsonPath = $foundPath . DIRECTORY_SEPARATOR . 'extension.json';
        $extensionConfig = json_decode(File::get($extensionJsonPath), true);
        $type = isset($extensionConfig['type']) ? $extensionConfig['type'] : 'extension';

        $currentTime = now();

        // Registration process is intentionally left empty.
        if ($this->storage === 'database' && $this->hasDatabase()) {
            $existing = DB::table($this->extensionsTable)->where('name', $extension)->first();
            if ($existing) {
                // Update all fields (type and updated_at) except 'active'
                if ($existing->type !== $type) {
                    DB::table($this->extensionsTable)
                        ->where('name', $extension)
                        ->update(['type' => $type, 'updated_at' => $currentTime]);
                } else {
                    DB::table($this->extensionsTable)
                        ->where('name', $extension)
                        ->update(['updated_at' => $currentTime]);
                }
                return "Extension '{$extension}' is already installed.";
            }
            DB::table($this->extensionsTable)->insert([
                'name'       => $extension,
                'type'       => $type,
                'active'     => false,
                'created_at' => $currentTime,
                'updated_at' => $currentTime,
            ]);
            return "Extension '{$extension}' installed successfully.";
        } else {
            // Get the raw extension data from file storage and update all fields except 'active'
            $extensions = $this->all()->map(fn($ext) => $ext->getData())->toArray();
            foreach ($extensions as &$e) {
                if ($e['name'] === $extension) {
                    // Preserve the current 'active' status.
                    $activeStatus = $e['active'] ?? false;
                    $e = array_merge($e, [
                        'type'       => $type,
                        'updated_at' => $currentTime->toDateTimeString(),
                    ]);
                    $e['active'] = $activeStatus;
                    $this->saveExtensions($extensions);
                    return "Extension '{$extension}' is already installed (file) and data updated.";
                }
            }
            $extensions[] = [
                'name'       => $extension,
                'type'       => $type,
                'active'     => false,
                'created_at' => $currentTime->toDateTimeString(),
                'updated_at' => $currentTime->toDateTimeString(),
            ];
            $this->saveExtensions($extensions);
            return "Extension '{$extension}' installed successfully (file).";
        }
    }


    /**
     * Enable an extension.
     *
     * @param string $extension Extension name.
     * @return string Result message.
     */
    public function enable(string $extension): string
    {
        if ($this->storage === 'database' && $this->hasDatabase()) {
            $updated = DB::table($this->extensionsTable)
                ->where('name', $extension)
                ->update(['active' => true, 'updated_at' => now()]);
            return $updated ? "Extension '{$extension}' enabled." : "Extension '{$extension}' not found.";
        } else {
            $extensions = $this->all()->map(fn($ext) => $ext->getData())->toArray();
            $found = false;
            foreach ($extensions as &$e) {
                if ($e['name'] === $extension) {
                    $e['active'] = true;
                    $e['updated_at'] = now()->toDateTimeString();
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return "Extension '{$extension}' not found in file storage.";
            }
            $this->saveExtensions($extensions);
            return "Extension '{$extension}' enabled (file).";
        }
    }

    /**
     * Disable an extension.
     *
     * @param string $extension Extension name.
     * @return string Result message.
     */
    public function disable(string $extension): string
    {
        if ($this->storage === 'database' && $this->hasDatabase()) {
            $updated = DB::table($this->extensionsTable)
                ->where('name', $extension)
                ->update(['active' => false, 'updated_at' => now()]);
            return $updated ? "Extension '{$extension}' disabled." : "Extension '{$extension}' not found.";
        } else {
            $extensions = $this->all()->map(fn($ext) => $ext->getData())->toArray();
            $found = false;
            foreach ($extensions as &$e) {
                if ($e['name'] === $extension) {
                    $e['active'] = false;
                    $e['updated_at'] = now()->toDateTimeString();
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return "Extension '{$extension}' not found in file storage.";
            }
            $this->saveExtensions($extensions);
            return "Extension '{$extension}' disabled (file).";
        }
    }

    /**
     * Delete an extension.
     * Removes the extension from storage and deletes its directory from all configured paths.
     *
     * @param string $extension Extension name.
     * @return string Result message.
     */
    public function delete(string $extension): string
    {
        $deletedFromStorage = false;
        if ($this->storage === 'database' && $this->hasDatabase()) {
            $deleted = DB::table($this->extensionsTable)
                ->where('name', $extension)
                ->delete();
            if ($deleted) {
                $deletedFromStorage = true;
            } else {
                return "Extension '{$extension}' not found in database.";
            }
        } else {
            $extensions = $this->all()->map(fn($ext) => $ext->getData())->toArray();
            $originalCount = count($extensions);
            $extensions = array_filter($extensions, fn($e) => $e['name'] !== $extension);
            if (count($extensions) !== $originalCount) {
                $deletedFromStorage = true;
                $this->saveExtensions(array_values($extensions));
            } else {
                return "Extension '{$extension}' not found in file storage.";
            }
        }

        // Remove the extension directory from all configured paths.
        foreach ($this->extensionsPaths as $path) {
            $extensionDir = $path . DIRECTORY_SEPARATOR . $extension;
            if (File::isDirectory($extensionDir)) {
                File::deleteDirectory($extensionDir);
            }
        }

        return $deletedFromStorage
            ? "Extension '{$extension}' deleted successfully."
            : "Extension '{$extension}' deletion failed.";
    }

    /**
     * Scan the extensions directories to find all extensions (directories with an extension.json file).
     *
     * @return array List of found extension names.
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
     * Scan the extensions directories and synchronize (add new extensions and delete removed ones) with storage.
     *
     * @return array Array with two keys:
     *   - 'added': list of extensions that were added.
     *   - 'deleted': list of extensions that were deleted.
     */
    public function discoverAndSync(): array
    {
        // Get the list of extension names from all configured paths.
        $foundExtensions = $this->discoverExtensions();

        // For each found extension, call install() to update its data.
        // This will update the type and timestamps, preserving the "active" status.
        $updated = [];
        foreach ($foundExtensions as $extensionName) {
            $this->install($extensionName);
            $updated[] = $extensionName;
        }

        // Get the current list of extension names from storage.
        $storedExtensions = $this->all()->map(fn($ext) => $ext->getData())->toArray();
        $storedExtensionNames = array_map(fn($item) => $item['name'], $storedExtensions);

        // Determine which stored extensions are no longer found in the filesystem.
        $deleted = array_diff($storedExtensionNames, $foundExtensions);
        foreach ($deleted as $extensionName) {
            $this->delete($extensionName);
        }

        return ['updated' => array_values($updated), 'deleted' => array_values($deleted)];
    }

    /**
     * Retrieve extensions by type.
     *
     * @param string $type
     * @return Collection|Extension[]
     */
    public function getByType(string $type): Collection
    {
        return $this->all()->filter(fn($e) => $e->getType() === $type);
    }

    /**
     * Retrieve extensions by name.
     *
     * @param string $name
     * @return Collection|Extension[]
     */
    public function getByName(string $name): Collection
    {
        return $this->all()->filter(fn($e) => $e->getName() === $name);
    }

    /**
     * Retrieve extensions by active status.
     *
     * @param bool $active
     * @return Collection|Extension[]
     */
    public function getByActive(bool $active): Collection
    {
        return $this->all()->filter(fn($e) => $e->isActive() === $active);
    }
}
