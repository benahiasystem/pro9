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
| La identidad de la tienda viaja en el payload (external_uuid) MÁS la
| credencial por tienda X-Store-Secret (TOFU, emitida en el primer sync).
| El riesgo de suplantación por token global, que el plan original aceptaba,
| quedó cerrado en plan-seguridad-marketplace.md (A3.1).
*/

Route::prefix('v1/marketplace')
    ->middleware(['auth:system_api', 'marketplace.enabled'])
    ->group(function () {
        Route::post('sync', 'Api\SyncController@store');
        Route::get('status/{external_uuid}', 'Api\StatusController@show');

        // «Ocultar mi tienda»: despublicación inmediata con uuid + X-Store-Secret.
        Route::post('hide', 'Api\HideController@store');
    });
