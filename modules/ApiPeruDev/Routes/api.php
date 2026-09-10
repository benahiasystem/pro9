<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

//Route::get('generate_token', 'ServiceController@dispatch');

use Illuminate\Support\Facades\Route;

$current_hostname = app(Hyn\Tenancy\Contracts\CurrentHostname::class);

if($current_hostname) {
    Route::domain($current_hostname->fqdn)->group(function () {
        Route::middleware(['auth:api'])->group(function () {
            Route::prefix('service')->group(function () {
                Route::get('{type}/{number}', 'ServiceController@service');
            });
        });
    });
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
