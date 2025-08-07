<?php

namespace Gigabait93\Extensions\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

abstract class AbstractCommand extends Command
{
    protected Filesystem $files;
    protected array $bases;

    public function __construct()
    {
        parent::__construct();

        $this->files = new Filesystem;
        $this->bases = config('extensions.paths', []);
    }
}
