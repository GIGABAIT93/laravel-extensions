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
     * @method static ?\Gigabait93\Extensions\Entities\Extension get(string $id)
     * @method static ?\Gigabait93\Extensions\Entities\Extension find(string $idOrName)
     * @method static ?\Gigabait93\Extensions\Entities\Extension findByName(string $name)
     * @method static ?\Gigabait93\Extensions\Entities\Extension findByNameAndType(string $name, ?string $type = null)
     * @method static ?\Gigabait93\Extensions\Entities\Extension one(string $idOrName, ?string $type = null)
     * @method static \Gigabait93\Extensions\Support\OpResult discover()
     * @method static void reloadActive()
     * @method static \Gigabait93\Extensions\Support\OpResult enable(string $id)
     * @method static \Gigabait93\Extensions\Support\OpResult disable(string $id)
     * @method static \Gigabait93\Extensions\Support\OpResult delete(string $id)
     * @method static \Gigabait93\Extensions\Support\OpResult install(string $id)
     * @method static \Gigabait93\Extensions\Support\OpResult installDependencies(string $id)
     * @method static \Gigabait93\Extensions\Support\OpResult installAndEnable(string $id)
     * @method static string enableAsync(string $id, bool $autoInstallDeps = false)
     * @method static string disableAsync(string $id)
     * @method static string installDepsAsync(string $id, bool $autoEnable = false)
     * @method static string installAndEnableAsync(string $id)
     * @method static string enableQueued(string $id, ?string $onSuccess = null, ?string $onFailure = null)
     * @method static string disableQueued(string $id, ?string $onSuccess = null, ?string $onFailure = null)
     * @method static string installDepsQueued(string $id, ?string $onSuccess = null, ?string $onFailure = null)
     * @method static bool migrate(string $id)
     * @method static array missingPackages(string $id)
     * @method static array missingExtensions(string $id)
     * @method static array requiredByEnabled(string $id)
     * @method static bool isProtected(string $id)
     * @method static bool isSwitchType(string $id)
     * @method static bool hasComposerFile(string $id)
     * @method static \\Gigabait93\\Extensions\\Support\\OpResult validateExtensionExists(string $id)
     * @method static \\Gigabait93\\Extensions\\Support\\OpResult validateCanEnable(string $id)
     * @method static \\Gigabait93\\Extensions\\Support\\OpResult validateCanDisable(string $id)
     * @method static \\Gigabait93\\Extensions\\Support\\OpResult validateCanDelete(string $id)
     * @method static array typedPaths()
     * @method static ?string pathForType(string $type)
     * @method static array types()
     */
    protected static function getFacadeAccessor(): string
    {
        return 'extensions';
    }
}
