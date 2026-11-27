<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        // ########## INICIO CORRECCIÓN INICIALIZACIÓN TENANT ##########
        \App\Http\Middleware\EnsureTenantConnection::class,
        // ######### FIN CORRECCIÓN INICIALIZACIÓN TENANT ##########
        \Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        // ######## INICIO CALCULOS EXACTOS SAB ########
        \App\Http\Middleware\PreserveJsonDecimals::class,
        // ######## FIN CALCULOS EXACTOS SAB ########
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        \App\Http\Middleware\RemoveClientCalculationSnapshots::class,
        \App\Http\Middleware\TrustProxies::class,
        \App\Http\Middleware\ForceHttps::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Tenancy\Middleware\HostnameActions::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\ProductionSurface::class,
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \App\Http\Middleware\StartWebSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
            \App\Http\Middleware\ResolveTenantUploads::class,
            \App\Http\Middleware\RefreshPrivateMedia::class,
            // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
            \App\Http\Middleware\LockedAdmin::class,
        ],

        'api' => [
            \App\Http\Middleware\ProductionSurface::class,
            'throttle:60,1',
            'bindings',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
            \App\Http\Middleware\ResolveTenantUploads::class,
            \App\Http\Middleware\RefreshPrivateMedia::class,
            // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'input.request' => \App\CoreFacturalo\InputRequest::class,
        //'module' => \App\Http\Middleware\CheckModule::class,
        'redirect.module' => \App\Http\Middleware\RedirectModule::class,
        'redirect.level' => \App\Http\Middleware\RedirectModuleLevel::class,
        'locked.tenant' => \App\Http\Middleware\LockedTenant::class,
        //'locked.admin' => \App\Http\Middleware\LockedAdmin::class,
        'tenant.auto.login' => \Modules\MultiUser\Http\Middleware\Tenant\AutoLogin::class,
        'check.email.verified' => \Modules\LevelAccess\Http\Middleware\CheckEmailIsVerified::class,
        'enable.guest.register' => \App\Http\Middleware\EnableGuestRegister::class,
        'reseller.system.admin' => \App\Http\Middleware\EnsureResellerSystemAdminPermissions::class,
    ];
}
