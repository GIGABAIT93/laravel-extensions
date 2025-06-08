<?php

namespace Gigabait93\Extensions\Providers;

use Gigabait93\Extensions\Contracts\ActivatorInterface;
use Gigabait93\Extensions\Services\ExtensionsService;
use Gigabait93\Extensions\Entities\Extension;
use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class ExtensionsServiceProvider extends ServiceProvider
{
    /** Register package bindings */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/extensions.php', 'extensions');

        // Activator
        $this->app->bind(ActivatorInterface::class, fn($app) => $app->make($app['config']->get('extensions.activator')));

        // ExtensionsService
        $this->app->singleton(ExtensionsService::class, function ($app) {
            return new ExtensionsService($app->make(ActivatorInterface::class), config('extensions.paths', []));
        });
        $this->app->alias(ExtensionsService::class, 'extensions');
    }

    /** Bootstrap package features */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->publishes([
            __DIR__ . '/../../config/extensions.php' => config_path('extensions.php'),
            __DIR__ . '/../../database/migrations' => database_path('migrations'),
        ], 'extensions');

        // Artisan commands
        $this->registerCommands();

        // Schedule Detection of New Expansions
        $this->app->booted(function () {
            $this->app->make(Schedule::class)
                ->command('extension:discover')
                ->everyFiveMinutes();
        });

        // Hard order loading active extensions
        $extensions = $this->app->make(ExtensionsService::class)
            ->all()
            ->filter(fn(Extension $e) => $e->isActive());

        $order = $this->app['config']->get('extensions.load_order', []);
        $sorted = $extensions->sortBy(function (Extension $e) use ($order) {
            $pos = array_search($e->getName(), $order, true);
            return $pos === false ? PHP_INT_MAX : $pos;
        });

        // Registration of providers in order
        $sorted->each(fn(Extension $e) => $this->registerExtensionProvider($e));
    }

    /**
     * If in expression.json the Provider is specified - register it
     */
    protected function registerExtensionProvider(Extension $e): void
    {
        $prov = $e->getMeta()['provider'] ?? null;
        if ($prov && class_exists($prov)) {
            $this->app->register($prov);
        }
    }

    protected function registerCommands(): void
    {
        $this->commands([
            \Gigabait93\Extensions\Commands\InstallCommand::class,
            \Gigabait93\Extensions\Commands\ListCommand::class,
            \Gigabait93\Extensions\Commands\EnableCommand::class,
            \Gigabait93\Extensions\Commands\DisableCommand::class,
            \Gigabait93\Extensions\Commands\DeleteCommand::class,
            \Gigabait93\Extensions\Commands\MigrateCommand::class,
            \Gigabait93\Extensions\Commands\DiscoverCommand::class,
            \Gigabait93\Extensions\Commands\MekeCommand::class,
        ]);
    }
}
