<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$projectRoot = dirname(__DIR__, 4);

require $projectRoot . '/vendor/autoload.php';

$app = require $projectRoot . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$options = getopt(
    '',
    [
        'legacy:',
        'migrations:',
        'data:',
        'source::',
        'legacy-target::',
        'current-target::',
    ]
);
$legacyPath = $options['legacy'] ?? null;
$migrationsPath = $options['migrations'] ?? null;
$dataPath = $options['data'] ?? null;
$source = $options['source'] ?? 'tenancy_bbb';
$legacyTarget = $options['legacy-target'] ?? 'pro8_legacy_seed_test';
$currentTarget = $options['current-target'] ?? 'pro8_current_seed_test';

if (
    !is_string($legacyPath)
    || !is_string($migrationsPath)
    || !is_string($dataPath)
    || !is_string($source)
    || !is_string($legacyTarget)
    || !is_string($currentTarget)
) {
    fail(
        'Uso: --legacy=RUTA --migrations=RUTA --data=ARCHIVO '
        . '[--source=BASE --legacy-target=BASE_seed_test '
        . '--current-target=BASE_seed_test]'
    );
}

foreach ([$source, $legacyTarget, $currentTarget] as $database) {
    assertSafeIdentifier($database);
}

if (
    preg_match('/_seed_test$/', $legacyTarget) !== 1
    || preg_match('/_seed_test$/', $currentTarget) !== 1
) {
    fail('Las bases temporales deben terminar en _seed_test.');
}

if ($legacyTarget === $currentTarget || in_array($source, [$legacyTarget, $currentTarget], true)) {
    fail('Las bases fuente y temporales deben ser distintas.');
}

$legacyPath = absolutePath($legacyPath, $projectRoot);
$migrationsPath = absolutePath($migrationsPath, $projectRoot);
$dataPath = absolutePath($dataPath, $projectRoot);

if (!is_dir($legacyPath) || !is_dir($migrationsPath) || !is_file($dataPath)) {
    fail('No existen las migraciones históricas, las actuales o el archivo de datos.');
}

$payload = require $dataPath;
$seedData = $payload['tables'] ?? null;

if (!is_array($seedData)) {
    fail('El archivo de datos iniciales no es válido.');
}

$admin = DB::connection('system');

foreach ([$legacyTarget, $currentTarget] as $target) {
    if ($admin->table('websites')->where('uuid', $target)->exists()) {
        fail("La base temporal {$target} pertenece a un tenant registrado.");
    }
}

$sourceSchema = $admin->selectOne(
    'SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
     FROM information_schema.SCHEMATA
     WHERE SCHEMA_NAME = ?',
    [$source]
);

if ($sourceSchema === null) {
    fail("No existe la base fuente {$source}.");
}

$previousDefault = DB::getDefaultConnection();
$result = null;
$exitCode = 0;

