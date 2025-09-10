<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Tests;

use Gigabait93\Extensions\Providers\ExtensionsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ExtensionsServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $base = __DIR__ . '/fixtures/extensions';

        $app['config']->set('extensions.paths', [
            'Modules' => $base . '/Modules',
            'Themes' => $base . '/Themes',
        ]);
        $app['config']->set('extensions.activator', \Gigabait93\Extensions\Activators\FileActivator::class);
        $app['config']->set('extensions.json_file', $base . '/statuses.json');
        $app['config']->set('extensions.switch_types', ['Themes']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        @unlink(__DIR__ . '/fixtures/extensions/statuses.json');
    }
}
