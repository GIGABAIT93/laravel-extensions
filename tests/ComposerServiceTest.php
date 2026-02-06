<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Tests;

use Gigabait93\Extensions\Services\ComposerService;
use Gigabait93\Extensions\Support\ManifestValue;
use Illuminate\Support\Facades\Process;

class ComposerServiceTest extends TestCase
{
    public function test_install_dependencies_uses_targeted_composer_update(): void
    {
        Process::fake();

        $service = $this->app->make(ComposerService::class);
        $service->installDependencies(['vendor/foo', 'vendor/bar']);

        Process::assertRan(function ($process, $result) {
            if (!is_string($process->command)) {
                return false;
            }

            return str_contains($process->command, 'composer update')
                && str_contains($process->command, 'vendor/foo')
                && str_contains($process->command, 'vendor/bar')
                && str_contains($process->command, '--with-all-dependencies');
        });
    }

    public function test_extension_composer_included_matches_merge_plugin_patterns(): void
    {
        $tmp = sys_get_temp_dir() . '/composer_' . uniqid() . '.json';
        file_put_contents($tmp, json_encode([
            'extra' => [
                'merge-plugin' => [
                    'include' => ['tests/fixtures/extensions/*/*/composer.json'],
                ],
            ],
            'config' => [
                'allow-plugins' => [
                    'wikimedia/composer-merge-plugin' => true,
                ],
            ],
        ], JSON_PRETTY_PRINT));

        try {
            config(['extensions.composer.root_json' => $tmp]);

            $service = $this->app->make(ComposerService::class);
            $manifest = new ManifestValue(
                id: 'sample',
                name: 'Sample',
                provider: 'Modules\\Sample\\Providers\\SampleServiceProvider',
                path: base_path('tests/fixtures/extensions/Modules/Sample')
            );

            $this->assertTrue($service->isMergePluginAllowed());
            $this->assertTrue($service->hasMergePluginIncludes());
            $this->assertTrue($service->isExtensionComposerIncluded($manifest));
        } finally {
            @unlink($tmp);
        }
    }
}
