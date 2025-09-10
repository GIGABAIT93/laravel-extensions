<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Tests;

use Gigabait93\Extensions\Activators\FileActivator;
use PHPUnit\Framework\TestCase as BaseTestCase;

class FileActivatorTest extends BaseTestCase
{
    public function test_enable_and_disable_persist_type(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'activator_');
        if ($path === false) {
            $this->fail('Failed to create temp file');
        }
        $activator = new FileActivator($path);

        $activator->enable('demo', 'Themes');
        $this->assertTrue($activator->isEnabled('demo'));
        $data = $activator->statuses();
        $this->assertSame('Themes', $data['demo']['type']);

        $activator->disable('demo', 'Themes');
        $data = $activator->statuses();
        $this->assertFalse($data['demo']['enabled']);
        $this->assertSame('Themes', $data['demo']['type']);

        @unlink($path);
    }
}
