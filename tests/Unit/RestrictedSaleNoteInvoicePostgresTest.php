<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\Http\Controllers\Tenant\SaleNoteController;
use App\Models\Tenant\{Company, Item, SaleNote};
use App\Support\Exact\Decimal as D;
use Modules\Inventory\Models\ItemWarehouse;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** @group postgres-integration */
final class RestrictedSaleNoteInvoicePostgresTest extends TestCase
{
    /** @dataProvider invoiceAmounts */
    public function test_conversion_keeps_exact_discount_when_a_sale_note_line_is_not_invoiced(bool $mixed): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);
        try {
            [$db, $app, $invoiceInput] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture);
            \Tests\Support\PostgresTestEnvironment::assertServer($db);
            (new ReflectionMethod($fixture, 'bootGlobalInventory'))->invoke($fixture, $app);
            Company::firstOrFail()->forceFill(['fiscal_emission_mode' => 'free_form'])->save();
            $item = Item::findOrFail($invoiceInput['items'][0]['item_id']);
            $item->forceFill(['stock' => '10'])->save();
            $warehouse = ItemWarehouse::where('item_id', $item->id)->firstOrFail();
            $warehouse->forceFill(['stock' => '10'])->save();
            $line = array_replace($invoiceInput['items'][0], ['quantity' => '1', 'unit_price' => '1',
                'affectation_igv_type_id' => '20']);
            $discount = ['discount_type_id' => '03', 'description' => 'Descuento global', 'is_amount' => true,
                'amount' => '0.335', 'amount_exact' => D::fraction('3349999999999/10000000000000')];
            $discounts = [$discount + ['base' => '1', 'factor' => '0.335']];
            if ($mixed) {
                $line['affectation_igv_type_id'] = '10';
                $line['unit_price'] = '116';
                $line['discounts'] = [
                    ['is_amount' => true, 'from_global_distribution' => true, 'discount_type_id' => '00', 'amount' => '10'],
                    ['is_amount' => true, 'from_global_distribution' => true, 'discount_type_id' => '01', 'amount' => '20'],
                ];
                $discounts = [
                    ['is_amount' => true, 'discount_type_id' => '02', 'amount' => '20'],
                    ['is_amount' => true, 'discount_type_id' => '03', 'amount' => '20'],
                ];
            }
            $noteInput = array_replace($invoiceInput, ['id' => null, 'series' => 'NV01', 'document_type_id' => '80',
                'prefix' => 'NV', 'payments' => [], 'payment_condition_id' => '02', 'discounts' => $discounts,
                'items' => [$line, array_replace($line, ['quantity' => '4'])]]);
            $controller = new SaleNoteController();
            $note = $db->transaction(function () use ($controller, $noteInput) {
                $note = SaleNote::create($controller->mergeData($noteInput));
                foreach ($noteInput['items'] as $source) {
                    $note->items()->create($source);
                }
                return $note;
            });
            self::assertSame('5', D::represent($warehouse->fresh()->exactValue('stock')));

            $invoiceInput = array_replace($invoiceInput, ['sale_note_id' => $note->id, 'payment_condition_id' => '02',
                'payments' => [], 'discounts' => $discounts, 'items' => [$line]]);
            $invoice = (new Facturalo())->forWebDocuments()->save($invoiceInput)->getDocument()->fresh();
            if ($mixed) {
                self::assertTrue($invoice->exactValue('total_taxed')->isEqualTo('80'));
                self::assertTrue($invoice->exactValue('total_igv')->isEqualTo('12.8'));
                self::assertTrue($invoice->exactValue('total_discount')->isEqualTo('40'));
                self::assertTrue($invoice->exactValue('total_payable')->isEqualTo('72.8'));
            } else {
                self::assertTrue($invoice->exactValue('total_discount')->isEqualTo('3349999999999/10000000000000'));
                self::assertTrue($invoice->exactValue('total')->isEqualTo('6650000000001/10000000000000'));
                self::assertSame('0.67', D::represent($invoice->exactValue('total_payable')));
            }
            self::assertSame(1, $invoice->items()->count());
            self::assertSame(2, $note->items()->count());
            self::assertSame('5', D::represent($warehouse->fresh()->exactValue('stock')));
            self::assertSame('5', D::represent($item->fresh()->exactValue('stock')));
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }
    public static function invoiceAmounts(): array
    {
        return ['fracción fija' => [false], 'descuentos distribuidos mixtos' => [true]];
    }
}
