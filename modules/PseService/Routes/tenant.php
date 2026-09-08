<?php

use Illuminate\Support\Facades\Route;

$hostname = app(Hyn\Tenancy\Contracts\CurrentHostname::class);
if ($hostname) {
    Route::domain($hostname->fqdn)->group(function () {
        Route::middleware(['auth', 'locked.tenant'])->group(function () {
            // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
            // No se registran rutas PSE en la operación local venezolana.
            // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
        });
    });
};
