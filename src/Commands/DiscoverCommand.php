<?php

namespace Gigabait93\Extensions\Commands;

use Illuminate\Console\Command;
use Gigabait93\Extensions\Services\ExtensionManager;

class DiscoverCommand extends Command
{
    protected $signature = 'extension:discover';
    protected $description = 'Scan extensions catalog and synchronize them with repository';

    public function handle(): void
    {
        $manager = new ExtensionManager();
        $data = $manager->discoverAndSync();

        if (empty($data)) {
            $this->info('There are no new synchronization extensions.');
        } else {
            $this->info('Added: ' . count($data['added'] ?? []) . '. Deleted: ' . count($data['deleted'] ?? []) . '.');
        }
    }
}
