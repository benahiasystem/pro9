<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tenant\ConfigurationImageController;

Route::get('generate_token', 'Tenant\Api\MobileController@getSeries');
Route::post('consultas/search', 'System\PublicDocumentSearchController@searchApi')
    ->middleware('throttle:30,1')
    ->name('api.public_search.search');

// Envío de WhatsApp desde el número conectado al superadmin, para servicios
// externos. Auth propia por token (Authorization: Bearer), ver
// System\Api\WhatsAppNotifyApiController.
Route::prefix('whatsapp-notify')->middleware('throttle:30,1')->group(function () {
    Route::post('text', 'System\Api\WhatsAppNotifyApiController@text')->name('api.whatsapp_notify.text');
    Route::post('media', 'System\Api\WhatsAppNotifyApiController@media')->name('api.whatsapp_notify.media');
    Route::post('pdf', 'System\Api\WhatsAppNotifyApiController@pdf')->name('api.whatsapp_notify.pdf');
});

$hostname = app(Hyn\Tenancy\Contracts\CurrentHostname::class);
if ($hostname) {
    Route::domain($hostname->fqdn)->group(function () {
        Route::post('configurations/default-image', [ConfigurationImageController::class, 'upload']);
        Route::post('login', 'Tenant\Api\MobileController@login');

        Route::middleware(['auth:api', 'locked.tenant'])->group(function () {
            //MOBILE

            Route::get('stats/{startDate}/{endDate}', 'Tenant\Api\MobileController@stats');
            Route::get('record/qrapi', 'Tenant\Api\MobileController@record_qrapi');
            Route::get('document/series', 'Tenant\Api\MobileController@getSeries');
            Route::get('document/series-dispatch', 'Tenant\Api\MobileController@getSeriesDispatch');
            Route::get('document/paymentmethod', 'Tenant\Api\MobileController@getPaymentmethod');
            Route::get('document/tables', 'Tenant\Api\MobileController@tables');
            Route::get('document/customers', 'Tenant\Api\MobileController@customers');
            Route::post('document/email', 'Tenant\Api\MobileController@document_email');
            Route::post('sale-note', 'Tenant\Api\SaleNoteController@store');
            Route::get('sale-note/series', 'Tenant\Api\SaleNoteController@series');
            Route::get('sale-note/lists', 'Tenant\Api\SaleNoteController@lists');
            Route::get('sale-note/find/{id}', 'Tenant\Api\SaleNoteController@record');
            Route::post('item', 'Tenant\Api\MobileController@item');
            Route::post('items/{id}/update', 'Tenant\Api\MobileController@updateItem');
            Route::get('item/destroy/{item}', 'Tenant\Api\MobileController@destroyItem');
            Route::get('configuration-web', 'Tenant\Api\MobileController@configWeb');
            Route::post('item/upload', 'Tenant\Api\MobileController@upload');
            Route::post('person', 'Tenant\Api\MobileController@person');
            Route::get('document/search-items', 'Tenant\Api\MobileController@searchItems');
            Route::get('document/search-customers', 'Tenant\Api\MobileController@searchCustomers');
            Route::post('sale-note/email', 'Tenant\Api\SaleNoteController@email');
            Route::post('sale-note/{id}/generate-cpe', 'Tenant\Api\SaleNoteController@generateCPE');
            Route::get('document/get-data/{id}', 'Tenant\Api\MobileController@getDataToDispatch');

            Route::get('report', 'Tenant\Api\MobileController@report');

            Route::post('documents', 'Tenant\Api\DocumentController@store');
            Route::get('documents/lists', 'Tenant\Api\DocumentController@lists');
            Route::get('documents/lists/{startDate}/{endDate}', 'Tenant\Api\DocumentController@lists');
            // "documents/record/{id}" ya lo registra el modulo MobileApp, que gana por
            // orden de registro; por eso este endpoint usa "find".
            Route::get('documents/find/{id}', 'Tenant\Api\DocumentController@record');

            // Mismo contrato que documents/find y sale-note/find: un registro por id,
            // envuelto en "data", con 404 cuando no existe o no pertenece al vendedor.
            Route::get('quotation/find/{id}', 'Tenant\Api\QuotationController@record');
            Route::get('purchase/find/{id}', 'Tenant\Api\PurchaseController@record');
            Route::get('dispatch/find/{id}', 'Tenant\Api\DispatchController@record');
            Route::post('documents/updatedocumentstatus', 'Tenant\Api\DocumentController@updatestatus');
            Route::post('voided', 'Tenant\Api\VoidedController@store');
            Route::post('retentions', 'Tenant\Api\RetentionController@store');
            Route::post('dispatches', 'Tenant\Api\DispatchController@store');
            Route::get('services/ruc/{number}', 'Tenant\Api\ServiceController@ruc');
            Route::get('services/dni/{number}', 'Tenant\Api\ServiceController@dni');
            Route::post('perceptions', 'Tenant\Api\PerceptionController@store');

            Route::get('dispatches/tables', 'Tenant\Api\DispatchController@tables');
            Route::get('dispatches/records', 'Tenant\Api\DispatchController@records');

            //liquidacion de compra
            Route::post('purchase-settlements', 'Tenant\Api\PurchaseSettlementController@store');

            //Pedidos
            Route::get('orders', 'Tenant\Api\OrderController@records');
            Route::post('orders', 'Tenant\Api\OrderController@store');

            //Company
            Route::get('company', 'Tenant\Api\CompanyController@record');
            Route::get('company/customers', 'Tenant\Api\CompanyController@searchCustomers');

            // Cotizaciones
            Route::get('quotations/list', 'Tenant\Api\QuotationController@list');
            Route::post('quotations', 'Tenant\Api\QuotationController@store');
            Route::post('quotations/email', 'Tenant\Api\QuotationController@email');
            Route::get('quotations/tables', 'Tenant\Api\QuotationController@tables');

            //Caja
            Route::post('cash/restaurant', 'Tenant\Api\CashController@storeRestaurant');
            Route::post('cash/cash_document', 'Tenant\Api\CashController@cash_document');
            Route::get('cash/opening_cash', 'Tenant\Api\CashController@opening_cash');
            Route::get('cash/opening_cash_check/{cash_id}', 'Tenant\Api\CashController@opening_cash_check');
            Route::get('cash/available-restaurant', 'Tenant\Api\CashController@cash_available');
            Route::post('cash/open', 'Tenant\CashController@store');
            Route::get('cash/close/{cash}', 'Tenant\Api\CashController@close');

            //Vendeya
            Route::prefix('sellnow')->group(function () {
                Route::get('/items', 'Tenant\Api\SellnowController@items');
                Route::get('/categories', 'Tenant\Api\SellnowController@categories');
                Route::post('/favoriteitem', 'Tenant\Api\SellnowController@setFavoriteItem');
                Route::get('/customers', 'Tenant\Api\CompanyController@searchCustomers');
            });

            Route::prefix('consigneds')->group(function () {
                Route::get('/tables', 'Tenant\Api\ConsignedController@tables');
                Route::get('/data', 'Tenant\Api\ConsignedController@data');
                Route::get('/records', 'Tenant\Api\ConsignedController@records');
                Route::get('/record/{consigned}', 'Tenant\Api\ConsignedController@record');
                Route::post('', 'Tenant\Api\ConsignedController@store');
                Route::post('/store-document', 'Tenant\Api\ConsignedController@storeDocument');
                Route::get('/search_by_customer/{id}', 'Tenant\Api\ConsignedController@searchByCustomer');
                Route::get('/addresses', 'Tenant\Api\ConsignedController@consignedAddresses');
            });
            Route::get('price-labels/active', 'Tenant\Api\MobileController@priceLabels');

            // Placas por cliente - giro de negocio grifo/taps (equivalente movil de
            // bussiness_turns/plates del modulo BusinessTurn).
            Route::get('plates/{person_id}', 'Tenant\Api\PlateController@records');
            Route::post('plates', 'Tenant\Api\PlateController@store');
            Route::post('plates/{plate}/destroy', 'Tenant\Api\PlateController@destroy');
        });
        Route::get('documents/search/customers', 'Tenant\DocumentController@searchCustomers');

        Route::post('documents/status', 'Tenant\Api\ServiceController@documentStatus');

        Route::get('sendserver/{document_id}/{query?}', 'Tenant\DocumentController@sendServer');
        Route::post('configurations/generateDispatch', 'Tenant\ConfigurationController@generateDispatch');

        // Contenido de los certificados de qz tray
        Route::get('certificates-qztray/private', 'Tenant\CertificateQzTrayController@private');
        Route::get('certificates-qztray/digital', 'Tenant\CertificateQzTrayController@digital');

    });
} else {
    Route::domain(env('APP_URL_BASE'))->group(function () {

        Route::post('login', 'System\Api\AuthController@login');
        Route::get('plans', 'System\Api\PlanController@records');

        Route::middleware(['auth:system_api'])->group(function () {

            //Resellers
            Route::post('reseller/detail', 'System\Api\ResellerController@resellerDetail');
            Route::post('reseller/lockedAdmin', 'System\Api\ResellerController@lockedAdmin');
            Route::post('reseller/lockedTenant', 'System\Api\ResellerController@lockedTenant');
            Route::get('reseller/detailsLimitReseller', 'System\Api\ResellerController@detailsLimitReseller');

            //Tenants
            Route::post('/tenants', 'System\Api\TenantController@store');

            Route::get('restaurant/partner/list', 'System\Api\RestaurantPartnerController@list');
            Route::post('restaurant/partner/store', 'System\Api\RestaurantPartnerController@store');
            Route::post('restaurant/partner/search', 'System\Api\RestaurantPartnerController@search');

        });

    });

}
