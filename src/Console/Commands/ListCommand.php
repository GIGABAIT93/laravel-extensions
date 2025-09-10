<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Console\Commands;

use Gigabait93\Extensions\Services\ExtensionService;

use function Laravel\Prompts\table;

/**
 * Display registered extensions with optional filters.
 */
class ListCommand extends BaseCommand
{
    protected $signature = 'extensions:list {--type= : Filter by extension type} {--enabled : Show only enabled extensions} {--disabled : Show only disabled extensions} {--plain : Output without formatting}';

    protected $description = 'Display registered extensions with optional filters';

    public function handle(ExtensionService $extensions): int
    {
        $type = (string) ($this->option('type') ?? '');
        $enabled = (bool) $this->option('enabled');
        $disabled = (bool) $this->option('disabled');

        if ($type === '' && !$enabled && !$disabled) {
            $type = $this->selectTypeInteractively($extensions, '');
        }

        $items = $extensions->allByType($type);
        if ($enabled) {
            $items = $items->filter->isEnabled();
        }
        if ($disabled) {
            $items = $items->reject->isEnabled();
        }

        $rows = $items->map(function ($e) {
            $status = $e->isBroken()
                ? __('extensions::lang.status_broken')
                : ($e->isEnabled()
                    ? __('extensions::lang.status_enabled')
                    : __('extensions::lang.status_disabled'));

            return [
                $e->name(),
                $e->id(),
                $e->type(),
                $status,
                $e->version(),
            ];
        })->all();

        $interactive = $this->isInteractive();
        if ($interactive) {
            table([
                __('extensions::lang.name'),
                __('extensions::lang.id'),
                __('extensions::lang.type'),
                __('extensions::lang.status'),
                __('extensions::lang.version'),
            ], $rows);
        } else {
            foreach ($rows as $r) {
                $this->line(sprintf('- %s (%s) — %s — %s — %s', $r[0], $r[1], $r[2], $r[3], $r[4]));
            }
        }
        if (count($rows) === 0) {
            $this->line(__('extensions::lang.no_extensions_filter'));
        } else {
            $this->line(__('extensions::lang.total', ['count' => count($rows)]));
        }

        return self::SUCCESS;
    }
}
