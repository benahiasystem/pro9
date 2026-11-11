<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\{SessionController, CompanyController, CatalogController, EntryController, ReportController, OfflineController, Pro9Controller};
use Modules\Accounting\Http\Middleware\{CompanyAccess, RequirePermission};
Route::get('auth/me', [SessionController::class, 'me']);
Route::post('auth/logout', [SessionController::class, 'logout']);
Route::post('auth/mfa/verify', [SessionController::class, 'verify'])->middleware('throttle:accounting-mfa');
Route::post('auth/mfa/enroll', [SessionController::class, 'enroll'])->middleware('throttle:accounting-mfa');
Route::post('auth/mfa/confirm', [SessionController::class, 'confirm'])->middleware('throttle:accounting-mfa');
Route::get('health', fn() => ['status' => 'ok', 'release' => config('accounting.release'), 'server_time' => now()->toIso8601String()]);
Route::get('companies', [CompanyController::class, 'index']);
Route::post('companies', [CompanyController::class, 'store']);
Route::prefix('companies/{company}')->whereUuid('company')->middleware(CompanyAccess::class)->group(function () {
    Route::get('/', [CompanyController::class, 'show']);
    Route::patch('/', [CompanyController::class, 'update'])->middleware(RequirePermission::class . ':company');
    Route::get('catalogs/{kind}', [CatalogController::class, 'index'])->middleware(RequirePermission::class . ':read');
    Route::post('catalogs/{kind}', [CatalogController::class, 'store'])->middleware(RequirePermission::class . ':catalogs');
    Route::patch('catalogs/{kind}/{id}', [CatalogController::class, 'update'])->middleware(RequirePermission::class . ':catalogs')->whereUuid('id');
    Route::get('entries', [EntryController::class, 'index'])->middleware(RequirePermission::class . ':read');
    Route::post('entries', [EntryController::class, 'store'])->middleware(RequirePermission::class . ':draft');
    Route::get('entries/{id}', [EntryController::class, 'show'])->middleware(RequirePermission::class . ':read')->whereUuid('id');
    Route::patch('entries/{id}', [EntryController::class, 'update'])->middleware(RequirePermission::class . ':draft')->whereUuid('id');
    foreach (['submit' => 'draft', 'observe' => 'review', 'approve' => 'approve', 'post' => 'post', 'reverse' => 'reverse'] as $action => $permission) {
        Route::post('entries/{id}/' . $action, [EntryController::class, $action])->middleware(RequirePermission::class . ':' . $permission)->whereUuid('id');
    }
    Route::get('reports/{kind}', [ReportController::class, 'show'])->middleware(RequirePermission::class . ':read');
    Route::get('pro9/status', [Pro9Controller::class, 'status'])->middleware(RequirePermission::class . ':integration');
    Route::get('pro9/sources/{kind}', [Pro9Controller::class, 'index'])->middleware(RequirePermission::class . ':integration');
    Route::get('pro9/sources/{kind}/{id}', [Pro9Controller::class, 'show'])->middleware(RequirePermission::class . ':integration')->whereNumber('id');
    Route::post('pro9/sources/{kind}/{id}/receive', [Pro9Controller::class, 'receive'])->middleware(RequirePermission::class . ':integration')->whereNumber('id');
    Route::get('documents', [Pro9Controller::class, 'documents'])->middleware(RequirePermission::class . ':read');
    Route::post('offline/prepare', [OfflineController::class, 'prepare'])->middleware(RequirePermission::class . ':local');
    Route::post('offline/sync', [OfflineController::class, 'sync'])->middleware(RequirePermission::class . ':local');
    Route::get('offline/devices', [OfflineController::class, 'devices'])->middleware(RequirePermission::class . ':local');
    Route::post('offline/devices/{id}/revoke', [OfflineController::class, 'revoke'])->middleware(RequirePermission::class . ':local')->whereUuid('id');
});
