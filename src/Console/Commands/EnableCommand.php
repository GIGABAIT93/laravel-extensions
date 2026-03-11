<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Console\Commands;

use Gigabait93\Extensions\Services\ExtensionService;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\warning;

/**
 * Enable one or more extensions.
 */
class EnableCommand extends BaseCommand
{
    protected $signature = 'extensions:enable {id? : Extension ID or name}
                            {--ids= : Comma-separated list of extension IDs}
                            {--type= : Filter by extension type}
                            {--all : Apply to all extensions}
                            {--queue : Run as queued operations}
                            {--auto-install-deps : For queue mode, auto-install dependencies before enabling}
                            {--json : Output as JSON}
                            {--plain : Output without formatting}';

    protected $description = 'Enable one or more extensions';

    public function handle(ExtensionService $extensions): int
    {
        $explicitIds = $this->parseIdsOption($this->option('ids'));
        $type = $this->selectTypeInteractively($extensions, (string) ($this->option('type') ?? ''));
        $arg = $this->argument('id');
        $all = (bool) $this->option('all');
        $queue = (bool) $this->option('queue');
        $autoInstallDeps = (bool) $this->option('auto-install-deps');
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
                enabled: false,
                emptyMessage: __('extensions::lang.no_disabled_selection'),
                promptLabel: __('extensions::lang.select_enable'),
            );
        }

        if (empty($targets)) {
            warning(__('extensions::lang.nothing_to_enable'));

            return self::SUCCESS;
        }

        // Handle switchable types
        if ($interactive && $type !== '' && $this->isSwitchableType($type) && count($targets) > 1) {
            $targets = [$this->selectSingleFromSwitchable($extensions, $targets, $type)];
        }

        $rows = [];
        $failed = 0;
        foreach ($targets as $id) {
            if ($queue) {
                $operationId = $extensions->enableAsync($id, $autoInstallDeps);
                $rows[] = [$id, __('extensions::lang.queued_operation', ['id' => $operationId])];

                continue;
            }

            try {
                $res = $extensions->enable($id);
                if ($res->isFailure() && $res->errorCode === 'missing_packages') {
                    $install = $interactive
                        ? confirm(label: __('extensions::lang.install_missing_packages', ['id' => $id]), default: true)
                        : false;
                    if ($install) {
                        $installResult = $extensions->installDependencies($id);
                        if ($installResult->isSuccess()) {
                            $res = $extensions->enable($id);
                        }
                    }
                }
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
