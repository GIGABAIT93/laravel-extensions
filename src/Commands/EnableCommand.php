<?php

namespace Gigabait93\Extensions\Commands;

use Gigabait93\Extensions\Commands\Concerns\InteractsWithExtensions;
use Gigabait93\Extensions\Facades\Extensions;

class EnableCommand extends AbstractCommand
{
    use InteractsWithExtensions;

    protected $signature = 'extension:enable {extension?}';
    protected $description = 'Enable the extension';

    public function handle(): void
    {
        $name = $this->promptExtension('extensions::commands.select_extension_enable');
        if (! $name) {
            return;
        }
        $result = Extensions::enable($name);
        $this->info($result);
    }
}
