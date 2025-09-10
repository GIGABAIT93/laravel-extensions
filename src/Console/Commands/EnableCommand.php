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
    protected $signature = 'extensions:enable {id? : Extension ID or name} {--type= : Filter by extension type} {--all : Apply to all extensions} {--plain : Output without formatting}';

    protected $description = 'Enable one or more extensions';

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
            enabled: false,
            emptyMessage: __('extensions::lang.no_disabled_selection'),
            promptLabel: __('extensions::lang.select_enable'),
        );

        if (empty($targets)) {
            warning(__('extensions::lang.nothing_to_enable'));

            return self::SUCCESS;
        }

        // Handle switchable types
        if ($interactive && $type !== '' && $this->isSwitchableType($type) && count($targets) > 1) {
            $targets = [$this->selectSingleFromSwitchable($extensions, $targets, $type)];
        }

        $rows = [];
        foreach ($targets as $id) {
            $res = $extensions->enable($id);
            if ($res->isFailure() && $res->errorCode === 'missing_packages') {
                $install = confirm(label: __('extensions::lang.install_missing_packages', ['id' => $id]), default: true);
                if ($install) {
                    $installResult = $extensions->installDependencies($id);
                    if ($installResult->isSuccess()) {
                        $res = $extensions->enable($id);
                    }
                }
            }
            $rows[] = [$id, $this->formatResult($res->isSuccess(), $res->message)];
        }

        $this->displayResults($rows, $interactive);

        return self::SUCCESS;
    }
}
