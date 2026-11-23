<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\Models\Tenant\Company;
use App\Models\Tenant\Person;
use App\Services\Fiscal\FiscalDocumentPersistence;
use App\Services\Fiscal\FiscalProfileService;
use App\Services\Fiscal\IvaRetentionService;
use App\Support\Exact\Decimal;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Tests\Support\SpeProfiles;

/** @group postgres-integration */
final class InvoiceRetentionPrecisionPostgresTest extends TestCase
{
    public function test_the_iva_proposal_amount_registers_at_cents_without_changing_the_original_formula(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);

        try {
            [, , $input] = (new ReflectionMethod($fixture, 'bootIvaFixture'))->invoke($fixture);
            $input['payments'] = [];
            $input['payment_condition_id'] = '02';
            unset($input['iva_retention_prevision']);
            $invoice = (new Facturalo())->save($input)->getDocument()->fresh();
            $makeVoucher = new ReflectionMethod($fixture, 'ivaVoucher');
            $voucher = $makeVoucher->invoke($fixture, $invoice, ['base' => '0.01', 'amount' => '0.0075']);
            $balance = Decimal::fraction($invoice->exactBalance());

            try {
                IvaRetentionService::store($invoice, $voucher);
                self::fail('El servicio debe rechazar un importe distinto al liquidado a centavos.');
            } catch (ValidationException $error) {
                self::assertArrayHasKey('amount', $error->errors());
            }
            self::assertSame(0, $invoice->received_retentions()->count());
            self::assertSame($balance, Decimal::fraction($invoice->fresh()->exactBalance()));

            $retention = IvaRetentionService::store($invoice, array_replace($voucher, ['amount' => '0.01']));
            self::assertSame('applied', $retention->application_status);
            self::assertTrue($retention->exactValue('expected_amount')->isEqualTo('0.0075'));
            self::assertTrue($retention->exactValue('applied_amount')->isEqualTo('0.01'));
            self::assertSame(1, $invoice->received_retentions()->count());
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }

    public function test_invoice_retention_validates_dates_and_rounds_the_formula_directly_to_cents(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);

        try {
            [$db, , $input] = (new ReflectionMethod($fixture, 'bootIvaFixture'))->invoke($fixture);
            FiscalProfileService::store(Company::firstOrFail(), SpeProfiles::profile(['evidence_reference' => null]));
            $input['payments'] = [];
            $input['payment_condition_id'] = '02';
            unset($input['iva_retention_prevision']);
            $invoice = (new Facturalo())->save($input)->getDocument()->fresh();
            $voucher = (new ReflectionMethod($fixture, 'ivaVoucher'))->invoke($fixture, $invoice);
            $voucher['agent_id'] = $invoice->customer_id;
            $retention = IvaRetentionService::store($invoice, $voucher);
            self::assertSame('applied', $retention->application_status);
            $balance = Decimal::fraction($invoice->fresh()->exactBalance());
            $count = $invoice->received_retentions()->count();
            $unchangedRetention = $retention->fresh()->getAttributes();
            $otherAgent = Person::create(['type'=>'customers', 'identity_document_type_id'=>'1',
                'number'=>'24000099', 'name'=>'DEMO agente distinto', 'country_id'=>'VE',
                'address'=>'DEMO Caracas', 'email'=>'qa@example.test']);

            $invalid = [
                'different agent' => [['agent_id' => $otherAgent->id], 'agent_id'],
                'different agent rif' => [['agent_rif' => 'J-00000000-0'], 'agent_rif'],
                'different invoice control' => [['invoice_control' => 'CONTROL-AJENO'], 'invoice_control'],
                'different invoice number' => [['invoice_number' => 'FACTURA-AJENA'], 'invoice_control'],
                'voucher invalid format' => [['voucher_number' => '2026090000000X'], 'voucher_number'],
                'voucher different period' => [['voucher_number' => '20260800000001'], 'voucher_number'],
                'before issue' => [['voucher_date' => $invoice->date_of_issue->copy()->subDay()->toDateString()], 'voucher_date'],
                'after today' => [['voucher_date' => now()->addDay()->toDateString()], 'voucher_date'],
                'amount zero' => [['amount' => '0'], 'amount'],
                'base zero' => [['base' => '0'], 'base'],
                'rate below precision' => [['exchange_rate' => '0.000000001'], 'exchange_rate'],
                'positive IVA subtrahend' => [['subtrahend' => '0.000000000001'], 'percentage'],
                'missing 100 percent evidence' => [[
                    'percentage' => '100', 'amount' => '32', 'fiscal_context' => ['iva_100_basis' => 'portal_100'],
                ], 'fiscal_context'],
                'expected rounds down' => [['base' => '0.006666666666', 'amount' => '0.005'], 'amount'],
                'expected rounds up' => [['base' => '0.006666666667', 'amount' => '0.004999999999'], 'amount'],
            ];
            foreach ($invalid as $name => [$changes, $field]) {
                try {
                    FiscalDocumentPersistence::retentionValidated($invoice, array_replace($voucher, $changes), $retention);
                    self::fail($name);
                } catch (ValidationException $error) {
                    self::assertArrayHasKey($field, $error->errors(), $name);
                }
                self::assertSame($balance, Decimal::fraction($invoice->fresh()->exactBalance()), $name);
                self::assertSame($count, $invoice->received_retentions()->count(), $name);
                self::assertSame($unchangedRetention, $retention->fresh()->getAttributes(), $name);
                self::assertSame(0, $db->transactionLevel(), $name);
            }

            $db->table('documents')->where('id', $invoice->id)->update(['document_type_id' => '07']);
            try {
                FiscalDocumentPersistence::retentionValidated($invoice, $voucher, $retention);
                self::fail('Una nota no debe aceptar retención de factura.');
            } catch (ValidationException $error) {
                self::assertArrayHasKey('document', $error->errors());
            } finally {
                $db->table('documents')->where('id', $invoice->id)->update(['document_type_id' => '01']);
            }
            self::assertSame($balance, Decimal::fraction($invoice->fresh()->exactBalance()));
            self::assertSame($count, $invoice->received_retentions()->count());
            self::assertSame($unchangedRetention, $retention->fresh()->getAttributes());
            self::assertSame(0, $db->transactionLevel());

            $updated = FiscalDocumentPersistence::retentionValidated($invoice, array_replace($voucher, [
                'base' => '0.006666666667', 'amount' => '0.005', 'exchange_rate' => '1.00000001',
            ]), $retention)->fresh();
            self::assertSame($retention->id, $updated->id);
            self::assertSame('1.00000001', $updated->exchange_rate);
            self::assertTrue($updated->exactValue('amount')->isEqualTo('0.005'));
            self::assertTrue($updated->exactValue('expected_amount')->isEqualTo('0.00500000000025'));
            self::assertSame($count, $invoice->received_retentions()->count());
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }
}
