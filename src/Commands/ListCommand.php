<?php

namespace Gigabait93\Extensions\Commands;

use Illuminate\Console\Command;
use Gigabait93\Extensions\Services\Extensions;
use Illuminate\Support\Facades\App;

class ListCommand extends Command
{
    protected $signature = 'extension:list';
    protected $description = 'Outputs a list of all extensions';

    public function handle(): void
    {
        $manager = App::make(Extensions::class);
        $extensions = $manager->all();

        if ($extensions->isEmpty()) {
            $this->info('There are no extensions installed.');
            return;
        }

        $data = $extensions->map(function ($item) {
            $name = $item->getName() ?? $item['name'];
            $active = ($item->isActive() ?? $item['active']) ? 'active' : 'inactive';
            $type = $item->getType() ?? ($item['type'] ?? '');
            return [
                'Name'   => $name,
                'Status' => $active,
                'Type'   => $type,
            ];
        })->toArray();

        $this->table(['Name', 'Status', 'Type'], $data);
    }
}
