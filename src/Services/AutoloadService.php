<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Services;

use Composer\Autoload\ClassLoader;
use Gigabait93\Extensions\Support\ManifestValue;

class AutoloadService
{
    private ?ClassLoader $loader = null;

    /** @var array<string,bool> */
    private array $mapped = [];

    public function __construct(?ClassLoader $loader = null)
    {
        $this->loader = $loader ?? $this->resolveComposerLoader();
    }

    public function ensurePsr4(ManifestValue $manifest, string $namespace): void
    {
        if (!$this->loader) {
            return;
        }
        $root = rtrim($manifest->path, DIRECTORY_SEPARATOR);
        $src = $root . DIRECTORY_SEPARATOR . 'src';
        $paths = is_dir($src) ? [$src, $root] : [$root];
        $ns = rtrim($namespace, '\\') . '\\';
        if (isset($this->mapped[$ns])) {
            return;
        }
        $this->loader->setPsr4($ns, $paths);
        $this->mapped[$ns] = true;
    }

    private function resolveComposerLoader(): ?ClassLoader
    {
        foreach (spl_autoload_functions() ?: [] as $autoload) {
            if (is_array($autoload) && $autoload[0] instanceof ClassLoader) {
                return $autoload[0];
            }
        }

        return null;
    }
}
