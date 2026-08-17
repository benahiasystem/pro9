<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$projectRoot = dirname(__DIR__, 4);

require $projectRoot . '/vendor/autoload.php';

$app = require $projectRoot . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$options = getopt('', ['legacy:', 'target:', 'output:', 'charset-source::']);
$legacyPath = $options['legacy'] ?? null;
$target = $options['target'] ?? null;
$outputPath = $options['output'] ?? null;
$charsetSource = $options['charset-source'] ?? 'tenancy_bbb';

if (
    !is_string($legacyPath)
    || !is_string($target)
    || !is_string($outputPath)
    || !is_string($charsetSource)
) {
    fail(
        'Uso: --legacy=RUTA --target=BASE_seed_source_test '
        . '--output=ARCHIVO [--charset-source=BASE]'
    );
}

assertSafeIdentifier($target, 'base temporal');
assertSafeIdentifier($charsetSource, 'base de referencia');

if (preg_match('/_seed_source_test$/', $target) !== 1) {
    fail('La base temporal debe terminar en _seed_source_test.');
}

$legacyPath = absolutePath($legacyPath, $projectRoot);
$outputPath = absolutePath($outputPath, $projectRoot);

if (!is_dir($legacyPath)) {
    fail("No existe el directorio de migraciones históricas {$legacyPath}.");
}

if ((glob($legacyPath . '/*.php') ?: []) === []) {
    fail('El directorio histórico no contiene migraciones PHP ejecutables.');
}

$outputDirectory = dirname($outputPath);

if (
    !is_dir($outputDirectory)
    && !mkdir($outputDirectory, 0775, true)
    && !is_dir($outputDirectory)
) {
    fail("No se pudo crear {$outputDirectory}.");
}

if (is_file($outputPath)) {
    fail("El archivo de salida ya existe: {$outputPath}");
}

$admin = DB::connection('system');

if ($admin->table('websites')->where('uuid', $target)->exists()) {
    fail("La base {$target} pertenece a un tenant registrado.");
}

$schema = $admin->selectOne(
    'SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
     FROM information_schema.SCHEMATA
     WHERE SCHEMA_NAME = ?',
    [$charsetSource]
);

if ($schema === null) {
    fail("No existe la base de referencia {$charsetSource}.");
}

$summary = null;
$exitCode = 0;
$previousDefaultConnection = DB::getDefaultConnection();

try {
    $admin->unprepared("DROP DATABASE IF EXISTS `{$target}`");
    $admin->unprepared(
        sprintf(
            'CREATE DATABASE `%s` CHARACTER SET %s COLLATE %s',
            $target,
            $schema->DEFAULT_CHARACTER_SET_NAME,
            $schema->DEFAULT_COLLATION_NAME
        )
    );

    configureTenantConnection($target);
    DB::connection('tenant')->unprepared('SET FOREIGN_KEY_CHECKS=0');

    $status = Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => $legacyPath,
        '--realpath' => true,
        '--force' => true,
    ]);

    if ($status !== 0) {
        failWithException(
            "Las migraciones históricas fallaron.\n"
            . substr(Artisan::output(), -15000)
        );
    }

    DB::connection('tenant')->unprepared('SET FOREIGN_KEY_CHECKS=1');
    assertForeignKeyIntegrity($admin, $target);

    $tables = tableNames($admin, $target);
    $orderedTables = orderTables($admin, $target, $tables);
    $dataset = [];
    $totalRows = 0;

    foreach ($orderedTables as $table) {
        $count = (int) $admin->selectOne(
            sprintf('SELECT COUNT(*) AS total FROM `%s`.`%s`', $target, $table)
        )->total;

        if ($count === 0) {
            continue;
        }

        $columns = columnNames($admin, $target, $table);
        $keyColumns = identityColumns($admin, $target, $table, $columns);
        $rows = fetchRows($admin, $target, $table, $keyColumns, $columns);

        $dataset[$table] = [
            'key_columns' => $keyColumns,
            'rows' => $rows,
        ];
        $totalRows += count($rows);
    }

    $policyAdjustments = applyRequiredPolicyRecords($dataset);
    $totalRows = array_sum(
        array_map(
            static fn (array $table): int => count($table['rows']),
            $dataset
        )
    );

    $payload = [
        'source' => 'migraciones tenant históricas',
        'tables' => $dataset,
    ];
    $contents = "<?php\n\n"
        . "/**\n"
        . " * Datos iniciales consolidados desde las migraciones tenant históricas.\n"
        . " * Generado de forma determinista; no editar manualmente.\n"
        . " */\n\n"
        . 'return '
        . var_export($payload, true)
        . ";\n";

    if (file_put_contents($outputPath, $contents) === false) {
        failWithException("No se pudo escribir {$outputPath}.");
    }

    $summary = [
        'legacy_migrations' => count(glob($legacyPath . '/*.php') ?: []),
        'seeded_tables' => count($dataset),
        'seeded_rows' => $totalRows,
        'policy_adjustments' => $policyAdjustments,
        'output' => $outputPath,
        'tables' => array_map(
            static fn (array $table): int => count($table['rows']),
            $dataset
        ),
    ];
} catch (Throwable $exception) {
    $exitCode = 1;
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
} finally {
    DB::setDefaultConnection($previousDefaultConnection);
    DB::purge('tenant');
    $admin = DB::connection('system');
    $admin->unprepared("DROP DATABASE IF EXISTS `{$target}`");
}

