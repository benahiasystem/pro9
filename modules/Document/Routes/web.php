<?php


$current_hostname = app(Hyn\Tenancy\Contracts\CurrentHostname::class);

if($current_hostname) {
    Route::domain($current_hostname->fqdn)->group(function () {
        Route::middleware(['auth', 'locked.tenant','check.email.verified'])->group(function () {

            // ########## INICIO CAMBIO SIN XML CDR SUNAT
            // No existe una bandeja activa de comprobantes pendientes de envío fiscal.
            // ######### FIN CAMBIO SIN XML CDR SUNAT

            /**
            * documents/pay-constancy/upload
            * documents/pay-constancy/save
            * documents/detraction/tables
            * documents/data-table/customers
            * documents/prepayments/{type}
            * documents/search-items
            * documents/search/item/{item}
            * documents/consult_cdr/{document}
            * documents/item-lots
            * documents/regularize-lots/{document_item_id}
             */
            Route::prefix('documents')->group(function() {
                Route::post('pay-constancy/upload', 'DocumentController@upload');
                Route::post('pay-constancy/save', 'DocumentController@savePayConstancy');
                // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
                // Sin catálogo de detracciones no se publica este endpoint.
                // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
                Route::get('data-table/customers', 'DocumentController@dataTableCustomers');
                Route::get('prepayments/{type}', 'DocumentController@prepayments');
                Route::get('search-items', 'DocumentController@searchItems');
                Route::get('search/item/{item}', 'DocumentController@searchItemById');
                // ########## INICIO CAMBIO SIN XML CDR SUNAT
                // Consulta CDR y envío forzado no se registran en operación local.
                // ######### FIN CAMBIO SIN XML CDR SUNAT

                Route::get('item-lots', 'DocumentController@searchLots');
                Route::get('regularize-lots/{document_item_id}', 'DocumentController@regularizeLots');

                Route::post('item_lots', 'DocumentController@searchItemLots');

            });

            Route::prefix('series-configurations')->group(function() {

                Route::get('', 'SeriesConfigurationController@index')->name('tenant.series_configurations.index')->middleware('redirect.level','check.email.verified');
                Route::get('records', 'SeriesConfigurationController@records');
                Route::get('tables', 'SeriesConfigurationController@tables');
                Route::post('', 'SeriesConfigurationController@store');
                Route::delete('{record}', 'SeriesConfigurationController@destroy');

            });

            // ########## INICIO CAMBIO SIN XML CDR SUNAT
            // Se retiraron validación CPE y regularización de envíos fiscales.
            // ######### FIN CAMBIO SIN XML CDR SUNAT
        });
    });
}
