<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

// ######## INICIO CONTRATO ORDEN DE MIGRACIONES TENANT ########
class TenantMigrationOrderContractTest extends TestCase
{
    public function test_all_tables_precede_the_final_foreign_keys(): void
    {
        $root = dirname(__DIR__, 2) . '/database/migrations/tenant/';
        $files = glob($root . '*.php');
        sort($files, SORT_STRING);
        self::assertSame('2026_08_17_000999_add_tenant_foreign_keys.php', basename(array_pop($files)));
        foreach ($files as $file) {
            self::assertMatchesRegularExpression('/_create_.+_table\.php$/', $file);
            self::assertStringNotContainsString('ADD CONSTRAINT', file_get_contents($file));
        }
        $quotations = file_get_contents($root . '2026_08_17_000276_create_quotations_table.php');
        self::assertStringContainsString("`source` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin'", $quotations);
        self::assertStringNotContainsString('number_year', $quotations);
    }
}
// ######## FIN CONTRATO ORDEN DE MIGRACIONES TENANT ########
