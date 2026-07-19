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
    ->middleware(['marketplace.enabled', 'marketplace.visitor'])
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

        // Pone y quita: el cuerpo lleva `recomendar` true/false. Un solo
        // endpoint con el estado explícito, no un toggle ciego, para que el
        // resultado dependa de lo que pidió el cliente y no del estado que
        // hubiera en el servidor. add/remove son idempotentes por separado
        // (insertOrIgnore / delete), así que un doble envío en la misma
        // dirección no rompe nada. El orden entre un "pon" y un "quita"
        // concurrentes lo garantiza el cliente, que serializa las peticiones
        // (ver el guard `busy` en MktRecommend): no se dispara una hasta que
        // vuelve la anterior.
        //
        // El límite real —un pulgar por producto y visitante— lo impone la
        // clave única en base de datos. El throttle solo evita que se automatice
        // desde una misma IP.
        Route::post('recomendacion', 'Web\RecommendController@store')
            ->middleware('throttle:60,60')
            ->name('marketplace.public.recommend');
    });
