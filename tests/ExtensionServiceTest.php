<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Tests;

use Gigabait93\Extensions\Contracts\ActivatorContract;
use Gigabait93\Extensions\Services\BootstrapService;
use Gigabait93\Extensions\Services\ExtensionService;
use Illuminate\Support\Facades\File;

class ExtensionServiceTest extends TestCase
{
    /**
     * Holds backup of Alpha theme for restore test.
     */
    private static string $alphaBackup;

    public function test_discovery_finds_all_extensions_with_providers(): void
    {
        $service = $this->app->make(ExtensionService::class);
        $service->discover();

        $ids = $service->all()->pluck('id')->all();
        $this->assertEqualsCanonicalizing(['sample', 'base', 'addon', 'alpha', 'beta'], $ids);

        foreach ($service->all() as $ext) {
            $this->assertNotEmpty($ext->provider);
        }
    }

    public function test_enable_marks_extension_as_enabled(): void
    {
        $service = $this->app->make(ExtensionService::class);
        $service->enable('sample');

        $this->assertTrue($service->get('sample')?->isEnabled());
    }

    public function test_disable_marks_extension_as_disabled(): void
    {
        $service = $this->app->make(ExtensionService::class);
        $service->enable('sample');
        $service->disable('sample');

        $this->assertFalse($service->get('sample')?->isEnabled());
    }

    public function test_enabling_theme_disables_other_theme(): void
    {
        $service = $this->app->make(ExtensionService::class);
        $service->enable('alpha');
        $this->assertTrue($service->get('alpha')?->isEnabled());

        $service->enable('beta');

        $this->assertTrue($service->get('beta')?->isEnabled());
        $this->assertFalse($service->get('alpha')?->isEnabled());
    }

    public function test_protected_extension_cannot_be_disabled(): void
    {
        config(['extensions.protected' => ['Modules' => 'Sample']]);

        $service = $this->app->make(ExtensionService::class);
        $service->enable('sample');
        $result = $service->disable('sample');

        $this->assertFalse($result->isSuccess());
        $this->assertSame('protected', $result->errorCode);
        $this->assertTrue($service->get('sample')?->isEnabled());
    }

    public function test_protected_extension_cannot_be_deleted(): void
    {
        config(['extensions.protected' => ['Modules' => 'Sample']]);

        $service = $this->app->make(ExtensionService::class);
        $service->enable('sample');
        $result = $service->delete('sample');

        $this->assertFalse($result->isSuccess());
        $this->assertSame('protected', $result->errorCode);
        $this->assertDirectoryExists(__DIR__ . '/fixtures/extensions/Modules/Sample');
    }

    public function test_enable_records_type_in_activator(): void
    {
        $service = $this->app->make(ExtensionService::class);
        $service->enable('sample');

        $activator = $this->app->make(ActivatorContract::class);
        $statuses = $activator->statuses();

        $this->assertSame('Modules', $statuses['sample']['type']);
    }

    public function test_enabling_extension_with_missing_dependency_fails(): void
    {
        $service = $this->app->make(ExtensionService::class);
        $res = $service->enable('addon');

        $this->assertFalse($res->isSuccess());
        $this->assertSame('missing_extensions', $res->errorCode);
    }

    public function test_enabling_extension_with_dependency_succeeds(): void
    {
        $service = $this->app->make(ExtensionService::class);
        $service->enable('base');
        $res = $service->enable('addon');

        $this->assertTrue($res->isSuccess());
        $this->assertTrue($service->get('addon')?->isEnabled());
    }

    public function test_disabling_required_extension_fails(): void
    {
        $service = $this->app->make(ExtensionService::class);
        $service->enable('base');
        $service->enable('addon');
        $res = $service->disable('base');

        $this->assertFalse($res->isSuccess());
        $this->assertSame('required_by', $res->errorCode);
        $this->assertTrue($service->get('base')?->isEnabled());
    }

    public function test_all_by_type_filters_extensions(): void
    {
        $service = $this->app->make(ExtensionService::class);
        $service->discover();

        $themes = $service->allByType('themes')->pluck('id')->all();
        $this->assertEqualsCanonicalizing(['alpha', 'beta'], $themes);

        $modules = $service->allByType('MODULES')->pluck('id')->all();
        $this->assertEqualsCanonicalizing(['sample', 'base', 'addon'], $modules);
    }

    public function test_enabled_and_disabled_by_type(): void
    {
        $service = $this->app->make(ExtensionService::class);
        $service->discover();
        $service->enable('alpha');

        $enabled = $service->enabledByType('themes')->pluck('id')->all();
        $this->assertSame(['alpha'], $enabled);

        $disabled = $service->disabledByType('themes')->pluck('id')->all();
        $this->assertSame(['beta'], $disabled);
    }

