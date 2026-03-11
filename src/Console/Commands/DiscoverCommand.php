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
    protected $signature = 'extensions:discover {--json : Output as JSON} {--plain : Output without formatting}';

    protected $description = 'Rediscover extensions in configured paths';

    public function handle(ExtensionService $extensions): int
    {
        $result = $extensions->discover();

        if ($this->isJsonOutput()) {
            $this->line((string) json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $result->isSuccess() ? self::SUCCESS : self::FAILURE;
        }

        if ($result->isSuccess()) {
            info($result->message ?? __('extensions::lang.extensions_discovered'));

            return self::SUCCESS;
        }

        $this->error($result->message ?? __('extensions::lang.discovery_failed'));

        return self::FAILURE;
    }
}
