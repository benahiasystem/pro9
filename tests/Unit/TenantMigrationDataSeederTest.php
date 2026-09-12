<?php

namespace Tests\Unit;

use Tests\TestCase;

// ########### INICIO CAMBIO RECONSTRUCCIÓN MIGRACIONES TENANT
class TenantMigrationDataSeederTest extends TestCase
{
    /** @test */
    public function it_contains_the_current_initial_catalogs(): void
    {
        $tables = $this->tables();
        $totalRows = array_sum(array_map(
            static fn (array $table): int => count($table['rows']),
            $tables
        ));

        // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
        self::assertCount(72, $tables);
        self::assertSame(861, $totalRows);
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
        self::assertCount(325, $createMigrations);
        self::assertCount(1, $foreignKeyMigrations);
        self::assertCount(326, glob(database_path('migrations/tenant/*.php')) ?: []);
        self::assertSame([], glob(database_path('migrations/tenant/*_migrate_*.php')) ?: []);

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

        self::assertSame('catálogos iniciales Venezuela', $payload['source']);
        self::assertIsArray($payload['tables']);

        return $payload['tables'];
    }
}
// ########### FIN CAMBIO RECONSTRUCCIÓN MIGRACIONES TENANT
