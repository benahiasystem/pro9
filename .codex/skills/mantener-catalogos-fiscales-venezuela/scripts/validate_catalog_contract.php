<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$projectRoot = dirname(__DIR__, 4);
require $projectRoot.'/vendor/autoload.php';
$app = require $projectRoot.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$target = 'pro9_ve_catalog_test_'.bin2hex(random_bytes(6));
$admin = DB::connection('system');

if ($admin->table('websites')->where('uuid', $target)->exists()) {
    throw new RuntimeException("La base temporal {$target} pertenece a un tenant registrado.");
}

$previousDefault = DB::getDefaultConnection();
$createdDatabase = false;

try {
    $admin->unprepared("CREATE DATABASE `{$target}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $createdDatabase = true;

    $tenant = config('database.connections.system');
    $tenant['database'] = $target;
    config(['database.connections.tenant' => $tenant]);
    DB::purge('tenant');
    DB::setDefaultConnection('tenant');

    runArtisan('migrate', [
        '--database' => 'tenant',
        '--path' => $projectRoot.'/database/migrations/tenant',
        '--realpath' => true,
        '--force' => true,
    ]);

    // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
    assertSame(0, DB::connection('tenant')->table('expense_reasons')->count(), 'La migración de expense_reasons no debe insertar datos.');
    // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES

    runArtisan('db:seed', [
        '--database' => 'tenant',
        '--class' => 'Database\\Seeders\\TenancyDatabaseSeeder',
        '--force' => true,
    ]);

    $schema = DB::connection('tenant')->getSchemaBuilder();
    foreach ([
        'cat_other_tax_concept_types', 'cat_perception_types',
        'cat_related_documents_types', 'cat_related_tax_document_types',
        'cat_summary_status_types', 'cat_system_isc_types',
        'pse_providers', 'cat_detraction_types', 'cat_payment_method_types',
    ] as $table) {
        assertSame(false, $schema->hasTable($table), "La tabla {$table} no debe existir.");
    }

    foreach ([
        'banks' => 13,
        'payment_method_types' => 12,
        'cat_affectation_igv_types' => 2,
        'cat_attribute_types' => 27,
        'cat_charge_discount_types' => 6,
        'cat_document_types' => 10,
        'cat_legend_types' => 1,
        'cat_note_credit_types' => 4,
        'cat_note_debit_types' => 3,
        'cat_operation_types' => 2,
        'cat_transfer_reason_types' => 6,
        'expense_reasons' => 30,
        'groups' => 1,
        'departments' => 25,
        'provinces' => 335,
        'districts' => 1138,
    ] as $table => $count) {
        assertSame($count, DB::connection('tenant')->table($table)->count(), "Conteo incorrecto en {$table}.");
    }

    // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
    $expectedExpenseReasons = [
        1 => 'Honorarios profesionales',
        2 => 'Publicidad, propaganda y mercadeo',
        3 => 'Comisiones de ventas y corretaje',
        4 => 'Mantenimiento y reparación',
        5 => 'Vigilancia y seguridad',
        6 => 'Limpieza y aseo',
        7 => 'Fletes, transporte y mensajería',
        8 => 'Arrendamiento de inmuebles',
        9 => 'Alquiler de bienes muebles y equipos',
        10 => 'Energía eléctrica',
        11 => 'Agua potable',
        12 => 'Telecomunicaciones, Internet y servicios digitales',
        13 => 'Viáticos, viajes y movilización',
        14 => 'Gastos de representación',
        15 => 'Papelería, útiles y suministros de oficina',
        16 => 'Impuestos, tasas y contribuciones',
        17 => 'Multas, sanciones e intereses de mora',
        18 => 'Gastos sin soporte fiscal válido',
        19 => 'Sueldos, salarios y remuneraciones',
        20 => 'Beneficios laborales y prestaciones sociales',
        21 => 'Aportes patronales: IVSS, FAOV e INCES',
        22 => 'Seguros y pólizas',
        23 => 'Gastos bancarios, comisiones y servicios financieros',
        24 => 'Intereses y gastos de financiamiento',
        25 => 'Depreciación y amortización',
        26 => 'Combustible, lubricantes y peajes',
        27 => 'Repuestos y mantenimiento de vehículos',
        28 => 'Sistemas, software, licencias y suscripciones',
        29 => 'Servicios profesionales técnicos y consultoría',
        30 => 'Otros gastos operativos',
    ];
    $actualExpenseReasons = DB::connection('tenant')->table('expense_reasons')
        ->orderBy('id')
        ->pluck('description', 'id')
        ->all();
    assertSame($expectedExpenseReasons, $actualExpenseReasons, 'El catálogo expense_reasons no coincide con el contrato venezolano.');
    // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES

    foreach (['documents', 'sale_notes', 'purchases', 'quotations', 'order_notes', 'contracts', 'fixed_asset_purchases', 'suscription_plans', 'user_rel_suscription_plans'] as $table) {
        assertSame(false, $schema->hasColumn($table, 'detraction'), "Columna retirada en {$table}.");
    }
    foreach (['items' => ['subject_to_detraction'], 'companies' => ['detraction_account'], 'configurations' => ['detraction_amount_rounded_int', 'available_detraction_for_amount_minor']] as $table => $columns) {
        foreach ($columns as $column) {
            assertSame(false, $schema->hasColumn($table, $column), "Columna retirada {$table}.{$column}.");
        }
    }

    echo "Migración y seeding venezolano verificados en {$target}.\n";
} finally {
    DB::setDefaultConnection($previousDefault);
    DB::purge('tenant');
    $admin = DB::connection('system');
    if ($createdDatabase) {
        $admin->unprepared("DROP DATABASE IF EXISTS `{$target}`");
    }
}

function runArtisan(string $command, array $parameters): void
{
    if (Artisan::call($command, $parameters) !== 0) {
        throw new RuntimeException("{$command} falló:\n".Artisan::output());
    }
}

function assertSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException("{$message} Esperado: ".var_export($expected, true).'; actual: '.var_export($actual, true));
    }
}
