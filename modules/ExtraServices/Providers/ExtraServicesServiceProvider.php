<?php

namespace Modules\ExtraServices\Providers;

use Illuminate\Support\ServiceProvider;

class ExtraServicesServiceProvider extends ServiceProvider
{
    /**
     * Ejecuta los servicios del módulo.
     * 
     * @return void
     */
    public function boot(): void
    {
        $this->registerConfig();
        $this->registerViews();
        $this->registerCommands();
    }

    /**
     * Registra los servicios del módulo.
     * 
     * @return void
     */
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * Registra los comandos Artisan del módulo.
     */
    protected function registerCommands(): void
    {
        $this->commands([
            \Modules\ExtraServices\Console\Commands\SetUrlServiceApidocsCommand::class,
            \Modules\ExtraServices\Console\Commands\SetUrlObtainApidocsCommand::class,
        ]);
    }

    /**
     * Registra la configuración del módulo.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../Config/config.php' => config_path('extraservices.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php', 'extraservices'
        );
    }

    /**
     * Registra las vistas del módulo.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/extraservices');

        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ],'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path . '/modules/extraservices';
        }, \Config::get('view.paths')), [$sourcePath]), 'extraservices');
    }

    /**
     * Registra las traducciones del módulo.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/extraservices');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'extraservices');
        } else {
            $this->loadTranslationsFrom(__DIR__ .'/../Resources/lang', 'extraservices');
        }
    }

    /**
     * Register an additional directory of factories.
     *
     * @return void
     */
    public function registerFactories()
    {
        if (! app()->environment('production') && $this->app->runningInConsole()) {
            app(Factory::class)->load(__DIR__ . '/../Database/factories');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}