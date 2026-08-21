<?php

namespace Modules\CashReport\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    protected $moduleNamespace = 'Modules\CashReport\Http\Controllers';

    public function map()
    {
        Route::middleware('web')
            ->namespace($this->moduleNamespace)
            ->group(__DIR__.'/../Routes/web.php');
    }
}
