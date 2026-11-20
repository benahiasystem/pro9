<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\Http\Controllers\Tenant\SaleNoteController;
use App\Models\Tenant\SaleNote;
use Illuminate\Routing\UrlGenerator;
use Modules\Inventory\Models\ItemWarehouse;
use Modules\Item\Models\ItemLot;
use Modules\Order\Http\Controllers\OrderNoteController;
use Modules\Order\Http\Requests\OrderNoteRequest;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** @group postgres-integration */
final class InvoiceSerialSelectionPostgresTest extends TestCase
{
    /** @dataProvider sources */
    public function test_invoice_and_origin_leave_unselected_serials_available(string $source): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);
        try {
            [$db, $app, $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture);
            (new ReflectionMethod($fixture, 'bootGlobalInventory'))->invoke($fixture, $app);
            $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
            $db->table('configurations')->update(['auto_send_hka' => false]);
            $url = $this->createMock(UrlGenerator::class);
            $url->method('to')->willReturn('http://fiscal-schema.example.test');
            $app->instance(UrlGenerator::class, $url);

            $line = $input['items'][0];
            $stock = ItemWarehouse::where('item_id', $line['item_id'])->where('warehouse_id', $line['warehouse_id'])->sole();
            $initial = $stock->exactValue('stock');
            $serial = ItemLot::create([
                'series' => 'SERIAL-NO-SELECCIONADO-FACTURA',
                'item_id' => $line['item_id'],
                'warehouse_id' => $line['warehouse_id'],
                'item_loteable_type' => 'test',
                'item_loteable_id' => $line['item_id'],
                'date' => '2026-11-12',
                'has_sale' => false,
            ]);
            $line['item']['lots'] = [['id' => $serial->id, 'has_sale' => 'false']];
            $input = array_replace($input, ['payments' => [], 'payment_condition_id' => '02', 'items' => [$line]]);

            if ($source === 'order') {
                $orders = $this->getMockBuilder(OrderNoteController::class)->onlyMethods(['createPdf'])->getMock();
                $payload = array_replace($input, ['prefix' => 'PED', 'prepayments' => []]);
                $request = OrderNoteRequest::create('/order-notes', 'POST', $payload);
                validator($request->all(), $request->rules())->validate();
                $input['order_note_id'] = $orders->store($request)['data']['id'];
            } elseif (in_array($source, ['sale-note', 'sale-note-removed'], true)) {
                $notes = $this->getMockBuilder(SaleNoteController::class)->onlyMethods(['createPdf'])->getMock();
                $saved = $notes->storeWithData(array_replace($input, [
                    'id' => null, 'series' => 'NV01', 'document_type_id' => '80', 'prefix' => 'NV',
                ]));
                self::assertTrue($saved['success'], $saved['message'] ?? '');
                $input['sale_note_id'] = $saved['data']['id'];
            }
            self::assertFalse($serial->fresh()->has_sale, 'El origen no seleccionó el serial.');

            if ($source === 'sale-note-removed') {
                $otherInvoice = $input;
                unset($otherInvoice['sale_note_id']);
                $otherInvoice['items'][0]['item']['lots'][0]['has_sale'] = true;
                (new Facturalo())->forWebDocuments()->save($otherInvoice);
                self::assertTrue($serial->fresh()->has_sale, 'Otra factura vendió el serial.');
                $note = SaleNote::findOrFail($input['sale_note_id']);
                $db->transaction(fn () => $note->items()->sole()->delete());
                self::assertTrue($serial->fresh()->has_sale, 'La nota no puede liberar un serial que no reservó.');
                self::assertTrue($stock->fresh()->exactValue('stock')->isEqualTo($initial->minus($line['quantity'])));
                return;
            }

            $invoice = (new Facturalo())->forWebDocuments()->save($input)->getDocument();
            self::assertFalse($serial->fresh()->has_sale, 'La factura no seleccionó el serial.');
            self::assertTrue($stock->fresh()->exactValue('stock')->isEqualTo($initial->minus($line['quantity'])));
            $credit = (new ReflectionMethod($fixture, 'noteInput'))->invoke($fixture, $input, $invoice, '07', '07', [
                ['affected_document_item_id' => $invoice->items()->sole()->id, 'quantity' => '1'],
            ]);
            (new Facturalo())->forWebDocuments()->save($credit);
            self::assertFalse($serial->fresh()->has_sale, 'La devolución conserva la disponibilidad del serial ajeno a la partida.');
            self::assertTrue($stock->fresh()->exactValue('stock')->isEqualTo($initial->minus($line['quantity'])->plus(1)));
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }

    public static function sources(): array
    {
        return [
            'pedido' => ['order'],
            'nota de venta' => ['sale-note'],
            'factura directa' => ['invoice'],
            'eliminar partida de nota' => ['sale-note-removed'],
        ];
    }
}
