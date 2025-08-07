<?php

namespace Gigabait93\Extensions\Commands;

use Gigabait93\Extensions\Facades\Extensions;
use Gigabait93\Extensions\Commands\Concerns\InteractsWithExtensions;
use function Laravel\Prompts\select;

class InstallCommand extends AbstractCommand
{
    use InteractsWithExtensions;

    protected $signature = 'extension:install {extension?} {--force}';
    protected $description = 'Set up a new extension (migrate + seed)';

    public function handle(): void
    {
        $force = $this->option('force');
        $name = $this->promptExtension('extensions::commands.select_extension_install');
        if (! $name) {
            return;
        }

        $result = Extensions::install($name, $force);
        $this->info($result);
    }
}
