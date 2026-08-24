<?php

use Illuminate\Support\Facades\Route;

$current_hostname = app(Hyn\Tenancy\Contracts\CurrentHostname::class);

if($current_hostname) {
    Route::domain($current_hostname->fqdn)->group(function () {
        Route::middleware(['auth', 'locked.tenant'])->group(function () {
            Route::prefix('apiperudev')->group(function () {
                // ########## INICIO CAMBIO SIN XML CDR SUNAT
                // La validación masiva CPE no se registra en operación local.
                // ######### FIN CAMBIO SIN XML CDR SUNAT
            });
            //ruta distinta a la version actual
            Route::prefix('service')->group(function () {
                Route::get('exchange/{date}', 'ServiceController@exchange');
                Route::get('ruc-establecimientos/{number}', 'ServiceController@establishments');
                Route::get('{type}/{number}', 'ServiceController@service');

                // ########## INICIO CAMBIO SIN XML CDR SUNAT
                // Las guías locales no publican envío ni consulta de ticket fiscal.
                // ######### FIN CAMBIO SIN XML CDR SUNAT
            });
        });
    });
} else {
    $prefix = env('PREFIX_URL',null);
    $prefix = !empty($prefix)?$prefix.".":'';
    $app_url = $prefix. env('APP_URL_BASE');

    Route::domain($app_url)->group(function () {
        Route::middleware(['auth:admin', 'reseller.system.admin'])->group(function () {
            Route::prefix('service')->group(function () {
                Route::get('{type}/{number}', 'ServiceController@service');
            });
        });
    });
}
