<?php

namespace Modules\CashReport\Providers;

use Illuminate\Support\ServiceProvider;

class CashReportServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->registerViews();
    }

    public function register()
    {
        $this->app->register(RouteServiceProvider::class);
    }

    public function registerViews()
    {
        $viewPath = resource_path('views/modules/cashreport');
        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([$sourcePath => $viewPath], 'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path.'/modules/cashreport';
        }, \Config::get('view.paths')), [$sourcePath]), 'cashreport');
    }

    public function provides()
    {
        return [];
    }
}
