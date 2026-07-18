<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API del Marketplace — exactamente 2 endpoints
|--------------------------------------------------------------------------
| Autenticación: guard `system_api` (token driver sobre api_token de
| App\Models\System\User). Es el token del reseller que la app ya usa; no se
| emiten tokens por tienda ni se añade Sanctum.
|
| La identidad de la tienda viaja en el payload (external_uuid), no en la
| credencial. Riesgo aceptado y registrado en el plan: cualquier app con el
| token podría suplantar el external_uuid de otra tienda.
*/

Route::prefix('v1/marketplace')
    ->middleware(['auth:system_api', 'marketplace.enabled'])
    ->group(function () {
        Route::post('sync', 'Api\SyncController@store');
        Route::get('status/{external_uuid}', 'Api\StatusController@show');
    });
