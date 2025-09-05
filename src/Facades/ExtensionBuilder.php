<?php

namespace Gigabait93\Extensions\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Gigabait93\Extensions\Services\ExtensionBuilder
 *
 * @method static \Gigabait93\Extensions\Services\ExtensionBuilder name(string $name)
 * @method static \Gigabait93\Extensions\Services\ExtensionBuilder in(string $basePath)
 * @method static \Gigabait93\Extensions\Services\ExtensionBuilder stubs(array $groups)
 * @method static \Gigabait93\Extensions\Services\ExtensionBuilder addStub(string $group)
 * @method static \Gigabait93\Extensions\Services\ExtensionBuilder stubRoot(string $path)
 * @method static array paths()
 * @method static array stubGroups()
 * @method static string stubPath()
 * @method static string build(string $name = null, string $basePath = null, array $stubs = null)
 */
class ExtensionBuilder extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'extension.builder';
    }
}
