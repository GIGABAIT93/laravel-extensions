<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Tests;

use Gigabait93\Extensions\Support\ScaffoldConfig;

class ScaffoldConfigTest extends TestCase
{
    public function test_stubs_path_defaults_to_package(): void
    {
        config(['extensions.stubs.path' => null]);
        $expected = base_path('vendor/gigabait93/laravel-extensions/stubs/Extension');
        $this->assertSame($expected, ScaffoldConfig::stubsPath());
    }

    public function test_stubs_path_can_be_overridden(): void
    {
        config(['extensions.stubs.path' => '/tmp/stubs']);
        $this->assertSame('/tmp/stubs', ScaffoldConfig::stubsPath());
    }
}
