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
    protected $signature = 'extensions:disable {id? : Extension ID or name}
                            {--ids= : Comma-separated list of extension IDs}
                            {--type= : Filter by extension type}
                            {--all : Apply to all extensions}
                            {--queue : Run as queued operations}
                            {--json : Output as JSON}
                            {--plain : Output without formatting}';

    protected $description = 'Disable one or more extensions';

    public function handle(ExtensionService $extensions): int
    {
        $explicitIds = $this->parseIdsOption($this->option('ids'));
        $type = $this->selectTypeInteractively($extensions, (string) ($this->option('type') ?? ''));
        $arg = $this->argument('id');
        $all = (bool) $this->option('all');
        $queue = (bool) $this->option('queue');
        $interactive = $this->isInteractive();

        if ($this->hasConflictingTargetSelection(is_string($arg) ? $arg : null, $explicitIds, $all)) {
            $this->error(__('extensions::lang.conflicting_target_options'));

            return self::FAILURE;
        }

        if (!empty($explicitIds)) {
            $targets = $this->uniqueIds($explicitIds);
        } else {
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
        }

        if (empty($targets)) {
            warning(__('extensions::lang.nothing_to_disable'));

            return self::SUCCESS;
        }

        $rows = [];
        $failed = 0;
        foreach ($targets as $id) {
            if ($queue) {
                $operationId = $extensions->disableAsync($id);
                $rows[] = [$id, __('extensions::lang.queued_operation', ['id' => $operationId])];

                continue;
            }

            try {
                $res = $extensions->disable($id);
                if ($res->isFailure()) {
                    $failed++;
                }
                $rows[] = [$id, $this->formatResult($res->isSuccess(), $res->message)];
            } catch (\Throwable $e) {
                $failed++;
                $rows[] = [$id, $this->formatResult(false, $e->getMessage())];
            }
        }

        $this->displayResults($rows, $interactive, [
            'mode' => $queue ? 'queue' : 'sync',
            'failed' => $failed,
        ]);

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
