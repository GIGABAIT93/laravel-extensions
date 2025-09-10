<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Console\Commands;

use Gigabait93\Extensions\Services\ExtensionService;
use Laravel\Prompts\Exceptions\NonInteractiveValidationException;

use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\warning;

/**
 * Run migrations for chosen extensions.
 */
class MigrateCommand extends BaseCommand
{
    protected $signature = 'extensions:migrate {id? : Extension ID or name} {--type= : Filter by extension type} {--enabled : Only include enabled extensions} {--all : Run for all extensions} {--plain : Output without formatting}';

    protected $description = 'Run migrations for chosen extensions';

    public function handle(ExtensionService $extensions): int
    {
        $type = $this->selectTypeInteractively($extensions, (string) ($this->option('type') ?? ''));
        $onlyEnabled = (bool) $this->option('enabled');
        $all = (bool) $this->option('all');
        $arg = $this->argument('id');
        $interactive = $this->isInteractive();

        $pool = $onlyEnabled ? $extensions->enabledByType($type) : $extensions->allByType($type);
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
                    label: __('extensions::lang.select_migrate'),
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
            warning(__('extensions::lang.nothing_to_migrate'));

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($targets as $id) {
            $ok = $extensions->migrate($id);
            $rows[] = [$id, $ok ? __('extensions::lang.status_migrated') : __('extensions::lang.failed')];
        }

        $this->displayResults($rows, $interactive);

        return self::SUCCESS;
    }
}
