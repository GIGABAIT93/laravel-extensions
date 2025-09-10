<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Console\Commands;

use Gigabait93\Extensions\Services\ExtensionService;

use function Laravel\Prompts\info;

/**
 * Rediscover extensions registered in configured paths.
 */
class DiscoverCommand extends BaseCommand
{
    protected $signature = 'extensions:discover {--plain : Output without formatting}';

    protected $description = 'Rediscover extensions in configured paths';

    public function handle(ExtensionService $extensions): int
    {
        $extensions->discover();
        info(__('extensions::lang.extensions_discovered'));

        return self::SUCCESS;
    }
}
