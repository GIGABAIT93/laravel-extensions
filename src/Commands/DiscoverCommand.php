<?php

namespace Gigabait93\Extensions\Commands;

use Illuminate\Console\Command;
use Gigabait93\Extensions\Services\Extensions;

class DiscoverCommand extends Command
{
    protected $signature = 'extension:discover';
    protected $description = 'Scan extensions catalog and synchronize them with repository';

    public function handle(): void
    {
        $manager = new Extensions();
        $data = $manager->discoverAndSync();

        if (empty($data)) {
            $this->info('There are no new synchronization extensions.');
        } else {
            $this->info('Updated: ' . count($data['updated'] ?? []) . '. Deleted: ' . count($data['deleted'] ?? []) . '.');
        }
    }
}
