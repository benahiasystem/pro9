<?php

namespace App\Tenancy;

use App\Database\PostgresBootstrap;
use App\Database\SqlErrors;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// ########## INICIO CAMBIO POSTGRESQL 17
final class TenantProvisioning
{
    private function control() { return DB::connection('provisioner'); }
    private function ledger()
    {
        if (!config('database.connections.provisioning_ledger')) config(['database.connections.provisioning_ledger' => config('database.connections.system')]);
        return DB::connection('provisioning_ledger');
    }
    private function update(string $id, string $state, array $resources, ?string $error = null): void
    {
        $this->ledger()->table('tenant_provisioning_runs')->where('id', $id)->update(['state' => $state, 'resources' => json_encode($resources, JSON_THROW_ON_ERROR), 'error_code' => $error ?: null, 'updated_at' => now()]);
    }
    private function begin(string $name, string $operation, ?int $website, array $resources = []): string
    {
        PostgresBootstrap::identifier($name);
        $id = (string) Str::uuid();
        $this->ledger()->table('tenant_provisioning_runs')->insert(['id' => $id, 'tenant_name' => $name, 'website_id' => $website, 'operation' => $operation, 'state' => 'prepared', 'resources' => json_encode($resources), 'created_at' => now(), 'updated_at' => now()]);
        return $id;
    }
    private function role(string $name)
    {
        return $this->control()->selectOne('SELECT oid, rolname, shobj_description(oid, \'pg_authid\') AS marker FROM pg_roles WHERE rolname=?', [$name]);
    }
    private function database(string $name)
    {
        return $this->control()->selectOne('SELECT oid, datdba, datname, datallowconn::int AS datallowconn FROM pg_database WHERE datname=?', [$name]);
    }
    private function disconnectClients(string $name): void
    {
        $this->control()->select("SELECT pg_terminate_backend(pid, 5000) FROM pg_stat_activity WHERE datname=? AND backend_type='client backend' AND pid<>pg_backend_pid()", [$name]);
    }
    private function dropDatabase(string $name, bool $allowsConnections): void
    {
        $q = PostgresBootstrap::identifier($name);
        try {
            // PostgreSQL can stop its own autovacuum worker during an ordinary drop.
            $this->control()->statement('DROP DATABASE '.$q);
        } catch (\Illuminate\Database\QueryException $error) {
            if (SqlErrors::state($error) !== '55006') throw $error;
            if ($allowsConnections) $this->control()->statement('ALTER DATABASE '.$q.' ALLOW_CONNECTIONS false');
            try {
                $this->disconnectClients($name);
                $this->control()->statement('DROP DATABASE '.$q);
            } catch (\Throwable $failure) {
                if ($allowsConnections && $this->database($name)) $this->control()->statement('ALTER DATABASE '.$q.' ALLOW_CONNECTIONS true');
                throw $failure;
            }
        }
    }
    /** Password-bearing DDL bypasses Laravel query logging and exception SQL interpolation. */
    private function secretDdl(string $sql): void
    {
        try { $this->control()->getPdo()->exec($sql); }
        catch (\PDOException $error) { throw new \RuntimeException('No se pudo configurar la credencial PostgreSQL (SQLSTATE '.SqlErrors::state($error).').'); }
    }
    public function create(array $config, ?int $website, callable $install, bool $bootstrap = true): string
    {
        $name = $config['database'];
        if ($config['username'] !== $name) throw new \InvalidArgumentException('El propietario debe coincidir con el identificador tenant.');
        $q = PostgresBootstrap::identifier($name);
        if ($this->control()->transactionLevel()) throw new \LogicException('CREATE DATABASE debe ejecutarse fuera de transacciones.');
        $resources = TenantOnboarding::active() ? ['website_id'=>$website,'onboarding'=>true] : [];
        $id = $this->begin($name, 'create', TenantOnboarding::active() ? null : $website, $resources);
        try {
            if ($this->role($name) || $this->database($name)) throw new \LogicException('Ya existe un recurso con ese identificador tenant.');
            $this->control()->transaction(function () use ($q, $config, $id) {
                $this->secretDdl('CREATE ROLE '.$q.' LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION PASSWORD '.$this->control()->getPdo()->quote($config['password']));
                $this->control()->statement('COMMENT ON ROLE '.$q.' IS '.$this->control()->getPdo()->quote('pro9:'.$id));
                $current = PostgresBootstrap::identifier($this->control()->selectOne('SELECT current_user AS name')->name);
                $this->control()->statement('GRANT '.$q.' TO '.$current.' WITH SET TRUE, INHERIT TRUE');
            });
            $resources['role'] = ['name' => $name, 'oid' => $this->role($name)->oid];
            $this->update($id, 'prepared', $resources);
            $this->control()->statement('CREATE DATABASE '.$q.' OWNER '.$q." TEMPLATE template0 ENCODING 'UTF8'");
            $resources['database'] = ['name' => $name, 'oid' => $this->database($name)->oid];
            $this->update($id, 'provisioned', $resources);
            $this->control()->statement('REVOKE ALL ON DATABASE '.$q.' FROM PUBLIC');
            config(['database.connections.pro9_provisioning_target' => $config]);
            DB::purge('pro9_provisioning_target');
            $target = DB::connection('pro9_provisioning_target');
            $target->statement('REVOKE ALL ON SCHEMA public FROM PUBLIC');
            if ($bootstrap) PostgresBootstrap::install($target);
            DB::purge('pro9_provisioning_target');
            $install();
            $this->update($id, 'migrated', $resources);
            if (!TenantOnboarding::active()) $this->update($id, 'ready', $resources);
            return $id;
        } catch (\Throwable $error) {
            try { $this->compensate($id); }
            catch (\Throwable $cleanup) { $this->update($id, 'failed', $resources, SqlErrors::state($cleanup)); }
            throw $error;
        } finally { DB::purge('pro9_provisioning_target'); }
    }
    public function recover(string $id, bool $dryRun = true): array
    {
        $run = $this->ledger()->table('tenant_provisioning_runs')->where('id', $id)->first();
        if ($run && $run->operation === 'create' && $run->state === 'compensated') return ['id'=>$id,'tenant'=>$run->tenant_name,'state'=>$run->state,'action'=>'none','dry_run'=>$dryRun];
        if (!$run || $run->operation !== 'create' || $run->state === 'ready') throw new \LogicException('El intento no admite compensación de creación.');
        $resources = json_decode($run->resources, true, 512, JSON_THROW_ON_ERROR);
        $published = !empty($resources['onboarding']) && $this->ledger()->table('websites')->where('id', $resources['website_id'])->where('uuid', $run->tenant_name)->whereNull('deleted_at')->exists();
        if (!$dryRun) {
            if ($published) {
                $config = array_merge(config('database.connections.provisioner'), ['database'=>$run->tenant_name]);
                config(['database.connections.pro9_recovery_validation'=>$config]); DB::purge('pro9_recovery_validation');
                try { foreach (['companies','configurations','users'] as $table) if (!DB::connection('pro9_recovery_validation')->table($table)->exists()) throw new \LogicException('El alta publicada requiere revisión antes de habilitarla.'); }
                finally { DB::purge('pro9_recovery_validation'); }
                $this->complete($id, $resources['website_id']);
            } else $this->compensate($id);
        }
        return ['id'=>$id,'tenant'=>$run->tenant_name,'state'=>$run->state,'action'=>$published?'finalize':'compensate','dry_run'=>$dryRun];
    }
    public function complete(string $id, int $website): void
    {
        $run = $this->ledger()->table('tenant_provisioning_runs')->where('id', $id)->first();
        if (!$run || $run->state !== 'migrated') throw new \LogicException('El alta no está lista para publicarse.');
        $role = $this->role($run->tenant_name); $database = $this->database($run->tenant_name);
        if (!$role || !$database || $role->marker !== 'pro9:'.$id || (string)$database->datdba !== (string)$role->oid) throw new \LogicException('La propiedad del alta no coincide.');
        $resources = json_decode($run->resources, true, 512, JSON_THROW_ON_ERROR);
        $this->ledger()->transaction(function () use ($id, $resources, $website, $run) {
            if (!$this->ledger()->table('websites')->where('id', $website)->where('uuid', $run->tenant_name)->lockForUpdate()->first()) throw new \LogicException('No existe la metadata central del alta.');
            $this->update($id, 'ready', $resources);
            $this->ledger()->table('tenant_provisioning_runs')->where('id', $id)->update(['website_id'=>$website]);
        });
        app('cache')->forget('tenancy.website.'.$run->tenant_name);
        foreach ($this->ledger()->table('hostnames')->where('website_id', $website)->pluck('fqdn') as $fqdn) app('cache')->forget('tenancy.hostname.'.$fqdn);
    }
    private function compensate(string $id): void
    {
        $run = $this->ledger()->table('tenant_provisioning_runs')->where('id', $id)->first();
        if (!$run || $run->operation !== 'create') throw new \LogicException('Operación ajena.');
        $resources = json_decode($run->resources, true, 512, JSON_THROW_ON_ERROR);
        $this->update($id, 'compensating', $resources);
        $role = $this->role($run->tenant_name);
        if (!$role) {
            if ($this->database($run->tenant_name)) throw new \LogicException('Existe una base sin el rol propietario acreditado.');
        } elseif ($role->marker === 'pro9:'.$id) {
            $db = $this->database($run->tenant_name);
            if ($db && (string) $db->datdba !== (string) $role->oid) throw new \LogicException('La base no pertenece al recurso creado por el intento.');
            DB::purge('tenant');
            DB::purge('pro9_provisioning_target');
            if ($db) $this->dropDatabase($run->tenant_name, (bool) $db->datallowconn);
            $this->control()->statement('DROP ROLE '.PostgresBootstrap::identifier($run->tenant_name));
        } elseif (isset($resources['role']) || isset($resources['database'])) throw new \LogicException('La propiedad del recurso cambió; no se elimina.');
        $this->update($id, 'compensated', $resources);
    }
    public function delete(string $name, bool $deleteRole = true): void
    {
        $q = PostgresBootstrap::identifier($name);
        $role = $this->role($name); $db = $this->database($name);
        if (!$role || !str_starts_with($role->marker ?? '', 'pro9:') || ($db && (string) $db->datdba !== (string) $role->oid)) throw new \LogicException('No se acredita la propiedad del tenant.');
        $id = $this->begin($name, 'delete', null, ['role_oid' => $role->oid, 'database_oid' => $db->oid ?? null]);
        try {
            if ($db) $this->dropDatabase($name, (bool) $db->datallowconn);
            if ($deleteRole) $this->control()->statement('DROP ROLE '.$q);
            $this->update($id, 'ready', []);
        } catch (\Throwable $error) { $this->update($id, 'failed', [], SqlErrors::state($error)); throw $error; }
    }
    public function password(string $name, string $password): void
    {
        if (!str_starts_with($this->role($name)->marker ?? '', 'pro9:')) throw new \LogicException('No se acredita la propiedad del rol.');
        $this->secretDdl('ALTER ROLE '.PostgresBootstrap::identifier($name).' PASSWORD '.$this->control()->getPdo()->quote($password));
    }
    public function rename(string $old, array $config, string $previousPassword, bool $disconnectClients = true): void
    {
        $new = $config['database']; $qo = PostgresBootstrap::identifier($old); $qn = PostgresBootstrap::identifier($new);
        $role = $this->role($old); $db = $this->database($old);
        if (!$role || !$db || !str_starts_with($role->marker ?? '', 'pro9:') || (string) $role->oid !== (string) $db->datdba) throw new \LogicException('No se acredita la propiedad del tenant.');
        if ($this->role($new) || $this->database($new)) throw new \LogicException('El destino ya existe.');
        $resources=['old' => $old, 'role_oid' => $role->oid, 'database_oid' => $db->oid];
        $id = $this->begin($new, 'rename', null, $resources);
        $renamed = false;$roleRenamed=false;
        try {
            $rename = 'ALTER DATABASE '.$qo.' RENAME TO '.$qn;
            try {
                $this->control()->statement($rename);
            } catch (\Illuminate\Database\QueryException $error) {
                if (!$disconnectClients || SqlErrors::state($error) !== '55006') throw $error;
                $this->disconnectClients($old);
                $this->control()->statement($rename);
            }
            $renamed = true;
            $this->control()->transaction(function () use ($qo, $qn, $config, $new) {
                $this->control()->statement('ALTER ROLE '.$qo.' RENAME TO '.$qn);
                $this->password($new, $config['password']);
            });
            $roleRenamed=true;
            $this->update($id, 'ready', $resources);
        } catch (\Throwable $error) {
            try {
                if ($roleRenamed) $this->control()->transaction(function () use ($qo,$qn,$old,$previousPassword) {
                    $this->control()->statement('ALTER ROLE '.$qn.' RENAME TO '.$qo);
                    $this->password($old,$previousPassword);
                });
                if ($renamed) $this->control()->statement('ALTER DATABASE '.$qn.' RENAME TO '.$qo);
                $this->update($id, 'compensated', $resources);
            } catch (\Throwable $cleanup) {
                $this->update($id,'failed',$resources,SqlErrors::state($cleanup));
                throw new \RuntimeException('El renombrado requiere revisión manual; operación '.$id,0,$error);
            }
            throw $error;
        }
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
