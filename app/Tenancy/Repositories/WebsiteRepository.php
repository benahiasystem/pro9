<?php

namespace App\Tenancy\Repositories;

use App\Tenancy\Database\Connection;
use App\Tenancy\Environment;
use App\Tenancy\Models\Website;
use App\Tenancy\TenantOnboarding;
use App\Tenancy\TenantProvisioning;
use Illuminate\Database\Eloquent\Builder;

class WebsiteRepository
{
    public function __construct(private Connection $connection, private TenantProvisioning $provisioning)
    {
    }

    public function query(): Builder
    {
        return Website::query();
    }

    public function findById($id): ?Website
    {
        return $this->query()->find($id);
    }

    public function findByUuid(string $uuid): ?Website
    {
        return $this->query()->where('uuid', $uuid)->first();
    }

    public function create(Website $website): Website
    {
        if ($website->exists) {
            return $this->update($website);
        }

        $website->uuid ??= bin2hex(random_bytes(10));
        if (config('tenancy.db.auto-create-tenant-database', true)
            && config('tenancy.db.tenant-division-mode') === 'database'
            && $this->connection->system($website)->getDriverName() !== 'pgsql') {
            throw new \LogicException('El aprovisionamiento automático requiere PostgreSQL.');
        }
        $website->saveOrFail();

        if (config('tenancy.db.auto-create-tenant-database', true)
            && config('tenancy.db.tenant-division-mode') === 'database') {
            $config = $this->connection->generateConfigurationArray($website);
            $run = $this->provisioning->create($config, $website->id, function () use ($website) {
                if (!$this->connection->migrate($website)) {
                    throw new \RuntimeException('No se completaron las migraciones tenant.');
                }
                $seeder = config('tenancy.db.tenant-seed-class');
                if ($seeder && !$this->connection->seed($website, $seeder)) {
                    throw new \RuntimeException('No se completaron las semillas tenant.');
                }
            });
            TenantOnboarding::track($run, $website->id, $config);
        }

        return $website;
    }

    public function update(Website $website): Website
    {
        if (!$website->exists) {
            return $this->create($website);
        }
        $oldUuid = $website->getOriginal('uuid');
        if ($website->isDirty('uuid') && $oldUuid && config('tenancy.db.tenant-division-mode') === 'database') {
            $previous = clone $website;
            $previous->uuid = $oldUuid;
            $current = app(Environment::class)->tenant();
            if ($current?->getKey() === $website->getKey()) {
                app(Environment::class)->switchTenant(null);
            }
            $this->provisioning->rename(
                $oldUuid,
                $this->connection->generateConfigurationArray($website),
                $this->connection->generateConfigurationArray($previous)['password']
            );
        }
        $website->saveOrFail();
        return $website;
    }

    public function delete(Website $website, bool $hard = false): Website
    {
        if (config('tenancy.db.auto-delete-tenant-database') && config('tenancy.db.tenant-division-mode') === 'database') {
            if (app(Environment::class)->tenant()?->getKey() === $website->getKey()) {
                app(Environment::class)->switchTenant(null);
            }
            $this->provisioning->delete($website->uuid, (bool) config('tenancy.db.auto-delete-tenant-database-user'));
        }
        $hard ? $website->forceDelete() : $website->delete();
        return $website;
    }
}
