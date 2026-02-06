<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Support;

final class ScaffoldConfig
{
    /** @return string[] groups that must always be generated */
    public static function mandatoryGroups(): array
    {
        return ['extension', 'providers', 'composer'];
    }

    /** Resolve stubs path using package default when not configured. */
    public static function stubsPath(): string
    {
        $path = config('extensions.stubs.path');
        if (!$path) {
            $vendorPath = base_path('vendor/gigabait93/laravel-extensions/stubs/Extension');
            $path = is_dir($vendorPath)
                ? $vendorPath
                : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR . 'Extension';
        }

        return rtrim((string) $path, DIRECTORY_SEPARATOR);
    }
}
