<?php

declare(strict_types=1);

use Database\Seeders\TenancyDatabaseSeeder;
use Database\Seeders\TenancyMockDataSeeder;
use Database\Seeders\TenantMigrationDataSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
// ########### INICIO VALIDADOR TENANT VENEZUELA
$projectRoot = dirname(__DIR__, 4);
require $projectRoot . '/vendor/autoload.php';
$app = require $projectRoot . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$options = getopt('', ['target:']);
$target = $options['target'] ?? null;

if (!is_string($target) || preg_match('/^[A-Za-z0-9_]+_data_test$/', $target) !== 1) {
    fwrite(STDERR, 'Use --target=NOMBRE_data_test.' . PHP_EOL);
    exit(1);
}

$admin = DB::connection('system');
if ($admin->table('websites')->where('uuid', $target)->exists()) {
    throw new RuntimeException("{$target} pertenece a un tenant registrado.");
}

$result = null;
$exitCode = 0;

try {
    $admin->unprepared("DROP DATABASE IF EXISTS `{$target}`");
    $admin->unprepared("CREATE DATABASE `{$target}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $connection = config('database.connections.system');
    $connection['database'] = $target;
    config([
        'database.default' => 'seed_validation',
        'database.connections.seed_validation' => $connection,
        'database.connections.tenant' => $connection,
    ]);
    DB::purge('seed_validation');
    DB::purge('tenant');
    DB::setDefaultConnection('seed_validation');
    DB::connection()->getPdo();

    runArtisan('migrate', [
        '--database' => 'seed_validation',
        '--path' => database_path('migrations/tenant'),
        '--realpath' => true,
        '--force' => true,
    ]);

    $initialSeeder = $app->make(TenantMigrationDataSeeder::class);
    $initialSeeder->setContainer($app);
    $initialSeeder->run();

    $base = require database_path('seeders/data/tenant_initial_data.php');
    $geo = require database_path('seeders/data/venezuela_geopolitical_data.php');
    $expectedTables = array_replace($base['tables'], $geo['tables']);
    $seedDifferences = [];

    foreach ($expectedTables as $table => $definition) {
        $expected = count($definition['rows']);
        $actual = DB::table($table)->count();
        if ($actual !== $expected) {
            $seedDifferences[$table] = compact('expected', 'actual');
        }
    }

    if ($seedDifferences !== []) {
        throw new RuntimeException('Conteos del seeder distintos: ' . json_encode($seedDifferences));
    }

    $catalog = catalogMetrics();
    assertCatalog($catalog);

    $mockSeeder = $app->make(TenancyMockDataSeeder::class);
    $mockSeeder->setContainer($app);
    $mockSeeder->run();
    $afterFirstMock = tableCounts();
    $mockSeeder->run();
    $afterSecondMock = tableCounts();

    if ($afterFirstMock !== $afterSecondMock) {
        throw new RuntimeException('TenancyMockDataSeeder no es idempotente por conteo.');
    }

    runArtisan('db:seed', [
        '--database' => 'seed_validation',
        '--class' => TenancyDatabaseSeeder::class,
        '--force' => true,
    ]);
    $afterFirstMainSeeder = tableCounts();
    runArtisan('db:seed', [
        '--database' => 'seed_validation',
        '--class' => TenancyDatabaseSeeder::class,
        '--force' => true,
    ]);
    $afterSecondMainSeeder = tableCounts();

    if ($afterFirstMainSeeder !== $afterSecondMainSeeder) {
        throw new RuntimeException('TenancyDatabaseSeeder no es idempotente por conteo.');
    }

    $orphans = foreignKeyOrphans($admin, $target);
    if ($orphans !== []) {
        throw new RuntimeException('Existen referencias huérfanas: ' . json_encode($orphans));
    }

    $mockMetrics = [
        'categories' => DB::table('categories')->where('name', 'like', 'MOCK-%')->count(),
        'brands' => DB::table('brands')->where('name', 'like', 'MOCK-%')->count(),
        'persons' => DB::table('persons')->where('number', 'like', 'MOCK-%')->count(),
        'items' => DB::table('items')->where('internal_id', 'like', 'MOCK-%')->count(),
    ];

    runArtisan('migrate:rollback', [
        '--database' => 'seed_validation',
        '--path' => database_path('migrations/tenant'),
        '--realpath' => true,
        '--force' => true,
    ]);

    $remainingTables = $admin->table('information_schema.TABLES')
        ->where('TABLE_SCHEMA', $target)
        ->where('TABLE_NAME', '<>', 'migrations')
        ->count();

    if ($remainingTables !== 0) {
        throw new RuntimeException("Rollback incompleto: {$remainingTables} tablas restantes.");
    }

    $result = [
        'target' => $target,
        'migration_files' => count(glob(database_path('migrations/tenant/*.php')) ?: []),
        'seeded_tables' => count($expectedTables),
        'seeded_rows' => array_sum(array_map(
            static fn (array $definition): int => count($definition['rows']),
            $expectedTables
        )),
        'catalog' => $catalog,
        'mock' => $mockMetrics,
        'foreign_key_orphans' => 0,
        'main_seeder_idempotent' => true,
        'rollback' => 'ok',
    ];
} catch (Throwable $exception) {
    $exitCode = 1;
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
} finally {
    DB::purge('tenant');
    DB::purge('seed_validation');
    DB::setDefaultConnection('system');
    $admin->unprepared("DROP DATABASE IF EXISTS `{$target}`");
}

if ($result !== null) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

exit($exitCode);

function runArtisan(string $command, array $arguments): void
{
    $status = Artisan::call($command, $arguments);
    if ($status !== 0) {
        throw new RuntimeException("{$command} falló:\n" . Artisan::output());
    }
}

function catalogMetrics(): array
{
    return [
        'countries' => DB::table('countries')->count(),
        'peru_countries' => DB::table('countries')->where('id', 'PE')->count(),
        'departments' => DB::table('departments')->count(),
        'provinces' => DB::table('provinces')->count(),
        'districts' => DB::table('districts')->count(),
        'ves' => DB::table('cat_currency_types')->where('id', 'VES')->where('active', 1)->count(),
        'usd' => DB::table('cat_currency_types')->where('id', 'USD')->where('active', 1)->count(),
        'legacy_currency' => DB::table('cat_currency_types')->whereIn('id', ['PEN', 'VED'])->count(),
    ];
}

function assertCatalog(array $catalog): void
{
    $expected = [
        'countries' => 239,
        'peru_countries' => 0,
        'departments' => 25,
        'provinces' => 335,
        'districts' => 1138,
        'ves' => 1,
        'usd' => 1,
        'legacy_currency' => 0,
    ];

    if ($catalog !== $expected) {
        throw new RuntimeException('Catálogo final inesperado: ' . json_encode($catalog));
    }
}

function tableCounts(): array
{
    $tables = DB::table('information_schema.TABLES')
        ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
        ->where('TABLE_NAME', '<>', 'migrations')
        ->orderBy('TABLE_NAME')
        ->pluck('TABLE_NAME');

    $counts = [];
    foreach ($tables as $table) {
        $counts[$table] = DB::table($table)->count();
    }

    return $counts;
}

function foreignKeyOrphans($admin, string $database): array
{
    $keys = $admin->table('information_schema.KEY_COLUMN_USAGE')
        ->select(['TABLE_NAME', 'COLUMN_NAME', 'REFERENCED_TABLE_NAME', 'REFERENCED_COLUMN_NAME'])
        ->where('TABLE_SCHEMA', $database)
        ->whereNotNull('REFERENCED_TABLE_NAME')
        ->get();
    $orphans = [];

    foreach ($keys as $key) {
        $table = str_replace('`', '``', $key->TABLE_NAME);
        $column = str_replace('`', '``', $key->COLUMN_NAME);
        $referencedTable = str_replace('`', '``', $key->REFERENCED_TABLE_NAME);
        $referencedColumn = str_replace('`', '``', $key->REFERENCED_COLUMN_NAME);
        $count = DB::selectOne(
            "SELECT COUNT(*) AS aggregate FROM `{$table}` child
             LEFT JOIN `{$referencedTable}` parent
               ON child.`{$column}` = parent.`{$referencedColumn}`
             WHERE child.`{$column}` IS NOT NULL AND parent.`{$referencedColumn}` IS NULL"
        )->aggregate;

        if ((int) $count > 0) {
            $orphans["{$table}.{$column}"] = (int) $count;
        }
    }

    return $orphans;
}
// ########### FIN VALIDADOR TENANT VENEZUELA
