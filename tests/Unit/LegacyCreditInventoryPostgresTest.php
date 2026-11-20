<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\Services\Fiscal\FiscalNote;
use Modules\Inventory\Models\ItemWarehouse;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** @group postgres-integration */
final class LegacyCreditInventoryPostgresTest extends TestCase
{
    public function test_voiding_a_legacy_fractional_credit_reverses_warehouse_stock_once(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);

        try {
            [$db, , $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture, false);
            $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
            $db->table('configurations')->update(['auto_send_hka' => false]);
            $input['items'][0]['quantity'] = '0.333333333333';
            $input['items'][0]['quantity_input'] = ['amount' => '1', 'unit_price' => '3'];
            $input['items'][0]['item']['presentation'] = ['quantity_unit' => '3'];
            $input['payments'] = [];
            $input['payment_condition_id'] = '02';
            $invoice = (new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
            $warehouse = ItemWarehouse::where('item_id', $input['items'][0]['item_id'])->sole();
            self::assertTrue($warehouse->exactValue('stock')->isEqualTo('9'));

            $creditInput = (new ReflectionMethod($fixture, 'noteInput'))->invoke($fixture, $input, $invoice, '07', '07', [
                ['affected_document_item_id' => $invoice->items()->sole()->id, 'quantity' => '0.1'],
            ]);
            $credit = (new Facturalo())->forWebDocuments()->save($creditInput)->getDocument()->fresh();
            self::assertTrue($warehouse->fresh()->exactValue('stock')->isEqualTo('9.3'));

            // Historical notes have no request key and follow the legacy inventory event.
            $db->table('notes')->where('document_id', $credit->id)->update(['creation_key' => null]);
            $credit = $credit->fresh();
            self::assertFalse(FiscalNote::ordinary($credit));
            $movements = $credit->inventory_kardex()->count();
            $db->transaction(fn () => $credit->update(['state_type_id' => '11']));
            self::assertTrue($warehouse->fresh()->exactValue('stock')->isEqualTo('9'));
            self::assertSame($movements + 1, $credit->inventory_kardex()->count());
            self::assertTrue($credit->inventory_kardex()->latest('id')->firstOrFail()->exactValue('quantity')->isEqualTo('-0.3'));

            $db->transaction(function () use ($credit) {
                $credit->update(['observation' => 'Actualización de referencia']);
                $credit->update(['state_type_id' => '09']);
            });
            self::assertSame($movements + 1, $credit->inventory_kardex()->count());
            self::assertTrue($warehouse->fresh()->exactValue('stock')->isEqualTo('9'));
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }
}
