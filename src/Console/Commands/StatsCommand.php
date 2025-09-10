<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Console\Commands;

use Gigabait93\Extensions\Services\ExtensionService;

use function Laravel\Prompts\table;

/**
 * Display detailed statistics about extensions.
 */
class StatsCommand extends BaseCommand
{
    protected $signature = 'extensions:stats {--json : Output as JSON}';

    protected $description = 'Display detailed statistics about extensions';

    public function handle(ExtensionService $extensions): int
    {
        $stats = $extensions->getStats();
        $totalSize = $extensions->getTotalSize();
        $broken = $extensions->getBroken();
        $withIssues = $extensions->getWithMissingDependencies();

        if ($this->option('json')) {
            $this->line(json_encode([
                'stats' => $stats,
                'total_size_bytes' => $totalSize,
                'broken_extensions' => $broken->pluck('id')->toArray(),
                'extensions_with_issues' => $withIssues->pluck('id')->toArray(),
            ], JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->components->info('Extension Statistics');

        // Basic stats
        table(
            ['Metric', 'Count'],
            [
                ['Total Extensions', $stats['total']],
                ['Enabled', $stats['enabled']],
                ['Disabled', $stats['disabled']],
                ['Broken', $stats['broken']],
                ['Protected', $stats['protected']],
                ['With Dependencies', $stats['with_dependencies']],
                ['Total Size', $this->formatBytes($totalSize)],
            ]
        );

        // By type breakdown
        if (!empty($stats['by_type'])) {
            $this->newLine();
            $this->components->info('Extensions by Type');
            
            $typeRows = [];
            foreach ($stats['by_type'] as $type => $count) {
                $typeRows[] = [$type, $count];
            }
            
            table(['Type', 'Count'], $typeRows);
        }

        // Issues
        if ($broken->isNotEmpty() || $withIssues->isNotEmpty()) {
            $this->newLine();
            $this->components->warn('Issues Found');
            
            if ($broken->isNotEmpty()) {
                $this->line('<fg=red>Broken Extensions:</> ' . $broken->pluck('id')->join(', '));
            }
            
            if ($withIssues->isNotEmpty()) {
                $this->line('<fg=yellow>Extensions with Missing Dependencies:</> ' . $withIssues->pluck('id')->join(', '));
            }
        }

        return self::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}