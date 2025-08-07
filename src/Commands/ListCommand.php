<?php

namespace Gigabait93\Extensions\Commands;

use Illuminate\Console\Command;
use Gigabait93\Extensions\Facades\Extensions;

class ListCommand extends Command
{
    protected $signature   = 'extension:list';
    protected $description = 'Outputs a list of all extensions';

    public function handle(): void
    {
        $rows = Extensions::all()->map(function ($ext) {
            return [
                $ext->getName(),
                $ext->isActive() ? trans('extensions::commands.status_active') : trans('extensions::commands.status_inactive'),
                $ext->getType(),
            ];
        })->toArray();

        $this->table([
            trans('extensions::commands.table_name'),
            trans('extensions::commands.table_status'),
            trans('extensions::commands.table_type'),
        ], $rows);
    }
}
