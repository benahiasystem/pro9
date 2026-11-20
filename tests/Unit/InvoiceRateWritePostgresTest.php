<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** @group postgres-integration */
final class InvoiceRateWritePostgresTest extends TestCase
{
    public function test_invoice_and_receipt_reject_positive_rates_that_would_underflow_to_zero(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);
        try {
            [$db, , $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture);
            $db->table('companies')->update(['fiscal_emission_mode' => 'free_form', 'igtf_enabled' => false]);
            $db->table('configurations')->update(['auto_send_hka' => false]);
            $rate = '10.12345678';
            $input['exchange_rate_sale'] = $rate;
            $receipt = ['date_of_payment' => $input['date_of_issue'], 'payment_destination_id' => 'cash',
                'financial_intermediation' => 'none', 'exchange_rate' => $rate];
            $input['payments'] = [
                $receipt + ['payment_method_type_id' => '01', 'currency_type_id' => 'VES', 'original_amount' => '130.77', 'payment' => '130.77'],
                $receipt + ['payment_method_type_id' => '10', 'currency_type_id' => 'USD', 'original_amount' => '10', 'payment' => '101.23'],
            ];
            $invoice = (new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
            $payment = $invoice->payments()->where('currency_type_id', 'USD')->sole();
            self::assertSame('VES', $invoice->currency_type_id);
            self::assertSame('101.23', $payment->payment);
            self::assertTrue($invoice->exactBalance()->isZero());

            foreach ([[$invoice, 'exchange_rate_sale'], [$payment, 'exchange_rate']] as [$record, $column]) {
                self::assertSame($rate, $record->{$column});
                foreach (['0.000000001', '1e-400', '-1e-400'] as $invalidRate) {
                    try {
                        $record->forceFill([$column => $invalidRate])->save();
                        self::fail("Se guardó la tasa inválida {$invalidRate} en {$column}.");
                    } catch (ValidationException $error) {
                        self::assertArrayHasKey($column, $error->errors());
                    }
                    self::assertSame($rate, $record->refresh()->{$column});
                    self::assertSame(0, $db->transactionLevel());
                }
            }
            self::assertTrue($invoice->fresh()->exactBalance()->isZero());
            self::assertSame(2, $invoice->payments()->count());
            $db->beginTransaction();
            try {
                $invoice->forceFill(['exchange_rate_sale' => '-0.000e400'])->save();
                self::assertSame('0.00000000', $invoice->refresh()->exchange_rate_sale);
                try {
                    $payment->forceFill(['exchange_rate' => '0'])->save();
                    self::fail('Se modificó la tasa de un cobro ya contabilizado.');
                } catch (ValidationException $error) {
                    self::assertArrayHasKey('payment', $error->errors());
                }
            } finally {
                $db->rollBack();
            }
            self::assertSame($rate, $invoice->refresh()->exchange_rate_sale);
            self::assertSame($rate, $payment->refresh()->exchange_rate);
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }
}
