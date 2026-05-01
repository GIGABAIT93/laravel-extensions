<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Tests;

use Gigabait93\Extensions\Activators\FileActivator;
use Gigabait93\Extensions\Providers\ExtensionsServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Scheduled\Providers\ScheduledServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class ExtensionProviderBootTimingTest extends Orchestra
{
    private string $statusFile;

    private string $registryCacheFile;

    protected function setUp(): void
    {
        $this->statusFile = __DIR__.'/fixtures/extensions/scheduled-statuses.json';
        $this->registryCacheFile = __DIR__.'/fixtures/extensions/scheduled-registry-cache.json';

        require_once __DIR__.'/fixtures/scheduled-extension/Modules/Scheduled/Providers/ScheduledServiceProvider.php';

        @unlink($this->statusFile);
        @unlink($this->registryCacheFile);

        ScheduledServiceProvider::$bootCalls = 0;
        ScheduledServiceProvider::$scheduleCallbacks = 0;

        parent::setUp();
    }

    protected function tearDown(): void
    {
        @unlink($this->statusFile);
        @unlink($this->registryCacheFile);

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [ExtensionsServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $base = __DIR__.'/fixtures/scheduled-extension';

        $app['config']->set('extensions.paths', [
            'Modules' => $base.'/Modules',
            'Themes' => $base.'/Themes',
        ]);
        $app['config']->set('extensions.activator', FileActivator::class);
        $app['config']->set('extensions.json_file', $this->statusFile);
        $app['config']->set('extensions.registry.cache.enabled', true);
        $app['config']->set('extensions.registry.cache.path', $this->registryCacheFile);
        $app['config']->set('extensions.registry.scan.recursive_fallback', true);

        file_put_contents($this->statusFile, json_encode([
            'scheduled' => [
                'enabled' => true,
                'type' => 'Modules',
            ],
        ], JSON_THROW_ON_ERROR));
    }

    public function test_enabled_extension_providers_boot_without_duplicate_booted_callbacks(): void
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $scheduledEvents = array_filter(
            $schedule->events(),
            static fn ($event): bool => $event->description === 'scheduled-fixture'
        );

        $this->assertSame(1, ScheduledServiceProvider::$bootCalls);
        $this->assertSame(1, ScheduledServiceProvider::$scheduleCallbacks);
        $this->assertCount(1, $scheduledEvents);
    }
}
