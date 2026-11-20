<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\Models\Tenant\Item;
use App\Support\Exact\Decimal;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Models\ItemWarehouse;
use Modules\Order\Http\Controllers\OrderNoteController;
use Modules\Order\Http\Requests\OrderNoteRequest;
use Modules\Order\Models\OrderNote;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** @group postgres-integration */
final class InvoiceOrderPackTrackingPostgresTest extends TestCase
{
    public function test_orders_reject_pack_level_tracking_and_keep_unassigned_catalog_lots_billable(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);

        try {
            [$db, , $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture, false);
            $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
            $db->table('configurations')->update(['auto_send_hka' => false]);
            $component = Item::findOrFail($input['items'][0]['item_id']);
            $stock = ItemWarehouse::where('item_id', $component->id)->sole();
            $pack = $component->replicate()->forceFill(['internal_id' => 'QA-PACK-LOTE', 'is_set' => true, 'stock' => '5']);
            $pack->save();
            $db->table('item_sets')->insert([
                'item_id' => $pack->id, 'individual_item_id' => $component->id, 'quantity' => '0.333333333333',
                'calculation_snapshot' => json_encode(Decimal::snapshot(['quantity' => '1/3']), JSON_THROW_ON_ERROR),
            ]);
            $lot = $db->table('item_lots_group')->insertGetId(['item_id' => $pack->id, 'code' => 'PACK-LOTE',
                'quantity' => '10', 'date_of_due' => '2027-10-27']);

            $input['items'][0]['item_id'] = $pack->id;
            $input['items'][0]['item'] = $pack->fresh()->toArray();
            $input['payment_condition_id'] = '02';
            $input['payments'] = [];
            $orderInput = array_replace($input, ['prefix' => 'PED', 'prepayments' => []]);
            $orders = $this->getMockBuilder(OrderNoteController::class)->onlyMethods(['createPdf'])->getMock();
            $store = static function (array $payload) use ($orders) {
                $request = OrderNoteRequest::create('/order-notes', 'POST', $payload);
                validator($request->all(), $request->rules())->validate();
                return $orders->store($request);
            };

            foreach ([
                ['lots_group' => [['id' => $lot, 'compromise_quantity' => '1']]],
                ['lots_group' => [['id' => $lot, 'compromise_quantity' => '1',
                    'calculation_snapshot' => Decimal::snapshot(['compromise_quantity' => '0'])]]],
                ['lots_group' => [['id' => $lot, 'compromise_quantity' => 'inválida']]],
                ['IdLoteSelected' => [['id' => $lot, 'compromise_quantity' => '1']]],
                ['lots' => [['id' => '1', 'has_sale' => true]]],
                ['lots' => [['id' => '1', 'has_sale' => 'true']]],
            ] as $assignment) {
                $tracked = $orderInput;
                $tracked['items'][0]['item'] = array_replace($tracked['items'][0]['item'], $assignment);
                try {
                    $store($tracked);
                    self::fail('El pedido no debe aceptar seguimiento a nivel del paquete.');
                } catch (ValidationException $error) {
                    self::assertArrayHasKey('items', $error->errors());
                }
                self::assertSame(0, $db->table('order_notes')->count());
                self::assertTrue($stock->fresh()->exactValue('stock')->isEqualTo('10'));
                self::assertTrue(Decimal::of($db->table('item_lots_group')->where('id', $lot)->value('quantity'))->isEqualTo('10'));
            }

            $orderInput['items'][0]['item']['lots_group'] = [['id' => $lot]];
            $orderInput['items'][0]['item']['lots'] = [['id' => '1', 'has_sale' => false], ['id' => '2', 'has_sale' => 'false']];
            $created = $store($orderInput);
            $order = OrderNote::findOrFail($created['data']['id']);
            $original = $order->items()->sole()->calculation_snapshot['inputs']['order_pack_inventory'];
            self::assertSame(['n' => '1', 'd' => '3'], $original['components'][0]['quantity']);
            self::assertTrue($stock->fresh()->exactValue('stock')->isEqualTo('28/3'));
            $input['order_note_id'] = $order->id;
            $invoice = (new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
            self::assertSame($order->id, $invoice->order_note_id);
            self::assertSame(0, $invoice->inventory_kardex()->count());
            self::assertTrue($stock->fresh()->exactValue('stock')->isEqualTo('28/3'));
            self::assertTrue(Decimal::of($db->table('item_lots_group')->where('id', $lot)->value('quantity'))->isEqualTo('10'));
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }
}
