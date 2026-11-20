<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Database\PostgresBootstrap;
use App\Tenancy\Database\Connection as TenantConnection;
use App\Tenancy\Models\Website;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Connection;
use RuntimeException;

final class BenchmarkTenantCohort
{
    public static function createCentral(): array
    {
        $control = self::control();
        $database = 'sab_fiscal_test_'.bin2hex(random_bytes(6));
        $identifier = self::identifier($control, $database);
        $options = $control->getDriverName() === 'pgsql'
            ? ' TEMPLATE template0'
            : ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        $control->statement('CREATE DATABASE '.$identifier.$options);

        $configuration = array_replace($control->getConfig(), ['name' => 'benchmark_central', 'database' => $database]);
        $key = bin2hex(random_bytes(32));
        try {
            if ($control->getDriverName() === 'pgsql') $control->statement('REVOKE CONNECT ON DATABASE '.$identifier.' FROM PUBLIC');
            $central = self::connect($configuration);
            if ($control->getDriverName() === 'pgsql') PostgresBootstrap::install($central);
            $schema = $central->getSchemaBuilder();
            $schema->create('websites', static function ($table): void {
                $table->bigIncrements('id');
                $table->string('uuid')->unique();
                $table->string('managed_by_database_connection')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
            $schema->create('hostnames', static function ($table): void {
                $table->bigIncrements('id');
                $table->string('fqdn')->unique();
                $table->string('redirect_to')->nullable();
                $table->boolean('force_https')->default(false);
                $table->timestamp('under_maintenance_since')->nullable();
                $table->unsignedBigInteger('website_id');
                $table->timestamps();
                $table->softDeletes();
            });
            $schema->create('configurations', static function ($table): void {
                $table->bigIncrements('id');
                $table->boolean('locked_admin')->default(false);
                $table->boolean('use_login_global')->default(false);
                $table->boolean('enable_guest_register')->default(false);
                $table->boolean('tenant_show_ads')->default(false);
            });
            $central->table('configurations')->insert(['id' => 1]);
            $schema->create('plans', static function ($table): void {
                $table->increments('id');
                $table->string('name');
                $table->integer('limit_documents');
                $table->boolean('include_sale_notes_limit_documents')->default(false);
            });
            $schema->create('clients', static function ($table): void {
                $table->increments('id');
                $table->unsignedBigInteger('hostname_id');
                $table->string('name');
                $table->unsignedInteger('plan_id');
                $table->integer('whatsapp_messages_limit_override')->nullable();
            });
            $central->table('plans')->insert(['id' => 1, 'name' => 'Plan HTTP temporal', 'limit_documents' => 0]);
        } catch (\Throwable $error) {
            self::dropDatabase($control, $database);
            throw $error;
        } finally {
            if (isset($central)) $central->disconnect();
        }

        return ['connection' => $configuration, 'key' => $key];
    }

    public static function register(array $cohort, Connection $tenant, string $hostname, string $name, Container $app): void
    {
        $control = self::control();
        $database = $tenant->getDatabaseName();
        if ($tenant->getDriverName() !== $control->getDriverName()) throw new RuntimeException('Motor de tenant inesperado.');
        $role = self::identifier($control, $database);
        $central = self::connect($cohort['connection']);
        $createdAt = '2026-09-01 00:00:00';
        // Record database ownership before creating the role, for recovery if the worker stops.
        $website = $central->table('websites')->insertGetId([
            'uuid' => $database, 'created_at' => $createdAt, 'updated_at' => $createdAt,
        ]);
        $model = (new Website())->forceFill(['id' => $website, 'uuid' => $database, 'created_at' => $createdAt]);
        $app['config']->set('tenancy.key', $cohort['key']);
        $password = TenantConnection::legacyPassword($model, $cohort['key'], $app['config']->get('app.key'));
        $quoted = $control->getPdo()->quote($password);

        if ($control->getDriverName() === 'pgsql') {
            $control->statement('CREATE ROLE '.$role.' LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE PASSWORD '.$quoted);
            $control->statement('REVOKE CONNECT ON DATABASE '.$role.' FROM PUBLIC');
            $control->statement('GRANT CONNECT ON DATABASE '.$role.' TO '.$role);
            $tenant->statement('GRANT USAGE ON SCHEMA public TO '.$role);
            $tenant->statement('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO '.$role);
            $tenant->statement('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO '.$role);
        } else {
            $account = $control->getPdo()->quote($database)."@'%'";
            $control->statement('CREATE USER '.$account.' IDENTIFIED BY '.$quoted);
            $control->statement('GRANT SELECT, INSERT, UPDATE, DELETE ON '.$role.'.* TO '.$account);
        }

        $hostnameId = $central->table('hostnames')->insertGetId(['fqdn' => $hostname, 'website_id' => $website]);
        $central->table('clients')->insert(['hostname_id' => $hostnameId, 'name' => $name, 'plan_id' => 1]);
        $central->disconnect();
        $control->disconnect();
    }

    public static function clean(array $cohort): void
    {
        $control = self::control();
        $central = self::connect($cohort['connection']);
        $databases = $central->table('websites')->pluck('uuid')->all();
        $central->disconnect();
        foreach ($databases as $database) {
            self::dropDatabase($control, $database);
            if ($control->getDriverName() === 'pgsql') {
                $control->statement('DROP ROLE IF EXISTS '.self::identifier($control, $database));
            } else {
                $account = $control->getPdo()->quote($database)."@'%'";
                $control->statement('DROP USER IF EXISTS '.$account);
            }
        }
        self::dropDatabase($control, $cohort['connection']['database']);
        $control->disconnect();
    }

    private static function dropDatabase(Connection $control, string $database): void
    {
        $force = $control->getDriverName() === 'pgsql' ? ' WITH (FORCE)' : '';
        $control->statement('DROP DATABASE IF EXISTS '.self::identifier($control, $database).$force);
    }

    private static function identifier(Connection $connection, string $name): string
    {
        if (!preg_match('/^sab_fiscal_test_[a-f0-9]{12}$/D', $name)) {
            throw new RuntimeException('Identificador fuera de la instancia temporal.');
        }
        $quote = $connection->getDriverName() === 'pgsql' ? '"' : '`';
        return $quote.$name.$quote;
    }

    private static function control(): Connection
    {
        if (getenv('SAB_POSTGRES_TESTS') !== '1') {
            throw new RuntimeException('Se requiere la ejecución temporal.');
        }
        if (getenv('SAB_FISCAL_MYSQL_TESTS') === '1') {
            if (getenv('DB_HOST') !== 'mysql') throw new RuntimeException('Requiere el host de referencia temporal.');
            return self::connect([
                'driver' => 'mysql', 'host' => 'mysql', 'port' => 3306, 'username' => 'root', 'password' => '',
                'database' => 'information_schema', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '',
            ]);
        }

        $connection = self::connect(PostgresTestEnvironment::connection());
        PostgresTestEnvironment::assertServer($connection);
        return $connection;
    }

    private static function connect(array $configuration): Connection
    {
        $manager = new Manager(new Container());
        $manager->addConnection($configuration);
        return $manager->getConnection();
    }
}