if ($summary !== null) {
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

exit($exitCode);

function fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function failWithException(string $message): void
{
    throw new RuntimeException($message);
}

function assertSafeIdentifier(string $value, string $label): void
{
    if (preg_match('/^[A-Za-z0-9_]+$/', $value) !== 1) {
        fail("Nombre inseguro para {$label}: {$value}");
    }
}

function absolutePath(string $path, string $projectRoot): string
{
    if (str_starts_with($path, '/')) {
        return rtrim($path, '/');
    }

    return $projectRoot . '/' . trim($path, '/');
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

function tableNames($connection, string $database): array
{
    return array_map(
        static fn (object $row): string => $row->TABLE_NAME,
        $connection->select(
            "SELECT TABLE_NAME
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ?
               AND TABLE_TYPE = 'BASE TABLE'
               AND TABLE_NAME <> 'migrations'
             ORDER BY TABLE_NAME",
            [$database]
        )
    );
}

function orderTables($connection, string $database, array $tables): array
{
    $tableSet = array_fill_keys($tables, true);
    $dependencies = array_fill_keys($tables, []);
    $foreignKeys = $connection->select(
        'SELECT TABLE_NAME, REFERENCED_TABLE_NAME
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE CONSTRAINT_SCHEMA = ?
           AND REFERENCED_TABLE_NAME IS NOT NULL',
        [$database]
    );

    foreach ($foreignKeys as $foreignKey) {
        $child = $foreignKey->TABLE_NAME;
        $parent = $foreignKey->REFERENCED_TABLE_NAME;

        if (
            $child === $parent
            || !isset($tableSet[$child], $tableSet[$parent])
        ) {
            continue;
        }

        $dependencies[$child][$parent] = true;
    }

    $remaining = $tableSet;
    $ordered = [];

    while ($remaining !== []) {
        $ready = [];

        foreach (array_keys($remaining) as $table) {
            if (array_intersect_key($dependencies[$table], $remaining) === []) {
                $ready[] = $table;
            }
        }

        sort($ready, SORT_STRING);

        if ($ready === []) {
            // El seeder restaura los registros con FOREIGN_KEY_CHECKS desactivado.
            // Para un componente cíclico no existe un orden topológico posible,
            // por lo que se conserva un orden determinista para dichas tablas.
            $ready = array_keys($remaining);
            sort($ready, SORT_STRING);
        }

        foreach ($ready as $table) {
            $ordered[] = $table;
            unset($remaining[$table]);
        }
    }

    return $ordered;
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

    if (in_array('id', $columns, true)) {
        return ['id'];
    }

    return $columns;
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

function fetchRows(
    $connection,
    string $database,
    string $table,
    array $keyColumns,
    array $columns
): array {
    $orderColumns = $keyColumns !== [] ? $keyColumns : $columns;
    $orderBy = implode(
        ', ',
        array_map(
            static fn (string $column): string => "`{$column}`",
            $orderColumns
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

    return array_map(
        static fn (object $row): array => (array) $row,
        $rows
    );
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

        $firstReferencedColumn = $columns[0]->REFERENCED_COLUMN_NAME;
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
                $firstReferencedColumn
            )
        )->total;

        if ($orphans !== 0) {
            $violations[] = "{$constraint->CONSTRAINT_NAME}: {$orphans}";
        }
    }

    if ($violations !== []) {
        failWithException(
            'Las migraciones históricas dejan claves foráneas huérfanas: '
            . implode(', ', $violations)
        );
    }
}

function applyRequiredPolicyRecords(array &$dataset): array
{
    // Pro9 preserva exactamente el resultado de su propio historial.
    return [];
}
