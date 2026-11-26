<?php

namespace Modules\Accounting\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\{Route, RateLimiter};
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Modules\Accounting\Domain\CompanyContext;
final class AccountingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'accounting');
        $this->app->scoped(CompanyContext::class, fn() => new CompanyContext());
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        $this->app->scoped(\Modules\Accounting\Support\PrivateStorage::class);
        $this->app->terminating(fn()=>app(\Modules\Accounting\Support\PrivateStorage::class)->cleanup());
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        $this->app->singleton('accounting.errors', \Modules\Accounting\Http\Middleware\ApiErrors::class);
    }
    public function boot(): void
    {
        $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
        $kernel->prependToMiddlewarePriority(\Modules\Accounting\Http\Middleware\TenantRequired::class);
        $kernel->prependToMiddlewarePriority(\Modules\Accounting\Http\Middleware\ApiErrors::class);
        $this->app->booted(function () {
            Route::prefix('api/accounting/v1')->middleware(['api', \Modules\Accounting\Http\Middleware\ApiErrors::class, \Modules\Accounting\Http\Middleware\TenantRequired::class, 'auth:api', 'locked.tenant', \Modules\Accounting\Http\Middleware\ActiveUser::class])->group(__DIR__ . '/../Routes/api.php');
            Route::get('contabilidad/{path?}', \Modules\Accounting\Http\Controllers\FrontendController::class)->where('path', '.*')->middleware(\Modules\Accounting\Http\Middleware\TenantRequired::class);
        });
        RateLimiter::for('accounting-mfa', fn(Request $r) => Limit::perMinute(5)->by((string) $r->user()?->id . '|' . $r->ip()));
        if ($this->app->runningInConsole()) {
            $this->commands([\Modules\Accounting\Console\InstallAccounting::class]);
        }
    }
}
