<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Services;

use Gigabait93\Extensions\Support\ManifestValue;
use Illuminate\Support\Collection;

class RegistryService
{
    /** @var string[] */
    private array $paths;

    private string $basePath;

    /** @var array<string,string> map canonicalType => absolutePath */
    private array $typedPaths = [];

    /** @var array<string, ManifestValue> */
    private array $manifests = [];

    private bool $discovered = false;

    public function __construct(array $paths, string $basePath)
    {
        $this->paths = $paths;
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        $this->typedPaths = $this->buildTypedPaths($paths);
    }

    public function discover(): void
    {
        $this->discovered = false;
        $manifests = [];
        foreach ($this->typedPaths as $type => $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            // Fast 1-level scan
            $entries = @scandir($dir) ?: [];
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $path = $dir . DIRECTORY_SEPARATOR . $entry;
                if (!is_dir($path)) {
                    continue;
                }
                $manifestPath = $path . DIRECTORY_SEPARATOR . 'extension.json';
                if (is_file($manifestPath)) {
                    $manifest = $this->readManifest($manifestPath, $type);
                    if ($manifest) {
                        $manifests[$manifest->id] = $manifest;
                    }
                }
            }

            // Recursive fallback to catch nested manifests (robustness)
            $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            /** @var \SplFileInfo $file */
            foreach ($rii as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                if (strtolower($file->getFilename()) !== 'extension.json') {
                    continue;
                }
                $manifest = $this->readManifest($file->getPathname(), $type);
                if ($manifest) {
                    $manifests[$manifest->id] = $manifest;
                }
            }
        }
        $this->setManifests(array_values($manifests));
        $this->discovered = true;
    }

    private function readManifest(string $file, ?string $forcedType = null): ?ManifestValue
    {
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return null;
        }
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return null;
        }
        $required = ['id', 'name', 'provider'];
        foreach ($required as $k) {
            if (!isset($json[$k]) || !is_string($json[$k]) || $json[$k] === '') {
                return null;
            }
        }
        $dir = dirname($file);
        $type = is_string($forcedType) ? $forcedType : (string) ($json['type'] ?? '');
        $type = $this->canonicalizeType($type);
        if ($type === '' && is_string($json['type'] ?? null)) {
            $type = (string) $json['type'];
        }
        // Try read extension's composer.json for requires
        $requiresPackages = [];
        $composerPath = $dir . DIRECTORY_SEPARATOR . 'composer.json';
        if (is_file($composerPath)) {
            $craw = @file_get_contents($composerPath);
            if ($craw !== false) {
                $cjson = json_decode($craw, true);
                if (is_array($cjson) && isset($cjson['require']) && is_array($cjson['require'])) {
                    foreach ($cjson['require'] as $pkg => $constraint) {
                        if (is_string($pkg) && is_string($constraint) && $pkg !== 'php') {
                            $requiresPackages[$pkg] = $constraint;
                        }
                    }
                }
            }
        }

        return new ManifestValue(
            id: (string) $json['id'],
            name: (string) $json['name'],
            provider: (string) $json['provider'],
            path: $dir,
            description: (string) ($json['description'] ?? $type . ' ' . $json['name']),
            author: (string) ($json['author'] ?? config('app.name')),
            type: $type,
            version: isset($json['version']) && is_string($json['version']) ? $json['version'] : '1.0.0',
            compatible_with: isset($json['compatible_with']) && is_string($json['compatible_with']) ? $json['compatible_with'] : '1.0.0',
            requires_extensions: isset($json['requires_extensions']) && is_array($json['requires_extensions']) ? array_values(array_filter($json['requires_extensions'], 'is_string')) : [],
            requires_packages: $requiresPackages,
            meta: $json,
        );
    }

    private function normalizePath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('~^[A-Za-z]:\\\\~', $path) === 1) {
            return rtrim($path, DIRECTORY_SEPARATOR);
        }

        return rtrim($this->basePath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR);
    }

    /** Build map of canonical type => absolute path (case-insensitive for type names). */
    private function buildTypedPaths(array $paths): array
    {
        $typed = [];
        foreach ($paths as $key => $val) {
            if (is_string($key) && is_string($val)) {
                $typed[$this->canonicalizeType((string) $key)] = $this->normalizePath((string) $val);
            } elseif (is_string($val)) {
                // Untyped path; keep with empty type key but unique index
                $typed[uniqid('path_', true)] = $this->normalizePath((string) $val);
            }
        }

        return $typed;
    }

    /** Convert provided type to canonical type name from config, case-insensitive. */
    private function canonicalizeType(string $type): string
    {
        $type = trim($type);
        if ($type === '') {
            return '';
        }
        // Build once: lower => canonical
        static $map = null;
        if ($map === null) {
            $map = [];
            foreach ($this->paths as $k => $v) {
                if (is_string($k)) {
                    $map[strtolower($k)] = (string) $k;
                }
            }
        }
        $lower = strtolower($type);

        return $map[$lower] ?? $type;
    }

    /** @return Collection<int, ManifestValue> */
    public function all(): Collection
    {
        if (!$this->discovered) {
            $this->discover();
        }

        return collect(array_values($this->manifests));
    }

    public function find(string $id): ?ManifestValue
    {
        if (!$this->discovered) {
            $this->discover();
        }

        return $this->manifests[$id] ?? null;
    }

    /** @param ManifestValue[] $manifests */
    public function setManifests(array $manifests): void
    {
        $this->manifests = [];
        foreach ($manifests as $m) {
            $this->manifests[$m->id] = $m;
        }
        $this->discovered = true;
    }

    /** Clear discovery cache */
    public function clearCache(): void
    {
        $this->discovered = false;
        $this->manifests = [];
    }

    /** @return string[] canonical type names from config */
    public function types(): array
    {
        $types = [];
        foreach ($this->paths as $k => $_) {
            if (is_string($k)) {
                $types[] = $this->canonicalizeType((string) $k);
            }
        }

        return array_values(array_unique($types));
    }

    /** @return array<string,string> canonicalType => absolutePath */
    public function typedPaths(): array
    {
        $out = [];
        foreach ($this->paths as $k => $v) {
            if (is_string($k) && is_string($v)) {
                $out[$this->canonicalizeType((string) $k)] = $this->normalizePath((string) $v);
            }
        }

        return $out;
    }

    public function pathForType(string $type): ?string
    {
        $canonical = $this->canonicalizeType($type);
        $paths = $this->typedPaths();

        return $paths[$canonical] ?? null;
    }
}
