<?php
// ######### INICIO CAMBIO NELSON #########

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$projectRoot = dirname(__DIR__, 4);

require $projectRoot . '/vendor/autoload.php';

$app = require $projectRoot . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$options = getopt('', ['source:', 'legacy:', 'output:', 'date::']);
$source = $options['source'] ?? null;
$legacyPath = $options['legacy'] ?? null;
$outputPath = $options['output'] ?? null;
$migrationDate = $options['date'] ?? date('Y_m_d');

if (!is_string($source) || !is_string($legacyPath) || !is_string($outputPath)) {
    fail('Uso: --source=BASE --legacy=RUTA --output=RUTA [--date=YYYY_MM_DD]');
}

assertSafeIdentifier($source, 'base de datos fuente');

if (preg_match('/^\d{4}_\d{2}_\d{2}$/', $migrationDate) !== 1) {
    fail('La fecha debe usar el formato YYYY_MM_DD.');
}

$legacyPath = absolutePath($legacyPath, $projectRoot);
$outputPath = absolutePath($outputPath, $projectRoot);

if (!is_dir($legacyPath)) {
    fail("No existe el directorio histórico: {$legacyPath}");
}

prepareEmptyOutputDirectory($outputPath);

$connection = DB::connection('system');
$databaseExists = $connection->selectOne(
    'SELECT COUNT(*) AS total FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
    [$source]
);

if ((int) $databaseExists->total !== 1) {
    fail("No existe la base fuente {$source}.");
}

assertOnlyBaseTables($connection, $source);

$tables = array_map(
    static fn (object $row): string => $row->TABLE_NAME,
    $connection->select(
        "SELECT TABLE_NAME
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'
         ORDER BY TABLE_NAME",
        [$source]
    )
);

$tables = array_values(array_filter(
    $tables,
    static fn (string $table): bool => $table !== 'migrations'
));

if ($tables === []) {
    fail("La base {$source} no contiene tablas de aplicación.");
}

foreach ($tables as $table) {
    assertSafeIdentifier($table, 'tabla');
}

$columnRows = $connection->select(
    'SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, COLUMN_TYPE, IS_NULLABLE,
            COLUMN_DEFAULT, EXTRA, COLUMN_COMMENT, COLLATION_NAME,
            GENERATION_EXPRESSION
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = ?
     ORDER BY TABLE_NAME, ORDINAL_POSITION',
    [$source]
);

$columnsByTable = [];
$columnComments = 0;

foreach ($columnRows as $column) {
    if ($column->TABLE_NAME === 'migrations') {
        continue;
    }

    $columnsByTable[$column->TABLE_NAME][] = $column;

    if ($column->COLUMN_COMMENT !== '') {
        $columnComments++;
    }
}

$tableRows = $connection->select(
    "SELECT TABLE_NAME, TABLE_COMMENT
     FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'",
    [$source]
);
$tableCommentsByTable = [];
$tableComments = 0;

foreach ($tableRows as $tableRow) {
    if ($tableRow->TABLE_NAME === 'migrations') {
        continue;
    }

    $tableCommentsByTable[$tableRow->TABLE_NAME] = $tableRow->TABLE_COMMENT;

    if ($tableRow->TABLE_COMMENT !== '') {
        $tableComments++;
    }
}

$legacyDocs = collectLegacyDocumentation($legacyPath);
$foreignKeyRows = $connection->select(
    'SELECT TABLE_NAME, REFERENCED_TABLE_NAME
     FROM information_schema.KEY_COLUMN_USAGE
     WHERE CONSTRAINT_SCHEMA = ?
       AND REFERENCED_TABLE_NAME IS NOT NULL
     ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION',
    [$source]
);

[$orderedTables, $cycleBreaks] = orderTables($tables, $foreignKeyRows);

$createStatements = [];
$foreignKeys = [];

foreach ($orderedTables as $table) {
    $row = $connection->selectOne(
        sprintf('SHOW CREATE TABLE `%s`.`%s`', $source, $table)
    );
    $values = (array) $row;
    $createSql = $values['Create Table'] ?? null;

    if (!is_string($createSql)) {
        fail("No se pudo obtener SHOW CREATE TABLE para {$table}.");
    }

    [$createWithoutForeignKeys, $tableForeignKeys] = splitForeignKeys($table, $createSql);
    $createStatements[$table] = $createWithoutForeignKeys;
    $foreignKeys = array_merge($foreignKeys, $tableForeignKeys);
}

$expectedForeignKeys = (int) $connection->selectOne(
    'SELECT COUNT(*) AS total
     FROM information_schema.REFERENTIAL_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = ?',
    [$source]
)->total;

if (count($foreignKeys) !== $expectedForeignKeys) {
    fail(
        sprintf(
            'Se interpretaron %d claves foráneas, pero MySQL reporta %d.',
            count($foreignKeys),
            $expectedForeignKeys
        )
    );
}

$sequence = 1;

