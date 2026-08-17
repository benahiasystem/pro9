<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$projectRoot = dirname(__DIR__, 4);

require $projectRoot . '/vendor/autoload.php';

$app = require $projectRoot . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$options = getopt('', ['source:', 'target:', 'migrations:']);
$source = $options['source'] ?? null;
$target = $options['target'] ?? null;
$migrationsPath = $options['migrations'] ?? null;

if (!is_string($source) || !is_string($target) || !is_string($migrationsPath)) {
    fwrite(
        STDERR,
        'Uso: --source=BASE --target=BASE_schema_test --migrations=RUTA' . PHP_EOL
    );
    exit(1);
}

assertSafeIdentifier($source, 'base fuente');
assertSafeIdentifier($target, 'base temporal');

if ($source === $target) {
    fwrite(STDERR, 'La base temporal no puede ser la base fuente.' . PHP_EOL);
    exit(1);
}

if (preg_match('/_schema_test$/', $target) !== 1) {
    fwrite(
        STDERR,
        'Por seguridad, la base temporal debe terminar en _schema_test.' . PHP_EOL
    );
    exit(1);
}

if (!str_starts_with($migrationsPath, '/')) {
    $migrationsPath = $projectRoot . '/' . trim($migrationsPath, '/');
}

if (!is_dir($migrationsPath)) {
    fwrite(STDERR, "No existe {$migrationsPath}." . PHP_EOL);
    exit(1);
}

$migrationFiles = glob($migrationsPath . '/*.php') ?: [];

if ($migrationFiles === []) {
    fwrite(STDERR, 'No hay migraciones PHP para validar.' . PHP_EOL);
    exit(1);
}

$admin = DB::connection('system');
$registeredTenant = $admin->table('websites')->where('uuid', $target)->exists();

if ($registeredTenant) {
    fwrite(
        STDERR,
        "La base {$target} pertenece a un tenant registrado y no puede usarse." . PHP_EOL
    );
    exit(1);
}

$sourceSchema = $admin->selectOne(
    'SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
     FROM information_schema.SCHEMATA
     WHERE SCHEMA_NAME = ?',
    [$source]
);

if ($sourceSchema === null) {
    fwrite(STDERR, "No existe la base fuente {$source}." . PHP_EOL);
    exit(1);
}

$result = null;
$exitCode = 0;

try {
    assertMigrationDependencyOrder($admin, $source, $migrationsPath);

    $admin->unprepared("DROP DATABASE IF EXISTS `{$target}`");
    $admin->unprepared(
        sprintf(
            'CREATE DATABASE `%s` CHARACTER SET %s COLLATE %s',
            $target,
            $sourceSchema->DEFAULT_CHARACTER_SET_NAME,
            $sourceSchema->DEFAULT_COLLATION_NAME
        )
    );

    $testConfig = config('database.connections.system');
    $testConfig['database'] = $target;
    config(['database.connections.schema_validation' => $testConfig]);
    DB::purge('schema_validation');
    DB::connection('schema_validation')->getPdo();

    echo "[1/5] Ejecutando migración limpia..." . PHP_EOL;
    runArtisanMigration('migrate', $migrationsPath);

    echo "[2/5] Comparando estructura completa con {$source}..." . PHP_EOL;
    $firstComparison = compareSchemas($admin, $source, $target);

    echo "[3/5] Ejecutando rollback completo..." . PHP_EOL;
    runArtisanMigration('migrate:rollback', $migrationsPath);
    assertRollbackState($admin, $target);

    echo "[4/5] Ejecutando segunda migración limpia..." . PHP_EOL;
    runArtisanMigration('migrate', $migrationsPath);

    echo "[5/5] Repitiendo comparación estructural..." . PHP_EOL;
    $secondComparison = compareSchemas($admin, $source, $target);

    $result = [
        'source' => $source,
        'temporary_target' => $target,
        'migration_files' => count($migrationFiles),
        'dependency_order' => 'ok',
        'first_migration' => 'ok',
        'first_comparison' => 'exact',
        'rollback' => 'ok',
        'second_migration' => 'ok',
        'second_comparison' => 'exact',
        'metrics' => $secondComparison,
        'first_metrics' => $firstComparison,
    ];
} catch (Throwable $exception) {
    $exitCode = 1;
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
} finally {
    DB::purge('schema_validation');
    $admin->disconnect();
    DB::reconnect('system');
    DB::connection('system')->unprepared("DROP DATABASE IF EXISTS `{$target}`");
}

if ($result !== null) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

exit($exitCode);

function assertSafeIdentifier(string $value, string $label): void
{
    if (preg_match('/^[A-Za-z0-9_]+$/', $value) !== 1) {
        fwrite(STDERR, "Nombre inseguro para {$label}: {$value}" . PHP_EOL);
        exit(1);
    }
}

function runArtisanMigration(string $command, string $migrationsPath): void
{
    $status = Artisan::call($command, [
        '--database' => 'schema_validation',
        '--path' => $migrationsPath,
        '--realpath' => true,
        '--force' => true,
    ]);

    if ($status !== 0) {
        $output = Artisan::output();
        throw new RuntimeException(
            sprintf(
                "%s falló con código %d.\n%s",
                $command,
                $status,
                substr($output, -12000)
            )
        );
    }
}