    public function test_find_by_name_and_type_is_case_insensitive(): void
    {
        $service = $this->app->make(ExtensionService::class);
        $service->discover();

        $ext = $service->findByNameAndType('ALPHA', 'THEMES');
        $this->assertNotNull($ext);
        $this->assertSame('alpha', $ext->id());

        $none = $service->findByNameAndType('alpha', 'modules');
        $this->assertNull($none);
    }

    public function test_discover_prunes_missing_activator_entries(): void
    {
        $file = __DIR__ . '/fixtures/extensions/statuses.json';
        file_put_contents($file, json_encode(['ghost' => ['enabled' => true, 'type' => 'Modules']]));

        $service = $this->app->make(ExtensionService::class);
        $service->discover();

        $activator = $this->app->make(ActivatorContract::class);
        $this->assertArrayNotHasKey('ghost', $activator->statuses());
    }

    public function test_reload_active_triggers_bootstrapper_warmup(): void
    {
        $mock = $this->createMock(BootstrapService::class);
        $mock->expects($this->once())->method('warmup');
        $this->app->instance(BootstrapService::class, $mock);

        $service = $this->app->make(ExtensionService::class);
        $service->reloadActive();
    }

    public function test_install_and_enable_combines_operations(): void
    {
        $service = $this->app->make(ExtensionService::class);
        $res = $service->installAndEnable('sample');

        $this->assertTrue($res->isSuccess());
        $this->assertTrue($service->get('sample')?->isEnabled());
    }

    public function test_install_and_enable_propagates_enable_failure(): void
    {
        $service = $this->app->make(ExtensionService::class);
        $res = $service->installAndEnable('addon');

        $this->assertFalse($res->isSuccess());
        $this->assertSame('missing_extensions', $res->errorCode);
        $this->assertFalse($service->get('addon')?->isEnabled());
    }

    public function test_install_installs_dependencies_and_runs_migrations(): void
    {
        $service = $this->app->make(ExtensionService::class);
        $res = $service->install('sample');

        $this->assertTrue($res->isSuccess());
        $this->assertFalse($service->get('sample')?->isEnabled()); // Should NOT be enabled
        $this->assertArrayHasKey('migrations_run', $res->data);
    }

    public function test_install_fails_with_missing_extension(): void
    {
        $service = $this->app->make(ExtensionService::class);
        $res = $service->install('nonexistent');

        $this->assertFalse($res->isSuccess());
        $this->assertSame('not_found', $res->errorCode);
    }

    public function test_install_propagates_dependency_failure(): void
    {
        $service = $this->app->make(ExtensionService::class);
        $res = $service->install('addon'); // addon has missing extension dependencies

        $this->assertFalse($res->isSuccess());
        $this->assertSame('missing_extensions', $res->errorCode);
    }

    public function test_protected_switch_extension_can_disable_but_not_delete(): void
    {
        config(['extensions.protected' => ['Themes' => 'Alpha']]);

        $service = $this->app->make(ExtensionService::class);
        $service->enable('alpha');
        $disable = $service->disable('alpha');
        $this->assertTrue($disable->isSuccess());

        $delete = $service->delete('alpha');
        $this->assertFalse($delete->isSuccess());
        $this->assertSame('protected', $delete->errorCode);
        $this->assertDirectoryExists(__DIR__ . '/fixtures/extensions/Themes/Alpha');
    }

    public function test_z_unprotected_switch_extension_can_be_deleted(): void
    {
        self::$alphaBackup = sys_get_temp_dir() . '/alpha_' . uniqid();
        File::copyDirectory(__DIR__ . '/fixtures/extensions/Themes/Alpha', self::$alphaBackup);

        $service = $this->app->make(ExtensionService::class);
        $service->enable('alpha');
        $service->disable('alpha');

        $delete = $service->delete('alpha');
        $this->assertTrue($delete->isSuccess());
        $this->assertDirectoryDoesNotExist(__DIR__ . '/fixtures/extensions/Themes/Alpha');
    }

    public function test_zz_restore_deleted_extension(): void
    {
        File::copyDirectory(self::$alphaBackup, __DIR__ . '/fixtures/extensions/Themes/Alpha');

        $service = $this->app->make(ExtensionService::class);
        $service->discover();

        $this->assertNotNull($service->get('alpha'));
        $this->assertDirectoryExists(__DIR__ . '/fixtures/extensions/Themes/Alpha');

        File::deleteDirectory(self::$alphaBackup);
    }
}
