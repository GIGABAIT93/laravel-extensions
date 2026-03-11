<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Tests;

use Gigabait93\Extensions\Services\RegistryService;
use Illuminate\Support\Facades\File;

class RegistryServiceCacheTest extends TestCase
{
    public function test_persisted_registry_cache_is_invalidated_when_manifest_changes(): void
    {
        $sandbox = sys_get_temp_dir() . '/extensions_registry_' . uniqid('', true);
        $modulesPath = $sandbox . '/Modules';
        $cachePath = $sandbox . '/registry-cache.json';

        File::copyDirectory(__DIR__ . '/fixtures/extensions/Modules', $modulesPath);

        try {
            $registry = new RegistryService(
                ['Modules' => $modulesPath],
                base_path(),
                true,
                $cachePath,
                true,
            );

            $initial = $registry->find('sample');
            $this->assertNotNull($initial);
            $this->assertFileExists($cachePath);
            $this->assertSame('1.0.0', $initial->version);

            sleep(1);
            $manifestPath = $modulesPath . '/Sample/extension.json';
            $json = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
            $json['version'] = '2.0.0';
            file_put_contents($manifestPath, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            $reloaded = new RegistryService(
                ['Modules' => $modulesPath],
                base_path(),
                true,
                $cachePath,
                true,
            );

            $updated = $reloaded->find('sample');
            $this->assertNotNull($updated);
            $this->assertSame('2.0.0', $updated->version);
        } finally {
            File::deleteDirectory($sandbox);
        }
    }

    public function test_clear_cache_can_forget_persisted_registry_snapshot(): void
    {
        $sandbox = sys_get_temp_dir() . '/extensions_registry_' . uniqid('', true);
        $modulesPath = $sandbox . '/Modules';
        $cachePath = $sandbox . '/registry-cache.json';

        File::copyDirectory(__DIR__ . '/fixtures/extensions/Modules', $modulesPath);

        try {
            $registry = new RegistryService(
                ['Modules' => $modulesPath],
                base_path(),
                true,
                $cachePath,
                true,
            );

            $this->assertNotNull($registry->find('sample'));
            $this->assertFileExists($cachePath);

            $registry->clearCache(true);

            $this->assertFileDoesNotExist($cachePath);
        } finally {
            File::deleteDirectory($sandbox);
        }
    }
}
