<?php

namespace App\Providers;

// ########## INICIO CAMBIO POSTGRESQL 17
class PostgresServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register()
    {
        $this->app->bind(\Hyn\Tenancy\Queue\DispatcherMiddleware::class, \App\Tenancy\TenantDispatcherMiddleware::class);
        $this->app->bind(\Hyn\Tenancy\Listeners\Database\MigratesTenants::class, \App\Tenancy\MigratesTenants::class);
        \Illuminate\Database\Connection::resolverFor('pgsql', static fn ($pdo, $database, $prefix, $config) => new \App\Database\PostgresConnection($pdo, $database, $prefix, $config));
        $this->app->extend('tenancy.db.drivers', function ($drivers) {
            return $drivers->put('pgsql', \App\Tenancy\PostgresDriver::class);
        });
        $this->app->extend('migrator', function ($existing, $app) {
            $migrator = new \App\Database\PostgresMigrator($app['migration.repository'], $app['db'], $app['files'], $app['events']);
            foreach ($existing->paths() as $path) $migrator->path($path);
            return $migrator;
        });
    }
    public function boot()
    {
        $events=$this->app['events'];
        $start=static function ($event) {\App\Tenancy\TenantJobContext::begin($event->job);};
        $events->listen(\Illuminate\Queue\Events\JobProcessing::class,$start);
        // Hyn may have registered its switch listener before this provider.
        // Capture the caller's context first, preserving every other listener.
        $this->app->booted(static function () use ($events,$start) {
            $event=\Illuminate\Queue\Events\JobProcessing::class;
            $listeners=$events->getRawListeners()[$event] ?? [];
            $events->forget($event);$events->listen($event,$start);
            foreach ($listeners as $listener) if ($listener!==$start) $events->listen($event,$listener);
        });
        foreach ([\Illuminate\Queue\Events\JobProcessed::class,\Illuminate\Queue\Events\JobExceptionOccurred::class,\Illuminate\Queue\Events\JobFailed::class] as $event) {
            $events->listen($event,static fn ($event) => \App\Tenancy\TenantJobContext::finish($event->job));
        }
    }

}
// ######### FIN CAMBIO POSTGRESQL 17