foreach ($orderedTables as $table) {
    $slug = preg_replace('/[^a-z0-9_]+/', '_', strtolower($table));
    $filename = sprintf(
        '%s_%06d_create_%s_table.php',
        $migrationDate,
        $sequence++,
        $slug
    );
    $documentation = renderDocumentation(
        $source,
        $table,
        $columnsByTable[$table] ?? [],
        $tableCommentsByTable[$table] ?? '',
        $legacyDocs[$table] ?? null
    );
    $migration = renderTableMigration(
        $documentation,
        $table,
        $createStatements[$table]
    );

    writeGeneratedFile($outputPath . '/' . $filename, $migration);
}

$foreignKeyFilename = sprintf(
    '%s_%06d_add_tenant_foreign_keys.php',
    $migrationDate,
    $sequence
);
writeGeneratedFile(
    $outputPath . '/' . $foreignKeyFilename,
    renderForeignKeyMigration($foreignKeys)
);

$summary = [
    'source' => $source,
    'output' => $outputPath,
    'table_migrations' => count($orderedTables),
    'foreign_key_migrations' => 1,
    'files' => count($orderedTables) + 1,
    'columns' => count($columnRows) - count($connection->select(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
        [$source, 'migrations']
    )),
    'indexes' => (int) $connection->selectOne(
        'SELECT COUNT(*) AS total
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME <> ?',
        [$source, 'migrations']
    )->total,
    'foreign_keys' => count($foreignKeys),
    'table_comments' => $tableComments,
    'column_comments' => $columnComments,
    'legacy_phpdocs_preserved' => count(array_intersect_key($legacyDocs, array_flip($tables))),
    'dependency_cycle_breaks' => $cycleBreaks,
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

function fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
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

function prepareEmptyOutputDirectory(string $outputPath): void
{
    if (!is_dir($outputPath) && !mkdir($outputPath, 0775, true) && !is_dir($outputPath)) {
        fail("No se pudo crear el directorio de salida {$outputPath}.");
    }

    $entries = array_values(array_diff(scandir($outputPath) ?: [], ['.', '..']));

    if ($entries !== []) {
        fail("El directorio de salida debe estar vacío: {$outputPath}");
    }
}

function assertOnlyBaseTables($connection, string $source): void
{
    $unsupported = $connection->select(
        "SELECT TABLE_NAME AS object_name, TABLE_TYPE AS object_type
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ? AND TABLE_TYPE <> 'BASE TABLE'",
        [$source]
    );
    $triggers = (int) $connection->selectOne(
        'SELECT COUNT(*) AS total FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ?',
        [$source]
    )->total;
    $routines = (int) $connection->selectOne(
        'SELECT COUNT(*) AS total FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = ?',
        [$source]
    )->total;
    $events = (int) $connection->selectOne(
        'SELECT COUNT(*) AS total FROM information_schema.EVENTS WHERE EVENT_SCHEMA = ?',
        [$source]
    )->total;

    if ($unsupported !== [] || $triggers !== 0 || $routines !== 0 || $events !== 0) {
        fail('El esquema contiene vistas, triggers, rutinas o eventos no soportados por este generador.');
    }
}

function collectLegacyDocumentation(string $legacyPath): array
{
    $documentation = [];
    $files = glob($legacyPath . '/*.php') ?: [];

    foreach ($files as $file) {
        $source = (string) file_get_contents($file);

        if (preg_match("/Schema::create\\(\\s*['\"]([^'\"]+)['\"]/", $source, $tableMatch) !== 1) {
            continue;
        }

        $table = $tableMatch[1];
        assertSafeIdentifier($table, 'tabla documentada');

        $firstUse = strpos($source, "\nuse ");
        $prefix = $firstUse === false ? $source : substr($source, 0, $firstUse);

        if (preg_match_all('/\/\*\*.*?\*\//s', $prefix, $docMatches) === false) {
            continue;
        }

        if (($docMatches[0] ?? []) !== []) {
            $documentation[$table] = implode("\n\n", $docMatches[0]);
        }
    }

    return $documentation;
}

function orderTables(array $tables, array $foreignKeyRows): array
{
    $tableSet = array_fill_keys($tables, true);
    $dependencies = array_fill_keys($tables, []);

    foreach ($foreignKeyRows as $row) {
        $child = $row->TABLE_NAME;
        $parent = $row->REFERENCED_TABLE_NAME;

        if (
            $child === 'migrations'
            || $parent === 'migrations'
            || $child === $parent
            || !isset($tableSet[$child], $tableSet[$parent])
        ) {
            continue;
        }

        $dependencies[$child][$parent] = true;
    }

    $remaining = $tableSet;
    $ordered = [];
    $cycleBreaks = [];

    while ($remaining !== []) {
        $ready = [];

        foreach (array_keys($remaining) as $table) {
            if (array_intersect_key($dependencies[$table], $remaining) === []) {
                $ready[] = $table;
            }
        }

        sort($ready, SORT_STRING);

        if ($ready === []) {
            $ready = [array_key_first($remaining)];
            $cycleBreaks[] = $ready[0];
        }

        foreach ($ready as $table) {
            $ordered[] = $table;
            unset($remaining[$table]);
        }
    }

    return [$ordered, $cycleBreaks];
}

function splitForeignKeys(string $table, string $createSql): array
{
    $lines = preg_split('/\R/', $createSql);
    $kept = [];
    $foreignKeys = [];

    foreach ($lines as $line) {
        if (
            preg_match(
                '/^\s*(CONSTRAINT\s+`([^`]+)`\s+FOREIGN KEY\s+.*?)(,?)$/',
                $line,
                $matches
            ) === 1
        ) {
            $foreignKeys[] = [
                'table' => $table,
                'name' => $matches[2],
                'definition' => rtrim(trim($matches[1]), ','),
            ];
            continue;
        }

        $kept[] = $line;
    }

    for ($index = count($kept) - 1; $index >= 0; $index--) {
        if (preg_match('/^\)\s+ENGINE=/', $kept[$index]) === 1) {
            for ($previous = $index - 1; $previous >= 0; $previous--) {
                if (trim($kept[$previous]) !== '') {
                    $kept[$previous] = rtrim($kept[$previous], ',');
                    break;
                }
            }
            break;
        }
    }

    return [implode("\n", $kept), $foreignKeys];
}

function renderDocumentation(
    string $source,
    string $table,
    array $columns,
    string $tableComment,
    ?string $legacyDocumentation
): string {
    $parts = [];

    if ($legacyDocumentation !== null) {
        $parts[] = trim($legacyDocumentation);
    }

    $lines = [
        '/**',
        " * Estructura efectiva clonada desde `{$source}`.",
        " * Tabla: `{$table}`.",
    ];

    if ($tableComment !== '') {
        $lines[] = ' * Comentario de tabla: ' . phpDocText($tableComment);
    }

    $lines[] = ' *';
    $lines[] = ' * Inventario de columnas:';

    foreach ($columns as $column) {
        $attributes = [
            $column->COLUMN_TYPE,
            $column->IS_NULLABLE === 'YES' ? 'NULL' : 'NOT NULL',
        ];

        if ($column->COLUMN_DEFAULT !== null) {
            $attributes[] = 'DEFAULT ' . phpDocText((string) $column->COLUMN_DEFAULT);
        }

        if ($column->EXTRA !== '') {
            $attributes[] = $column->EXTRA;
        }

        if ($column->COLLATION_NAME !== null) {
            $attributes[] = 'COLLATE ' . $column->COLLATION_NAME;
        }

        if ($column->GENERATION_EXPRESSION !== '') {
            $attributes[] = 'GENERATED ' . phpDocText($column->GENERATION_EXPRESSION);
        }

        $description = $column->COLUMN_COMMENT === ''
            ? 'Sin comentario definido en el esquema fuente.'
            : phpDocText($column->COLUMN_COMMENT);

        $lines[] = sprintf(
            ' * - `%s`: %s — %s',
            $column->COLUMN_NAME,
            implode('; ', $attributes),
            $description
        );
    }

    $lines[] = ' */';
    $parts[] = implode("\n", $lines);

    return implode("\n\n", $parts);
}

function phpDocText(string $value): string
{
    $value = str_replace(["\r", "\n", '*/'], [' ', ' ', '* /'], $value);

    return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
}

function renderTableMigration(string $documentation, string $table, string $createSql): string
{
    return <<<PHP
<?php

{$documentation}

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
{$createSql}
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `{$table}`');
    }
};
PHP;
}

