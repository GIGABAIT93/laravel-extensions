<?php

namespace Gigabait93\Extensions\Commands;

use Illuminate\Console\Command;
use Gigabait93\Extensions\Facades\Extensions;

class DeleteCommand extends Command
{
    protected $signature   = 'extension:delete {extension?}';
    protected $description = 'Remove the extension';

    public function handle(): void
    {
        $name   = $this->argument('extension');
        if (! $name) {
            $list = Extensions::all()->map(fn($e) => $e->getName())->toArray();
            if ($this->input->isInteractive()) {
                $name = $this->choice(trans('extensions::commands.select_extension_delete'), $list);
            } else {
                $this->error(trans('extensions::commands.extension_name_required'));
                return;
            }
        }
        $result = Extensions::delete($name);
        $this->info($result);
    }
}
