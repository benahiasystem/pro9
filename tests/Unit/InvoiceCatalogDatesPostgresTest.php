<?php

namespace Tests\Unit;

use App\Http\Controllers\SearchItemController;
use App\Http\Resources\Tenant\ItemCollection;
use App\Models\Tenant\{Item, ItemWarehousePrice, Warehouse};
use App\Support\Exact\Decimal as D;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** @group postgres-integration */
final class InvoiceCatalogDatesPostgresTest extends TestCase
{
    public function test_catalog_dates_are_independent_and_invoice_selection_keeps_original_warehouse_price(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);

        try {
            [$db, , $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture, false);
            $db->table('configurations')->update(['decimal_quantity' => 2]);
            $id = $input['items'][0]['item_id'];
            $item = Item::findOrFail($id);
            $item->setExactValues(['sale_unit_price' => D::of('1/3')])
                ->forceFill(['sale_affectation_igv_type_id' => '20'])->save();
            $request = Request::create('/items/records', 'GET');
            $created = '2026-08-18 09:10:11';
            $updated = '2026-09-19 10:11:12';
            foreach ([[$created, null], [null, $updated], [$created, $updated], [null, null]] as [$creation, $revision]) {
                $db->table('items')->where('id', $id)->update(['created_at' => $creation, 'updated_at' => $revision]);
                $catalog = (new ItemCollection(collect([$item->fresh()])))->resolve($request)[0];
                self::assertSame($creation ?? '', $catalog['created_at']);
                self::assertSame($revision ?? '', $catalog['updated_at']);
                self::assertSame('Bs. 0.33', $catalog['sale_unit_price_with_igv']);
            }

            $branch = $input['establishment_id'];
            $nearby = 9007199254740992;
            $selected = 9007199254740993;
            foreach ([$nearby, $selected] as $warehouseId) {
                $db->table('warehouses')->insert(['id' => $warehouseId, 'establishment_id' => $branch, 'description' => 'Almacén temporal']);
                $db->table('item_warehouse')->insert(['item_id' => $id, 'warehouse_id' => $warehouseId, 'stock' => '10']);
            }
            (new ItemWarehousePrice(['item_id' => $id, 'warehouse_id' => $selected]))
                ->setExactValues(['price' => D::of('2/3')])->save();
            $warehouse = Warehouse::findOrFail($selected);
            $db->table('items')->where('id', $id)->update(['created_at' => $created, 'updated_at' => null, 'date_of_due' => '2027-08-01']);
            $invoiceOption = $item->fresh()->getDataToItemModal($warehouse, forInvoice: true);
            $selectedRows = collect($invoiceOption['warehouses'])->keyBy('warehouse_id');
            self::assertFalse($selectedRows[$nearby]['checked']);
            self::assertTrue($selectedRows[$selected]['checked']);
            self::assertSame('2027-08-01', $invoiceOption['date_of_due']);
            self::assertSame('0.666666666667', $invoiceOption['sale_unit_price']);
            self::assertSame(['n' => '2', 'd' => '3'], $invoiceOption['calculation_snapshot']['values']['sale_unit_price']);
            $legacyOption = $item->fresh()->getDataToItemModal($warehouse);
            self::assertSame($invoiceOption['sale_unit_price'], $legacyOption['sale_unit_price']);
            self::assertSame(['n' => '666666666667', 'd' => '1000000000000'], $legacyOption['calculation_snapshot']['values']['sale_unit_price']);
            $noteOption = SearchItemController::TransformToModalSaleNote(collect([$item->fresh()]), $warehouse)->sole();
            self::assertSame('', $noteOption['updated_at']);
            self::assertSame('Bs. 0.33', $noteOption['sale_unit_price_with_igv']);
            self::assertSame($invoiceOption['sale_unit_price'], $noteOption['sale_unit_price']);
            self::assertSame($invoiceOption['calculation_snapshot']['values']['sale_unit_price'], $noteOption['calculation_snapshot']['values']['sale_unit_price']);
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }
}
