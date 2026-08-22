<?php

$current_hostname = app(Hyn\Tenancy\Contracts\CurrentHostname::class);

if ($current_hostname) {
    Route::domain($current_hostname->fqdn)->group(function () {
        Route::middleware(['auth', 'locked.tenant'])->group(function () {

            Route::prefix('cash-reports')->group(function () {
                Route::get('catalog', 'CashReportController@catalog');
                Route::get('generate/{type}/{cash}', 'CashReportController@generate');
            });

        });
    });
}
