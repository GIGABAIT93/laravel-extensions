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
                'Name'   => $ext->getName(),
                'Status' => $ext->isActive() ? 'active' : 'inactive',
                'Type'   => $ext->getType(),
            ];
        })->toArray();

        $this->table(['Name', 'Status', 'Type'], $rows);
    }
}
