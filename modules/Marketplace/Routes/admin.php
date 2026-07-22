<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin del Marketplace
|--------------------------------------------------------------------------
| Dominio del sistema, guard `admin` + `reseller.system.admin`.
|
| Prefijo `marketplace/admin` y NO `admin/marketplace`: el middleware
| EnsureResellerSystemAdminPermissions deriva la clave de permiso del PRIMER
| segmento de la URL (App\Models\System\User::canAccessSystemPath). Con
| `admin/...` la clave sería "admin", que no es un módulo otorgable; con
| `marketplace/...` es "marketplace", que sí puede concederse a un admin de
| reseller vía module_permissions.
|
| Ninguna ruta lleva `marketplace.enabled`: el admin debe funcionar con el
| marketplace apagado.
*/

Route::prefix('marketplace/admin')
    ->middleware(['auth:admin', 'reseller.system.admin'])
    ->group(function () {
        Route::get('/', 'Admin\MarketplaceAdminController@index')->name('marketplace.admin.index');

        // Tiendas
        Route::get('stores', 'Admin\StoreController@index');
        Route::get('stores/{id}/items', 'Admin\StoreController@items');
        Route::post('stores/{id}/approve', 'Admin\StoreController@approve');
        Route::post('stores/{id}/reject', 'Admin\StoreController@reject');
        Route::post('stores/{id}/disable', 'Admin\StoreController@disable');
        Route::post('stores/{id}/enable', 'Admin\StoreController@enable');

        Route::post('stores/{id}/reset-secret', 'Admin\StoreController@resetSecret');

        // Seguridad: rastro de contactos (con ?export=1 → CSV) y alertas.
        Route::get('contact-requests', 'Admin\ContactRequestController@index');
        Route::get('alerts', 'Admin\SecurityAlertController@index');
        Route::post('alerts/{id}/read', 'Admin\SecurityAlertController@markRead');

        // Denuncias
        Route::get('reports', 'Admin\ReportController@index');
        Route::post('reports/{id}/dismiss', 'Admin\ReportController@dismiss');
        Route::post('items/{id}/block', 'Admin\ReportController@blockItem');
        Route::post('items/{id}/unblock', 'Admin\ReportController@unblockItem');

        // Categorías
        Route::get('categories', 'Admin\CategoryController@index');
        Route::patch('categories/{id}', 'Admin\CategoryController@update');

        // Ajustes
        Route::get('settings', 'Admin\SettingController@index');
        Route::put('settings', 'Admin\SettingController@update');
    });
