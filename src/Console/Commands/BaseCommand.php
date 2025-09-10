<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Console\Commands;

use Gigabait93\Extensions\Entities\Extension as Extension;
use Gigabait93\Extensions\Services\ExtensionService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Laravel\Prompts\Exceptions\NonInteractiveValidationException;

use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;

/**
 * Base class for extension console commands with shared helpers.
 */
abstract class BaseCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Ask user to filter by type when running interactively.
     */
    protected function selectTypeInteractively(ExtensionService $extensions, string $current = ''): string
    {
        if (!$this->input->isInteractive() || $this->option('plain')) {
            return $current;
        }

        $types = $extensions->types();
        $opts = array_merge(['' => __('extensions::lang.all_types')], array_combine($types, $types));

        try {
            return (string) select(
                label: __('extensions::lang.filter_by_type'),
                options: $opts,
                default: $current ?: ''
            );
        } catch (NonInteractiveValidationException) {
            return $current;
        }
    }

    protected function findExtension(ExtensionService $extensions, ?string $arg, string $type): ?Extension
    {
        if (!is_string($arg) || $arg === '') {
            return null;
        }

        return $extensions->one($arg, $type);
    }

    protected function displayResults(array $rows, bool $interactive = true): void
    {
        if ($interactive && !$this->option('plain')) {
            table([__('extensions::lang.extension'), __('extensions::lang.result')], $rows);
        } else {
            foreach ($rows as [$ext, $result]) {
                $this->line("- $ext: $result");
            }
        }
    }

    protected function formatExtensionLabel(Extension $extension): string
    {
        return sprintf(
            '%s (%s) — %s',
            $extension->name(),
            $extension->id(),
            $extension->type()
        );
    }

    protected function formatExtensionOptions(Collection $extensions): array
    {
        $options = [];
        foreach ($extensions as $extension) {
            $options[$extension->id()] = $this->formatExtensionLabel($extension);
        }

        return $options;
    }

    /**
     * Resolve target extension IDs for enable or disable commands.
     */
    protected function resolveTargets(
        ExtensionService $extensions,
        string $type,
        ?string $arg,
        bool $all,
        bool $interactive,
        bool $enabled,
        string $emptyMessage,
        string $promptLabel
    ): array {
        if ($all) {
            $list = $enabled
                ? $extensions->enabledByType($type)
                : $extensions->disabledByType($type);

            return $list->map->id()->all();
        }

        if ($extension = $this->findExtension($extensions, $arg, $type)) {
            return [$extension->id()];
        }

        if ($interactive) {
            $list = $enabled
                ? $extensions->enabledByType($type)
                : $extensions->disabledByType($type);
            $options = $this->formatExtensionOptions($list);

            if (empty($options)) {
                info($emptyMessage);

                return [];
            }

            try {
                return multiselect(
                    label: $promptLabel,
                    options: $options,
                    required: false,
                );
            } catch (NonInteractiveValidationException) {
                $this->warn(__('extensions::lang.non_interactive_no_selection'));

                return [];
            }
        }

        if (!is_string($arg) || $arg === '') {
            $this->warn(__('extensions::lang.no_target_specified'));

            return [];
        }

        return [];
    }

    protected function isInteractive(): bool
    {
        return $this->input->isInteractive() && !$this->option('plain');
    }

    /**
     * Format result message based on success state.
     */
    protected function formatResult(bool $success, ?string $message = null): string
    {
        if ($success) {
            return $message ?? __('extensions::lang.success');
        }

        return __('extensions::lang.failed') . ($message ? ': ' . $message : '');
    }

    protected function isSwitchableType(string $type): bool
    {
        $switch = array_map('strtolower', (array) config('extensions.switch_types', []));

        return in_array(strtolower($type), $switch, true);
    }

    protected function selectSingleFromSwitchable(ExtensionService $extensions, array $targets, string $type): string
    {
        $opts = [];
        foreach ($targets as $id) {
            $extension = $extensions->one($id, $type);
            $text = $extension ? sprintf('%s (%s)', $extension->name(), $id) : $id;
            $opts[$id] = $text;
        }

        try {
            return (string) select(
                label: __('extensions::lang.switchable_pick_one'),
                options: $opts
            );
        } catch (NonInteractiveValidationException) {
            return reset($targets) ?: $targets[0];
        }
    }
}
