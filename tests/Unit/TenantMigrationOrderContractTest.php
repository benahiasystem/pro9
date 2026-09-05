<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

// ########### INICIO CONTRATO ORDEN DE MIGRACIONES TENANT ###########
class TenantMigrationOrderContractTest extends TestCase
{
    /** @test */
    public function tenant_extensions_run_after_the_consolidated_schema(): void
    {
        $root = dirname(__DIR__, 2).'/database/migrations/tenant/';
        $files = glob($root.'*.php');

        self::assertNotFalse($files);

        $names = array_map('basename', $files);
        sort($names, SORT_STRING);

        $foreignKeys = array_search('2026_08_17_000328_add_tenant_foreign_keys.php', $names, true);
        $quotationTable = array_search('2026_08_17_000276_create_quotations_table.php', $names, true);
        $quotationSource = array_search('2026_08_17_010000_tenant_add_source_to_quotations_table.php', $names, true);

        self::assertNotFalse($foreignKeys);
        self::assertNotFalse($quotationTable);
        self::assertNotFalse($quotationSource);
        self::assertLessThan($quotationSource, $quotationTable);
        self::assertLessThan($quotationSource, $foreignKeys);
    }

    /** @test */
    public function no_tenant_migration_precedes_the_consolidated_schema(): void
    {
        $root = dirname(__DIR__, 2).'/database/migrations/tenant/';
        $files = glob($root.'*.php');

        self::assertNotFalse($files);

        foreach ($files as $file) {
            self::assertGreaterThanOrEqual(
                '2026_08_17_000001',
                substr(basename($file), 0, strlen('2026_08_17_000001')),
                basename($file).' se ejecutaría antes de crear las tablas base.'
            );
        }
    }
}
// ########### FIN CONTRATO ORDEN DE MIGRACIONES TENANT ###########