try {
    createDatabase($admin, $legacyTarget, $sourceSchema);
    createDatabase($admin, $currentTarget, $sourceSchema);

    echo "[1/8] Reproduciendo migraciones históricas..." . PHP_EOL;
    configureTenantConnection($legacyTarget);
    DB::connection('tenant')->unprepared('SET FOREIGN_KEY_CHECKS=0');
    runArtisan('migrate', $legacyPath);
    DB::connection('tenant')->unprepared('SET FOREIGN_KEY_CHECKS=1');
    assertForeignKeyIntegrity($admin, $legacyTarget);
    $legacyData = captureSeedData($admin, $legacyTarget);
    applyRequiredPolicyRecords($legacyData);
    compareDatasets($legacyData, $seedData, true, 'archivo generado');

    echo "[2/8] Migrando estructura consolidada..." . PHP_EOL;
    configureTenantConnection($currentTarget);
    runArtisan('migrate', $migrationsPath);

    echo "[3/8] Ejecutando seeder consolidado..." . PHP_EOL;
    runSeeder();
    assertForeignKeyIntegrity($admin, $currentTarget);
    compareDatabaseWithSeedData($admin, $currentTarget, $seedData);

    echo "[4/8] Ejecutando rollback completo..." . PHP_EOL;
    runArtisan('migrate:rollback', $migrationsPath);
    assertRollbackState($admin, $currentTarget);

    echo "[5/8] Ejecutando segunda migración..." . PHP_EOL;
    runArtisan('migrate', $migrationsPath);

    echo "[6/8] Ejecutando nuevamente el seeder..." . PHP_EOL;
    runSeeder();

    echo "[7/8] Repitiendo comparación de datos e integridad..." . PHP_EOL;
    assertForeignKeyIntegrity($admin, $currentTarget);
    compareDatabaseWithSeedData($admin, $currentTarget, $seedData);

    echo "[8/8] Ejecutando el seeder tenant principal..." . PHP_EOL;
    runSeeder('Database\\Seeders\\TenancyDatabaseSeeder');
    assertForeignKeyIntegrity($admin, $currentTarget);
    compareDatabaseWithSeedData($admin, $currentTarget, $seedData, true);

    $result = [
        'legacy_migrations' => count(glob($legacyPath . '/*.php') ?: []),
        'current_migrations' => count(glob($migrationsPath . '/*.php') ?: []),
        'seeded_tables' => count($seedData),
        'seeded_rows' => array_sum(
            array_map(
                static fn (array $table): int => count($table['rows']),
                $seedData
            )
        ),
        'historical_data_comparison' => 'exact',
        'first_migration_and_seed' => 'ok',
        'first_data_comparison' => 'exact',
        'foreign_key_integrity' => 'ok',
        'rollback' => 'ok',
        'second_migration_and_seed' => 'ok',
        'second_data_comparison' => 'exact',
        'tenancy_database_seeder' => 'ok',
    ];
} catch (Throwable $exception) {
    $exitCode = 1;
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
} finally {
    DB::setDefaultConnection($previousDefault);
    DB::purge('tenant');
    $admin = DB::connection('system');
    $admin->unprepared("DROP DATABASE IF EXISTS `{$legacyTarget}`");
    $admin->unprepared("DROP DATABASE IF EXISTS `{$currentTarget}`");
}

if ($result !== null) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

exit($exitCode);

function fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function assertSafeIdentifier(string $value): void
{
    if (preg_match('/^[A-Za-z0-9_]+$/', $value) !== 1) {
        fail("Nombre de base inseguro: {$value}");
    }
}

function absolutePath(string $path, string $projectRoot): string
{
    if (str_starts_with($path, '/')) {
        return rtrim($path, '/');
    }

    return $projectRoot . '/' . trim($path, '/');
}

function createDatabase($connection, string $database, object $sourceSchema): void
{
    $connection->unprepared("DROP DATABASE IF EXISTS `{$database}`");
    $connection->unprepared(
        sprintf(
            'CREATE DATABASE `%s` CHARACTER SET %s COLLATE %s',
            $database,
            $sourceSchema->DEFAULT_CHARACTER_SET_NAME,
            $sourceSchema->DEFAULT_COLLATION_NAME
        )
    );
}

function configureTenantConnection(string $database): void
{
    $connection = config('database.connections.system');
    $connection['database'] = $database;
    config(['database.connections.tenant' => $connection]);
    DB::purge('tenant');
    DB::setDefaultConnection('tenant');
    DB::connection('tenant')->getPdo();
}

function runArtisan(string $command, string $path): void
{
    $status = Artisan::call($command, [
        '--database' => 'tenant',
        '--path' => $path,
        '--realpath' => true,
        '--force' => true,
    ]);

    if ($status !== 0) {
        throw new RuntimeException(
            "{$command} falló.\n" . substr(Artisan::output(), -15000)
        );
    }
}

