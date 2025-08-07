<?php

namespace Gigabait93\Extensions\Commands;

use Gigabait93\Extensions\Facades\Extensions;
use Gigabait93\Extensions\Commands\Concerns\InteractsWithExtensions;
use function Laravel\Prompts\select;

class DisableCommand extends AbstractCommand
{
    use InteractsWithExtensions;

    protected $signature   = 'extension:disable {extension?}';
    protected $description = 'Disable the extension';

    public function handle(): void
    {
        $name = $this->promptExtension('extensions::commands.select_extension_disable');
        if (! $name) {
            return;
        }
        $result = Extensions::disable($name);
        $this->info($result);
    }
}
