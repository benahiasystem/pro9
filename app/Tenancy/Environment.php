<?php

namespace App\Tenancy;

use App\Tenancy\Contracts\CurrentHostname;
use App\Tenancy\Contracts\Tenant;
use App\Tenancy\Database\Connection;
use App\Tenancy\Models\Hostname;
use App\Tenancy\Models\Website;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Tenancy\Environment as TenancyEnvironment;

class Environment
{
    public const REQUEST_HOSTNAME = 'sab.tenancy.request-hostname';

    public function __construct(
        private Application $app,
        private TenancyEnvironment $environment,
        private Connection $connection
    ) {
    }

    public function tenant(?Website $website = null): ?Website
    {
        if ($website !== null) {
            $this->switchTenant($website);
        }
        return $this->app->make(Tenant::class);
    }

    public function hostname(?Hostname $hostname = null): ?Hostname
    {
        if ($hostname !== null) {
            $this->app->instance(CurrentHostname::class, $hostname);
            return $hostname;
        }
        $host = $this->app->make(CurrentHostname::class);
        $website = $this->tenant();
        if (!$website || !$website->getKey()) {
            return $host;
        }
        if ($host && (string) $host->website_id === (string) $website->getKey()) {
            return $host;
        }
        return $website->hostnames()->orderBy('id')->first();
    }

    public function website(): ?Website
    {
        return $this->hostname()?->website;
    }

    public function switchTenant(?Website $website): void
    {
        if (!$website) {
            $this->environment->setTenant(null)->setIdentified(false);
            $this->connection->purge();
        } else {
            if (!$website->getKey()) {
                throw new \LogicException('El website debe estar persistido antes de activar el contexto.');
            }
            if (($this->connection->configuration()['uuid'] ?? null) !== $website->uuid) {
                $this->connection->purge();
            }
            try {
                $this->environment->setTenant($website);
            } catch (\Throwable $error) {
                $this->switchTenant(null);
                throw $error;
            }
        }

        $this->app->instance(Tenant::class, $website);
        if ($this->app->resolved('filesystem')) {
            $this->app['filesystem']->forgetDisk('tenant');
        }
        $disk = config('tenancy.website.disk') ?? 'tenancy-default';
        $filesystem = config('filesystems.disks.'.$disk, []);
        if ($website && $disk !== false) {
            $filesystem['root'] = rtrim($filesystem['root'], '/').'/'.$website->uuid;
        }
        config(['filesystems.disks.tenant' => $website && $disk !== false ? $filesystem : []]);

        if (config('cache.default') === 'redis') {
            $this->app['cache']->store('redis')->setPrefix($website?->uuid ?? config('cache.prefix'));
        }
    }

    public function run(Website $website, callable $callback)
    {
        $previous = $this->tenant();
        $default = DB::getDefaultConnection();
        try {
            $this->switchTenant($website);
            return $callback();
        } finally {
            DB::setDefaultConnection($default);
            $this->switchTenant($previous);
        }
    }
}
