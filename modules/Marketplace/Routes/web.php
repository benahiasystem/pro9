<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas públicas del Marketplace
|--------------------------------------------------------------------------
| Acotadas al dominio del sistema por RouteServiceProvider.
|
| Todas pasan por `marketplace.enabled`: con el marketplace apagado el
| visitante ve un 503 amable, no un error crudo.
*/

Route::prefix(config('marketplace.route_prefix', 'marketplace'))
    ->middleware('marketplace.enabled')
    ->group(function () {
        Route::get('/', 'Web\MarketplaceController@index')->name('marketplace.public.index');

        // El enlace compartible. 410 si la tienda ya no está aprobada.
        Route::get('tienda/{slug}', 'Web\MarketplaceController@store')->name('marketplace.public.store');

        // JSON para el componente Vue. Sirve también las sugerencias del
        // buscador con ?suggest=1.
        Route::get('feed', 'Web\FeedController@index')->name('marketplace.public.feed');

        Route::post('denuncia', 'Web\ReportController@store')
            ->middleware('throttle:10,60')
            ->name('marketplace.public.report');
    });
