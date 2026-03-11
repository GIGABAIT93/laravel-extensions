<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Services;

use Gigabait93\Extensions\Support\JsonFileReader;
use Gigabait93\Extensions\Support\ManifestValue;
use Illuminate\Support\Collection;

class RegistryService
{
    private const CACHE_VERSION = 1;

    /** @var string[] */
    private array $paths;

    private string $basePath;

    private bool $cacheEnabled;

    private string $cachePath;

    private bool $recursiveFallback;

    /** @var array<string,string> map canonicalType => absolutePath */
    private array $typedPaths = [];

    /** @var array<string,string> map lowercase type => canonical type */
    private array $typeMap = [];

    /** @var array<string, ManifestValue> */
    private array $manifests = [];

    private bool $discovered = false;

    public function __construct(
        array $paths,
        string $basePath,
        bool $cacheEnabled = true,
        string $cachePath = '',
        bool $recursiveFallback = true,
    ) {
        $this->paths = $paths;
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        $this->cacheEnabled = $cacheEnabled;
        $this->cachePath = $cachePath;
        $this->recursiveFallback = $recursiveFallback;
        $this->typedPaths = $this->buildTypedPaths($paths);
    }

    public function discover(): void
    {
        $records = $this->scanFilesystem();
        $this->setManifests(array_map(
            static fn (array $record): ManifestValue => $record['manifest'],
            array_values($records)
        ));
        $this->writeCachePayload($records);
    }

    private function ensureLoaded(): void
    {
        if ($this->discovered) {
            return;
        }

        if ($this->loadFromCache()) {
            return;
        }

        $this->discover();
    }

    private function loadFromCache(): bool
    {
        if (!$this->cacheEnabled) {
            return false;
        }

        $payload = JsonFileReader::read($this->cachePath);
        if (!is_array($payload) || !$this->isCachePayloadFresh($payload)) {
            return false;
        }

        $manifests = [];
        foreach ($payload['manifests'] as $cachedManifest) {
            if (!is_array($cachedManifest)) {
                return false;
            }

            $manifest = $this->manifestFromArray($cachedManifest['manifest'] ?? null);
            if (!$manifest instanceof ManifestValue) {
                return false;
            }

            $manifests[] = $manifest;
        }

        $this->setManifests($manifests);

        return true;
    }

