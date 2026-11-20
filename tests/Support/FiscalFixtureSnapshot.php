<?php
namespace Tests\Support;

use Illuminate\Database\Connection;
use App\Database\PostgresBackup;
use App\Database\SchemaInspector;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Private native dump, valid only for this process and this schema revision. */
final class FiscalFixtureSnapshot
{
    private static ?string $path = null;
    private static ?string $key = null;
    private static ?string $template = null;
    private static ?int $ownerPid = null;
    private static ?\PDO $control = null;
    private static function key(): string
    {
        $files = array_merge(glob(database_path('migrations/tenant/*.php')), glob(database_path('seeders/*.php')), glob(database_path('seeders/data/*.php')));
        $files = array_merge($files, glob(app_path('Database/*.php')));
        sort($files); $hash=hash_init('sha256');hash_update($hash,'PostgreSQL 17.11');
        foreach ($files as $file) hash_update($hash,basename($file).hash_file('sha256',$file));
        return hash_final($hash);
    }
    public static function capture(Connection $db): void
    {
        self::assertTemporaryDatabase($db);
        if (self::$path && is_file(self::$path)) unlink(self::$path);
        self::$path = tempnam(sys_get_temp_dir(),'pro9-pg-fixture-');
        (new PostgresBackup())->dump($db->getConfig(),self::$path);
        self::$key=self::key();
        register_shutdown_function(static function (): void { if (self::$path && is_file(self::$path)) unlink(self::$path); });
        if (getenv('PRO9_POSTGRES_FAST_FIXTURES') === '1') {
            self::$ownerPid = getmypid();
            $config = PostgresTestEnvironment::connection();
            self::$control = new \PDO('pgsql:host='.$config['host'].';port='.$config['port'].';dbname=postgres', $config['username'], $config['password'], [\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION]);
            if (self::$template) self::$control->exec('DROP DATABASE IF EXISTS "'.self::$template.'" WITH (FORCE)');
            self::$template = 'pro9_fiscal_test_'.bin2hex(random_bytes(6));
            self::disconnect($db->getDatabaseName());
            self::$control->exec('CREATE DATABASE "'.self::$template.'" TEMPLATE "'.$db->getDatabaseName().'"');
            self::$control->exec('REVOKE ALL ON DATABASE "'.self::$template.'" FROM PUBLIC');
            register_shutdown_function(static function (): void {
                if (getmypid() === self::$ownerPid && self::$control && self::$template) self::$control->exec('DROP DATABASE IF EXISTS "'.self::$template.'" WITH (FORCE)');
            });
        }
    }
    public static function restore(Connection $db, bool $forceNative = false): bool
    {
        self::assertTemporaryDatabase($db);
        if (!self::$path || !is_file(self::$path) || self::$key!==self::key()) return false;
        if (SchemaInspector::tables($db)) throw new \LogicException('El fixture requiere una base temporal vacía.');
        if (!$forceNative && self::$template && self::$control && getmypid() === self::$ownerPid) {
            $name = $db->getDatabaseName();
            self::disconnect($name);
            self::$control->exec('DROP DATABASE "'.$name.'" WITH (FORCE)');
            self::$control->exec('CREATE DATABASE "'.$name.'" TEMPLATE "'.self::$template.'"');
            return true;
        }
        (new PostgresBackup())->restore($db->getConfig(),self::$path);
        return true;
    }
    private static function disconnect(string $database): void
    {
        foreach (app('db')->getConnections() as $connection) if ($connection->getDatabaseName() === $database) {
            if ($connection->transactionLevel()) throw new \LogicException('El fixture no clona una transacción abierta.');
            $connection->disconnect();
        }
    }
    private static function assertTemporaryDatabase(Connection $db): void
    {
        $name=(string)$db->getDatabaseName();
        if (!preg_match('/^pro9_fiscal_test_[a-f0-9]{12}$/D',$name)) throw new \LogicException('El fixture sólo admite bases temporales.');
        if ($db->selectOne('SELECT current_database() AS name')->name!==$name) throw new \LogicException('La conexión no apunta a su base temporal.');
        PostgresTestEnvironment::assertServer($db);
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
