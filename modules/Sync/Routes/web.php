<?php

use Illuminate\Support\Facades\Route;

$current_hostname = app(Hyn\Tenancy\Contracts\CurrentHostname::class);

if ($current_hostname) {
    Route::domain($current_hostname->fqdn)->group(function () {
        Route::middleware(['auth', 'locked.tenant'])->group(function () {
            Route::prefix('sync')->group(function () {
                Route::get('/', 'PanelController@index')->name('tenant.sync.index');
                Route::post('machines/records', 'PanelController@machines');
                Route::post('machines/{id}/revoke', 'PanelController@revoke');
                Route::post('machines/{id}/release-series', 'PanelController@releaseSeries');
                Route::post('events/records', 'PanelController@events');
                Route::post('events/{id}/retry', 'PanelController@retryEvent');
                Route::post('events/{id}/discard', 'PanelController@discardEvent');
            });
        });
    });
}
