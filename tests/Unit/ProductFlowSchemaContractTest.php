<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

// ######## INICIO CONTRATO FLUJO DE PRODUCTOS ########
class ProductFlowSchemaContractTest extends TestCase
{
    public function test_parent_structure_is_created_directly(): void
    {
        $root = dirname(__DIR__, 2) . '/database/migrations/tenant/';
        $source = file_get_contents($root . '2026_08_17_000228_create_items_table.php');
        self::assertStringContainsString('`parent_item_id` int(10) unsigned DEFAULT NULL', $source);
        self::assertStringContainsString('KEY `items_parent_item_id_index` (`parent_item_id`)', $source);
        $keys = file_get_contents($root . '2026_08_17_000999_add_tenant_foreign_keys.php');
        self::assertStringContainsString('FOREIGN KEY (`parent_item_id`) REFERENCES `items` (`id`)', $keys);
        self::assertStringContainsString('DROP FOREIGN KEY `items_parent_item_id_foreign`', $keys);
        self::assertSame([], glob($root . '*repair_items*'));
        foreach (['product_variables', 'product_variable_values', 'item_variation_values'] as $table) {
            self::assertCount(1, glob($root . '*_create_' . $table . '_table.php'));
        }
    }
}
// ######## FIN CONTRATO FLUJO DE PRODUCTOS ########
