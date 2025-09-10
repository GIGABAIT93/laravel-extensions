<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Console\Commands;

use Gigabait93\Extensions\Services\ExtensionService;

use function Laravel\Prompts\table;

/**
 * Search extensions by various criteria.
 */
class SearchCommand extends BaseCommand
{
    protected $signature = 'extensions:search 
                            {query? : Search query} 
                            {--author= : Filter by author}
                            {--type= : Filter by type}
                            {--enabled : Show only enabled extensions}
                            {--disabled : Show only disabled extensions}
                            {--broken : Show only broken extensions}';

    protected $description = 'Search extensions by various criteria';

    public function handle(ExtensionService $extensions): int
    {
        $query = $this->argument('query');
        $author = $this->option('author');
        $type = $this->option('type');
        $enabled = $this->option('enabled');
        $disabled = $this->option('disabled');
        $broken = $this->option('broken');

        // Start with all extensions
        $results = $extensions->all();

        // Apply search query
        if ($query) {
            $results = $extensions->search($query);
        }

        // Apply author filter
        if ($author) {
            $results = $extensions->findByAuthor($author);
        }

        // Apply type filter
        if ($type) {
            $results = $results->filter(function ($extension) use ($type) {
                return strtolower($extension->type) === strtolower($type);
            });
        }

        // Apply status filters
        if ($enabled) {
            $results = $results->filter->isEnabled();
        }

        if ($disabled) {
            $results = $results->reject->isEnabled();
        }

        if ($broken) {
            $results = $results->filter->isBroken();
        }

        if ($results->isEmpty()) {
            $this->components->warn('No extensions found matching your criteria.');

            return self::SUCCESS;
        }

        $this->components->info("Found {$results->count()} extension(s)");

        $rows = $results->map(function ($extension) {
            $status = $extension->isBroken()
                ? '<fg=red>Broken</>'
                : ($extension->isEnabled()
                    ? '<fg=green>Enabled</>'
                    : '<fg=yellow>Disabled</>');

            return [
                $extension->name,
                $extension->id,
                $extension->type,
                $extension->author,
                $status,
                $extension->version,
            ];
        })->toArray();

        table(
            ['Name', 'ID', 'Type', 'Author', 'Status', 'Version'],
            $rows
        );

        return self::SUCCESS;
    }
}
