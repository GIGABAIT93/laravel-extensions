<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Console\Commands;

use Gigabait93\Extensions\Services\ExtensionService;
use Laravel\Prompts\Exceptions\NonInteractiveValidationException;

use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\warning;

/**
 * Install composer dependencies for selected extensions.
 */
class InstallDepsCommand extends BaseCommand
{
    protected $signature = 'extensions:install-deps {id? : Extension ID or name}
                            {--ids= : Comma-separated list of extension IDs}
                            {--type= : Filter by extension type}
                            {--all : Target all extensions}
                            {--queue : Run as queued operations}
                            {--auto-enable : For queue mode, auto-enable extension after deps install}
                            {--enable-after : In sync mode, enable extension after successful deps install}
                            {--json : Output as JSON}
                            {--plain : Output without formatting}';

    protected $description = 'Install composer dependencies for selected extensions';

    public function handle(ExtensionService $extensions): int
    {
        $explicitIds = $this->parseIdsOption($this->option('ids'));
        $type = $this->selectTypeInteractively($extensions, (string) ($this->option('type') ?? ''));
        $arg = $this->argument('id');
        $all = (bool) $this->option('all');
        $queue = (bool) $this->option('queue');
        $autoEnable = (bool) $this->option('auto-enable');
        $enableAfter = (bool) $this->option('enable-after');
        $interactive = $this->isInteractive();

        if ($this->hasConflictingTargetSelection(is_string($arg) ? $arg : null, $explicitIds, $all)) {
            $this->error(__('extensions::lang.conflicting_target_options'));

            return self::FAILURE;
        }

        if (!empty($explicitIds)) {
            $targets = $this->uniqueIds($explicitIds);
        } elseif ($all) {
            $targets = $extensions->allByType($type)->map->id()->all();
        } elseif ($ext = $this->findExtension($extensions, is_string($arg) ? $arg : null, $type)) {
            $targets = [$ext->id()];
        } elseif ($interactive) {
            $list = $extensions->allByType($type);
            $options = $this->formatExtensionOptions($list);
            if (empty($options)) {
                info(__('extensions::lang.no_extensions_selection'));

                return self::SUCCESS;
            }

            try {
                $targets = multiselect(
                    label: __('extensions::lang.select_install_deps'),
                    options: $options,
                    required: false,
                );
            } catch (NonInteractiveValidationException) {
                $this->warn(__('extensions::lang.non_interactive_no_selection'));

                return self::SUCCESS;
            }
        } else {
            if (!$all && (!is_string($arg) || $arg === '')) {
                $this->warn(__('extensions::lang.no_target_specified'));

                return self::SUCCESS;
            }
            $targets = [];
        }

        if (empty($targets)) {
            warning(__('extensions::lang.nothing_selected'));

            return self::SUCCESS;
        }

        $rows = [];
        $failed = 0;
        foreach ($targets as $id) {
            if ($queue) {
                $operationId = $extensions->installDepsAsync($id, $autoEnable);
                $rows[] = [$id, __('extensions::lang.queued_operation', ['id' => $operationId])];

                continue;
            }

            try {
                $res = $extensions->installDependencies($id);
                $message = $res->isSuccess() ? __('extensions::lang.status_installed') : __('extensions::lang.failed');

                if ($res->isSuccess() && $enableAfter) {
                    $enable = $extensions->enable($id);
                    if ($enable->isFailure()) {
                        $failed++;
                        $message = __('extensions::lang.status_installed') . '; ' . $this->formatResult(false, $enable->message);
                    } else {
                        $message = __('extensions::lang.status_installed') . '; ' . __('extensions::lang.status_enabled');
                    }
                } elseif ($res->isFailure()) {
                    $failed++;
                }

                $rows[] = [$id, $message];
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
