<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\Services\Fiscal\DocumentEditSettlements;
use App\Services\Fiscal\FiscalDocumentPersistence;
use App\Services\Fiscal\FreeFormControl;
use App\Services\Fiscal\IvaRetentionService;
use App\Support\Exact\Decimal as D;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Tests\Support\PostgresTestEnvironment;

/** @group postgres-integration */
final class InvoiceEditPaymentIntegrityPostgresTest extends TestCase
{
    public function test_invoice_edit_validates_receipt_keys_without_rebuilding_payments(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);
        try {
            [$db, , $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture);
            PostgresTestEnvironment::assertServer($db);
            $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
            $db->table('configurations')->update(['auto_send_hka' => false]);
            $input['payment_condition_id'] = '02';
            $input['payments'] = [];
            $invoice = (new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
            $receipt = [
                'date_of_payment' => $invoice->date_of_issue,
                'payment_method_type_id' => '01',
                'payment_method_snapshot' => ['collection_kind' => 'cash', 'currency_type_id' => 'VES'],
                'currency_type_id' => 'VES',
                'exchange_rate' => '1',
                'operation_key' => Str::uuid()->toString(),
            ];
            $payment = $invoice->payments()->make($receipt);
            $payment->id = 9007199254740993;
            $payment->setExactValues(['payment' => D::of('1/3'), 'original_amount' => D::of('1/3')])->save();
            $original = $payment->fresh()->getAttributes();
            $otherInvoice = (new Facturalo())->forWebDocuments()->save(array_replace($input, [
                'external_id' => Str::uuid()->toString(),
            ]))->getDocument();
            $foreignPayment = $otherInvoice->payments()->make(array_replace($receipt, ['operation_key' => Str::uuid()->toString()]));
            $foreignPayment->id = 9007199254740992;
            $foreignPayment->setExactValues(['payment' => D::of('2/3'), 'original_amount' => D::of('2/3')])->save();
            $edit = array_replace($input, ['id' => $invoice->id, 'series' => $invoice->series, 'number' => $invoice->number]);
            $edit['items'][0]['record_id'] = $invoice->items()->sole()->id;
            $payload = static fn ($rows) => array_replace($edit, ['payments' => $rows]);

            foreach ([
                [['id' => '9223372036854775808']],
                [['id' => '09007199254740993']],
                [['id' => '9007199254740993.0']],
                [['id' => true]],
                [['id' => ['9007199254740993']]],
                [[]],
                ['invalid-row'],
                'invalid-list',
            ] as $rows) {
                try {
                    (new Facturalo())->forWebDocuments()->update($payload($rows), $invoice->id);
                    self::fail('La edición debe rechazar referencias de cobro inválidas.');
                } catch (ValidationException $error) {
                    self::assertArrayHasKey('payments', $error->errors());
                }
                $db->enableQueryLog();
                $db->flushQueryLog();
                try {
                    DocumentEditSettlements::validateInput($invoice, $payload($rows));
                    self::fail('La validación debe rechazar la entrada antes de consultar el cobro.');
                } catch (ValidationException $error) {
                    self::assertArrayHasKey('payments', $error->errors());
                    self::assertSame([], $db->getQueryLog());
                } finally {
                    $db->disableQueryLog();
                }
                self::assertSame($original, $payment->fresh()->getAttributes());
                self::assertSame(0, $db->transactionLevel());
            }

            foreach ([[['id' => '9007199254740992']], [['id' => (string) $payment->id, 'payment' => '0.34']]] as $rows) {
                try {
                    (new Facturalo())->forWebDocuments()->update($payload($rows), $invoice->id);
                    self::fail('La edición debe rechazar un cobro ajeno o modificado.');
                } catch (ValidationException $error) {
                    self::assertArrayHasKey('payments', $error->errors());
                }
                self::assertSame($original, $payment->fresh()->getAttributes());
            }

            (new Facturalo())->forWebDocuments()->update($payload([[
                'id' => (string) $payment->id,
                'payment' => '0.333333333333',
            ]]), $invoice->id);
            self::assertSame($original, $payment->fresh()->getAttributes());
            self::assertTrue($payment->fresh()->exactValue('payment')->isEqualTo('1/3'));
            self::assertSame(1, $invoice->payments()->count());
            self::assertTrue($foreignPayment->fresh()->exactValue('payment')->isEqualTo('2/3'));
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }

    public function test_edit_prevalidation_preserves_the_retention_customer_and_the_cash_cent_boundary(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);
        try {
            [$db, , $input] = (new ReflectionMethod($fixture, 'bootIvaFixture'))->invoke($fixture);
            PostgresTestEnvironment::assertServer($db);
            $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
            $db->table('configurations')->update(['auto_send_hka' => false]);
            $input = array_replace($input, [
                'payment_condition_id' => '02',
                'payments' => [],
                'iva_retention_prevision' => null,
            ]);
            $invoice = (new Facturalo())->save($input)->getDocument();
            $cash = clone $invoice;
            $cash->payment_condition_id = '01';
            foreach ([
                ['0.0049999999999', '0'],
                ['0.005', '0.01'],
                ['0.0050000000001', '0.01'],
                ['-0.01', '0'],
            ] as [$outstanding, $expected]) {
                $actual = FiscalDocumentPersistence::freeFormCashShortfall($cash, $outstanding);
                self::assertTrue($actual->isEqualTo($expected), "El faltante {$outstanding} produjo {$actual}.");
            }

            FreeFormControl::register($invoice, ['control_number' => '00-1', 'evidence_reference' => 'Factura física temporal']);
            $invoice->refresh();
            $voucher = (new ReflectionMethod($fixture, 'ivaVoucher'))->invoke($fixture, $invoice);
            $retention = IvaRetentionService::store($invoice, $voucher);
            self::assertTrue($invoice->received_retentions()->applied()->whereKey($retention->id)->exists());
            $id = (string) $invoice->customer_id;
            foreach (['0'.$id, $id.'.0', '9223372036854775808', (string) ($invoice->customer_id + 1)] as $malformed) {
                try {
                    DocumentEditSettlements::validateInput($invoice, ['customer_id' => $malformed]);
                    self::fail('Una factura con retención debe conservar su cliente con clave canónica.');
                } catch (ValidationException $error) {
                    self::assertArrayHasKey('customer_id', $error->errors());
                }
            }
            DocumentEditSettlements::validateInput($invoice, ['customer_id' => $id]);
            DocumentEditSettlements::validateInput($invoice, []);
            self::assertSame($id, (string) $invoice->fresh()->customer_id);
            self::assertSame(1, $invoice->received_retentions()->count());
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }
}
