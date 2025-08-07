<?php

namespace Gigabait93\Extensions\Commands;

use Illuminate\Console\Command;
use Gigabait93\Extensions\Facades\Extensions;

class EnableCommand extends Command
{
    protected $signature = 'extension:enable {extension?}';
    protected $description = 'Enable the extension';

    public function handle(): void
    {
        $name   = $this->argument('extension');
        if (! $name) {
            $list = Extensions::all()->map(fn($e) => $e->getName())->toArray();
            if ($this->input->isInteractive()) {
                $name = $this->choice(trans('extensions::commands.select_extension_enable'), $list);
            } else {
                $this->error(trans('extensions::commands.extension_name_required'));
                return;
            }
        }
        $result = Extensions::enable($name);
        $this->info($result);
    }
}
