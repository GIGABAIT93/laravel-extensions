<?php

namespace Gigabait93\Extensions\Actions;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

class AddStubsAction
{
    public function __construct(
        protected Filesystem          $files,
        protected GenerateStubsAction $generator
    )
    {
    }

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