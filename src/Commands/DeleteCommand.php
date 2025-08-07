<?php

namespace Gigabait93\Extensions\Commands;

use Gigabait93\Extensions\Facades\Extensions;
use Gigabait93\Extensions\Commands\Concerns\InteractsWithExtensions;
use function Laravel\Prompts\select;

class DeleteCommand extends AbstractCommand
{
    use InteractsWithExtensions;

    protected $signature   = 'extension:delete {extension?}';
    protected $description = 'Remove the extension';

    public function handle(): void
    {
        $name = $this->promptExtension('extensions::commands.select_extension_delete');
        if (! $name) {
            return;
        }
        $result = Extensions::delete($name);
        $this->info($result);
    }
}
