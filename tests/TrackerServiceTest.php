<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Tests;

use Gigabait93\Extensions\Services\TrackerService;

class TrackerServiceTest extends TestCase
{
    public function test_create_operation_persists_in_database_store(): void
    {
        $tracker = $this->app->make(TrackerService::class);

        $operationId = $tracker->createOperation('enable', 'sample', ['auto_install_deps' => true]);

        $this->assertDatabaseHas('extension_operations', [
            'id' => $operationId,
            'type' => 'enable',
            'extension_id' => 'sample',
            'status' => 'queued',
        ]);
    }

    public function test_get_operations_by_extensions_returns_grouped_results(): void
    {
        $tracker = $this->app->make(TrackerService::class);

        $first = $tracker->createOperation('enable', 'sample');
        $second = $tracker->createOperation('disable', 'sample');
        $third = $tracker->createOperation('enable', 'base');

        $grouped = $tracker->getOperationsByExtensions(['sample', 'base']);

        $this->assertCount(2, $grouped['sample']);
        $this->assertCount(1, $grouped['base']);
        $this->assertEqualsCanonicalizing([$first, $second], array_column($grouped['sample'], 'id'));
        $this->assertSame($third, $grouped['base'][0]['id']);
    }

    public function test_get_pending_operation_id_ignores_completed_operations(): void
    {
        $tracker = $this->app->make(TrackerService::class);

        $completedId = $tracker->createOperation('enable', 'sample');
        $tracker->markAsCompleted($completedId, [], 'done');

        $pendingId = $tracker->createOperation('enable', 'sample');

        $this->assertSame($pendingId, $tracker->getPendingOperationId('sample', 'enable'));
        $this->assertTrue($tracker->isOperationPending('sample', 'enable'));
    }
}
