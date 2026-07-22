<?php

namespace Modules\Marketplace\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected $moduleNamespace = 'Modules\Marketplace\Http\Controllers';

    public function map()
    {
        $this->mapConfigRoute();

        // Interruptor maestro (.env → config('marketplace.enabled')). Apagado,
        // no se registra el resto: admin, api y público responden 404, así que
        // «no es posible ingresar» de verdad, no solo se esconde el enlace.
        if (! config('marketplace.enabled', false)) {
            return;
        }

        $this->mapApiRoutes();
        $this->mapAdminRoutes();
        $this->mapWebRoutes();
    }

    protected function mapConfigRoute()
    {
        Route::prefix('api/v1/marketplace')
            ->middleware(['api', 'auth:system_api'])
            ->namespace($this->moduleNamespace)
            ->group(function () {
                Route::get('config', 'Api\ConfigController@show')->name('marketplace.api.config');
            });
    }

    /**
     * Admin. Se registra ANTES que las rutas públicas para que
     * `marketplace/admin` gane sobre cualquier patrón con comodín.
     */
    protected function mapAdminRoutes()
    {
        Route::domain($this->systemDomain())
            ->middleware('web')
            ->namespace($this->moduleNamespace)
            ->group(module_path('Marketplace', '/Routes/admin.php'));
    }

    /**
     * Rutas públicas del marketplace.
     *
     * Van acotadas al **dominio del sistema**: los datos viven en la conexión
     * `system` y no tienen nada que ver con los tenants. Es la misma forma en
     * que routes/web.php separa el panel del reseller.
     */
    protected function mapWebRoutes()
    {
        Route::domain($this->systemDomain())
            ->middleware('web')
            ->namespace($this->moduleNamespace)
            ->group(module_path('Marketplace', '/Routes/web.php'));
    }

    /**
     * API para la app móvil.
     *
     * No se acota por dominio a propósito: los modelos usan UsesSystemConnection,
     * así que responden igual desde cualquier host, y la app se compila
     * apuntando al dominio del reseller.
     */
    protected function mapApiRoutes()
    {
        Route::prefix('api')
            ->middleware('api')
            ->namespace($this->moduleNamespace)
            ->group(module_path('Marketplace', '/Routes/api.php'));
    }

    /**
     * Mismo cálculo que routes/web.php para el dominio del panel del sistema.
     */
    private function systemDomain(): string
    {
        $prefix = env('PREFIX_URL', null);

        return (! empty($prefix) ? $prefix . '.' : '') . env('APP_URL_BASE');
    }
}
