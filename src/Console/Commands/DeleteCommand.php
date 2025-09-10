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
    protected $signature = 'extensions:delete {id? : Extension ID or name} {--type= : Filter by extension type} {--all : Delete all extensions} {--plain : Output without formatting}';

    protected $description = 'Permanently delete extensions from storage';

    public function handle(ExtensionService $extensions): int
    {
        $type = $this->selectTypeInteractively($extensions, (string) ($this->option('type') ?? ''));
        $all = (bool) $this->option('all');
        $arg = $this->argument('id');
        $interactive = $this->isInteractive();

        $pool = $extensions->allByType($type);
        if ($all) {
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
        if ($interactive && !confirm(__('extensions::lang.confirm_delete'), false)) {
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($targets as $id) {
            $res = $extensions->delete($id);
            $rows[] = [$id, $this->formatResult($res->isSuccess(), $res->message ?? __('extensions::lang.status_unknown'))];
        }

        $this->displayResults($rows, $interactive);

        return self::SUCCESS;
    }
}
