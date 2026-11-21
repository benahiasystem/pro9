<?php

namespace App\Tenancy\Database;

use App\Tenancy\Environment;
use App\Tenancy\Models\Hostname;
use App\Tenancy\Models\Website;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tenancy\Affects\Connections\Contracts\ProvidesConfiguration;
use Tenancy\Identification\Contracts\Tenant;

class Connection implements ProvidesConfiguration
{
    public const DEFAULT_SYSTEM_NAME = 'system';
    public const DEFAULT_TENANT_NAME = 'tenant';

    public function tenantName(): string
    {
        return config('tenancy.db.tenant-connection-name', self::DEFAULT_TENANT_NAME);
    }

    public function systemName(): string
    {
        return config('tenancy.db.system-connection-name', self::DEFAULT_SYSTEM_NAME);
    }

    public function system(Website|Hostname|null $for = null): \Illuminate\Database\Connection
    {
        $website = $for instanceof Hostname ? $for->website : $for;
        return DB::connection($website?->managed_by_database_connection ?: $this->systemName());
    }

    public function get(): \Illuminate\Database\Connection
    {
        return DB::connection($this->tenantName());
    }

    public function exists(?string $connection = null): bool
    {
        return isset(DB::getConnections()[$connection ?? $this->tenantName()]);
    }

    public function configuration(?string $connection = null): array
    {
        return config('database.connections.'.($connection ?? $this->tenantName())) ?? [];
    }

    public function purge(?string $connection = null): void
    {
        $connection ??= $this->tenantName();
        DB::purge($connection);
        config(['database.connections.'.$connection => []]);
    }

    public function set(Website|Hostname|null $for, ?string $connection = null): bool
    {
        $website = $for instanceof Hostname ? $for->website : $for;
        if ($connection === null || $connection === $this->tenantName()) {
            app(Environment::class)->switchTenant($website);
        } else {
            $this->purge($connection);
            config(['database.connections.'.$connection => $website ? $this->generateConfigurationArray($website) : []]);
        }
        return true;
    }

    public function configure(Tenant $tenant): array
    {
        if (!$tenant instanceof Website) {
            throw new \InvalidArgumentException('Se requiere un website de SAB.');
        }
        return $this->generateConfigurationArray($tenant);
    }

    public function generateConfigurationArray(Website $website): array
    {
        $name = $website->managed_by_database_connection ?? $this->systemName();
        $configuration = config('database.connections.'.$name);
        if (!is_array($configuration) || empty($configuration['driver'])) {
            throw new \LogicException('La conexión administradora del website no existe.');
        }

        $configuration['uuid'] = $website->uuid;
        $mode = config('tenancy.db.tenant-division-mode', 'database');
        if ($mode === 'bypass') {
            return $configuration;
        }
        if ($mode !== 'database') {
            throw new \LogicException('SAB requiere una base PostgreSQL por empresa.');
        }
        if (!is_string($website->uuid) || !preg_match('/\A[A-Za-z0-9_][A-Za-z0-9_-]{0,62}\z/D', $website->uuid)) {
            throw new \LogicException('El identificador PostgreSQL de la empresa no es válido.');
        }

        $configuration['password'] = self::legacyPassword($website, config('tenancy.key'), config('app.key'));
        $configuration['database'] = $configuration['username'] = $website->uuid;
        unset($configuration['url'], $configuration['read'], $configuration['write']);

        return $configuration;
    }

    public static function legacyPassword(Website $website, ?string $key, ?string $appKey): string
    {
        // Keep the exact derivation used by the already provisioned PostgreSQL roles.
        return $key === null
            ? md5(sprintf('%s.%d', $appKey, $website->id))
            : md5(sprintf('%d.%s.%s.%s', $website->id, $website->uuid, $website->created_at, $key));
    }

    public function migrate(Website|Hostname $for, ?string $path = null): bool
    {
        $path = realpath($path ?? database_path('migrations/tenant'));
        return $path !== false && $this->runFor($for, 'migrate', ['--path' => [$path], '--realpath' => true]);
    }

    public function seed(Website|Hostname $for, ?string $class = null): bool
    {
        return $this->runFor($for, 'db:seed', ['--class' => $class ?? config('tenancy.db.tenant-seed-class')]);
    }

    private function runFor(Website|Hostname $for, string $command, array $options): bool
    {
        $website = $for instanceof Hostname ? $for->website : $for;
        if (!$website) {
            throw new \LogicException('El dominio no está asociado a una empresa.');
        }
        return app(Environment::class)->run($website, fn () => Artisan::call(
            $command, $options + ['--database' => $this->tenantName(), '--force' => true]
        ) === 0);
    }
}
