<?php

namespace Gigabait93\Extensions\Actions;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

/**
 * Orchestrates creating a new extension using selected stubs.
 */
class CreateExtensionAction
{
    public function __construct(
        protected Filesystem          $files,
        protected GenerateStubsAction $generator
    ) {
    }

    /**
     * Create a new extension directory and scaffold using selected stub groups.
     *
     * @param string $name Extension name (StudlyCase)
     * @param string $basePath Base folder where the extension will be created
     * @param string $type Extension type (derived from base folder name)
     * @param string[] $stubs Stub groups to generate
     */
    public function execute(string $name, string $basePath, string $type, array $stubs): void
    {
        $namespace = ucfirst(basename($basePath));
        $destination = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . $name;

        if ($this->files->exists($destination)) {
            throw new RuntimeException(
                trans('extensions::messages.extension_exists', compact('name', 'destination'))
            );
        }
        $type = strtolower($type);
        $this->generator->execute($name, $namespace, $destination, $type, $stubs);
    }
}
