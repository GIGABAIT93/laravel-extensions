<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Console\Commands;

use Gigabait93\Extensions\Services\ExtensionService;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;

/**
 * Bulk operations on multiple extensions.
 */
class BulkCommand extends BaseCommand
{
    protected $signature = 'extensions:bulk 
                            {operation : Operation to perform (enable|disable)}
                            {--ids= : Comma-separated list of extension IDs}
                            {--type= : Apply to all extensions of this type}
                            {--author= : Apply to all extensions by this author}
                            {--queue : Run as queued operations}
                            {--force : Skip confirmation}
                            {--json : Output as JSON}
                            {--plain : Output without formatting}';

    protected $description = 'Perform bulk operations on multiple extensions';

    public function handle(ExtensionService $extensions): int
    {
        $operation = strtolower((string) $this->argument('operation'));
        $ids = $this->parseIdsOption($this->option('ids'));
        $type = $this->option('type');
        $author = $this->option('author');
        $force = (bool) $this->option('force');
        $queue = (bool) $this->option('queue');

        if (!in_array($operation, ['enable', 'disable'])) {
            $this->components->error('Operation must be either "enable" or "disable"');

            return self::FAILURE;
        }

        $selectorCount = 0;
        $selectorCount += !empty($ids) ? 1 : 0;
        $selectorCount += (is_string($type) && $type !== '') ? 1 : 0;
        $selectorCount += (is_string($author) && $author !== '') ? 1 : 0;

        if ($selectorCount > 1) {
            $this->error(__('extensions::lang.bulk_selector_conflict'));

            return self::FAILURE;
        }

        // Collect target extension IDs
        $targetIds = [];

        if (!empty($ids)) {
            $targetIds = $this->uniqueIds($ids);
        } elseif ($type) {
            $targetIds = $extensions->allByType($type)->pluck('id')->toArray();
        } elseif ($author) {
            $targetIds = $extensions->findByAuthor($author)->pluck('id')->toArray();
        } else {
            if (!$this->isInteractive()) {
                $this->error(__('extensions::lang.bulk_requires_selector_non_interactive'));

                return self::FAILURE;
            }

            // Interactive selection
            $allExtensions = $extensions->all();
            $choices = $allExtensions->mapWithKeys(function ($extension) {
                $status = $extension->isEnabled() ? '✓' : '✗';

                return [$extension->id => "{$extension->name} ({$extension->id}) [{$status}]"];
            })->toArray();

            $targetIds = multiselect(
                label: "Select extensions to {$operation}:",
                options: $choices,
                required: true
            );
        }

        if (empty($targetIds)) {
            $this->components->warn('No extensions selected.');

            return self::SUCCESS;
        }

        // Filter based on operation logic
        if ($operation === 'enable') {
            $targetIds = array_filter($targetIds, function ($id) use ($extensions) {
                $ext = $extensions->get($id);

                return $ext && !$ext->isEnabled();
            });
        } else {
            $targetIds = array_filter($targetIds, function ($id) use ($extensions) {
                $ext = $extensions->get($id);

                return $ext && $ext->isEnabled();
            });
        }
        $targetIds = array_values($targetIds);

        if (empty($targetIds)) {
            $this->components->info("No extensions need to be {$operation}d.");

            return self::SUCCESS;
        }

        // Confirmation
        if (!$force) {
            if (!$this->isInteractive()) {
                $this->error(__('extensions::lang.bulk_force_required_non_interactive'));

                return self::FAILURE;
            }

            $confirmed = confirm(
                label: "Are you sure you want to {$operation} " . count($targetIds) . ' extension(s)?',
                default: false
            );

            if (!$confirmed) {
                $this->components->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        // Perform bulk operation
        $successful = 0;
        $failed = 0;
        $rows = [];

        if (!$this->isJsonOutput()) {
            $this->components->info("Performing bulk {$operation} on " . count($targetIds) . ' extension(s)...');
        }

        foreach ($targetIds as $id) {
            try {
                if ($queue) {
                    $opId = $operation === 'enable'
                        ? $extensions->enableAsync($id)
                        : $extensions->disableAsync($id);

                    $rows[] = [$id, __('extensions::lang.queued_operation', ['id' => $opId])];
                    $successful++;
                    continue;
                }

                $result = $operation === 'enable'
                    ? $extensions->enable($id)
                    : $extensions->disable($id);

                if ($result->isSuccess()) {
                    $successful++;
                    $rows[] = [$id, $result->message ?? __('extensions::lang.success')];
                    continue;
                }

                $failed++;
                $rows[] = [$id, $result->message ?? __('extensions::lang.failed')];
            } catch (\Throwable $e) {
                $failed++;
                $rows[] = [$id, __('extensions::lang.failed') . ': ' . $e->getMessage()];
            }
        }

        $this->displayResults($rows, $this->isInteractive(), [
            'operation' => $operation,
            'mode' => $queue ? 'queue' : 'sync',
            'total' => count($targetIds),
            'successful' => $successful,
            'failed' => $failed,
        ]);

        if (!$this->isJsonOutput()) {
            $this->components->info("Bulk operation completed. Success: {$successful}, Failed: {$failed}");
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
