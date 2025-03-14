<?php

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
        $this->storage = $config['storage'] ?? 'file';
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
     * Retrieve the list of extensions from storage (database or file).
     */
    public function getExtensions(): Collection
    {
        if ($this->storage === 'database' && $this->hasDatabase()) {
            return DB::table($this->extensionsTable)->get();
        }

        if (!File::exists($this->jsonFile)) {
            return collect([]);
        }

        $data = json_decode(File::get($this->jsonFile), true);
        return collect($data);
    }

    /**
     * Save extensions data when using file storage.
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
     * @return Collection
     */
    public function wrapExtensions(array $data): Collection
    {
        return collect($data)->map(function ($item) {
            return new Extension($item);
        });
    }

    /**
     * Install an extension.
     * This method checks if at least one extension path contains the extension directory and an extension.json file.
     * The installation process itself is left empty.
     *
     * @param string $extension Extension name.
     * @param string|null $type Optional extension type.
     * @return string Result message.
     */
    public function install(string $extension, ?string $type = 'extension'): string
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

        // Registration process is intentionally left empty.

        if ($this->storage === 'database' && $this->hasDatabase()) {
            $existing = DB::table($this->extensionsTable)->where('name', $extension)->first();
            if ($existing) {
                if ($type !== null && $existing->type !== $type) {
                    DB::table($this->extensionsTable)
                        ->where('name', $extension)
                        ->update(['type' => $type, 'updated_at' => now()]);
                }
                return "Extension '{$extension}' is already installed.";
            }
            DB::table($this->extensionsTable)->insert([
                'name'       => $extension,
                'type'       => $type,
                'active'     => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return "Extension '{$extension}' installed successfully.";
        } else {
            $extensions = $this->getExtensions()->toArray();
            foreach ($extensions as &$e) {
                if ($e['name'] === $extension) {
                    if ($type !== null) {
                        $e['type'] = $type;
                    }
                    $this->saveExtensions($extensions);
                    return "Extension '{$extension}' is already installed (file).";
                }
            }
            $extensions[] = [
                'name'       => $extension,
                'type'       => $type,
                'active'     => false,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
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
            $extensions = $this->getExtensions()->toArray();
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
            $extensions = $this->getExtensions()->toArray();
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
     * This method removes the extension from storage and deletes its directory from all configured paths.
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
            $extensions = $this->getExtensions()->toArray();
            $originalCount = count($extensions);
            $extensions = array_filter($extensions, function ($e) use ($extension) {
                return $e['name'] !== $extension;
            });
            if (count($extensions) !== $originalCount) {
                $deletedFromStorage = true;
                $this->saveExtensions(array_values($extensions));
            } else {
                return "Extension '{$extension}' not found in file storage.";
            }
        }

        // Remove the extension directory from all configured paths if it exists
        foreach ($this->extensionsPaths as $path) {
            $extensionDir = $path . DIRECTORY_SEPARATOR . $extension;
            if (File::isDirectory($extensionDir)) {
                File::deleteDirectory($extensionDir);
            }
        }

        return $deletedFromStorage ? "Extension '{$extension}' deleted successfully." : "Extension '{$extension}' deletion failed.";
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
                $directories = File::directories($path);
                foreach ($directories as $dir) {
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
    public function discoverAndSyncExtensions(): array
    {
        // Get extensions found in the filesystem (directories with extension.json)
        $foundExtensions = $this->discoverExtensions();

        // Get the current extensions from storage (database or file)
        $storedExtensions = $this->getExtensions()->toArray();
        $storedExtensionNames = array_map(function ($item) {
            return $item->name ?? $item['name'];
        }, $storedExtensions);

        // Determine which extensions need to be added and deleted using array_diff
        $added = array_diff($foundExtensions, $storedExtensionNames);
        $deleted = array_diff($storedExtensionNames, $foundExtensions);

        foreach ($deleted as $extensionName) {
            $this->delete($extensionName);
        }
        foreach ($added as $extensionName) {
            $this->install($extensionName);
        }

        return ['added' => array_values($added), 'deleted' => array_values($deleted)];
    }

    /**
     * Retrieve extensions by type.
     *
     * @param string $type
     * @return Collection
     */
    public function getExtensionsByType(string $type): Collection
    {
        return $this->getExtensions()->filter(function ($e) use ($type) {
            return ($e->type ?? $e['type']) === $type;
        });
    }
}
