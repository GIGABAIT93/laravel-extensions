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
    protected $signature = 'extensions:install-deps {id? : Extension ID or name} {--type= : Filter by extension type} {--all : Target all extensions} {--plain : Output without formatting}';

    protected $description = 'Install composer dependencies for selected extensions';

    public function handle(ExtensionService $extensions): int
    {
        $type = $this->selectTypeInteractively($extensions, (string) ($this->option('type') ?? ''));
        $arg = $this->argument('id');
        $all = (bool) $this->option('all');
        $interactive = $this->isInteractive();

        if ($all) {
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
        foreach ($targets as $id) {
            $res = $extensions->installDependencies($id);
            $rows[] = [$id, $res->isSuccess() ? __('extensions::lang.status_installed') : __('extensions::lang.failed')];
        }

        $this->displayResults($rows, $interactive);

        return self::SUCCESS;
    }
}