function assertMigrationDependencyOrder(
    $connection,
    string $source,
    string $migrationsPath
): void {
    $orderByTable = [];

    foreach (glob($migrationsPath . '/*_create_*_table.php') ?: [] as $file) {
        $filename = basename($file);
        $contents = (string) file_get_contents($file);

        if (
            preg_match('/_(\d{6})_create_.*_table\.php$/', $filename, $orderMatch) !== 1
            || preg_match('/CREATE TABLE `([^`]+)`/', $contents, $tableMatch) !== 1
        ) {
            throw new RuntimeException(
                "No se pudo interpretar el orden de {$filename}."
            );
        }

        $orderByTable[$tableMatch[1]] = (int) $orderMatch[1];
    }

    $expectedTables = tableNames($connection, $source, false);

    $generatedTables = array_keys($orderByTable);
    $missingTables = array_values(array_diff($expectedTables, $generatedTables));
    $extraTables = array_values(array_diff($generatedTables, $expectedTables));

    if ($missingTables !== [] || $extraTables !== []) {
        throw new RuntimeException(
            'Las migraciones por tabla no cubren exactamente el esquema fuente. Faltan: '
            . json_encode($missingTables)
            . '; sobran: '
            . json_encode($extraTables)
        );
    }

    // Las claves foráneas se aplican en la última migración, después de crear
    // todas las tablas. Por ello los ciclos no imponen un orden imposible entre
    // las migraciones de tablas; la comparación exacta de SHOW CREATE TABLE
    // valida que cada clave quede restaurada al finalizar el proceso.
}

function compareSchemas($admin, string $source, string $target): array
{
    $sourceTables = tableNames($admin, $source, false);
    $targetTables = tableNames($admin, $target, false);

    if ($sourceTables !== $targetTables) {
        $missing = array_values(array_diff($sourceTables, $targetTables));
        $extra = array_values(array_diff($targetTables, $sourceTables));

        throw new RuntimeException(
            'Diferencia de tablas. Faltan: '
            . json_encode($missing)
            . '; sobran: '
            . json_encode($extra)
        );
    }

    $differences = [];

    foreach ($sourceTables as $table) {
        $sourceCreate = showCreateTable($admin, $source, $table);
        $targetCreate = showCreateTable($admin, $target, $table);

        if ($sourceCreate !== $targetCreate) {
            $differences[] = $table;
        }
    }

    if ($differences !== []) {
        throw new RuntimeException(
            'SHOW CREATE TABLE difiere para: '
            . implode(', ', array_slice($differences, 0, 30))
        );
    }

    $sourceMetrics = schemaMetrics($admin, $source);
    $targetMetrics = schemaMetrics($admin, $target);

    if ($sourceMetrics !== $targetMetrics) {
        throw new RuntimeException(
            'Los conteos estructurales difieren. Fuente: '
            . json_encode($sourceMetrics)
            . '; destino: '
            . json_encode($targetMetrics)
        );
    }

    return $targetMetrics;
}

function tableNames($connection, string $database, bool $includeMigrations): array
{
    $sql = "SELECT TABLE_NAME
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'";
    $bindings = [$database];

    if (!$includeMigrations) {
        $sql .= ' AND TABLE_NAME <> ?';
        $bindings[] = 'migrations';
    }

    $sql .= ' ORDER BY TABLE_NAME';

    return array_map(
        static fn (object $row): string => $row->TABLE_NAME,
        $connection->select($sql, $bindings)
    );
}

function showCreateTable($connection, string $database, string $table): string
{
    assertSafeIdentifier($database, 'base comparada');
    assertSafeIdentifier($table, 'tabla comparada');

    $row = $connection->selectOne(
        sprintf('SHOW CREATE TABLE `%s`.`%s`', $database, $table)
    );
    $values = (array) $row;
    $createSql = $values['Create Table'] ?? null;

    if (!is_string($createSql)) {
        throw new RuntimeException("No se pudo leer {$database}.{$table}.");
    }

    return $createSql;
}

function schemaMetrics($connection, string $database): array
{
    $tables = (int) $connection->selectOne(
        "SELECT COUNT(*) AS total
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ?
           AND TABLE_TYPE = 'BASE TABLE'
           AND TABLE_NAME <> 'migrations'",
        [$database]
    )->total;
    $columns = (int) $connection->selectOne(
        "SELECT COUNT(*) AS total
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME <> 'migrations'",
        [$database]
    )->total;
    $indexes = (int) $connection->selectOne(
        "SELECT COUNT(*) AS total
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME <> 'migrations'",
        [$database]
    )->total;
    $foreignKeys = (int) $connection->selectOne(
        'SELECT COUNT(*) AS total
         FROM information_schema.REFERENTIAL_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = ?',
        [$database]
    )->total;
    $tableComments = (int) $connection->selectOne(
        "SELECT COUNT(*) AS total
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ?
           AND TABLE_NAME <> 'migrations'
           AND TABLE_COMMENT <> ''",
        [$database]
    )->total;
    $columnComments = (int) $connection->selectOne(
        "SELECT COUNT(*) AS total
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ?
           AND TABLE_NAME <> 'migrations'
           AND COLUMN_COMMENT <> ''",
        [$database]
    )->total;

    return [
        'tables' => $tables,
        'columns' => $columns,
        'indexes' => $indexes,
        'foreign_keys' => $foreignKeys,
        'table_comments' => $tableComments,
        'column_comments' => $columnComments,
    ];
}

function assertRollbackState($connection, string $target): void
{
    $tables = tableNames($connection, $target, true);

    if ($tables !== ['migrations']) {
        throw new RuntimeException(
            'El rollback dejó tablas inesperadas: ' . json_encode($tables)
        );
    }

    $rows = (int) $connection->selectOne(
        sprintf('SELECT COUNT(*) AS total FROM `%s`.`migrations`', $target)
    )->total;

    if ($rows !== 0) {
        throw new RuntimeException(
            "El rollback dejó {$rows} registros en la tabla migrations."
        );
    }
}
