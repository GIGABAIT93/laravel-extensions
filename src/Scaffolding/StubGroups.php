<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Scaffolding;

/**
 * Helper for resolving and scanning stub groups.
 */
final class StubGroups
{
    public static function resolve(string $relative): string
    {
        $top = strtolower(strtok($relative, DIRECTORY_SEPARATOR));

        return match (true) {
            $relative === 'composer.json.stub' => 'composer',
            $relative === 'extension.json.stub' => 'extension',
            $relative === 'helpers.php.stub' => 'helpers',
            default => $top,
        };
    }

    /** @return string[] */
    public static function scan(string $root): array
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        if (!is_dir($root)) {
            return [];
        }
        $groups = [];
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        /** @var \SplFileInfo $file */
        foreach ($rii as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $rel = ltrim(str_replace($root, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            if (!str_ends_with($rel, '.stub')) {
                continue;
            }
            $groups[] = self::resolve($rel);
        }
        $groups = array_values(array_unique($groups));
        sort($groups);

        return $groups;
    }
}
