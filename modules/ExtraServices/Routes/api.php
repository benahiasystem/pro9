<?php

use Illuminate\Support\Facades\Route;

$prefix = env('PREFIX_URL',null);
$prefix = !empty($prefix)?$prefix.".":'';
$app_url = $prefix. env('APP_URL_BASE');

$hostname = app(Hyn\Tenancy\Contracts\CurrentHostname::class);

// Rutas para el system
Route::domain($app_url)->group(function () {
    Route::middleware(['auth:system_api'])->group(function() {
        Route::prefix('extra-services')->group(function () {
            Route::post('activate', 'ExtraServicesController@activateService');
            Route::post('inactivate', 'ExtraServicesController@inactivateService');
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