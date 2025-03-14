<?php

namespace Gigabait93\Extensions\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Gigabait93\Extensions\Services\ExtensionManager;
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
        // Merge package configuration with application's configuration.
        $this->mergeConfigFrom(__DIR__ . '/../Config/extensions.php', 'extensions');
    }

    public function boot(): void
    {
        // Publish the configuration file.
        $this->publishes([
            __DIR__ . '/../Config/extensions.php' => config_path('extensions.php'),
        ], 'config');

        // Register console commands when running in the console.
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                ListCommand::class,
                EnableCommand::class,
                DisableCommand::class,
                DeleteCommand::class,
                DiscoverCommand::class,
            ]);

            // Schedule the extension:discover command to run every five minutes.
            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('extension:discover')->everyFiveMinutes();
            });
        }

        // Dynamically load active extensions.
        $extensionManager = new ExtensionManager();
        $activeExtensions = $extensionManager->getExtensions()->filter(function ($e) {
            return $e->active ?? $e['active'];
        });

        foreach ($activeExtensions as $extension) {
            $extensionName = $extension->name ?? $extension['name'];
            $registered = false;
            // Loop through all configured extension paths.
            foreach (config('extensions.extensions_paths') as $path) {
                $extensionJsonPath = $path . DIRECTORY_SEPARATOR . $extensionName . DIRECTORY_SEPARATOR . 'extension.json';
                if (!file_exists($extensionJsonPath)) {
                    continue;
                }
                $jsonContent = file_get_contents($extensionJsonPath);
                $extensionConfig = json_decode($jsonContent, true);
                if (empty($extensionConfig['provider'])) {
                    continue;
                }
                $providerClass = $extensionConfig['provider'];
                if (class_exists($providerClass)) {
                    $this->app->register($providerClass);
                    $registered = true;
                    break;
                }
            }
        }
    }
}
