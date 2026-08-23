<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PurchaseDefaultWarehouseSourceContractTest extends TestCase
{
    /** @test */
    public function purchase_item_modal_uses_the_tenant_default_warehouse_after_loading_its_options(): void
    {
        $source = $this->source('resources/js/views/tenant/purchases/partials/item.vue');

        self::assertStringContainsString('this.warehouses = response.data.warehouses', $source);
        self::assertStringContainsString('this.form.warehouse_id = this.defaultWarehouseId(response.data.configuration)', $source);
        self::assertStringContainsString('defaultWarehouseId(configuration = this.config)', $source);
        self::assertStringContainsString('String(warehouse.id) === String(configuredWarehouseId)', $source);
        self::assertStringNotContainsString('let warehouse = 1', $source);
    }

    /** @test */
    public function tenant_configuration_exposes_the_establishment_default_warehouse(): void
    {
        $source = $this->source('app/Models/Tenant/Configuration.php');

        self::assertStringContainsString("Warehouse::where('establishment_id', \$establishment_id)->first()", $source);
        self::assertStringContainsString("'warehouse_id' => \$warehouse->id", $source);
    }

    private function source(string $relativePath): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$relativePath);
        self::assertNotFalse($source, $relativePath);

        return $source;
    }
}
