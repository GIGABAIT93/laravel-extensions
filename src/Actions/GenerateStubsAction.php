<?php

namespace Gigabait93\Extensions\Actions;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Generate files from stub templates for a given extension.
 */
class GenerateStubsAction
{
    public function __construct(
        protected Filesystem $files,
        protected string     $stubRoot
    ) {
    }

    /**
     * @param string $name Extension name in StudlyCase
     * @param string $namespace Base namespace
     * @param string $dest Destination path
     * @param string $type Extension type
     * @param array $groups Stub groups to generate
     */
    public function execute(string $name, string $namespace, string $dest, string $type, array $groups): void
    {
        $groups = array_map('strtolower', $groups);
        $snake = Str::snake($name);
        $snakePlural = Str::plural($snake);
        $camelLower = lcfirst(Str::camel($name));

        foreach ($this->files->allFiles($this->stubRoot) as $file) {
            $stubPath = $file->getPathname();
            $rel = Str::after($stubPath, $this->stubRoot . DIRECTORY_SEPARATOR);
            $rel = str_replace('\\', '/', $rel);
            $group = Str::before($rel, '/');
            $group = Str::before($group, '.');

            if (!in_array(Str::lower($group), $groups, true)) {
                continue;
            }


            if (preg_match('#^Database/Migrations/migration_create_.*\\.stub$#', $rel)) {
                $timestamp = now()->format('Y_m_d_His');
                $rel = "Database/Migrations/{$timestamp}_create_{$snakePlural}_table.php";
            } else {
                $rel = str_replace(
                    ['{{name}}', '.stub'],
                    [$name, ''],
                    $rel
                );
            }

            $target = $dest . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if ($this->files->exists($target)) {
                continue;
            }
            $this->files->ensureDirectoryExists(dirname($target));

            $stub = $this->files->get($stubPath);
            $content = str_replace(
                ['{{namespace}}', '{{name}}', '{{snake}}', '{{snakePlural}}', '{{camelLower}}', '{{type}}'],
                [$namespace, $name, $snake, $snakePlural, $camelLower, $type],
                $stub
            );

            $this->files->put($target, $content);
        }
    }
}
