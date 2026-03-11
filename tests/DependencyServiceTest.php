<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Tests;

use Gigabait93\Extensions\Services\DependencyService;
use Gigabait93\Extensions\Support\ManifestValue;
use PHPUnit\Framework\TestCase as BaseTestCase;

class DependencyServiceTest extends BaseTestCase
{
    public function test_missing_packages_ignores_platform_requirements(): void
    {
        $service = new DependencyService();

        $manifest = new ManifestValue(
            id: 'demo',
            name: 'Demo',
            provider: 'Modules\\Demo\\Providers\\DemoServiceProvider',
            path: __DIR__,
            requires_packages: [
                'ext-json' => '*',
                'lib-icu' => '*',
                'composer-runtime-api' => '^2.0',
                'vendor/package-that-will-never-exist' => '^1.0',
            ],
        );

        $missing = $service->missingPackages($manifest);

        $this->assertContains('vendor/package-that-will-never-exist', $missing);
        $this->assertNotContains('ext-json', $missing);
        $this->assertNotContains('lib-icu', $missing);
        $this->assertNotContains('composer-runtime-api', $missing);
    }
}
