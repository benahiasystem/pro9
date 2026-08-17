<?php

namespace Tests\Unit;

use Tests\TestCase;

// ######## INICIO PRUEBAS PUENTE TENANT HISTÓRICO VENEZUELA ########
class ExistingTenantVenezuelaMigrationTest extends TestCase
{
    /** @test */
    public function it_has_an_incremental_migration_for_existing_tenants(): void
    {
        $source = file_get_contents(app_path('Support/Venezuela/ExistingTenantMigrator.php'));

        self::assertStringContainsString("'country_id', 'PE', 'VE'", $source);
        self::assertStringContainsString("'nationality_id', 'PE', 'VE'", $source);
        self::assertStringContainsString("'Cédula de Identidad (V)'", $source);
        self::assertStringContainsString("'RIF (V/E/J/G/P)'", $source);
        self::assertStringContainsString("DEFAULT 'VE'", $source);
        self::assertStringContainsString('25 estados, 335 municipios y 1138 parroquias', $source);
    }

    /** @test */
    public function it_reconciles_only_the_consolidated_baseline_before_incremental_changes(): void
    {
        $source = file_get_contents(app_path('Console/Commands/MigrateExistingTenantToVenezuela.php'));

        self::assertStringContainsString("tenant:migrate-venezuela", $source);
        self::assertStringContainsString('_000(?:[0-2][0-9]{2}|3(?:0[0-9]|1[0-9]|2[0-8]))_', $source);
        self::assertStringContainsString('2026_08_17_000329', $source);
        self::assertStringContainsString('2026_08_17_000330', $source);
        self::assertStringContainsString('where(\'uuid\', $tenant)', $source);
    }
}
// ######## FIN PRUEBAS PUENTE TENANT HISTÓRICO VENEZUELA ########
