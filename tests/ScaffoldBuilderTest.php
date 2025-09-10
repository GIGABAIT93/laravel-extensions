<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Tests;

use Gigabait93\Extensions\Scaffolding\ExtensionBuilder;
use Illuminate\Support\Facades\File;

class ScaffoldBuilderTest extends TestCase
{
    public function test_generates_selected_and_mandatory_groups(): void
    {
        $tmp = sys_get_temp_dir() . '/ext_scaffold_' . uniqid();
        File::makeDirectory($tmp, 0o777, true);

        try {
            $builder = $this->app->make(ExtensionBuilder::class);
            $builder->withType('Modules')
                ->withName('Blog')
                ->withBasePath($tmp)
                ->withStubsPath(dirname(__DIR__) . '/stubs/Extension')
                ->withGroups(['config'])
                ->withForce(true)
                ->build();

            $this->assertFileExists($tmp . '/Blog/extension.json');
            $this->assertFileExists($tmp . '/Blog/composer.json');
            $this->assertFileExists($tmp . '/Blog/Providers/BlogServiceProvider.php');
            $this->assertFileExists($tmp . '/Blog/Config/config.php');
            $this->assertDirectoryDoesNotExist($tmp . '/Blog/Database');
        } finally {
            File::deleteDirectory($tmp);
        }
    }

    public function test_replaces_placeholders_in_files(): void
    {
        $tmp = sys_get_temp_dir() . '/ext_scaffold_' . uniqid();
        File::makeDirectory($tmp, 0o777, true);

        try {
            $builder = $this->app->make(ExtensionBuilder::class);
            $builder->withType('Modules')
                ->withName('Blog')
                ->withBasePath($tmp)
                ->withStubsPath(dirname(__DIR__) . '/stubs/Extension')
                ->withGroups([])
                ->withForce(true)
                ->build();

            $ext = json_decode(File::get($tmp . '/Blog/extension.json'), true);
            $this->assertSame('blog', $ext['id']);
            $this->assertSame('Modules\\Blog\\Providers\\BlogServiceProvider', $ext['provider']);

            $composer = json_decode(File::get($tmp . '/Blog/composer.json'), true);
            $this->assertSame('extension/blog', $composer['name']);
            $this->assertSame('Extension Blog', $composer['description']);
        } finally {
            File::deleteDirectory($tmp);
        }
    }

    public function test_build_returns_metadata(): void
    {
        $tmp = sys_get_temp_dir() . '/ext_scaffold_' . uniqid();
        File::makeDirectory($tmp, 0o777, true);

        try {
            $builder = $this->app->make(ExtensionBuilder::class);
            $result = $builder->withType('Modules')
                ->withName('Blog')
                ->withBasePath($tmp)
                ->withStubsPath(dirname(__DIR__) . '/stubs/Extension')
                ->withGroups([])
                ->withForce(true)
                ->build();

            $this->assertSame($tmp . DIRECTORY_SEPARATOR . 'Blog', $result['path']);
            $this->assertSame('Modules\\Blog', $result['namespace']);
            $this->assertContains('extension.json', $result['files']);
        } finally {
            File::deleteDirectory($tmp);
        }
    }

    public function test_get_available_types(): void
    {
        $types = ExtensionBuilder::getAvailableTypes();
        $this->assertIsArray($types);
        $this->assertContains('Modules', $types);
        $this->assertContains('Themes', $types);
        $this->assertCount(2, $types);
    }

    public function test_get_available_stub_groups(): void
    {
        $stubsPath = dirname(__DIR__) . '/stubs/Extension';
        $groups = ExtensionBuilder::getAvailableStubGroups($stubsPath);

        $this->assertIsArray($groups);
        $this->assertContains('extension', $groups);
        $this->assertContains('composer', $groups);
        $this->assertContains('providers', $groups);
    }

    public function test_get_available_stub_groups_with_invalid_path(): void
    {
        $invalidPath = '/path/that/does/not/exist';
        $groups = ExtensionBuilder::getAvailableStubGroups($invalidPath);

        $this->assertIsArray($groups);
        $this->assertEmpty($groups);
    }
}
