<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Console\Commands;

use Gigabait93\Extensions\Services\ExtensionService;

use function Laravel\Prompts\confirm;

use Laravel\Prompts\Exceptions\NonInteractiveValidationException;

use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\warning;

/**
 * Permanently delete extensions from storage.
 */
class DeleteCommand extends BaseCommand
{
    protected $signature = 'extensions:delete {id? : Extension ID or name}
                            {--ids= : Comma-separated list of extension IDs}
                            {--type= : Filter by extension type}
                            {--all : Delete all extensions}
                            {--force : Skip confirmation in interactive mode}
                            {--json : Output as JSON}
                            {--plain : Output without formatting}';

    protected $description = 'Permanently delete extensions from storage';

    public function handle(ExtensionService $extensions): int
    {
        $explicitIds = $this->parseIdsOption($this->option('ids'));
        $type = $this->selectTypeInteractively($extensions, (string) ($this->option('type') ?? ''));
        $all = (bool) $this->option('all');
        $force = (bool) $this->option('force');
        $arg = $this->argument('id');
        $interactive = $this->isInteractive();

        if ($this->hasConflictingTargetSelection(is_string($arg) ? $arg : null, $explicitIds, $all)) {
            $this->error(__('extensions::lang.conflicting_target_options'));

            return self::FAILURE;
        }

        $pool = $extensions->allByType($type);
        if (!empty($explicitIds)) {
            $targets = $this->uniqueIds($explicitIds);
        } elseif ($all) {
            $targets = $pool->map->id()->all();
        } elseif ($ext = $this->findExtension($extensions, is_string($arg) ? $arg : null, $type)) {
            $targets = [$ext->id()];
        } elseif ($interactive) {
            $options = $this->formatExtensionOptions($pool);
            if (empty($options)) {
                info(__('extensions::lang.no_extensions_selection'));

                return self::SUCCESS;
            }
            try {
                $targets = multiselect(
                    label: __('extensions::lang.select_delete'),
                    options: $options,
                    required: false,
                );
            } catch (NonInteractiveValidationException) {
                warning(__('extensions::lang.no_selection'));

                return self::SUCCESS;
            }
        } else {
            if (!$all) {
                $this->warn(__('extensions::lang.no_target_specified'));

                return self::SUCCESS;
            }
            $targets = [];
        }

        if (empty($targets)) {
            warning(__('extensions::lang.nothing_to_delete'));

            return self::SUCCESS;
        }
        if (!$force) {
            if ($interactive) {
                if (!confirm(__('extensions::lang.confirm_delete'), false)) {
                    return self::SUCCESS;
                }
            } else {
                $this->error(__('extensions::lang.delete_requires_force_non_interactive'));

                return self::FAILURE;
            }
        }

        $rows = [];
        $failed = 0;
        foreach ($targets as $id) {
            try {
                $res = $extensions->delete($id);
                if ($res->isFailure()) {
                    $failed++;
                }
                $rows[] = [$id, $this->formatResult($res->isSuccess(), $res->message ?? __('extensions::lang.status_unknown'))];
            } catch (\Throwable $e) {
                $failed++;
                $rows[] = [$id, $this->formatResult(false, $e->getMessage())];
            }
        }

        $this->displayResults($rows, $interactive, ['failed' => $failed]);

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
