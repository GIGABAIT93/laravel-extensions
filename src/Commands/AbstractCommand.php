<?php

namespace Gigabait93\Extensions\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Base command with filesystem helper and configured extension paths.
 */
abstract class AbstractCommand extends Command
{
    /** Filesystem utility instance. */
    protected Filesystem $files;

    /** Configured base paths for extensions. */
    protected array $bases;

    public function __construct()
    {
        parent::__construct();

        $this->files = new Filesystem();
        $this->bases = config('extensions.paths', []);
    }
}
