<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Support;

/**
 * Utility class for consistent path resolution and file operations.
 */
class PathResolver
{
    /**
     * Get the composer.json path for an extension.
     *
     * @param string $extensionPath
     * @return string
     */
    public static function getComposerPath(string $extensionPath): string
    {
        return rtrim($extensionPath, DIRECTORY_SEPARATOR) . '/composer.json';
    }

    /**
     * Get the extension.json path for an extension.
     *
     * @param string $extensionPath
     * @return string
     */
    public static function getManifestPath(string $extensionPath): string
    {
        return rtrim($extensionPath, DIRECTORY_SEPARATOR) . '/extension.json';
    }

    /**
     * Check if an extension has a composer file.
     *
     * @param string $extensionPath
     * @return bool
     */
    public static function hasComposerFile(string $extensionPath): bool
    {
        return JsonFileReader::exists(self::getComposerPath($extensionPath));
    }

    /**
     * Check if an extension has a manifest file.
     *
     * @param string $extensionPath
     * @return bool
     */
    public static function hasManifestFile(string $extensionPath): bool
    {
        return JsonFileReader::exists(self::getManifestPath($extensionPath));
    }

    /**
     * Get provider file path from namespace.
     *
     * @param string $extensionPath
     * @param string $providerNamespace
     * @return string
     */
    public static function getProviderPath(string $extensionPath, string $providerNamespace): string
    {
        $extensionPath = rtrim($extensionPath, DIRECTORY_SEPARATOR);
        $providerPath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, trim($providerNamespace, '\\/')) . '.php';
        $fullNamespacePath = $extensionPath . DIRECTORY_SEPARATOR . $providerPath;

        if (file_exists($fullNamespacePath)) {
            return $fullNamespacePath;
        }

        $segments = preg_split('/[\\\\\/]+/', trim($providerNamespace, '\\/')) ?: [];
        if (count($segments) > 2) {
            $trimmed = implode(DIRECTORY_SEPARATOR, array_slice($segments, 2)) . '.php';
            $trimmedPath = $extensionPath . DIRECTORY_SEPARATOR . $trimmed;

            if (file_exists($trimmedPath)) {
                return $trimmedPath;
            }
        }

        return $fullNamespacePath;
    }

    /**
     * Check if an extension has a provider file.
     *
     * @param string $extensionPath
     * @param string $providerNamespace
     * @return bool
     */
    public static function hasProviderFile(string $extensionPath, string $providerNamespace): bool
    {
        return file_exists(self::getProviderPath($extensionPath, $providerNamespace));
    }

    /**
     * Find README file in extension directory.
     *
     * @param string $extensionPath
     * @return string|null Returns the path to the first found README file
     */
    public static function findReadmePath(string $extensionPath): ?string
    {
        $readmeFiles = ['README.md', 'readme.md', 'README.txt', 'readme.txt'];
        foreach ($readmeFiles as $file) {
            $path = rtrim($extensionPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
