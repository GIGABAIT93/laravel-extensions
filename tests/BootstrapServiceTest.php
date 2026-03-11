<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Tests;

use Gigabait93\Extensions\Contracts\ActivatorContract;
use Gigabait93\Extensions\Services\AutoloadService;
use Gigabait93\Extensions\Services\BootstrapService;
use Gigabait93\Extensions\Services\RegistryService;
use Gigabait93\Extensions\Support\ManifestValue;

class BootstrapServiceTest extends TestCase
{
    public function test_warmup_uses_cached_registry_without_rediscovery(): void
    {
        $manifest = new ManifestValue(
            id: 'sample',
            name: 'Sample',
            provider: 'Sample\\Providers\\SampleServiceProvider',
            path: __DIR__ . '/fixtures/extensions/Modules/Sample',
            type: 'Modules'
        );

        $registry = $this->getMockBuilder(RegistryService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['discover', 'find'])
            ->getMock();
        $registry->expects($this->never())->method('discover');
        $registry->expects($this->once())
            ->method('find')
            ->with('sample')
            ->willReturn($manifest);

        $activator = $this->createMock(ActivatorContract::class);
        $activator->expects($this->once())
            ->method('statuses')
            ->willReturn([
                'sample' => ['enabled' => true, 'type' => 'Modules'],
            ]);

        $bootstrapper = $this->getMockBuilder(BootstrapService::class)
            ->setConstructorArgs([
                $this->app,
                $this->createMock(AutoloadService::class),
                $registry,
                $activator,
            ])
            ->onlyMethods(['registerProvider'])
            ->getMock();

        $bootstrapper->expects($this->once())
            ->method('registerProvider')
            ->with($manifest);

        $bootstrapper->warmup();
        $bootstrapper->warmup();
    }
}
