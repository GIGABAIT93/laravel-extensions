<?php

namespace Gigabait93\Extensions\Services;

use Gigabait93\Extensions\Contracts\ActivatorInterface;
use Gigabait93\Extensions\Services\Concerns\DiscoversExtensions;
use Gigabait93\Extensions\Services\Concerns\ExtensionHelpers;
use Gigabait93\Extensions\Services\Concerns\ManagesExtensions;
use Gigabait93\Extensions\Services\Concerns\QueriesExtensions;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;

/**
 * High-level API for discovering, querying, and managing extensions.
 */
class ExtensionsService
{
    use DiscoversExtensions;
    use QueriesExtensions;
    use ManagesExtensions;
    use ExtensionHelpers;

    /** Filesystem utility. */
    protected Filesystem $fs;
    /** App base path (with trailing separator). */
    protected string $basePath;
    /** Configured paths to search for extensions. */
    protected array $paths;
    /** In-memory cache of extensions. */
    protected ?Collection $cache = null;
    /** Activator storage backend. */
    protected ActivatorInterface $activator;

    /**
     * Constructor.
     *
     * @param ActivatorInterface $activator
     * @param string[] $paths Directories to scan for extensions
     */
    public function __construct(ActivatorInterface $activator, array $paths)
    {
        $this->activator = $activator;
        $this->paths     = $paths;
        $this->fs        = new Filesystem();
        $this->basePath  = rtrim(app()->basePath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
}
