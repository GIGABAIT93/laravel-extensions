<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Scaffolding;

use DateTimeImmutable;
use Gigabait93\Extensions\Services\RegistryService;
use Gigabait93\Extensions\Support\ScaffoldConfig;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Builder responsible for generating extension skeletons from stubs.
 */
class ExtensionBuilder
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly RegistryService $registry,
    ) {
        $this->mandatoryGroups = ScaffoldConfig::mandatoryGroups();
    }

    private string $type = '';

    private string $name = '';

    private ?string $basePath = null; // Root path for this type

    private ?string $stubsPath = null; // Root folder with stub templates

    private array $groups = [];

    private bool $force = false;

    /** @var string[] groups that are always generated */
    private array $mandatoryGroups = [];

    private int $migrationSequence = 0;

    public function withType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function withName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function withBasePath(?string $path): self
    {
        $this->basePath = $path;

        return $this;
    }

    public function withStubsPath(?string $path): self
    {
        $this->stubsPath = $path;

        return $this;
    }

    /** @param string[] $groups */
    public function withGroups(array $groups): self
    {
        $this->groups = $groups;

        return $this;
    }

    public function withForce(bool $force): self
    {
        $this->force = $force;

        return $this;
    }

    /**
     * Build the extension scaffold.
     * @return array{path: string, namespace: string, files: array<int,string>}
     */
    public function build(): array
    {
        $this->migrationSequence = 0;

        [$type, $name, $namespace] = $this->resolveNames();
        $base = $this->resolveBasePath($type);
        $stubs = $this->resolveStubsPath();
        $targetRoot = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;

        $this->ensureDirectory($base);
        $this->prepareTargetRoot($targetRoot, $name);

        $created = [];
        $selected = array_map('strtolower', $this->groups);
        $mandatory = array_map('strtolower', $this->mandatoryGroups);
        foreach ($this->iterStubs($stubs) as $rel => $abs) {
            $group = StubGroups::resolve($rel);
            $isMandatory = in_array(strtolower($group), $mandatory, true);
            if (!empty($selected) && !in_array(strtolower($group), $selected, true) && !$isMandatory) {
                continue;
            }
            [$outRel, $content] = $this->renderStub($rel, $abs, $type, $name, $namespace);
            $outPath = $targetRoot . DIRECTORY_SEPARATOR . $outRel;
            $outDir = dirname($outPath);
            $this->ensureDirectory($outDir);
            if ($this->files->exists($outPath) && !$this->force) {
                continue; // skip existing unless forcing
            }
            $this->writeFile($outPath, $content);
            $created[] = $outRel;
        }

        $this->registry->clearCache(true);

        return [
            'path' => $targetRoot,
            'namespace' => $namespace,
            'files' => $created,
        ];
    }

    private function resolveNames(): array
    {
        $type = trim($this->type);
        if ($type === '') {
            throw new \RuntimeException(__('extensions::lang.type_required'));
        }
        $type = $this->canonicalType($type);

        $studlyName = Str::studly(trim($this->name));
        if ($studlyName === '') {
            throw new \RuntimeException(__('extensions::lang.name_required'));
        }
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $studlyName)) {
            throw new \RuntimeException(__('extensions::lang.invalid_extension_name', ['name' => $studlyName]));
        }

        $namespace = ($type !== '' ? $type . '\\' : '') . $studlyName;

        return [$type, $studlyName, $namespace];
    }

    private function resolveBasePath(string $type): string
    {
        if (is_string($this->basePath) && $this->basePath !== '') {
            return rtrim((string) $this->basePath, DIRECTORY_SEPARATOR);
        }
        $path = $this->registry->pathForType($type);
        if (!$path) {
            throw new \RuntimeException(__('extensions::lang.base_path_not_configured', ['type' => $type]));
        }

        return rtrim($path, DIRECTORY_SEPARATOR);
    }

    private function resolveStubsPath(): string
    {
        $path = $this->stubsPath ?? ScaffoldConfig::stubsPath();
        if (!is_dir($path) || !is_readable($path)) {
            throw new \RuntimeException(__('extensions::lang.stubs_path_invalid', ['path' => $path]));
        }

        return rtrim($path, DIRECTORY_SEPARATOR);
    }

    /** @return iterable<string,string> rel => abs */
    private function iterStubs(string $root): iterable
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        $collected = [];
        /** @var \SplFileInfo $file */
        foreach ($rii as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $rel = ltrim(str_replace($root, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            // only *.stub
            if (!str_ends_with($rel, '.stub')) {
                continue;
            }

            $collected[$rel] = $file->getPathname();
        }

        ksort($collected, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($collected as $rel => $path) {
            yield $rel => $path;
        }
    }

    /** @return array{0:string,1:string} [relativeOutPath, content] */
    private function renderStub(string $rel, string $abs, string $type, string $name, string $namespace): array
    {
        $snake = Str::snake($name);
        $snakePlural = Str::snake(Str::pluralStudly($name));

        $content = $this->files->get($abs);
        $namespaceReplace = $namespace;
        if (str_ends_with($rel, '.json.stub')) {
            // Escape backslashes for JSON strings
            $namespaceReplace = str_replace('\\', '\\\\', $namespace);
        }
        $content = str_replace(
            ['{{name}}', '{{snake}}', '{{snakePlural}}', '{{namespace}}', '{{type}}'],
            [$name, $snake, $snakePlural, $namespaceReplace, $type],
            $content
        );

        $outRel = $rel;
        $outRel = str_replace(
            ['{{name}}', '{{snake}}', '{{snakePlural}}'],
            [$name, $snake, $snakePlural],
            $outRel
        );
        // special-case migrations filename convention (normalize separators for portability)
        $normRel = str_replace(DIRECTORY_SEPARATOR, '/', $outRel);
        if (str_starts_with($normRel, 'Database/Migrations/migration_create_')) {
            $timestamp = $this->nextMigrationTimestamp();
            $suffix = substr($normRel, strlen('Database/Migrations/migration_'));
            $outRel = 'Database/Migrations/' . $timestamp . '_' . $suffix;
            $outRel = str_replace('/', DIRECTORY_SEPARATOR, $outRel);
        }
        // drop .stub suffix
        if (str_ends_with($outRel, '.stub')) {
            $outRel = substr($outRel, 0, -5);
        }

        return [$outRel, $content];
    }

    private function canonicalType(string $type): string
    {
        // Resolve to canonical type name (not path), case-insensitive
        return $this->registryCanonicalName($type);
    }

    private function registryCanonicalName(string $type): string
    {
        foreach ($this->registry->types() as $t) {
            if (strtolower($t) === strtolower($type)) {
                return $t;
            }
        }

        return $type;
    }

    /**
     * Get all available extension types from the registry.
     *
     * @return string[]
     */
    public static function getAvailableTypes(): array
    {
        $registry = app(RegistryService::class);

        return $registry->types();
    }

    /**
     * Get all available stub groups from the default stubs path.
     *
     * @param string|null $stubsPath Optional custom stubs path
     * @return string[]
     */
    public static function getAvailableStubGroups(?string $stubsPath = null): array
    {
        $path = $stubsPath ?? ScaffoldConfig::stubsPath();

        return StubGroups::scan($path);
    }

    private function nextMigrationTimestamp(): string
    {
        $timestamp = (new DateTimeImmutable())->modify('+' . $this->migrationSequence . ' seconds')->format('Y_m_d_His');
        $this->migrationSequence++;

        return $timestamp;
    }

    private function prepareTargetRoot(string $targetRoot, string $name): void
    {
        if ($this->files->exists($targetRoot)) {
            if (!$this->force) {
                throw new \RuntimeException(__('extensions::lang.extension_exists', ['name' => $name, 'path' => $targetRoot]));
            }
            if (!$this->files->isDirectory($targetRoot)) {
                throw new \RuntimeException(__('extensions::lang.target_not_directory', ['path' => $targetRoot]));
            }

            return;
        }

        $this->ensureDirectory($targetRoot);
    }

    private function ensureDirectory(string $path): void
    {
        if ($this->files->exists($path) && !$this->files->isDirectory($path)) {
            throw new \RuntimeException(__('extensions::lang.target_not_directory', ['path' => $path]));
        }

        if ($this->files->isDirectory($path)) {
            if (!is_writable($path)) {
                throw new \RuntimeException(__('extensions::lang.path_not_writable', ['path' => $path]));
            }

            return;
        }

        try {
            $created = $this->files->makeDirectory($path, 0o775, true);
        } catch (\Throwable $e) {
            throw new \RuntimeException(__('extensions::lang.failed_create_directory', ['path' => $path]), 0, $e);
        }

        if (!$created) {
            throw new \RuntimeException(__('extensions::lang.failed_create_directory', ['path' => $path]));
        }
    }

    private function writeFile(string $path, string $content): void
    {
        $tmpPath = $path . '.tmp.' . Str::random(8);

        $bytes = @file_put_contents($tmpPath, $content, LOCK_EX);
        if ($bytes === false) {
            throw new \RuntimeException(__('extensions::lang.failed_write_file', ['path' => $path]));
        }

        if (!@rename($tmpPath, $path)) {
            @unlink($tmpPath);
            throw new \RuntimeException(__('extensions::lang.failed_move_file', ['path' => $path]));
        }
    }
}