    /**
     * @return array<string, array{manifest: ManifestValue, files: array<string, mixed>}>
     */
    private function scanFilesystem(): array
    {
        $records = [];

        foreach ($this->typedPaths as $type => $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $topLevelManifestDirs = [];
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
                if (!is_file($manifestPath)) {
                    continue;
                }

                $record = $this->readManifestRecord($manifestPath, $type);
                if ($record === null) {
                    continue;
                }

                $records[$record['manifest']->id] = $record;
                $topLevelManifestDirs[$this->normalizeComparisonPath($path)] = true;
            }

            if (!$this->recursiveFallback) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveCallbackFilterIterator(
                    new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                    function (\SplFileInfo $current) use ($topLevelManifestDirs): bool {
                        if (!$current->isDir()) {
                            return true;
                        }

                        $normalized = $this->normalizeComparisonPath($current->getPathname());
                        if (isset($topLevelManifestDirs[$normalized])) {
                            return false;
                        }

                        return !in_array(strtolower($current->getFilename()), $this->excludedRecursiveDirectories(), true);
                    }
                )
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if (!$file->isFile() || strtolower($file->getFilename()) !== 'extension.json') {
                    continue;
                }

                $record = $this->readManifestRecord($file->getPathname(), $type);
                if ($record === null) {
                    continue;
                }

                $records[$record['manifest']->id] = $record;
            }
        }

        return $records;
    }

    /**
     * @return array{manifest: ManifestValue, files: array<string, mixed>}|null
     */
    private function readManifestRecord(string $file, ?string $forcedType = null): ?array
    {
        $json = JsonFileReader::read($file);
        if ($json === null) {
            return null;
        }

        $required = ['id', 'name', 'provider'];
        foreach ($required as $key) {
            if (!isset($json[$key]) || !is_string($json[$key]) || $json[$key] === '') {
                return null;
            }
        }

        $dir = dirname($file);
        $type = is_string($forcedType) ? $forcedType : (string) ($json['type'] ?? '');
        $type = $this->canonicalizeType($type);
        if ($type === '' && is_string($json['type'] ?? null)) {
            $type = (string) $json['type'];
        }

        $composerPath = $dir . DIRECTORY_SEPARATOR . 'composer.json';
        $requiresPackages = [];
        $composerData = JsonFileReader::read($composerPath);
        if (is_array($composerData) && isset($composerData['require']) && is_array($composerData['require'])) {
            foreach ($composerData['require'] as $pkg => $constraint) {
                if (is_string($pkg) && is_string($constraint) && $this->shouldTrackPackageDependency($pkg)) {
                    $requiresPackages[$pkg] = $constraint;
                }
            }
        }

        return [
            'manifest' => new ManifestValue(
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
            ),
            'files' => [
                'manifest_path' => $file,
                'manifest_mtime' => $this->safeFileMtime($file),
                'composer_path' => is_file($composerPath) ? $composerPath : null,
                'composer_mtime' => is_file($composerPath) ? $this->safeFileMtime($composerPath) : null,
            ],
        ];
    }

    private function normalizePath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('~^[A-Za-z]:[\\\\/]~', $path) === 1) {
            return rtrim($path, DIRECTORY_SEPARATOR);
        }

        return rtrim($this->basePath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR);
    }

    /** Build map of canonical type => absolute path (case-insensitive for type names). */
    private function buildTypedPaths(array $paths): array
    {
        $typed = [];

        foreach ($paths as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $this->typeMap[strtolower($key)] = $key;
                $typed[$this->canonicalizeType($key)] = $this->normalizePath($value);

                continue;
            }

            if (!is_string($value)) {
                continue;
            }

            $normalized = $this->normalizePath($value);
            $typed['path_' . count($typed)] = $normalized;
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

        $lower = strtolower($type);

        return $this->typeMap[$lower] ?? $type;
    }

    /** @return string[] */
    private function excludedRecursiveDirectories(): array
    {
        return ['vendor', 'node_modules', '.git', '.svn', '.hg'];
    }

    private function shouldTrackPackageDependency(string $package): bool
    {
        $package = strtolower(trim($package));

        if ($package === '' || $package === 'php') {
            return false;
        }

        return !str_starts_with($package, 'ext-')
            && !str_starts_with($package, 'lib-')
            && !str_starts_with($package, 'composer-');
    }

    private function writeCachePayload(array $records): void
    {
        if (!$this->cacheEnabled || $this->cachePath === '') {
            return;
        }

        $payload = [
            'version' => self::CACHE_VERSION,
            'base_path' => $this->basePath,
            'typed_paths' => $this->typedPaths,
            'roots' => $this->buildRootSnapshots(),
            'manifests' => [],
        ];

        foreach (array_values($records) as $record) {
            $payload['manifests'][] = [
                'manifest' => $this->manifestToArray($record['manifest']),
                'files' => $record['files'],
            ];
        }

        $dir = dirname($this->cachePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0o775, true);
        }

        JsonFileReader::write($this->cachePath, $payload);
    }

    private function isCachePayloadFresh(array $payload): bool
    {
        if (($payload['version'] ?? null) !== self::CACHE_VERSION) {
            return false;
        }

        if (($payload['base_path'] ?? null) !== $this->basePath) {
            return false;
        }

        if (($payload['typed_paths'] ?? null) !== $this->typedPaths) {
            return false;
        }

        $roots = $payload['roots'] ?? null;
        if (!is_array($roots)) {
            return false;
        }

        foreach ($this->buildRootSnapshots() as $key => $snapshot) {
            if (($roots[$key] ?? null) !== $snapshot) {
                return false;
            }
        }

        $cachedManifests = $payload['manifests'] ?? null;
        if (!is_array($cachedManifests)) {
            return false;
        }

        foreach ($cachedManifests as $entry) {
            if (!is_array($entry)) {
                return false;
            }

            $files = $entry['files'] ?? null;
            if (!is_array($files)) {
                return false;
            }

            $manifestPath = $files['manifest_path'] ?? null;
            if (!is_string($manifestPath) || !is_file($manifestPath)) {
                return false;
            }

            if (($files['manifest_mtime'] ?? null) !== $this->safeFileMtime($manifestPath)) {
                return false;
            }

            $composerPath = $files['composer_path'] ?? null;
            $composerMtime = $files['composer_mtime'] ?? null;
            if (!is_string($composerPath) || $composerPath === '') {
                if ($composerMtime !== null) {
                    return false;
                }

                continue;
            }

            if (!is_file($composerPath)) {
                return false;
            }

            if ($composerMtime !== $this->safeFileMtime($composerPath)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, array{path: string, exists: bool, mtime: int|null}> */
    private function buildRootSnapshots(): array
    {
        $snapshots = [];
        foreach ($this->typedPaths as $key => $path) {
            $snapshots[$key] = [
                'path' => $path,
                'exists' => is_dir($path),
                'mtime' => is_dir($path) ? $this->safeFileMtime($path) : null,
            ];
        }

        return $snapshots;
    }

    private function manifestToArray(ManifestValue $manifest): array
    {
        return [
            'id' => $manifest->id,
            'name' => $manifest->name,
            'provider' => $manifest->provider,
            'path' => $manifest->path,
            'description' => $manifest->description,
            'author' => $manifest->author,
            'type' => $manifest->type,
            'version' => $manifest->version,
            'compatible_with' => $manifest->compatible_with,
            'requires_extensions' => $manifest->requires_extensions,
            'requires_packages' => $manifest->requires_packages,
            'meta' => $manifest->meta,
        ];
    }

    private function manifestFromArray(mixed $data): ?ManifestValue
    {
        if (!is_array($data)) {
            return null;
        }

        $required = ['id', 'name', 'provider', 'path'];
        foreach ($required as $key) {
            if (!isset($data[$key]) || !is_string($data[$key]) || $data[$key] === '') {
                return null;
            }
        }

        return new ManifestValue(
            id: $data['id'],
            name: $data['name'],
            provider: $data['provider'],
            path: $data['path'],
            description: is_string($data['description'] ?? null) ? $data['description'] : '',
            author: is_string($data['author'] ?? null) ? $data['author'] : '',
            type: is_string($data['type'] ?? null) ? $data['type'] : 'Modules',
            version: is_string($data['version'] ?? null) ? $data['version'] : '1.0.0',
            compatible_with: is_string($data['compatible_with'] ?? null) ? $data['compatible_with'] : '1.0.0',
            requires_extensions: is_array($data['requires_extensions'] ?? null) ? array_values(array_filter($data['requires_extensions'], 'is_string')) : [],
            requires_packages: is_array($data['requires_packages'] ?? null) ? array_filter($data['requires_packages'], static fn ($value, $key) => is_string($key) && is_string($value), ARRAY_FILTER_USE_BOTH) : [],
            meta: is_array($data['meta'] ?? null) ? $data['meta'] : [],
        );
    }

    private function safeFileMtime(string $path): ?int
    {
        $mtime = @filemtime($path);

        return $mtime === false ? null : $mtime;
    }

    private function normalizeComparisonPath(string $path): string
    {
        return str_replace('\\', '/', rtrim(strtolower($path), '/'));
    }

    /** @return Collection<int, ManifestValue> */
    public function all(): Collection
    {
        $this->ensureLoaded();

        return collect(array_values($this->manifests));
    }

    public function find(string $id): ?ManifestValue
    {
        $this->ensureLoaded();

        return $this->manifests[$id] ?? null;
    }

    /** @param ManifestValue[] $manifests */
    public function setManifests(array $manifests): void
    {
        $this->manifests = [];
        foreach ($manifests as $manifest) {
            $this->manifests[$manifest->id] = $manifest;
        }
        $this->discovered = true;
    }

    /** Clear in-memory discovery cache, optionally removing the persisted registry snapshot. */
    public function clearCache(bool $forgetPersisted = false): void
    {
        $this->discovered = false;
        $this->manifests = [];

        if ($forgetPersisted && $this->cacheEnabled && $this->cachePath !== '' && is_file($this->cachePath)) {
            @unlink($this->cachePath);
        }
    }

    /** @return string[] canonical type names from config */
    public function types(): array
    {
        $types = [];
        foreach ($this->paths as $key => $_) {
            if (is_string($key)) {
                $types[] = $this->canonicalizeType($key);
            }
        }

        return array_values(array_unique($types));
    }

    /** @return array<string,string> canonicalType => absolutePath */
    public function typedPaths(): array
    {
        $out = [];
        foreach ($this->paths as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $out[$this->canonicalizeType($key)] = $this->normalizePath($value);
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
