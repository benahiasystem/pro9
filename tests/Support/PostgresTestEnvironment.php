<?php

namespace Tests\Support;

// ########## INICIO CAMBIO POSTGRESQL 17
final class PostgresTestEnvironment
{
    /** Host-captured source provenance, independent of a host-only worktree .git pointer. */
    public static function source(?string $root=null): array
    {
        $runId=getenv('SAB_POSTGRES_RUN_ID');
        if (getenv('SAB_POSTGRES_TESTS')!=='1' || !is_string($runId) || !preg_match('/^sab-pgtest-[a-f0-9]{12}$/D',$runId)) {
            throw new \LogicException('Utilice el ejecutor PostgreSQL temporal para identificar las fuentes.');
        }
        $path=($root ?? dirname(__DIR__,2)).'/informes/evidencias/postgresql_17_implementacion/resultados/'.$runId.'-fuentes.json';
        if (!is_file($path)) throw new \RuntimeException('Falta el inventario de fuentes de esta ejecución.');
        $source=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
        if (!is_array($source) || !is_array($source['files'] ?? null) || !$source['files']
            || !is_string($source['sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D',$source['sha256'])
            || !is_string($source['revision'] ?? null) || !preg_match('/^(?:[a-f0-9]{40}|[a-f0-9]{64})$/D',$source['revision'])
            || !hash_equals($source['sha256'],hash('sha256',json_encode($source['files'],JSON_THROW_ON_ERROR)))) {
            throw new \RuntimeException('El inventario de fuentes está vacío o no corresponde a su huella.');
        }
        foreach ($source['files'] as $file=>$hash) {
            if (!is_string($file) || str_starts_with($file,'/') || in_array('..',explode('/',$file),true)
                || ($hash!==null && (!is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D',$hash)))) {
                throw new \RuntimeException('El inventario contiene una ruta o huella inválida.');
            }
        }
        return $source;
    }

    public static function connection(): array
    {
        if (getenv('SAB_POSTGRES_TESTS') !== '1' || !getenv('SAB_POSTGRES_RUN_ID')) throw new \LogicException('Utilice el ejecutor de PostgreSQL temporal.');
        \Illuminate\Database\Connection::resolverFor('pgsql', static fn ($pdo, $database, $prefix, $config) => new \App\Database\PostgresConnection($pdo, $database, $prefix, $config));
        return ['driver' => 'pgsql', 'host' => getenv('DB_HOST'), 'port' => getenv('DB_PORT') ?: 5432,
            'username' => getenv('DB_USERNAME'), 'password' => getenv('DB_PASSWORD'), 'database' => 'postgres',
            'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public', 'sslmode' => 'disable', 'timezone' => 'America/Caracas'];
    }
    public static function assertServer(\Illuminate\Database\Connection $db): void
    {
        $marker = $db->selectOne("SELECT current_setting('sab.test_run', true) AS marker")->marker;
        if (!$marker || !hash_equals(getenv('SAB_POSTGRES_RUN_ID'), $marker)) throw new \LogicException('El servidor no pertenece a esta ejecución temporal.');
        if ((int) $db->selectOne('SHOW server_version_num')->server_version_num !== 170011) throw new \LogicException('Se requiere PostgreSQL 17.11.');
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
