<?php

namespace Modules\Sync\Providers;

use Illuminate\Support\ServiceProvider;

class SyncServiceProvider extends ServiceProvider
{
    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        // Las migraciones del módulo viven en database/migrations/tenant
        // (ruta canónica de tablas de tenant en este proyecto).

        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'sync');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(RouteServiceProvider::class);

        $this->commands([
            \Modules\Sync\Console\ProcessVoidsCommand::class,
        ]);
    }
}
