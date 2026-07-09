<?php

use Illuminate\Support\Facades\Route;

$prefix = env('PREFIX_URL',null);
$prefix = !empty($prefix)?$prefix.".":'';
$app_url = $prefix. env('APP_URL_BASE');

$hostname = app(Hyn\Tenancy\Contracts\CurrentHostname::class);

// Rutas para el system
Route::domain($app_url)->group(function () {
    Route::middleware('auth:admin')->group(function () {
        Route::prefix('extra-services')->group(function () {

            Route::get('/', 'ExtraServicesController@index')->name('system.services');
            Route::get('/records', 'ExtraServicesController@records');
            Route::post('/store', 'ExtraServicesController@store');

            Route::prefix('apidocs')->group(function () {
                Route::get('/usage/records', 'ClientUsageApidocsController@records');
            });
            
        });
    });
});

// Rutas para el tenant
if ($hostname) {
    Route::domain($hostname->fqdn)->group(function () {
        Route::prefix('extra-services')->group(function () {
            
        });
    });
}