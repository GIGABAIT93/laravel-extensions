<?php

namespace Gigabait93\Extensions\Activators;

use Illuminate\Support\Facades\File;

class FileActivator
{
    protected string $jsonFile;
    protected array $extensionsPaths;

    // Allowed keys with their default values
    protected array $allowedKeys = [
        'name'   => '',
        'type'   => 'module',
        'active' => false,
    ];

    public function __construct()
    {
        $config = config('extensions');
        $this->jsonFile = $config['json_file'] ?? base_path('extensions.json');
        $this->extensionsPaths = $config['extensions_paths'] ?? [base_path('modules')];
    }

    /**
     * Returns all extension status records from the JSON file.
     */
    public function all(): array
    {
        if (File::exists($this->jsonFile)) {
            $data = json_decode(File::get($this->jsonFile), true);
            return is_array($data) ? $data : [];
        }
        return [];
    }

    /**
     * Saves the provided extension status records to the JSON file.
     */
    public function saveExtensions(array $extensions): void
    {
        File::put($this->jsonFile, json_encode($extensions, JSON_PRETTY_PRINT));
    }

    /**
     * Sanitizes a record to contain only the allowed keys with defaults.
     */
    protected function sanitizeRecord(array $record): array
    {
        return array_merge($this->allowedKeys, array_intersect_key($record, $this->allowedKeys));
    }

    /**
     * Saves or updates a single extension record.
     * Only "type" and "active" are updated. If the record does not exist, it is created.
     */
    public function saveExtensionRecord(string $extension, array $data, bool $createIfNotExists = true): bool
    {
        // Filter data to allow only "type" and "active"
        $data = array_intersect_key($data, array_flip(['type', 'active']));
        $records = $this->all();
        $found = false;
        foreach ($records as &$record) {
            if (isset($record['name']) && $record['name'] === $extension) {
                $record = $this->sanitizeRecord(array_merge($record, $data));
                $found = true;
                break;
            }
        }
        if (!$found && $createIfNotExists) {
            $newRecord = $this->sanitizeRecord(array_merge(['name' => $extension], $data));
            $records[] = $newRecord;
        } elseif (!$found) {
            return false;
        }
        $this->saveExtensions($records);
        return true;
    }

    /**
     * Sets the active status of an extension.
     */
    public function setActive(string $extension, bool $status): bool
    {
        return $this->saveExtensionRecord($extension, ['active' => $status], true);
    }

    /**
     * Deletes the record for the given extension.
     */
    public function deleteExtension(string $extension): bool
    {
        $records = $this->all();
        $originalCount = count($records);
        $records = array_filter($records, fn($record) => ($record['name'] ?? null) !== $extension);
        if (count($records) !== $originalCount) {
            $this->saveExtensions(array_values($records));
            return true;
        }
        return false;
    }
}
