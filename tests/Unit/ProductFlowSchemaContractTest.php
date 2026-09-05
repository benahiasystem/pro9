<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

// ########### INICIO CONTRATO FLUJO DE PRODUCTOS ###########
class ProductFlowSchemaContractTest extends TestCase
{
    private const REPAIR_MIGRATION = '2026_08_23_000001_repair_items_parent_item_contract.php';

    /** @test */
    public function the_consolidated_items_schema_contains_the_variation_parent_contract(): void
    {
        $source = $this->source('database/migrations/tenant/2026_08_17_000228_create_items_table.php');

        self::assertStringContainsString('`parent_item_id` int(10) unsigned DEFAULT NULL', $source);
        self::assertStringContainsString('KEY `items_parent_item_id_index` (`parent_item_id`)', $source);
    }

    /** @test */
    public function the_legacy_migration_is_safe_when_it_runs_before_items_are_created(): void
    {
        $source = $this->source('database/migrations/tenant/2026_08_08_130100_tenant_add_parent_item_id_to_items.php');

        self::assertStringContainsString("! Schema::hasTable('items')", $source);
        self::assertStringContainsString("Schema::hasColumn('items', 'parent_item_id')", $source);
    }

    /** @test */
    public function the_repair_precedes_only_the_main_migrations_appended_during_integration(): void
    {
        $files = glob($this->root().'/database/migrations/tenant/*.php');
        self::assertNotFalse($files);

        $names = array_map('basename', $files);
        sort($names, SORT_STRING);

        $repairPosition = array_search(self::REPAIR_MIGRATION, $names, true);
        self::assertNotFalse($repairPosition);
        self::assertSame([
            '2026_08_23_000002_add_menu_preferences_to_users.php',
            '2026_08_23_000003_tenant_add_inventory_to_app_modules.php',
            '2026_08_23_000004_tenant_add_finance_to_app_modules.php',
            '2026_08_31_120000_tenant_add_enable_global_discount_to_configurations.php',
            '2026_08_31_130000_tenant_set_nrus_flag_in_plan_config.php',
            '2026_09_04_000001_sync_venezuela_identity_document_types.php',
        ], array_slice($names, $repairPosition + 1));
    }

    /** @test */
    public function the_repair_is_idempotent_and_reversible_for_column_index_and_foreign_key(): void
    {
        $source = $this->source('database/migrations/tenant/'.self::REPAIR_MIGRATION);

        self::assertStringContainsString('Schema::hasColumn(self::TABLE, self::COLUMN)', $source);
        self::assertStringContainsString('if (! $this->indexExists())', $source);
        self::assertStringContainsString('if (! $this->foreignExists())', $source);
        self::assertStringContainsString('$table->dropForeign(self::FOREIGN)', $source);
        self::assertStringContainsString('$table->dropIndex(self::INDEX)', $source);
        self::assertStringContainsString('$table->dropColumn(self::COLUMN)', $source);
    }

    private function source(string $relativePath): string
    {
        $source = file_get_contents($this->root().'/'.$relativePath);
        self::assertNotFalse($source, $relativePath);

        return $source;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
// ########### FIN CONTRATO FLUJO DE PRODUCTOS ###########
