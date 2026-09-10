<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Conexión Offline VendeYa
|--------------------------------------------------------------------------
| enroll:   credenciales del tenant (solo admin) — alta de máquina + series.
| snapshot/heartbeat: token de máquina (X-Machine-Token).
*/

Route::prefix('sync')->group(function () {
    Route::middleware('auth:api')->group(function () {
        Route::get('enroll-options', 'SyncController@enrollOptions');
        Route::post('enroll', 'SyncController@enroll');
    });

    Route::middleware('auth.machine')->group(function () {
        Route::get('snapshot', 'SyncController@snapshot');
        Route::post('heartbeat', 'SyncController@heartbeat');
        Route::post('batch', 'SyncController@batch');
    });
});
