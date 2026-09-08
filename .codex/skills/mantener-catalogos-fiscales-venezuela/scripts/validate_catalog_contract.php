<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant\Catalogs\DetractionType;
use App\Models\Tenant\Catalogs\PerceptionType;
use App\Models\Tenant\Catalogs\RelatedDocumentType;
use App\Models\Tenant\Catalogs\SummaryStatusType;
use App\Models\Tenant\Catalogs\SystemIscType;
use Modules\PseService\Models\PseProvider;

$projectRoot = dirname(__DIR__, 4);
require $projectRoot.'/vendor/autoload.php';
$app = require $projectRoot.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$target = 'pro9_venezuela_catalog_schema_test';
$admin = DB::connection('system');

if ($admin->table('websites')->where('uuid', $target)->exists()) {
    throw new RuntimeException("La base temporal {$target} pertenece a un tenant registrado.");
}

$previousDefault = DB::getDefaultConnection();

try {
    $admin->unprepared("DROP DATABASE IF EXISTS `{$target}`");
    $admin->unprepared("CREATE DATABASE `{$target}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

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
        'pse_providers', 'cat_detraction_types',
    ] as $table) {
        assertSame(false, $schema->hasTable($table), "La tabla {$table} no debe existir.");
    }

    foreach ([
        'banks' => 13,
        'cat_affectation_igv_types' => 2,
        'cat_attribute_types' => 27,
        'cat_charge_discount_types' => 6,
        'cat_document_types' => 10,
        'cat_legend_types' => 1,
        'cat_note_credit_types' => 4,
        'cat_note_debit_types' => 3,
        'cat_operation_types' => 2,
        'cat_payment_method_types' => 13,
        'cat_transfer_reason_types' => 6,
        'expense_reasons' => 18,
        'groups' => 1,
        'departments' => 25,
        'provinces' => 335,
        'districts' => 1138,
    ] as $table => $count) {
        assertSame($count, DB::connection('tenant')->table($table)->count(), "Conteo incorrecto en {$table}.");
    }

    foreach ([
        SystemIscType::available(),
        RelatedDocumentType::available(),
        PerceptionType::available(),
        SummaryStatusType::available(['1', '2']),
        DetractionType::available(),
        PseProvider::available(),
    ] as $optionalCatalog) {
        assertSame(0, $optionalCatalog->count(), 'Un consumidor consultó un catálogo retirado.');
    }

    echo "Migración y seeding venezolano verificados en {$target}.\n";
} finally {
    DB::setDefaultConnection($previousDefault);
    DB::purge('tenant');
    $admin = DB::connection('system');
    $admin->unprepared("DROP DATABASE IF EXISTS `{$target}`");
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
