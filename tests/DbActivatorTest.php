<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Tests;

use Gigabait93\Extensions\Activators\DbActivator;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

class DbActivatorTest extends Orchestra
{
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function test_enable_and_disable_persist_type(): void
    {
        $activator = new DbActivator();

        $activator->enable('demo', 'Themes');
        $this->assertTrue($activator->isEnabled('demo'));
        $data = $activator->statuses();
        $this->assertSame('Themes', $data['demo']['type']);

        $activator->disable('demo', 'Themes');
        $data = $activator->statuses();
        $this->assertFalse($data['demo']['enabled']);
        $this->assertSame('Themes', $data['demo']['type']);
    }

    public function test_status_reads_are_empty_when_extensions_table_is_missing(): void
    {
        Schema::dropIfExists('extensions');

        $activator = new DbActivator();

        $this->assertFalse($activator->isEnabled('demo'));
        $this->assertSame([], $activator->statuses());
    }
}
