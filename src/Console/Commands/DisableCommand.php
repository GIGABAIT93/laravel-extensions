<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Console\Commands;

use Gigabait93\Extensions\Services\ExtensionService;

use function Laravel\Prompts\warning;

/**
 * Disable one or more extensions.
 */
class DisableCommand extends BaseCommand
{
    protected $signature = 'extensions:disable {id? : Extension ID or name} {--type= : Filter by extension type} {--all : Apply to all extensions} {--plain : Output without formatting}';

    protected $description = 'Disable one or more extensions';

    public function handle(ExtensionService $extensions): int
    {
        $type = $this->selectTypeInteractively($extensions, (string) ($this->option('type') ?? ''));
        $arg = $this->argument('id');
        $all = (bool) $this->option('all');
        $interactive = $this->isInteractive();

        $targets = $this->resolveTargets(
            extensions: $extensions,
            type: $type,
            arg: is_string($arg) ? $arg : null,
            all: $all,
            interactive: $interactive,
            enabled: true,
            emptyMessage: __('extensions::lang.no_enabled_selection'),
            promptLabel: __('extensions::lang.select_disable'),
        );

        if (empty($targets)) {
            warning(__('extensions::lang.nothing_to_disable'));

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($targets as $id) {
            $res = $extensions->disable($id);
            $rows[] = [$id, $this->formatResult($res->isSuccess(), $res->message)];
        }

        $this->displayResults($rows, $interactive);

        return self::SUCCESS;
    }
}
