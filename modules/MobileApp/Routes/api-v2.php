<?php

$hostname = app(Hyn\Tenancy\Contracts\CurrentHostname::class);

if ($hostname)
{
    Route::domain($hostname->fqdn)->group(function () {

        // V2
        Route::get('app.json', 'Api\AppConfigurationController@app_json')->name('tenant.app_json');

        Route::middleware(['auth:api', 'locked.tenant'])->group(function () {

            Route::prefix('items')->group(function () {
                Route::get('records-scroll', 'Api\ItemController@byScroll');
                Route::post('save', 'Api\ItemController@save');
            });

            Route::prefix('persons')->group(function () {
                Route::post('/create/{type}', 'Api\PersonController@store');
                Route::get('/records-scroll/{type}', 'Api\PersonController@byScroll');
                Route::get('locations', 'Api\PersonController@locations');
            });

            Route::prefix('app-configurations')->group(function () {});

            Route::prefix('quotations')->group(function () {
                Route::get('records-scroll', 'Api\QuotationController@byScroll');
            });

            // factura, boleta, nota de venta
            Route::prefix('documents')->group(function () {
                Route::get('records-scroll', 'Api\DocumentCentralizedController@byScroll');
                // catalogos de "operacion sujeta a detraccion" (1001)
                Route::get('detraction-tables', 'Api\DocumentCentralizedController@detractionTables');
            });

            // pagos de comprobantes (factura, boleta)
            Route::prefix('document-payments')->group(function () {
                Route::get('tables', 'Api\DocumentPaymentController@tables');
                Route::get('document/{document_id}', 'Api\DocumentPaymentController@document');
                Route::get('records/{document_id}', 'Api\DocumentPaymentController@records');
                Route::post('/', 'Api\DocumentPaymentController@store');
                Route::delete('{id}', 'Api\DocumentPaymentController@destroy');
            });

            // bandeja de pagos recibidos (Yape / Plin) capturados por el equipo-principal
            Route::prefix('received-payments')->group(function () {
                Route::get('records-scroll', 'Api\ReceivedPaymentController@byScroll');
                Route::post('/', 'Api\ReceivedPaymentController@store');
                Route::post('{id}/claim', 'Api\ReceivedPaymentController@claim');
                Route::put('{id}', 'Api\ReceivedPaymentController@update');
                Route::post('{id}/discard', 'Api\ReceivedPaymentController@discard');
            });

            Route::prefix('purchases')->group(function () {
                Route::get('records-scroll', 'Api\PurchaseController@byScroll');
                // Route::get('items-scroll', 'Api\PurchaseController@itemsByScroll');
                Route::get('suppliers-scroll', 'Api\PurchaseController@suppliersByScroll');
            });

            Route::prefix('categories')->group(function () {});

            Route::prefix('establishments')->group(function () {
                Route::get('series', 'Api\EstablishmentController@withSeries');
                Route::post('change-user', '\App\Http\Controllers\Tenant\EstablishmentController@changeUserEstablishment');
            });

            // guias de remision (09 remitente, 31 transportista): catalogos livianos y listado unificado.
            // La emision usa los endpoints core: POST api/dispatches (09) y POST api/dispatch-carrier (31).
            Route::prefix('dispatches')->group(function () {
                Route::get('tables', 'Api\DispatchController@tables');
                Route::get('records-scroll', 'Api\DispatchController@byScroll');
            });

            // inventario: catalogos, traslado entre almacenes y ajuste de stock.
            // La lectura usa GET items/records-scroll (params warehouse_id, stock_filter).
            Route::prefix('inventory')->group(function () {
                Route::get('tables', 'Api\InventoryController@tables');
                Route::post('transfer', 'Api\InventoryController@transfer');
                Route::post('adjust', 'Api\InventoryController@adjust');
            });

            // finanzas: movimientos unificados de ingresos/egresos, registro de ingresos y gastos
            Route::prefix('finances')->group(function () {
                Route::get('tables', 'Api\FinanceController@tables');
                Route::get('movements-scroll', 'Api\FinanceController@movementsByScroll');
                Route::get('movements-summary', 'Api\FinanceController@movementsSummary');
                Route::post('income', 'Api\FinanceController@storeIncome');
                Route::post('income/{id}/void', 'Api\FinanceController@voidIncome');
                Route::post('expense', 'Api\FinanceController@storeExpense');
                Route::post('expense/{id}/void', 'Api\FinanceController@voidExpense');
            });

            Route::prefix('cash')->group(function () {
                Route::get('records-scroll', 'Api\CashController@byScroll');
            });

            Route::prefix('reports')->group(function () {});

        });
    });
}
else
{
    // Dominio system (central): autoregistro de tenant desde la app.
    // La creacion puede tardar varios minutos; no agregar throttle restrictivo.
    Route::domain(env('APP_URL_BASE'))->group(function () {

        Route::middleware('auth:system_api')->prefix('guest-register')->group(function () {
            Route::get('/', 'Api\GuestRegisterController@init');
            Route::get('service/ruc/{number}', '\App\Http\Controllers\System\ServiceController@ruc');
            Route::post('/', 'Api\GuestRegisterController@register');
        });
    });
}
