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
    public function test_item_profit_replay_rejects_unrepresentable_values_without_snapshot_column(): void
    {
        $fixture=new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);
        try {
            [$db, , $input]=(new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture, false);
            $id=$input['items'][0]['item_id'];
            $db->table('items')->where('id',$id)->update(['sale_unit_price'=>'1','purchase_unit_price'=>'3','percentage_of_profit'=>'0']);
            $db->statement('ALTER TABLE items DROP COLUMN calculation_snapshot');
            // Reconnect so the per-connection schema cache observes the historical schema.
            app('db')->purge('tenant');
            $item=Item::findOrFail($id);
            $payload=(new \App\Http\Resources\Tenant\ItemResource($item))->resolve(Request::create('/items/record/'.$id));
            $payload['sale_unit_price']='1'; $payload['percentage_of_profit']='-66.666666666667';
            $payload['exact_profit_sequence']=[['field'=>'sale_unit_price','value'=>'1']];
            try {
                app(\App\Http\Controllers\Tenant\ItemController::class)->store(\App\Http\Requests\Tenant\ItemRequest::create('/items','POST',$payload));
                self::fail('El esquema histórico no debe perder una fracción.');
            } catch (\Illuminate\Validation\ValidationException $error) {
                self::assertArrayHasKey('percentage_of_profit',$error->errors());
            }
            self::assertSame('0',(string) Item::findOrFail($id)->percentage_of_profit);
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }

    public function test_item_store_locks_and_rolls_back_on_the_tenant_connection(): void
    {
        $fixture=new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);
        $active=false; $default=null;
        try {
            [$db, , $input]=(new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture, false);
            $item=Item::findOrFail($input['items'][0]['item_id']);
            $payload=(new \App\Http\Resources\Tenant\ItemResource($item))->resolve(Request::create('/items/record/'.$item->id));
            $payload['sale_unit_price']='5'; $payload['exact_edited_fields']=['sale_unit_price'];
            config(['database.connections.item_probe'=>$db->getConfig()]);
            $manager=app('db'); $default=$manager->getDefaultConnection(); $manager->setDefaultConnection('system');
            $probe=$manager->connection('item_probe'); $locked=false; $level=0; $active=true; $id=$item->id;
            Item::saving(function ($changed) use (&$active,&$locked,&$level,$probe,$db,$id) {
                if (!$active || (string)$changed->id !== (string)$id) return;
                $level=$db->transactionLevel(); $probe->beginTransaction();
                try { $probe->select('SELECT id FROM items WHERE id = ? FOR UPDATE NOWAIT',[$id]); }
                catch (\Illuminate\Database\QueryException $error) { $locked=($error->errorInfo[0] ?? null)==='55P03'; }
                finally { $probe->rollBack(); }
            });
            $controller=app(\App\Http\Controllers\Tenant\ItemController::class);
            $result=$controller->store(\App\Http\Requests\Tenant\ItemRequest::create('/items','POST',$payload));
            self::assertTrue($result['success'], $result['message'] ?? '');
            self::assertGreaterThanOrEqual(1,$level); self::assertTrue($locked);
            $active=false; $before=Item::findOrFail($id)->exactValue('sale_unit_price');
            $payload['sale_unit_price']='7';
            $payload['item_unit_types']=[['id'=>null,'description'=>'Unidad inválida','unit_type_id'=>'UND',
                'quantity_unit'=>'invalid','price_default'=>'1']];
            try {
                $result=$controller->store(\App\Http\Requests\Tenant\ItemRequest::create('/items','POST',$payload));
                self::assertFalse($result['success']);
            } catch (\Illuminate\Validation\ValidationException $error) {
                self::assertNotEmpty($error->errors());
            }
            self::assertTrue(Item::findOrFail($id)->exactValue('sale_unit_price')->isEqualTo($before));
        } finally {
            $active=false;
            if ($default !== null) app('db')->setDefaultConnection($default);
            app('db')->disconnect('item_probe');
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }

    public function test_item_detail_preserves_exact_large_and_fractional_sale_prices(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);
        try {
            [$db, , $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture, false);
            $id=$input['items'][0]['item_id'];
            foreach (['9007199254740993.000000000001', '0.107', '1/3'] as $price) {
                $item=Item::findOrFail($id);
                $item->setExactValues(['sale_unit_price'=>D::of($price),'purchase_unit_price'=>D::of($price),'percentage_of_profit'=>D::of($price)])->save();
                $fresh=Item::findOrFail($id);
                $detail=(new \App\Http\Resources\Tenant\ItemResource($fresh))->resolve(Request::create('/items/record/'.$id));
                self::assertSame(D::represent(D::of($price)), $detail['sale_unit_price']);
                self::assertSame(D::represent(D::of($price)), $detail['purchase_unit_price']);
                self::assertSame(D::represent(D::of($price)), $detail['percentage_of_profit']);
                self::assertSame(D::represent(D::of($price)), $fresh->getFormatSaleUnitPrice());
            }
            $item=Item::findOrFail($id);
            $item->setExactValues(['purchase_unit_price'=>D::of('1/3'), 'percentage_of_profit'=>D::of('1/3')])->save();
            $payload=(new \App\Http\Resources\Tenant\ItemResource($item))->resolve(Request::create('/items/record/'.$id));
            $controller=app(\App\Http\Controllers\Tenant\ItemController::class);
            $request=\App\Http\Requests\Tenant\ItemRequest::create('/items','POST',$payload);
            $result=$controller->store($request);
            self::assertTrue($result['success'], $result['message'] ?? '');
            foreach (['sale_unit_price','purchase_unit_price','percentage_of_profit'] as $field) {
                self::assertTrue(Item::findOrFail($id)->exactValue($field)->isEqualTo('1/3'));
            }
            $payload['exact_edited_fields']=['sale_unit_price','purchase_unit_price','percentage_of_profit'];
            $result=$controller->store(\App\Http\Requests\Tenant\ItemRequest::create('/items','POST',$payload));
            self::assertTrue($result['success'], $result['message'] ?? '');
            foreach (['sale_unit_price','purchase_unit_price','percentage_of_profit'] as $field) {
                self::assertTrue(Item::findOrFail($id)->exactValue($field)->isEqualTo('333333333333/1000000000000'));
            }
            $invalid=$payload; $invalid['exact_edited_fields']=['calculation_snapshot'];
            $invalidRequest=\App\Http\Requests\Tenant\ItemRequest::create('/items','POST',$invalid);
            $rules=$invalidRequest->rules();
            self::assertTrue(validator($invalid,['exact_edited_fields.*'=>$rules['exact_edited_fields.*']])->fails());

            $item=Item::findOrFail($id);
            $item->setExactValues(['sale_unit_price'=>D::of('1'),'purchase_unit_price'=>D::of('1'),'percentage_of_profit'=>D::of('1/3')])->save();
            $payload=(new \App\Http\Resources\Tenant\ItemResource($item->fresh()))->resolve(Request::create('/items/record/'.$id));
            $payload['purchase_unit_price']='1000000000000';
            $payload['sale_unit_price']='1003333333333.333333333333';
            $payload['exact_profit_sequence']=[['field'=>'purchase_unit_price','value'=>'1000000000000']];
            $result=$controller->store(\App\Http\Requests\Tenant\ItemRequest::create('/items','POST',$payload));
            self::assertTrue($result['success'], $result['message'] ?? '');
            self::assertTrue(Item::findOrFail($id)->exactValue('sale_unit_price')->isEqualTo('3010000000000/3'));

            $item=Item::findOrFail($id);
            $item->setExactValues(['sale_unit_price'=>D::of('1/3'),'purchase_unit_price'=>D::of(1),'percentage_of_profit'=>D::of(5)])->save();
            $payload=(new \App\Http\Resources\Tenant\ItemResource($item->fresh()))->resolve(Request::create('/items/record/'.$id));
            $payload['purchase_unit_price']='9007199254740993';
            $payload['exact_profit_sequence']=[['field'=>'purchase_unit_price','value'=>'9007199254740993','recalculate'=>false]];
            $result=$controller->store(\App\Http\Requests\Tenant\ItemRequest::create('/items','POST',$payload));
            self::assertTrue($result['success'], $result['message'] ?? '');
            $fresh=Item::findOrFail($id);
            self::assertTrue($fresh->exactValue('sale_unit_price')->isEqualTo('1/3'));
            self::assertTrue($fresh->exactValue('purchase_unit_price')->isEqualTo('9007199254740993'));
            self::assertTrue($fresh->exactValue('percentage_of_profit')->isEqualTo(5));
            try {
                $controller->store(\App\Http\Requests\Tenant\ItemRequest::create('/items','POST',$payload));
                self::fail('Debe rechazar la base obsoleta.');
            } catch (\Illuminate\Validation\ValidationException $error) {
                self::assertArrayHasKey('exact_profit_base',$error->errors());
            }
            self::assertTrue(Item::findOrFail($id)->exactValue('sale_unit_price')->isEqualTo('1/3'));

            $denominator='1'.str_repeat('0',78).'7'; $numerator='1'.str_repeat('0',78).'8';
            $cost=D::of(['n'=>$numerator,'d'=>$denominator]);
            $item=Item::findOrFail($id);
            $item->setExactValues(['sale_unit_price'=>D::of(1),'purchase_unit_price'=>$cost,'percentage_of_profit'=>D::of(0)])->save();
            $payload=(new \App\Http\Resources\Tenant\ItemResource($item->fresh()))->resolve(Request::create('/items/record/'.$id));
            $sale=D::of('1000000000000000');
            $margin=$sale->minus($cost)->dividedBy($cost)->multipliedBy(100);
            $payload['sale_unit_price']=D::represent($sale); $payload['percentage_of_profit']=D::represent($margin);
            $payload['exact_profit_sequence']=[['field'=>'sale_unit_price','value'=>'1000000000000000']];
            $result=$controller->store(\App\Http\Requests\Tenant\ItemRequest::create('/items','POST',$payload));
            self::assertTrue($result['success'], $result['message'] ?? '');
            $fresh=Item::findOrFail($id);
            self::assertGreaterThan(81,strlen(D::fraction($fresh->exactValue('percentage_of_profit'))['n']));
            $reopened=(new \App\Http\Resources\Tenant\ItemResource($fresh))->resolve(Request::create('/items/record/'.$id));
            $reopened['exact_profit_sequence']=[];
            $result=$controller->store(\App\Http\Requests\Tenant\ItemRequest::create('/items','POST',$reopened));
            self::assertTrue($result['success'], $result['message'] ?? '');
            self::assertTrue(Item::findOrFail($id)->exactValue('percentage_of_profit')->isEqualTo($margin));
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }

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

            $item->setExactValues(['sale_unit_price' => D::of('9007199254740993.125')])
                ->forceFill(['sale_affectation_igv_type_id' => '10', 'has_igv' => false])->save();
            $catalog = (new ItemCollection(collect([$item->fresh()])))->resolve($request)[0];
            self::assertSame('Bs. 10448351135499552.03', $catalog['sale_unit_price_with_igv']);
            $item->forceFill(['has_igv' => true])->save();
            $catalog = (new ItemCollection(collect([$item->fresh()])))->resolve($request)[0];
            self::assertSame('Bs. 9007199254740993.13', $catalog['sale_unit_price_with_igv']);
            $export=$item->fresh()->setExactValues(['sale_unit_price' => D::of(100)])
                ->forceFill(['sale_affectation_igv_type_id' => 'EXP', 'has_igv' => false]);
            $catalog=(new ItemCollection(collect([$export])))->resolve($request)[0];
            self::assertSame('Bs. 100.00', $catalog['sale_unit_price_with_igv']);
            $item->setExactValues(['sale_unit_price' => D::of('1/3')])
                ->forceFill(['sale_affectation_igv_type_id' => '20', 'has_igv' => false])->save();

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
            $itemWithSelectedStock=$item->fresh()->forceFill(['stock'=>'9007199254740993.25']);
            self::assertSame('9,007,199,254,740,993.25', $itemWithSelectedStock->getFullDescription($warehouse)['stock']);
            $fraction=D::of('2512499999999/2500000000000');
            $item->fresh()->setExactValues(['stock'=>$fraction])->save();
            self::assertSame('1.00', $item->fresh()->getFullDescription($warehouse)['stock']);
            $warehouseRow=\App\Models\Tenant\ItemWarehouse::where('item_id',$id)->where('warehouse_id',$selected)->firstOrFail();
            $warehouseRow->setExactValues(['stock'=>D::of('1/3')])->save();
            $partial=Item::select('id','description','internal_id','unit_type_id','category_id','brand_id')->findOrFail($id);
            self::assertSame('0.33', $partial->getFullDescription($warehouse)['stock']);
            $missingWarehouse=new Warehouse(['description'=>'Sin existencia']); $missingWarehouse->id=$selected+10;
            self::assertSame('0.00', $partial->getFullDescription($missingWarehouse)['stock']);
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
