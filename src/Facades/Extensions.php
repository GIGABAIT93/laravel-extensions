<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Facades;

use Illuminate\Support\Facades\Facade;

class Extensions extends Facade
{
    /**
     * @method static \Illuminate\Support\Collection all()
     * @method static \Illuminate\Support\Collection enabled()
     * @method static \Illuminate\Support\Collection disabled()
     * @method static \Illuminate\Support\Collection allByType(?string $type = null)
     * @method static \Illuminate\Support\Collection enabledByType(?string $type = null)
     * @method static \Illuminate\Support\Collection disabledByType(?string $type = null)
     * @method static ?\Gigabait93\Extensions\Entities\ExtensionEntity get(string $id)
     * @method static ?\Gigabait93\Extensions\Entities\ExtensionEntity find(string $idOrName)
     * @method static ?\Gigabait93\Extensions\Entities\ExtensionEntity findByName(string $name)
     * @method static ?\Gigabait93\Extensions\Entities\ExtensionEntity findByNameAndType(string $name, ?string $type = null)
     * @method static ?\Gigabait93\Extensions\Entities\ExtensionEntity one(string $idOrName, ?string $type = null)
     * @method static void discover()
     * @method static void reloadActive()
     * @method static \Gigabait93\Extensions\Support\OpResult enable(string $id)
     * @method static \Gigabait93\Extensions\Support\OpResult disable(string $id)
     * @method static \Gigabait93\Extensions\Support\OpResult delete(string $id)
     * @method static \Gigabait93\Extensions\Support\OpResult installDependencies(string $id)
     * @method static void enableQueued(string $id, ?\Closure $onSuccess = null, ?\Closure $onFailure = null)
     * @method static void disableQueued(string $id, ?\Closure $onSuccess = null, ?\Closure $onFailure = null)
     * @method static void installDepsQueued(string $id, ?\Closure $onSuccess = null, ?\Closure $onFailure = null)
     * @method static bool migrate(string $id)
     * @method static array missingPackages(string $id)
     * @method static array typedPaths()
     * @method static ?string pathForType(string $type)
     * @method static array types()
     */
    protected static function getFacadeAccessor(): string
    {
        return 'extensions';
    }
}
