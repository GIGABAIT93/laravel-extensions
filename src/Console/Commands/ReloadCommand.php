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
    protected $signature = 'extensions:reload-active {--json : Output as JSON} {--plain : Output without formatting}';

    protected $description = 'Rediscover and reload active extensions';

    public function handle(ExtensionService $extensions): int
    {
        $discover = $extensions->discover();
        if ($discover->isFailure()) {
            if ($this->isJsonOutput()) {
                $this->line((string) json_encode($discover->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } else {
                $this->error($discover->message ?? __('extensions::lang.discovery_failed'));
            }

            return self::FAILURE;
        }

        $extensions->reloadActive();

        if ($this->isJsonOutput()) {
            $this->line((string) json_encode([
                'success' => true,
                'message' => __('extensions::lang.active_reloaded'),
                'discover' => $discover->toArray(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        info(__('extensions::lang.active_reloaded'));

        return self::SUCCESS;
    }
}