function runSeeder(
    string $class = 'Database\\Seeders\\TenantMigrationDataSeeder'
): void
{
    $status = Artisan::call('db:seed', [
        '--database' => 'tenant',
        '--class' => $class,
        '--force' => true,
    ]);

    if ($status !== 0) {
        throw new RuntimeException(
            "El seeder falló.\n" . substr(Artisan::output(), -15000)
        );
    }
}

function captureSeedData($connection, string $database): array
{
    $tables = $connection->select(
        "SELECT TABLE_NAME
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ?
           AND TABLE_TYPE = 'BASE TABLE'
           AND TABLE_NAME <> 'migrations'
         ORDER BY TABLE_NAME",
        [$database]
    );
    $result = [];

    foreach ($tables as $tableRow) {
        $table = $tableRow->TABLE_NAME;
        $count = (int) $connection->selectOne(
            sprintf('SELECT COUNT(*) AS total FROM `%s`.`%s`', $database, $table)
        )->total;

        if ($count === 0) {
            continue;
        }

        $columns = columnNames($connection, $database, $table);
        $keyColumns = identityColumns($connection, $database, $table, $columns);
        $orderBy = implode(
            ', ',
            array_map(
                static fn (string $column): string => "`{$column}`",
                $keyColumns !== [] ? $keyColumns : $columns
            )
        );
        $rows = $connection->select(
            sprintf(
                'SELECT * FROM `%s`.`%s` ORDER BY %s',
                $database,
                $table,
                $orderBy
            )
        );
        $result[$table] = [
            'key_columns' => $keyColumns,
            'rows' => array_map(
                static fn (object $row): array => (array) $row,
                $rows
            ),
        ];
    }

    return $result;
}

function columnNames($connection, string $database, string $table): array
{
    return array_map(
        static fn (object $row): string => $row->COLUMN_NAME,
        $connection->select(
            'SELECT COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION',
            [$database, $table]
        )
    );
}

function identityColumns(
    $connection,
    string $database,
    string $table,
    array $columns
): array {
    $primary = indexColumns($connection, $database, $table, 'PRIMARY');

    if ($primary !== []) {
        return $primary;
    }

    $uniqueIndex = $connection->selectOne(
        "SELECT INDEX_NAME
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = ?
           AND TABLE_NAME = ?
           AND NON_UNIQUE = 0
         ORDER BY INDEX_NAME
         LIMIT 1",
        [$database, $table]
    );

    if ($uniqueIndex !== null) {
        return indexColumns(
            $connection,
            $database,
            $table,
            $uniqueIndex->INDEX_NAME
        );
    }

    return in_array('id', $columns, true) ? ['id'] : $columns;
}

function indexColumns(
    $connection,
    string $database,
    string $table,
    string $index
): array {
    return array_map(
        static fn (object $row): string => $row->COLUMN_NAME,
        $connection->select(
            'SELECT COLUMN_NAME
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?
             ORDER BY SEQ_IN_INDEX',
            [$database, $table, $index]
        )
    );
}

function applyRequiredPolicyRecords(array &$dataset): void
{
    // No alterar datos históricos con políticas de otro proyecto.
}

function compareDatabaseWithSeedData(
    $connection,
    string $database,
    array $expected,
    bool $allowExtraTables = false
): void {
    $actual = captureSeedData($connection, $database);

    if ($allowExtraTables) {
        $actual = array_intersect_key($actual, $expected);
    }

    compareDatasets($expected, $actual, false, $database);
}

