<?php

namespace Gigabait93\Extensions\Providers;

use Gigabait93\Extensions\Contracts\ActivatorInterface;
use Gigabait93\Extensions\Services\Extensions;
use Gigabait93\Extensions\Entities\Extension;
use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class ExtensionsServiceProvider extends ServiceProvider
{
    /** Register package bindings */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/extensions.php',
            'extensions'
        );

        // Choosing the realization of the activator from the config
        $this->app->bind(ActivatorInterface::class, function ($app) {
            return $app->make(
                $app['config']->get('extensions.activator')
            );
        });

        // EXTENSIONS SERVICE SUPPLY Like Singleton
        $this->app->singleton(Extensions::class, function ($app) {
            return new Extensions(
                $app->make(ActivatorInterface::class),
                $app['config']->get('extensions.extensions_paths', [])
            );
        });
    }

    /** Bootstrap package features */
    public function boot(): void
    {
        // Migration of the package
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Publication of Configers and Migrations
        $this->publishes([
            __DIR__ . '/../../config/extensions.php' => config_path('extensions.php'),
        ], 'config');
        $this->publishes([
            __DIR__ . '/../../database/migrations' => database_path('migrations'),
        ], 'migrations');

        // Artisan commands
        $this->commands([
            \Gigabait93\Extensions\Commands\InstallCommand::class,
            \Gigabait93\Extensions\Commands\ListCommand::class,
            \Gigabait93\Extensions\Commands\EnableCommand::class,
            \Gigabait93\Extensions\Commands\DisableCommand::class,
            \Gigabait93\Extensions\Commands\DeleteCommand::class,
            \Gigabait93\Extensions\Commands\DiscoverCommand::class,
        ]);

        // Schedule Detection of New Expansions
        $this->app->booted(function () {
            $this->app->make(Schedule::class)
                ->command('extension:discover')
                ->everyFiveMinutes();
        });

        // Hard order loading active extensions
        $extensions = $this->app->make(Extensions::class)
            ->all()
            ->filter(fn(Extension $e) => $e->isActive());

        $order  = $this->app['config']->get('extensions.load_order', []);
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
    protected function registerExtensionProvider(Extension $extension): void
    {
        foreach (config('extensions.extensions_paths', []) as $path) {
            $file = "{$path}/{$extension->getName()}/extension.json";

            if (! file_exists($file)) {
                continue;
            }

            $data = json_decode(file_get_contents($file), true);
            if (! empty($data['provider']) && class_exists($data['provider'])) {
                $this->app->register($data['provider']);
                break;
            }
        }
    }
}
