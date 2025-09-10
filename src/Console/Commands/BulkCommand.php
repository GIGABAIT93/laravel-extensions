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
                            {--force : Skip confirmation}';

    protected $description = 'Perform bulk operations on multiple extensions';

    public function handle(ExtensionService $extensions): int
    {
        $operation = $this->argument('operation');
        $ids = $this->option('ids');
        $type = $this->option('type');
        $author = $this->option('author');
        $force = $this->option('force');

        if (!in_array($operation, ['enable', 'disable'])) {
            $this->components->error('Operation must be either "enable" or "disable"');
            return self::FAILURE;
        }

        // Collect target extension IDs
        $targetIds = [];

        if ($ids) {
            $targetIds = array_map('trim', explode(',', $ids));
        } elseif ($type) {
            $targetIds = $extensions->allByType($type)->pluck('id')->toArray();
        } elseif ($author) {
            $targetIds = $extensions->findByAuthor($author)->pluck('id')->toArray();
        } else {
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

        if (empty($targetIds)) {
            $this->components->info("No extensions need to be {$operation}d.");
            return self::SUCCESS;
        }

        // Confirmation
        if (!$force) {
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
        $this->components->info("Performing bulk {$operation} on " . count($targetIds) . ' extension(s)...');

        if ($operation === 'enable') {
            $results = $extensions->enableMultiple($targetIds);
        } else {
            $results = $extensions->disableMultiple($targetIds);
        }

        // Report results
        $successful = 0;
        $failed = 0;

        foreach ($results as $id => $result) {
            if ($result->isSuccess()) {
                $successful++;
                $this->line("<fg=green>✓</> {$id}: {$result->message}");
            } else {
                $failed++;
                $this->line("<fg=red>✗</> {$id}: {$result->message}");
            }
        }

        $this->components->info("Bulk operation completed. Success: {$successful}, Failed: {$failed}");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}