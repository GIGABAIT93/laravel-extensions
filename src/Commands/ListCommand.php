<?php

namespace Gigabait93\Extensions\Commands;

use Gigabait93\Extensions\Facades\Extensions;
use Illuminate\Console\Command;

class ListCommand extends Command
{
    protected $signature = 'extension:list';
    protected $description = 'Outputs a list of all extensions';

    public function handle(): void
    {
        $rows = Extensions::all()->map(function ($ext) {
            return [
                'Name' => $ext->getName(),
                'Status' => $ext->isActive() ? 'active' : 'inactive',
                'Type' => $ext->getType(),
            ];
        })->toArray();

        $this->table(['Name', 'Status', 'Type'], $rows);
    }
}
