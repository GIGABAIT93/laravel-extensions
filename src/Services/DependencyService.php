<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Services;

use Composer\InstalledVersions;
use Gigabait93\Extensions\Support\ManifestValue;

class DependencyService
{
    public function includeExtensionVendor(ManifestValue $manifest): void
    {
        $vendorAutoload = rtrim($manifest->path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        if (is_file($vendorAutoload)) {
            require_once $vendorAutoload;
        }
    }

    /**
     * Returns list of missing composer packages (keys only) for this extension.
     * Uses Composer\InstalledVersions when available.
     * No version constraint validation for now; only presence check.
     *
     * @return string[]
     */
    public function missingPackages(ManifestValue $manifest): array
    {
        $missing = [];
        foreach ($manifest->requires_packages as $pkg => $constraint) {
            if (!class_exists(InstalledVersions::class)) {
                // Composer runtime API not available; cannot verify — assume missing
                $missing[] = $pkg;
                continue;
            }
            if (!InstalledVersions::isInstalled($pkg)) {
                $missing[] = $pkg;
            }
        }

        return $missing;
    }
}
