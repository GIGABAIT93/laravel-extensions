<?php

namespace Gigabait93\Extensions\Actions;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

/**
 * Add stub-generated files to an existing extension.
 */
class AddStubsAction
{
    public function __construct(
        protected Filesystem          $files,
        protected GenerateStubsAction $generator
    ) {
    }

    /**
     * Add new stub groups to an existing extension.
     *
     * @param string $name Extension name (StudlyCase)
     * @param string $basePath Base folder where the extension resides
     * @param string $type Extension type (e.g. module, theme)
     * @param string[] $stubs Stub groups to generate
     */
    public function execute(string $name, string $basePath, string $type, array $stubs): void
    {
        $namespace = ucfirst(basename($basePath));
        $destination = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . $name;

        if (!$this->files->exists($destination)) {
            throw new RuntimeException(
                trans('extensions::messages.extension_missing', compact('name', 'destination'))
            );
        }

        $this->generator->execute($name, $namespace, $destination, $type, $stubs);
    }
}