function compareDatasets(
    array $expected,
    array $actual,
    bool $normalizeTimestamps,
    string $label
): void {
    $expectedTables = array_keys($expected);
    $actualTables = array_keys($actual);
    sort($expectedTables, SORT_STRING);
    sort($actualTables, SORT_STRING);

    if ($expectedTables !== $actualTables) {
        throw new RuntimeException(
            "Las tablas con datos difieren para {$label}. Faltan: "
            . json_encode(array_values(array_diff($expectedTables, $actualTables)))
            . '; sobran: '
            . json_encode(array_values(array_diff($actualTables, $expectedTables)))
        );
    }

    $differentTables = [];

    foreach ($expectedTables as $table) {
        $expectedRows = canonicalRows(
            $expected[$table]['rows'],
            $normalizeTimestamps
        );
        $actualRows = canonicalRows(
            $actual[$table]['rows'],
            $normalizeTimestamps
        );

        if ($expectedRows !== $actualRows) {
            $differentTables[] = $table;
        }
    }

    if ($differentTables !== []) {
        throw new RuntimeException(
            "Los registros difieren para {$label}: "
            . implode(', ', array_slice($differentTables, 0, 30))
        );
    }
}

function canonicalRows(array $rows, bool $normalizeTimestamps): array
{
    $canonical = [];

    foreach ($rows as $row) {
        if ($normalizeTimestamps) {
            foreach (['created_at', 'updated_at'] as $column) {
                if (array_key_exists($column, $row) && $row[$column] !== null) {
                    $row[$column] = '__GENERATED_TIMESTAMP__';
                }
            }
        }

        ksort($row, SORT_STRING);
        $canonical[] = json_encode(
            $row,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );
    }

    sort($canonical, SORT_STRING);

    return $canonical;
}

function assertForeignKeyIntegrity($connection, string $database): void
{
    $constraints = $connection->select(
        'SELECT TABLE_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME
         FROM information_schema.REFERENTIAL_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = ?',
        [$database]
    );
    $violations = [];

    foreach ($constraints as $constraint) {
        $columns = $connection->select(
            'SELECT COLUMN_NAME, REFERENCED_COLUMN_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE CONSTRAINT_SCHEMA = ?
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?
             ORDER BY ORDINAL_POSITION',
            [$database, $constraint->TABLE_NAME, $constraint->CONSTRAINT_NAME]
        );
        $join = [];
        $notNull = [];

        foreach ($columns as $column) {
            $join[] = sprintf(
                'child.`%s` = parent.`%s`',
                $column->COLUMN_NAME,
                $column->REFERENCED_COLUMN_NAME
            );
            $notNull[] = sprintf('child.`%s` IS NOT NULL', $column->COLUMN_NAME);
        }

        $orphans = (int) $connection->selectOne(
            sprintf(
                'SELECT COUNT(*) AS total
                 FROM `%s`.`%s` child
                 LEFT JOIN `%s`.`%s` parent ON %s
                 WHERE (%s) AND parent.`%s` IS NULL',
                $database,
                $constraint->TABLE_NAME,
                $database,
                $constraint->REFERENCED_TABLE_NAME,
                implode(' AND ', $join),
                implode(' AND ', $notNull),
                $columns[0]->REFERENCED_COLUMN_NAME
            )
        )->total;

        if ($orphans !== 0) {
            $violations[] = "{$constraint->CONSTRAINT_NAME}:{$orphans}";
        }
    }

    if ($violations !== []) {
        throw new RuntimeException(
            'Existen claves foráneas huérfanas: ' . implode(', ', $violations)
        );
    }
}

function assertRollbackState($connection, string $database): void
{
    $tables = array_map(
        static fn (object $row): string => $row->TABLE_NAME,
        $connection->select(
            "SELECT TABLE_NAME
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'
             ORDER BY TABLE_NAME",
            [$database]
        )
    );

    if ($tables !== ['migrations']) {
        throw new RuntimeException(
            'El rollback dejó tablas: ' . json_encode($tables)
        );
    }

    $rows = (int) $connection->selectOne(
        "SELECT COUNT(*) AS total FROM `{$database}`.`migrations`"
    )->total;

    if ($rows !== 0) {
        throw new RuntimeException('La tabla migrations no quedó vacía.');
    }
}
