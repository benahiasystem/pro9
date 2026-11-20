<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Models\ItemWarehouse;
use Modules\Item\Models\ItemLotsGroup;
use Modules\Order\Http\Controllers\OrderNoteController;
use Modules\Order\Http\Requests\OrderNoteRequest;
use Modules\Order\Models\OrderNote;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** @group postgres-integration */
final class InvoiceOrderTrackingKeyPostgresTest extends TestCase
{
    /** @dataProvider trackingTypes */
    public function test_orders_reject_aliased_tracking_keys_before_converting_to_invoice(string $tracking): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);

        try {
            [$db, , $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture, false);
            $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
            $db->table('configurations')->update(['auto_send_hka' => false]);
            $item = $input['items'][0]['item_id'];
            $warehouse = $input['items'][0]['warehouse_id'];
            $stock = ItemWarehouse::where('item_id', $item)->where('warehouse_id', $warehouse)->sole();
            $before = $stock->exactValue('stock');
            $serials = [];
            $lot = null;
            if ($tracking === 'lots') {
                $lot = $db->table('item_lots_group')->insertGetId([
                    'item_id' => $item, 'code' => 'ORDER-KEY-LOT', 'quantity' => '10', 'date_of_due' => '2027-10-27',
                ]);
                $trackingId = $lot;
                $selection = static fn ($id) => ['IdLoteSelected' => $id];
            } else {
                foreach (['ORDER-KEY-A', 'ORDER-KEY-B'] as $series) {
                    $serials[] = $db->table('item_lots')->insertGetId([
                        'series' => $series, 'date' => '2026-10-26', 'item_id' => $item, 'warehouse_id' => $warehouse,
                        'item_loteable_type' => 'test', 'item_loteable_id' => $item, 'has_sale' => false,
                    ]);
                }
                $trackingId = $serials[0];
                $selection = static fn ($id) => ['lots' => [
                    ['id' => $id, 'has_sale' => true], ['id' => $serials[1], 'has_sale' => true],
                ]];
            }

            $input['payment_condition_id'] = '02';
            $input['payments'] = [];
            $orders = $this->getMockBuilder(OrderNoteController::class)->onlyMethods(['createPdf'])->getMock();
            $store = static function ($id) use ($orders, $input, $selection) {
                $payload = array_replace($input, ['prefix' => 'PED', 'prepayments' => []]);
                $payload['items'][0]['item'] = array_replace($payload['items'][0]['item'], $selection($id));
                $request = OrderNoteRequest::create('/order-notes', 'POST', $payload);
                validator($request->all(), $request->rules())->validate();
                return $orders->store($request);
            };

            foreach ([$trackingId.'.9', $trackingId.'xyz', '0'.$trackingId, '9223372036854775808'] as $invalid) {
                try {
                    $store($invalid);
                    self::fail("La clave de seguimiento {$invalid} debe rechazarse.");
                } catch (ValidationException $error) {
                    self::assertArrayHasKey('items', $error->errors());
                }
                self::assertSame(0, $db->table('order_notes')->count());
                self::assertTrue($stock->fresh()->exactValue('stock')->isEqualTo($before));
                if ($lot !== null) self::assertTrue(ItemLotsGroup::findOrFail($lot)->exactValue('quantity')->isEqualTo(10));
                else self::assertSame(0, $db->table('item_lots')->whereIn('id', $serials)->where('has_sale', true)->count());
            }

            $order = OrderNote::findOrFail($store((string) $trackingId)['data']['id']);
            $input['order_note_id'] = $order->id;
            $invoice = (new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
            self::assertSame($order->id, $invoice->order_note_id);
            self::assertTrue($stock->fresh()->exactValue('stock')->isEqualTo($before->minus(2)));
            if ($lot !== null) {
                self::assertSame($lot, $invoice->items()->sole()->item->IdLoteSelected[0]->id);
                self::assertTrue(ItemLotsGroup::findOrFail($lot)->exactValue('quantity')->isEqualTo(8));
            } else {
                self::assertSame($trackingId, $invoice->items()->sole()->item->lots[0]->id);
                self::assertSame(2, $db->table('item_lots')->whereIn('id', $serials)->where('has_sale', true)->count());
            }
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }

    public static function trackingTypes(): array
    {
        return ['lotes' => ['lots'], 'seriales' => ['serials']];
    }
}
