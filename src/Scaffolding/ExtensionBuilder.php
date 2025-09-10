<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Scaffolding;

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
        [$type, $name, $namespace] = $this->resolveNames();
        $base = $this->resolveBasePath($type);
        $stubs = $this->resolveStubsPath();
        $targetRoot = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;

        if ($this->files->exists($targetRoot)) {
            if (!$this->force) {
                throw new \RuntimeException(__('extensions::lang.extension_exists', ['name' => $name, 'path' => $targetRoot]));
            }
        } else {
            $this->files->makeDirectory($targetRoot, 0o775, true);
        }

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
            if (!$this->files->isDirectory($outDir)) {
                $this->files->makeDirectory($outDir, 0o775, true);
            }
            if ($this->files->exists($outPath) && !$this->force) {
                continue; // skip existing unless forcing
            }
            $this->files->put($outPath, $content);
            $created[] = $outRel;
        }

        return [
            'path' => $targetRoot,
            'namespace' => $namespace,
            'files' => $created,
        ];
    }

    private function resolveNames(): array
    {
        $type = $this->type !== '' ? $this->canonicalType($this->type) : $this->type;
        $studlyName = Str::studly($this->name);
        $namespace = ($type !== '' ? $type . '\\' : '') . $studlyName;

        return [$type, $studlyName, $namespace];
    }

    private function resolveBasePath(string $type): string
    {
        if (is_string($this->basePath) && $this->basePath !== '') {
            return rtrim($this->basePath, DIRECTORY_SEPARATOR);
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
        if (!is_dir($path)) {
            throw new \RuntimeException(__('extensions::lang.stubs_path_invalid', ['path' => $path]));
        }

        return rtrim($path, DIRECTORY_SEPARATOR);
    }

    /** @return iterable<string,string> rel => abs */
    private function iterStubs(string $root): iterable
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
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
            yield $rel => $file->getPathname();
        }
    }

    /** @return array{0:string,1:string} [relativeOutPath, content] */
    private function renderStub(string $rel, string $abs, string $type, string $name, string $namespace): array
    {
        $snake = Str::snake($name);
        $snakePlural = Str::snake(Str::pluralStudly($name));

        $content = file_get_contents($abs) ?: '';
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
            $timestamp = date('Y_m_d_His');
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
}
