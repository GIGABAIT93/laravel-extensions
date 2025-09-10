<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Tests;

use Gigabait93\Extensions\Jobs\ExtensionDisableJob;
use Gigabait93\Extensions\Jobs\ExtensionEnableJob;
use Gigabait93\Extensions\Jobs\ExtensionInstallDepsJob;
use Gigabait93\Extensions\Services\ExtensionService;
use Gigabait93\Extensions\Services\TrackerService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

class ExtensionServiceAsyncTest extends TestCase
{
    public function test_enable_async_dispatches_job_and_tracks_operation(): void
    {
        Bus::fake();
        Cache::flush();

        $service = $this->app->make(ExtensionService::class);
        $opId = $service->enableAsync('sample');

        Bus::assertDispatched(ExtensionEnableJob::class, function ($job) use ($opId) {
            return $job->extensionId === 'sample' && $job->operationId === $opId;
        });

        $tracker = $this->app->make(TrackerService::class);
        $op = $tracker->getOperation($opId);
        $this->assertSame('enable', $op['type']);
        $this->assertSame('queued', $op['status']);
    }

    public function test_disable_async_dispatches_job_and_tracks_operation(): void
    {
        Bus::fake();
        Cache::flush();

        $service = $this->app->make(ExtensionService::class);
        $opId = $service->disableAsync('sample');

        Bus::assertDispatched(ExtensionDisableJob::class, function ($job) use ($opId) {
            return $job->extensionId === 'sample' && $job->operationId === $opId;
        });

        $tracker = $this->app->make(TrackerService::class);
        $op = $tracker->getOperation($opId);
        $this->assertSame('disable', $op['type']);
        $this->assertSame('queued', $op['status']);
    }

    public function test_install_deps_async_dispatches_job_and_tracks_operation(): void
    {
        Bus::fake();
        Cache::flush();

        $service = $this->app->make(ExtensionService::class);
        $opId = $service->installDepsAsync('sample', true);

        Bus::assertDispatched(ExtensionInstallDepsJob::class, function ($job) use ($opId) {
            return $job->extensionId === 'sample' && $job->operationId === $opId;
        });

        $tracker = $this->app->make(TrackerService::class);
        $op = $tracker->getOperation($opId);
        $this->assertSame('install_deps', $op['type']);
        $this->assertTrue($op['context']['auto_enable']);
    }

    public function test_install_and_enable_async_is_alias_for_install_deps_async(): void
    {
        Bus::fake();
        Cache::flush();

        $service = $this->app->make(ExtensionService::class);
        $opId = $service->installAndEnableAsync('sample');

        Bus::assertDispatched(ExtensionInstallDepsJob::class, function ($job) use ($opId) {
            return $job->extensionId === 'sample' && $job->operationId === $opId;
        });

        $tracker = $this->app->make(TrackerService::class);
        $op = $tracker->getOperation($opId);
        $this->assertTrue($op['context']['auto_enable']);
    }
}
