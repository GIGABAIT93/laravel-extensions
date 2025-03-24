<?php

namespace Gigabait93\Extensions\Services;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Gigabait93\Extensions\Entities\Extension;
use Gigabait93\Extensions\Activators\FileActivator;

class Extensions
{
    /**
     * @var Extensions|null
     */
    private static ?Extensions $instance = null;

    protected object $activator;
    protected array $extensionsPaths;
    protected ?Collection $cachedExtensions = null;
    protected string $jsonFile;

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct()
    {
        $config = config('extensions');
        $activatorClass = $config['activator'] ?? FileActivator::class;
        $this->activator = new $activatorClass();
        $this->extensionsPaths = $config['extensions_paths'] ?? [base_path('modules')];
        $this->jsonFile = $config['json_file'] ?? base_path('extensions.json');
    }

    /**
     * Returns the singleton instance of Extensions.
     */
    public static function getInstance(): Extensions
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Prevent cloning of the instance.
     */
    private function __clone() {}

    /**
     * Prevent unserializing of the instance.
     * @throws Exception
     */
    public function __wakeup()
    {
        throw new Exception('Cannot unserialize a singleton.');
    }

    /**
     * Retrieves all extensions with their metadata (from extension.json files)
     * and appends their status. The result is cached for better performance.
     */
    public function all(): Collection
    {
        if ($this->cachedExtensions !== null) {
            return $this->cachedExtensions;
        }

        $statuses = $this->activator->getStatuses();
        $extensions = [];

        foreach ($this->extensionsPaths as $path) {
            if (!is_dir($path)) {
                continue;
            }
            // We get a list of files by template: /modules/*/extension.json
            $files = glob($path . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'extension.json');
            if (!$files) {
                continue;
            }
            foreach ($files as $file) {
                $configData = json_decode(File::get($file), true);
                if (!is_array($configData)) {
                    continue;
                }
                // We get an extension from the directory name
                $name = basename(dirname($file));
                $extensions[$name] = array_merge($configData, [
                    'name'   => $name,
                    'active' => $statuses[$name] ?? false,
                ]);
            }
        }

        $this->cachedExtensions = $this->wrap($extensions);
        return $this->cachedExtensions;
    }

    /**
     * Wraps an array of data into a collection of Extension objects.
     */
    protected function wrap(array $data): Collection
    {
        return collect($data)->map(fn($item) => new Extension($item));
    }

    /**
     * Returns the directory path of an extension if it exists.
     */
    protected function getDirectory(string $extension): ?string
    {
        foreach ($this->extensionsPaths as $path) {
            $dir = $path . DIRECTORY_SEPARATOR . $extension;
            if (is_dir($dir) && file_exists($dir . DIRECTORY_SEPARATOR . 'extension.json')) {
                return $dir;
            }
        }
        return null;
    }

    /**
     * Retrieves the metadata of an extension from its extension.json file.
     */
    protected function getMeta(string $extension): ?array
    {
        if (!$dir = $this->getDirectory($extension)) {
            return null;
        }
        $configPath = $dir . DIRECTORY_SEPARATOR . 'extension.json';
        $configData = json_decode(File::get($configPath), true);
        return is_array($configData) ? $configData : null;
    }

    /**
     * "Installs" an extension by verifying its metadata exists.
     */
    public function install(string $extension): string
    {
        if (!$this->getMeta($extension)) {
            return "Extension '{$extension}' not found in the specified paths.";
        }
        $this->cachedExtensions = null;
        return "Extension '{$extension}' has been successfully installed.";
    }

    /**
     * Enables an extension (updates its status via the activator).
     */
    public function enable(string $extension): string
    {
        $result = $this->activator->setStatus($extension, true);
        $this->cachedExtensions = null;
        return $result
            ? "Extension '{$extension}' enabled."
            : "Failed to enable extension '{$extension}'.";
    }

    /**
     * Disables an extension (updates its status via the activator).
     */
    public function disable(string $extension): string
    {
        $result = $this->activator->setStatus($extension, false);
        $this->cachedExtensions = null;
        return $result
            ? "Extension '{$extension}' disabled."
            : "Failed to disable extension '{$extension}'.";
    }

    /**
     * Deletes an extension by removing its physical directory.
     */
    public function delete(string $extension): string
    {
        $dirFound = false;
        foreach ($this->extensionsPaths as $path) {
            $dir = $path . DIRECTORY_SEPARATOR . $extension;
            if (is_dir($dir)) {
                File::deleteDirectory($dir);
                $dirFound = true;
            }
        }
        if ($dirFound) {
            $this->cachedExtensions = null;
            return "Extension '{$extension}' successfully deleted.";
        }
        return "Extension '{$extension}' not found.";
    }

    /**
     * Discovers all extensions (directories containing an extension.json file).
     */
    public function discover(): array
    {
        $found = [];
        foreach ($this->extensionsPaths as $path) {
            if (!is_dir($path)) {
                continue;
            }
            $files = glob($path . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'extension.json');
            if (!$files) {
                continue;
            }
            foreach ($files as $file) {
                $found[] = basename(dirname($file));
            }
        }
        return $found;
    }

    /**
     * Retrieves an extension by its name.
     */
    public function getByName(string $name): Extension
    {
        return $this->all()->filter(fn($e) => ($e->getName() ?? null) === $name)->first();
    }

    /**
     * Filters extensions by type.
     */
    public function getByType(string $type): Collection
    {
        return $this->all()->filter(fn($e) => ($e->getType() ?? null) === $type);
    }

    /**
     * Filters extensions by their active status.
     */
    public function getByActive(bool $active): Collection
    {
        return $this->all()->filter(fn($e) => ($e->isActive() ?? false) === $active);
    }
}
