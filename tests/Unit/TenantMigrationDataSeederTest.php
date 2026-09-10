<?php

namespace Tests\Unit;

use Tests\TestCase;

// ########### INICIO CAMBIO RECONSTRUCCIÓN MIGRACIONES TENANT
class TenantMigrationDataSeederTest extends TestCase
{
    /** @test */
    public function it_contains_all_consolidated_historical_seed_records(): void
    {
        $tables = $this->tables();
        $totalRows = array_sum(array_map(
            static fn (array $table): int => count($table['rows']),
            $tables
        ));

        // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
        self::assertCount(73, $tables);
        self::assertSame(858, $totalRows);
        // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES

        foreach ($tables as $table => $definition) {
            self::assertNotEmpty($definition['key_columns'], $table);
            self::assertNotEmpty($definition['rows'], $table);
            self::assertCount(
                1,
                glob(database_path("migrations/tenant/*_create_{$table}_table.php")) ?: [],
                "Debe existir una migración consolidada para {$table}."
            );
        }
    }

    /** @test */
    public function every_application_table_has_one_consolidated_migration(): void
    {
        $createMigrations = glob(database_path('migrations/tenant/*_create_*_table.php')) ?: [];
        $foreignKeyMigrations = glob(database_path('migrations/tenant/*_add_tenant_foreign_keys.php')) ?: [];
        $currencyMigrations = glob(database_path('migrations/tenant/*_migrate_currency_code_to_ves.php')) ?: [];
        $existingTenantMigrations = glob(database_path('migrations/tenant/*_migrate_existing_tenant_to_venezuela.php')) ?: [];
        // ########## INICIO CAMBIO AFECTACIÓN IVA
        $ivaMigrations = glob(database_path('migrations/tenant/*_configure_venezuela_iva.php')) ?: [];
        // ######### FIN CAMBIO AFECTACIÓN IVA
        // ########## INICIO CAMBIO SUNAT A SENIAT
        $identityDocumentRenameMigrations = glob(database_path('migrations/tenant/*_rename_undomiciled_tax_document_to_doc_sin_rif.php')) ?: [];
        // ######### FIN CAMBIO SUNAT A SENIAT
        // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
        $catalogNameMigrations = glob(database_path('migrations/tenant/*_configure_venezuela_catalog_names.php')) ?: [];
        // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES

        // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
        self::assertCount(323, $createMigrations);
        // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
        self::assertCount(1, $foreignKeyMigrations);
        self::assertCount(1, $currencyMigrations);
        self::assertCount(1, $existingTenantMigrations);
        // ########## INICIO CAMBIO AFECTACIÓN IVA
        self::assertCount(1, $ivaMigrations);
        // ######### FIN CAMBIO AFECTACIÓN IVA
        // ########## INICIO CAMBIO SUNAT A SENIAT
        self::assertCount(1, $identityDocumentRenameMigrations);
        // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
        self::assertCount(1, $catalogNameMigrations);
        // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
        // ########### INICIO CONTRATO FLUJO DE PRODUCTOS ###########
        self::assertCount(
            1,
            glob(database_path('migrations/tenant/*_repair_items_parent_item_contract.php')) ?: []
        );
        // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
        self::assertCount(358, glob(database_path('migrations/tenant/*.php')) ?: []);
        // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
        // ########### FIN CONTRATO FLUJO DE PRODUCTOS ###########
        // ######### FIN CAMBIO SUNAT A SENIAT
    }

    /** @test */
    public function tenancy_database_seeder_runs_the_consolidated_initial_data(): void
    {
        $source = (string) file_get_contents(database_path('seeders/TenancyDatabaseSeeder.php'));

        self::assertStringContainsString(
            '$this->call(TenantMigrationDataSeeder::class)',
            $source
        );
    }

    private function tables(): array
    {
        $payload = require database_path('seeders/data/tenant_initial_data.php');

        self::assertSame('migraciones tenant históricas', $payload['source']);
        self::assertIsArray($payload['tables']);

        return $payload['tables'];
    }
}
// ########### FIN CAMBIO RECONSTRUCCIÓN MIGRACIONES TENANT
