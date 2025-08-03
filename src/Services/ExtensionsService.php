<?php

namespace Gigabait93\Extensions\Services;

use Gigabait93\Extensions\Contracts\ActivatorInterface;
use Gigabait93\Extensions\Services\Concerns\DiscoversExtensions;
use Gigabait93\Extensions\Services\Concerns\QueriesExtensions;
use Gigabait93\Extensions\Services\Concerns\ManagesExtensions;
use Gigabait93\Extensions\Services\Concerns\ExtensionHelpers;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;

class ExtensionsService
{
    use DiscoversExtensions;
    use QueriesExtensions;
    use ManagesExtensions;
    use ExtensionHelpers;

    protected Filesystem $fs;
    protected string $basePath;
    protected array $paths;
    protected ?Collection $cache = null;
    protected ActivatorInterface $activator;

    /**
     * Constructor.
     *
     * @param ActivatorInterface $activator
     * @param string[]           $paths       Directories to scan for extensions
     */
    public function __construct(ActivatorInterface $activator, array $paths)
    {
        $this->activator = $activator;
        $this->paths     = $paths;
        $this->fs        = new Filesystem;
        $this->basePath  = rtrim(app()->basePath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
}
