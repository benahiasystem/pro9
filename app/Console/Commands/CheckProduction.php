<?php

namespace App\Console\Commands;

use App\Services\Storage\TenantStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class CheckProduction extends Command
{
    protected $signature = 'sab:check-production';
    protected $description = 'Comprueba configuración, roles y acceso a los servicios de producción';

    public function handle(TenantStorage $storage): int
    {
        if (!app()->isProduction() || config('app.debug')
            || !config('tenant.force_https') || !config('session.secure')
            || config('session.domain') !== null
            || config('tenant_storage.disk') !== 'supabase-private'
            || !config('app.key') || !config('tenancy.key')
            || config('app.key') === config('tenancy.key')) {
            $this->error('La configuración de seguridad de producción está incompleta.');
            return self::FAILURE;
        }

        foreach (['system', 'provisioner'] as $role) {
            try {
                $db = DB::connection($role);
                $host = $db->getConfig('host');
                if (!is_string($host) || !preg_match('/\Adb\.[a-z0-9]+\.supabase\.co\z/', $host)
                    || $db->getConfig('sslmode') !== 'verify-full'
                    || !is_readable($db->getConfig('sslrootcert') ?? '')) {
                    throw new \RuntimeException();
                }
                $capabilities = $db->selectOne('SELECT rolsuper::int AS superuser, rolcreatedb::int AS createdb, rolcreaterole::int AS createrole FROM pg_roles WHERE rolname = current_user');
                $canProvision = $role === 'provisioner' ? 1 : 0;
                if ((int) $capabilities->superuser !== 0 || (int) $capabilities->createdb !== $canProvision
                    || (int) $capabilities->createrole !== $canProvision
                    || intdiv((int) $db->selectOne('SHOW server_version_num')->server_version_num, 10000) !== 17) {
                    throw new \RuntimeException();
                }
            } catch (Throwable) {
                $this->error('Verificación PostgreSQL fallida para '.$role.'.');
                return self::FAILURE;
            }
        }

        try {
            Redis::connection()->ping();
        } catch (Throwable) {
            $this->error('Redis no está disponible.');
            return self::FAILURE;
        }

        try {
            foreach ($storage->systemMedia()->listing() as $object) {
                break;
            }
        } catch (Throwable) {
            $this->error('El bucket de Supabase Storage no está disponible.');
            return self::FAILURE;
        }

        $this->info('Conexiones PostgreSQL, Redis y Supabase Storage verificadas.');
        return self::SUCCESS;
    }
}
