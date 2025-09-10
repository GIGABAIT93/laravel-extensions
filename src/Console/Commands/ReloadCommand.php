<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Console\Commands;

use Gigabait93\Extensions\Services\ExtensionService;

use function Laravel\Prompts\info;

/**
 * Rediscover and reload active extensions.
 */
class ReloadCommand extends BaseCommand
{
    protected $signature = 'extensions:reload-active {--plain : Output without formatting}';

    protected $description = 'Rediscover and reload active extensions';

    public function handle(ExtensionService $extensions): int
    {
        $extensions->discover();
        $extensions->reloadActive();
        info(__('extensions::lang.active_reloaded'));

        return self::SUCCESS;
    }
}
