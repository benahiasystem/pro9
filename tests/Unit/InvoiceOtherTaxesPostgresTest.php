<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\CoreFacturalo\InputRequest;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** @group postgres-integration */
final class InvoiceOtherTaxesPostgresTest extends TestCase
{
    public function test_ves_invoice_rejects_other_tax_without_details_or_beyond_input_precision(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);

        try {
            [$db, , $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture, false);
            $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
            $db->table('configurations')->update(['auto_send_hka' => false]);
            $input = array_replace($input, ['currency_type_id' => 'VES', 'payment_condition_id' => '02', 'payments' => []]);

            foreach (['0.000000000001' => 'taxes', '1e-400' => 'total_other_taxes'] as $positive => $field) {
                try {
                    (new Facturalo())->forWebDocuments()->save($input + ['total_other_taxes' => $positive]);
                    self::fail('Se aceptó un impuesto positivo sin detalle: '.$positive);
                } catch (\Illuminate\Validation\ValidationException $error) {
                    self::assertArrayHasKey($field, $error->errors(), json_encode($error->errors()));
                }
                self::assertSame(0, $db->table('documents')->count());
                self::assertSame(0, $db->table('document_taxes')->count());
                self::assertSame(0, $db->transactionLevel());
            }

            $invoice = (new Facturalo())->forWebDocuments()->save($input + ['total_other_taxes' => '0'])->getDocument();
            self::assertSame('VES', $invoice->currency_type_id);
            self::assertTrue($invoice->exactValue('total_other_taxes')->isZero());
            self::assertTrue($invoice->exactValue('total')->isEqualTo('232'));
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }

    public function test_ves_invoice_rejects_nonzero_line_other_taxes_and_preserves_zero(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);

        try {
            [$db, , $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture, false);
            $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
            $db->table('configurations')->update(['auto_send_hka' => false]);
            $input = array_replace($input, ['currency_type_id' => 'VES', 'payment_condition_id' => '02', 'payments' => []]);

            foreach (['0.000000000001', '1e-400', '-1e-400', '1e10000000', 'incorrecto', ['n' => '0', 'd' => '1']] as $declared) {
                $test = $input;
                $test['items'][0]['total_other_taxes'] = $declared;
                try {
                    (new Facturalo())->forWebDocuments()->save($test);
                    self::fail('Se aceptó un tributo inválido de partida: '.json_encode($declared));
                } catch (\Illuminate\Validation\ValidationException $error) {
                    self::assertArrayHasKey('items.total_other_taxes', $error->errors());
                }
                self::assertSame(0, $db->table('documents')->count());
                self::assertSame(0, $db->table('document_taxes')->count());
                self::assertSame(0, $db->transactionLevel());
            }

            $input['items'][0]['total_other_taxes'] = '-0.000e400';
            $invoice = (new Facturalo())->forWebDocuments()->save($input)->getDocument();
            self::assertSame('VES', $invoice->currency_type_id);
            self::assertTrue($invoice->items()->sole()->exactValue('total_other_taxes')->isZero());
            self::assertTrue($invoice->exactValue('total')->isEqualTo('232'));
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }

    public function test_invoice_edit_preserves_omitted_other_tax_details_and_revalues_them_at_the_new_rate(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);

        try {
            [$db, , $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture, false);
            $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
            $db->table('configurations')->update(['auto_send_hka' => false]);
            $input = array_replace($input, ['currency_type_id' => 'USD', 'exchange_rate_sale' => '10',
                'payment_condition_id' => '02', 'payments' => [], 'purchase_order' => null, 'total_exonerated' => '0',
                'date_of_due' => '2026-10-10', 'operation_type_id' => '0101']);
            $taxes = [
                ['code' => 'ADICIONAL', 'base' => '0.3', 'percentage' => '11'],
                ['code' => 'FRACCION', 'base' => '0.000000000001', 'percentage' => '0.00000001'],
            ];
            $invoice = (new Facturalo())->forWebDocuments()->save($input + ['taxes' => $taxes])->getDocument()->fresh();
            $original = $invoice->taxes()->where('tax_kind', 'OTI')->get()->keyBy('code');
            $this->assertCount(2, $original);
            self::assertTrue($invoice->exactValue('total')->isEqualTo('232.0330000000000000000001'));

            $edit = array_replace($input, ['id' => $invoice->id, 'series' => $invoice->series, 'number' => $invoice->number]);
            $edit['items'][0]['record_id'] = $invoice->items()->sole()->id;
            $editInvoice = static function (array $data) use ($invoice) {
                $request = Request::create('/documents/'.$invoice->id, 'PUT', $data);
                $request->attributes->set('sab.strict_input_exceptions', true);
                (new InputRequest())->handle($request, static function (Request $normalized) use ($invoice) {
                    return (new Facturalo())->forWebDocuments()->update($normalized->all(), $invoice->id);
                }, 'documentUpdate', 'web');
                return $invoice->fresh();
            };
            $saved = $editInvoice($edit);
            $current = $saved->taxes()->where('tax_kind', 'OTI')->get()->keyBy('code');
            self::assertCount(2, $current);
            foreach ($original as $code => $tax) {
                self::assertSame($tax->id, $current[$code]->id);
                self::assertSame($tax->calculation_snapshot, $current[$code]->calculation_snapshot);
            }
            self::assertTrue($saved->exactValue('total')->isEqualTo($invoice->exactValue('total')));

            $edit['items'][0]['record_id'] = $saved->items()->sole()->id;
            $edit['exchange_rate_sale'] = '12';
            $saved = $editInvoice($edit);
            $current = $saved->taxes()->where('tax_kind', 'OTI')->get()->keyBy('code');
            self::assertSame($original['ADICIONAL']->id, $current['ADICIONAL']->id);
            self::assertTrue($current['ADICIONAL']->exactValue('amount_ves')->isEqualTo('0.396'));
            self::assertTrue($current['FRACCION']->exactValue('amount')->isEqualTo('0.0000000000000000000001'));
            self::assertTrue($current['FRACCION']->exactValue('amount_ves')->isEqualTo('0.0000000000000000000012'));
            self::assertTrue($saved->currency_totals->exactValue('other_taxes')->isEqualTo('0.3960000000000000000012'));
            self::assertTrue($saved->exactValue('total')->isEqualTo($invoice->exactValue('total')));

            $edit['items'][0]['record_id'] = $saved->items()->sole()->id;
            $saved = $editInvoice($edit + ['taxes' => [['code' => 'REEMPLAZO', 'base' => '10', 'percentage' => '2']]]);
            $replacement = $saved->taxes()->where('tax_kind', 'OTI')->sole();
            self::assertSame('REEMPLAZO', $replacement->code);
            self::assertTrue($replacement->exactValue('amount_ves')->isEqualTo('2.4'));
            self::assertTrue($saved->exactValue('total')->isEqualTo('232.2'));

            $edit['items'][0]['record_id'] = $saved->items()->sole()->id;
            $saved = $editInvoice($edit + ['taxes' => []]);
            self::assertSame(0, $saved->taxes()->where('tax_kind', 'OTI')->count());
            self::assertTrue($saved->exactValue('total')->isEqualTo('232'));
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }
}
