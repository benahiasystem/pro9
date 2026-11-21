<?php

namespace App\Providers;

use App\Database\PostgresConnection;
use App\Database\PostgresMigrator;
use App\Tenancy\Contracts\CurrentHostname;
use App\Tenancy\Contracts\Tenant;
use App\Tenancy\Database\Connection;
use App\Tenancy\Environment;
use App\Tenancy\Repositories\HostnameRepository;
use App\Tenancy\TenantDispatcherMiddleware;
use App\Tenancy\TenantJobContext;
use App\Tenancy\TenantReadiness;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Queue;
use Illuminate\Support\ServiceProvider;
use Tenancy\Affects\Connections\Events\Resolving;

class PostgresServiceProvider extends ServiceProvider
{
    private static bool $queuePayloadRegistered = false;

    public function register(): void
    {
        \Illuminate\Database\Connection::resolverFor('pgsql', static fn ($pdo, $database, $prefix, $config) => new PostgresConnection($pdo, $database, $prefix, $config));
        $this->app->extend('migrator', function ($existing, $app) {
            $migrator = new PostgresMigrator($app['migration.repository'], $app['db'], $app['files'], $app['events']);
            foreach ($existing->paths() as $path) {
                $migrator->path($path);
            }
            return $migrator;
        });

        $this->app->singleton(Environment::class);
        $this->app->singleton(Tenant::class, static fn () => null);
        $this->app->singleton(Environment::REQUEST_HOSTNAME, function ($app) {
            if (!config('tenancy.hostname.auto-identification')
                || $app->runningInConsole()
                || !$app->bound('request')) {
                return null;
            }

            $connection = $app->make(Connection::class)->system();
            if (!$connection->getSchemaBuilder()->hasTable('hostnames')) {
                return null;
            }
            $hostname = $app->make(HostnameRepository::class)->findByHostname($app['request']->getHost());
            if (!$hostname?->website) {
                return null;
            }

            TenantReadiness::ensure($hostname->website->uuid);
            $app->make(Environment::class)->switchTenant($hostname->website);
            return $hostname;
        });
        $this->app->bind(CurrentHostname::class, static function ($app) {
            $hostname = $app->make(Environment::REQUEST_HOSTNAME);
            $website = $app->make(Tenant::class);
            return $hostname && $website && (string) $hostname->website_id === (string) $website->getKey()
                ? $hostname
                : null;
        });
    }

    public function boot(): void
    {
        $events = $this->app['events'];
        config([
            'tenancy.connections.tenant-connection-name' => config('tenancy.db.tenant-connection-name', 'tenant'),
            'filesystems.disks.tenancy-default' => ['driver' => 'local', 'root' => storage_path('app/tenancy/tenants')],
        ]);
        $events->listen(Resolving::class, static fn (Resolving $event) => $event->tenant ? app(Connection::class) : null);
        $events->listen(JobProcessing::class, static fn ($event) => TenantJobContext::begin($event->job));
        foreach ([JobProcessed::class, JobExceptionOccurred::class, JobFailed::class] as $event) {
            $events->listen($event, static fn ($event) => TenantJobContext::finish($event->job));
        }
        $this->app->make(Dispatcher::class)->pipeThrough([TenantDispatcherMiddleware::class]);

        if (!self::$queuePayloadRegistered) {
            Queue::createPayloadUsing(static fn ($connection, $queue, $payload) => [
                'website_id' => data_get($payload, 'data.command')->website_id ?? app(Environment::class)->tenant()?->getKey(),
            ]);
            self::$queuePayloadRegistered = true;
        }
    }
}
