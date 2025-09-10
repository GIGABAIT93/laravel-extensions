<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Support;

/**
 * Utility class for consistent JSON file reading operations.
 */
class JsonFileReader
{
    /**
     * Read and parse a JSON file.
     *
     * @param string $path
     * @return array|null Returns null if file doesn't exist or JSON is invalid
     */
    public static function read(string $path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Check if a JSON file exists and is readable.
     *
     * @param string $path
     * @return bool
     */
    public static function exists(string $path): bool
    {
        return file_exists($path) && is_readable($path);
    }

    /**
     * Write data to a JSON file.
     *
     * @param string $path
     * @param array $data
     * @param int $flags JSON encoding flags
     * @return bool
     */
    public static function write(string $path, array $data, int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES): bool
    {
        $json = json_encode($data, $flags);
        if ($json === false) {
            return false;
        }

        return file_put_contents($path, $json) !== false;
    }
}
