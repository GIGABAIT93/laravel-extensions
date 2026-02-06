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
                            {--broken : Show only broken extensions}
                            {--json : Output as JSON}
                            {--plain : Output without formatting}';

    protected $description = 'Search extensions by various criteria';

    public function handle(ExtensionService $extensions): int
    {
        $query = $this->argument('query');
        $author = $this->option('author');
        $type = $this->option('type');
        $enabled = $this->option('enabled');
        $disabled = $this->option('disabled');
        $broken = $this->option('broken');

        if ($enabled && $disabled) {
            $this->error(__('extensions::lang.enabled_disabled_conflict'));

            return self::FAILURE;
        }

        // Start with all extensions
        $results = $extensions->all();

        // Apply search query
        if (is_string($query) && $query !== '') {
            $needle = strtolower($query);
            $results = $results->filter(function ($extension) use ($needle) {
                return str_contains(strtolower($extension->name), $needle)
                    || str_contains(strtolower($extension->id), $needle)
                    || str_contains(strtolower($extension->description), $needle)
                    || str_contains(strtolower($extension->author), $needle);
            });
        }

        // Apply author filter
        if (is_string($author) && $author !== '') {
            $results = $results->filter(function ($extension) use ($author) {
                return strtolower($extension->author) === strtolower($author);
            });
        }

        // Apply type filter
        if (is_string($type) && $type !== '') {
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

        if ($this->isJsonOutput()) {
            $items = $results->map(function ($extension) {
                return [
                    'name' => $extension->name,
                    'id' => $extension->id,
                    'type' => $extension->type,
                    'author' => $extension->author,
                    'status' => $extension->isBroken() ? 'broken' : ($extension->isEnabled() ? 'enabled' : 'disabled'),
                    'version' => $extension->version,
                ];
            })->values()->all();

            $this->line((string) json_encode([
                'filters' => [
                    'query' => $query,
                    'author' => $author,
                    'type' => $type,
                    'enabled' => (bool) $enabled,
                    'disabled' => (bool) $disabled,
                    'broken' => (bool) $broken,
                ],
                'items' => $items,
                'count' => count($items),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
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

        if ($this->isInteractive()) {
            table(
                ['Name', 'ID', 'Type', 'Author', 'Status', 'Version'],
                $rows
            );
        } else {
            foreach ($rows as $row) {
                $this->line(sprintf('- %s (%s) — %s — %s — %s — %s', $row[0], $row[1], $row[2], $row[3], strip_tags($row[4]), $row[5]));
            }
        }

        return self::SUCCESS;
    }
}
