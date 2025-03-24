<?php

namespace Gigabait93\Extensions\Providers;

use Gigabait93\Extensions\Entities\Extension;
use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Gigabait93\Extensions\Services\Extensions;
use Gigabait93\Extensions\Commands\InstallCommand;
use Gigabait93\Extensions\Commands\ListCommand;
use Gigabait93\Extensions\Commands\EnableCommand;
use Gigabait93\Extensions\Commands\DisableCommand;
use Gigabait93\Extensions\Commands\DeleteCommand;
use Gigabait93\Extensions\Commands\DiscoverCommand;

class ExtensionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../Config/extensions.php', 'extensions');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/extensions.php' => config_path('extensions.php'),
        ], 'config');

        $this->commands([
            InstallCommand::class,
            ListCommand::class,
            EnableCommand::class,
            DisableCommand::class,
            DeleteCommand::class,
            DiscoverCommand::class,
        ]);

        $this->app->booted(function () {
            $this->app->make(Schedule::class)
                ->command('extension:discover')
                ->everyFiveMinutes();
        });

        $extensionManager = new Extensions();
        $activeExtensions = $extensionManager->all()->filter(fn(Extension $extension) => $extension->isActive());

        $activeExtensions->each(function (Extension $extension) {
            $this->registerExtensionProvider($extension);
        });
    }

    /**
     * Registers an extension provider if it exists.
     */
    protected function registerExtensionProvider(Extension $extension): void
    {
        $extensionName = $extension->getName();
        foreach (config('extensions.extensions_paths') as $path) {
            $configPath = $path . DIRECTORY_SEPARATOR . $extensionName . DIRECTORY_SEPARATOR . 'extension.json';
            if (!file_exists($configPath)) {
                continue;
            }
            $configData = json_decode(file_get_contents($configPath), true);
            if (empty($configData['provider'])) {
                continue;
            }
            $providerClass = $configData['provider'];
            if (class_exists($providerClass)) {
                $this->app->register($providerClass);
                break;
            }
        }
    }
}
