<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        //

        parent::boot();
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        if ($this->app->isProduction()) {
            Route::get('/storage/uploads/{folder}/{name}', static function (string $folder, string $name) {
                abort_unless(in_array($folder, ['login', 'system_ads'], true), 404);
                abort_unless(preg_match('/\A[a-zA-Z0-9_]{32,80}\.(?:png|jpe?g|gif|webp|avif|svg)\z/', $name), 404);
                $path = 'uploads/'.$folder.'/'.$name;
                $disk = app(\App\Services\Storage\TenantStorage::class)->systemMedia();
                abort_unless($disk->exists($path), 404);

                return $disk->download($path, $name, ['Content-Security-Policy' => "default-src 'none'; sandbox"], 'inline');
            })->whereIn('folder', ['login', 'system_ads'])->middleware(\App\Http\Middleware\ProductionSurface::class);
            Route::get('/healthz', static function () {
                try {
                    \Illuminate\Support\Facades\DB::connection('system')->selectOne('SELECT 1');
                    \Illuminate\Support\Facades\Redis::connection()->ping();
                    return response()->noContent()->header('Cache-Control', 'no-store');
                } catch (\Throwable $failure) {
                    return response('', 503)->header('Cache-Control', 'no-store');
                }
            })->middleware(\App\Http\Middleware\ProductionSurface::class);
        }
        $this->mapApiRoutes();

        $this->mapWebRoutes();

        //
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        // ########## INICIO CAMBIO SIN XML CDR SUNAT
        // Hyn identifica el tenant al terminar el arranque de la aplicación.
        // Cargar antes este archivo omite todas las rutas comerciales del tenant.
        $this->app->booted(function () {
            Route::middleware('web')
                 ->namespace($this->namespace)
                 ->group(base_path('routes/web.php'));
        });
        // ######### FIN CAMBIO SIN XML CDR SUNAT
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        // ########## INICIO CAMBIO SIN XML CDR SUNAT
        // Las rutas API tenant necesitan la misma identificación previa del hostname.
        $this->app->booted(function () {
            Route::prefix('api')
                 ->middleware('api')
                 ->namespace($this->namespace)
                 ->group(base_path('routes/api.php'));
        });
        // ######### FIN CAMBIO SIN XML CDR SUNAT
    }
}
