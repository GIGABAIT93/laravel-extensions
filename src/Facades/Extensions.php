<?php

namespace Gigabait93\Extensions\Facades;

use Illuminate\Support\Collection;
use Gigabait93\Extensions\Entities\Extension;
use Illuminate\Support\Facades\Facade;

/**
 * @see \Gigabait93\Extensions\Services\ExtensionsService
 *
 * @method static string[]                          discover()                                      Scan for new extensions and register them
 * @method static Collection<string, Extension>     all()                                           Get all extensions as entities
 * @method static Extension|null                    get(string $name)                               Get a single extension by name
 * @method static Collection<string, Extension>     active()                                        Get only active extensions
 * @method static Extension|null                    getByName(string $name)                         Alias for get()
 * @method static Collection<string, Extension>     getByType(string $type)                         Find by declared type
 * @method static Collection<string, Extension>     getByPath(string $path)                         Find by filesystem path
 * @method static Collection<string, Extension>     getByNamespace(string $namespace)               Find by base namespace
 * @method static Collection<string, Extension>     getByClass(string $class)                       Find by main class (placeholder)
 * @method static Collection<string, Extension>     getByTypeAndName(string $type, string $name)    Find by both type and name
 * @method static string                            install(string $name, bool $force = false)      Run migrations & seeders
 * @method static string                            enable(string $name)                            Enable an extension
 * @method static string                            disable(string $name)                           Disable an extension
 * @method static string                            delete(string $name)                            Delete the extension directory
 * @method static bool                              hasMigrations(string $name)                     Check if migrations exist
 * @method static bool                              hasSeeders(string $name)                        Check if seeders exist
 *
 */
class Extensions extends Facade
{

    protected static function getFacadeAccessor(): string
    {
        return 'extensions';
    }
}
