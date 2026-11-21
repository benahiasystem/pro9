<?php

$hostname = app(App\Tenancy\Contracts\CurrentHostname::class);

if ($hostname)
{
    Route::domain($hostname->fqdn)->group(function () {

        Route::middleware(['auth:api', 'locked.tenant'])->group(function () {


            Route::prefix('whatsapp-cloud')->group(function () {

                Route::post('send-message', 'Api\WhatsAppApiController@sendMessage');

            });

        });

    });
}