function renderForeignKeyMigration(array $foreignKeys): string
{
    $upStatements = [];
    $downRows = [];

    foreach ($foreignKeys as $foreignKey) {
        $upStatements[] = sprintf(
            "            <<<'SQL'\nALTER TABLE `%s` ADD %s\nSQL",
            $foreignKey['table'],
            $foreignKey['definition']
        );
        $downRows[] = sprintf(
            "            ['table' => '%s', 'name' => '%s']",
            addslashes($foreignKey['table']),
            addslashes($foreignKey['name'])
        );
    }

    $up = implode(",\n", $upStatements);
    $down = implode(",\n", array_reverse($downRows));

    return <<<PHP
<?php

/**
 * Agrega las claves foráneas después de crear todas las tablas.
 *
 * Este paso separado evita errores por dependencias circulares y permite que
 * el rollback retire primero todas las relaciones antes de borrar tablas.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        \$statements = [
{$up}
        ];

        foreach (\$statements as \$statement) {
            DB::unprepared(\$statement);
        }
    }

    public function down(): void
    {
        \$foreignKeys = [
{$down}
        ];

        foreach (\$foreignKeys as \$foreignKey) {
            DB::unprepared(
                sprintf(
                    'ALTER TABLE `%s` DROP FOREIGN KEY `%s`',
                    \$foreignKey['table'],
                    \$foreignKey['name']
                )
            );
        }
    }
};
PHP;
}

function writeGeneratedFile(string $path, string $contents): void
{
    if (file_put_contents($path, $contents . PHP_EOL) === false) {
        fail("No se pudo escribir {$path}.");
    }
}
// ######### FIN CAMBIO NELSON #########
