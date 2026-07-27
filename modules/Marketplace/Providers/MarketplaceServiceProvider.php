<?php

namespace Modules\Marketplace\Providers;

use Illuminate\Cache\Repository;
use Illuminate\Cache\RedisStore;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Modules\Marketplace\Http\Middleware\EnsureMarketplaceEnabled;
use Modules\Marketplace\Http\Middleware\EnsureMarketplaceVisitor;

class MarketplaceServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Marketplace';

    protected string $moduleNameLower = 'marketplace';

    public function boot()
    {
        $this->publishConfig();
        $this->registerViews();
        $this->registerTranslations();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
        $this->registerMiddleware();
        $this->registerCommands();
    }

    /**
     * marketplace:purge — programado en App\Console\Kernel (03:30, América/Lima).
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Marketplace\Console\PurgeCommand::class,
            ]);
        }
    }

    public function register()
    {
        // El merge va en register() (no en boot): así config('marketplace.*')
        // ya existe cuando el RouteServiceProvider mapea las rutas en su boot.
        // Si se hiciera en boot, map() correría antes del merge y el
        // interruptor 'enabled' tomaría el default en vez del valor del .env.
        $this->mergeConfigFrom(
            module_path($this->moduleName, 'Config/config.php'),
            $this->moduleNameLower
        );

        $this->app->register(RouteServiceProvider::class);
        $this->registerCache();
    }

    /**
     * Repositorio de cache propio, con prefijo fijo `mkt`.
     *
     * NO se puede usar Cache::store('redis') aquí: App\Providers\CacheServiceProvider
     * reescribe el prefijo de Redis en cada request con el UUID del tenant
     * resuelto por hostname (y lo deja vacío en el dominio del sistema, o en
     * `facturador_cache` bajo CLI). Los datos del marketplace son de la conexión
     * `system` y no pertenecen a ningún tenant, así que con el store compartido
     * cada contexto escribiría en un espacio de claves distinto y el
     * `INCR mkt:v` no invalidaría lo que lee el público.
     *
     * Con prefijo propio, las claves quedan exactamente como en el plan:
     * mkt:v · mkt:v{n}:settings · mkt:v{n}:feed:{md5} · mkt:v{n}:store:{slug}
     */
    protected function registerCache(): void
    {
        $this->app->singleton('marketplace.cache', function ($app) {
            $store = new RedisStore(
                $app['redis'],
                'mkt',
                $app['config']['cache.stores.redis.connection'] ?? 'default'
            );

            return new Repository($store);
        });
    }

    protected function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app['router'];

        $router->aliasMiddleware('marketplace.enabled', EnsureMarketplaceEnabled::class);
        $router->aliasMiddleware('marketplace.visitor', EnsureMarketplaceVisitor::class);
    }

    protected function publishConfig(): void
    {
        $this->publishes([
            module_path($this->moduleName, 'Config/config.php') => config_path($this->moduleNameLower . '.php'),
        ], 'config');
    }

    protected function registerViews(): void
    {
        $viewPath = resource_path('views/modules/' . $this->moduleNameLower);
        $sourcePath = module_path($this->moduleName, 'Resources/views');

        $this->publishes([$sourcePath => $viewPath], ['views', $this->moduleNameLower . '-module-views']);

        $this->loadViewsFrom(
            array_merge($this->getPublishableViewPaths(), [$sourcePath]),
            $this->moduleNameLower
        );
    }

    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/' . $this->moduleNameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
        } else {
            $this->loadTranslationsFrom(
                module_path($this->moduleName, 'Resources/lang'),
                $this->moduleNameLower
            );
        }
    }

    public function provides()
    {
        return [];
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];

        foreach (config('view.paths') as $path) {
            if (is_dir($path . '/modules/' . $this->moduleNameLower)) {
                $paths[] = $path . '/modules/' . $this->moduleNameLower;
            }
        }

        return $paths;
    }
}
