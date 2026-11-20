<?php

namespace Tests\Unit;

use App\Support\Exact\Decimal as D;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Http\Controllers\ItemController;
use Modules\Inventory\Models\ItemWarehouse;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

/** @group postgres-integration */
final class InvoiceStockQuantityPostgresTest extends TestCase
{
    public function test_invoice_stock_matches_the_exact_requested_quantity_and_presentation(): void
    {
        $fixture = new PrepaymentConsumersPostgresTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);
        try {
            $db = (new ReflectionProperty($fixture, 'db'))->getValue($fixture);
            $invoice = (new ReflectionMethod($fixture, 'input'))->invoke($fixture, '1', false);
            $db->table('inventory_configurations')->update(['stock_control' => true]);
            $row = $invoice['items'][0];
            $itemId = $row['item_id'];
            $warehouse = ItemWarehouse::where('item_id', $itemId)->firstOrFail();
            $payload = ['item_id' => $itemId, 'warehouse_id' => $warehouse->warehouse_id,
                'quantity' => '0.333333333333', 'quantity_exact' => ['n' => '1', 'd' => '3']];
            $check = static fn (array $input): array => (new ItemController())
                ->validateCurrentItemStock(Request::create('/validate-current-item-stock', 'POST', $input));

            $warehouse->setExactValues(['stock' => D::of('0.333333333333')])->save();
            self::assertFalse($check($payload)['success']);
            self::assertTrue($check(array_diff_key($payload, ['quantity_exact' => true]))['success']);
            $warehouse->setExactValues(['stock' => D::of('1/3')])->save();
            self::assertTrue($check($payload)['success']);
            $warehouse->refresh();
            $warehouse->stock = '0.333333333333';
            $warehouse->save();
            self::assertTrue($warehouse->fresh()->exactValue('stock')->isEqualTo('1/3'));
            $warehouse->stock = '0.333333333334';
            $warehouse->save();
            self::assertTrue($warehouse->fresh()->exactValue('stock')->isEqualTo('0.333333333334'));
            $warehouse->setExactValues(['stock' => D::of('0.333333333333')])->save();
            self::assertTrue($warehouse->fresh()->exactValue('stock')->isEqualTo('0.333333333333'));

            $payload['presentation'] = ['quantity_unit' => '3'];
            $warehouse->setExactValues(['stock' => D::of('0.999999999999')])->save();
            self::assertFalse($check($payload)['success']);
            $warehouse->setExactValues(['stock' => D::of('1')])->save();
            self::assertTrue($check($payload)['success']);

            $pack = (array) $db->table('items')->where('id', $itemId)->first();
            unset($pack['id']);
            $pack['internal_id'] = 'PACK-STOCK-EXACTO';
            $pack['is_set'] = true;
            $packId = $db->table('items')->insertGetId($pack);
            $db->table('item_sets')->insert(['item_id' => $packId, 'individual_item_id' => $itemId,
                'quantity' => '0.333333333333',
                'calculation_snapshot' => json_encode(D::snapshot(['quantity' => D::of('1/3')]), JSON_THROW_ON_ERROR)]);
            $packPayload = ['item_id' => $packId, 'warehouse_id' => $warehouse->warehouse_id, 'quantity' => '1'];
            $warehouse->setExactValues(['stock' => D::of('0.333333333333')])->save();
            self::assertFalse($check($packPayload)['success']);
            $warehouse->setExactValues(['stock' => D::of('1/3')])->save();
            self::assertTrue($check($packPayload)['success']);

            $payload['quantity'] = '0.33';
            $this->expectException(ValidationException::class);
            $check($payload);
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }
}
