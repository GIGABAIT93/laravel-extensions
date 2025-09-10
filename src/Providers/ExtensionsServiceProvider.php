<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Providers;

use Gigabait93\Extensions\Console\Commands\DeleteCommand;
use Gigabait93\Extensions\Console\Commands\DisableCommand;
use Gigabait93\Extensions\Console\Commands\DiscoverCommand;
use Gigabait93\Extensions\Console\Commands\EnableCommand;
use Gigabait93\Extensions\Console\Commands\InstallDepsCommand;
use Gigabait93\Extensions\Console\Commands\ListCommand;
use Gigabait93\Extensions\Console\Commands\MakeCommand;
use Gigabait93\Extensions\Console\Commands\MigrateCommand;
use Gigabait93\Extensions\Console\Commands\PublishCommand;
use Gigabait93\Extensions\Console\Commands\ReloadCommand;
use Gigabait93\Extensions\Console\Commands\SearchCommand;
use Gigabait93\Extensions\Console\Commands\StatsCommand;
use Gigabait93\Extensions\Console\Commands\BulkCommand;
use Gigabait93\Extensions\Contracts\ActivatorContract;
use Gigabait93\Extensions\Services\AutoloadService;
use Gigabait93\Extensions\Services\BootstrapService;
use Gigabait93\Extensions\Services\DependencyService;
use Gigabait93\Extensions\Services\ExtensionService;
use Gigabait93\Extensions\Services\MigratorService;
use Gigabait93\Extensions\Services\RegistryService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class ExtensionsServiceProvider extends ServiceProvider
{
    /** Register package bindings */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/extensions.php', 'extensions');

        // Core services (singletons)
        $this->app->singleton(AutoloadService::class, fn () => new AutoloadService());
        $this->app->singleton(RegistryService::class, function ($app) {
            return new RegistryService(
                config('extensions.paths', []),
                base_path(),
            );
        });
        $this->app->singleton(BootstrapService::class, function ($app) {
            return new BootstrapService(
                $app,
                $app->make(AutoloadService::class),
                $app->make(RegistryService::class),
                $app->make(ActivatorContract::class),
            );
        });
        $this->app->singleton(DependencyService::class, fn () => new DependencyService());
        $this->app->singleton(MigratorService::class, fn ($app) => new MigratorService($app));

        // Activator binding (configurable class)
        $this->app->bind(ActivatorContract::class, fn ($app) => $app->make(config('extensions.activator')));

        // Facade accessor binding for Extensions facade
        $this->app->singleton('extensions', function ($app) {
            return new ExtensionService(
                $app->make(ActivatorContract::class),
                $app->make(RegistryService::class),
                $app->make(BootstrapService::class),
                $app->make(DependencyService::class),
                $app->make(MigratorService::class),
                $app,
            );
        });
    }

    /** Bootstrap package features */
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../../lang', 'extensions');
        $this->publishes([
            __DIR__ . '/../../lang' => lang_path('vendor/extensions'),
        ], 'extensions-lang');

        $this->publishes([
            __DIR__ . '/../../database/migrations' => database_path('migrations'),
        ], 'extensions-migrations');

        $this->publishes([
            __DIR__ . '/../../config/extensions.php' => config_path('extensions.php'),
        ], 'extensions-config');

        $this->publishes([
            __DIR__ . '/../../stubs/Extension' => base_path('stubs/Extension'),
        ], 'extensions-stubs');

        $this->publishes([
            __DIR__ . '/../../config/extensions.php' => config_path('extensions.php'),
            __DIR__ . '/../../database/migrations' => database_path('migrations'),
            __DIR__ . '/../../lang' => lang_path('vendor/extensions'),
            __DIR__ . '/../../stubs/Extension' => base_path('stubs/Extension'),
        ], 'extensions');

        // Artisan commands
        $this->registerCommands();

        // Schedule periodic rediscovery without relying on a missing Artisan command
        $this->app->booted(function () {
            $this->app->make(Schedule::class)
                ->call(function () {
                    app(RegistryService::class)->discover();
                })
                ->everyFiveMinutes();
        });

        // Initialize extensions runtime (discover + bootstrap) — lightweight warmup
        $this->app->booted(function () {
            $bootstrapper = $this->app->make(BootstrapService::class);
            $bootstrapper->warmup();
        });

    }

    protected function registerCommands(): void
    {
        $this->commands([
            ListCommand::class,
            DiscoverCommand::class,
            EnableCommand::class,
            DisableCommand::class,
            InstallDepsCommand::class,
            ReloadCommand::class,
            MakeCommand::class,
            PublishCommand::class,
            DeleteCommand::class,
            MigrateCommand::class,
            StatsCommand::class,
            SearchCommand::class,
            BulkCommand::class,
        ]);
    }
}
