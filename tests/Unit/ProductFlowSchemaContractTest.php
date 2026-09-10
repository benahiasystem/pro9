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
    public function the_reordered_parent_item_migration_remains_idempotent(): void
    {
        $source = $this->source('database/migrations/tenant/2026_08_17_010010_tenant_add_parent_item_id_to_items.php');

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
            '2026_09_04_000002_ensure_warehouse_transfer_series.php',
            '2026_09_04_000003_rename_dispatch_document_types_to_delivery_orders.php',
            '2026_09_04_000004_disable_carrier_dispatch_document_types.php',
            '2026_09_04_000005_ensure_warehouse_internal_series.php',
            '2026_09_04_000006_add_transfer_id_to_temporary_kardex_records.php',
            '2026_09_04_000007_rename_warehouse_guides_to_notes.php',
            '2026_09_04_000008_create_offline_machines_table.php',
            '2026_09_04_000009_create_sync_events_table.php',
            '2026_09_04_000010_add_offline_columns_to_sync_events_table.php',
            '2026_09_04_000011_tenant_add_pos_image_aspect_ratio_to_configurations.php',
            '2026_09_04_000012_tenant_add_pos_image_fit_to_configurations.php',
            '2026_09_04_160000_tenant_add_dispatch_id_to_inventories_transfer.php',
            '2026_09_07_130000_tenant_backfill_default_series.php',
            '2026_09_08_160000_tenant_fix_editable_flag_on_documents_from_order_or_quotation.php',
            '2026_09_08_170000_tenant_add_seller_id_to_order_notes.php',
            '2026_09_09_150000_tenant_add_configuration_menu_module_level.php',
            '2026_09_10_160000_tenant_add_utilidades_widget_to_dashboard_layouts.php',
            '2026_09_10_200000_tenant_rollback_default_usd_exonerado_and_pos_sale_note.php',
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
