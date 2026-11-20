<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\Http\Controllers\Tenant\IvaRetentionController;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** @group postgres-integration */
final class InvoiceRetentionProposalPostgresTest extends TestCase
{
    /** @dataProvider retentionKinds */
    public function test_proposal_for_saved_invoice_uses_the_persisted_rational_amounts(string $kind): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);
        try {
            [$db, , $input] = (new ReflectionMethod($fixture, 'bootIslrFixture'))->invoke($fixture);
            \Tests\Support\PostgresTestEnvironment::assertServer($db);
            $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
            $db->table('configurations')->update(['auto_send_hka' => false]);
            $input['payment_condition_id'] = '02';
            $input['payments'] = [];
            $input['items'][0]['quantity'] = '0.333333333333';
            $input['items'][0]['quantity_input'] = ['amount' => '1', 'unit_price' => '3'];
            $input['islr_retention_prevision']['subtrahend'] = '0';
            $input['islr_retention_prevision']['currency_type_id'] = 'USD';
            $input['islr_retention_prevision']['exchange_rate'] = '3';
            $invoice = (new Facturalo())->save($input)->getDocument()->fresh();
            $quantity = $invoice->items()->firstOrFail()->exactValue('quantity');
            self::assertTrue($quantity->isEqualTo('1/3'));
            $savedField = strtolower($kind).'_retention_prevision';
            $saved = $invoice->fiscal_data->{$savedField};
            self::assertTrue($saved['eligible']);
            $request = array_replace($kind === 'ISLR' ? $input['islr_retention_prevision'] : [], [
                'tax_kind' => $kind, 'document_id' => $invoice->id, 'date' => $invoice->date_of_issue->format('Y-m-d'),
                'total_igv' => '0', 'total_taxed' => '0', 'currency_type_id' => $kind === 'ISLR' ? 'USD' : 'VES',
            ]);
            $requestType = new class extends Request {
                public function validate(array $rules): array
                {
                    return validator($this->all(), $rules)->validate();
                }
            };
            $proposal = (new IvaRetentionController())->proposal($requestType::create('/iva-retentions/proposal', 'POST', $request))['data'];
            self::assertEquals($saved['calculation_snapshot']['values'], $proposal['calculation_snapshot']['values']);
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }

    public function test_invoice_keeps_a_positive_islr_forecast_below_column_precision(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);
        try {
            [$db, , $input, $provider] = (new ReflectionMethod($fixture, 'bootIslrFixture'))->invoke($fixture);
            \Tests\Support\PostgresTestEnvironment::assertServer($db);
            $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
            $db->table('configurations')->update(['auto_send_hka' => false]);
            $input['payment_condition_id'] = '02';
            $input['payments'] = [];
            unset($input['iva_retention_prevision']);
            $input['items'][0] = array_replace($input['items'][0], [
                'quantity' => '0.000000000002', 'unit_price' => '1', 'affectation_igv_type_id' => '20',
            ]);
            $input['islr_retention_prevision']['percentage'] = '10';
            $input['islr_retention_prevision']['subtrahend'] = '0';

            $invoice = (new Facturalo())->save($input)->getDocument()->fresh();
            $forecast = $invoice->fiscal_data->islr_retention_prevision;
            self::assertTrue($forecast['eligible']);
            self::assertSame('0', $forecast['voucher_amount']);
            self::assertTrue(\App\Support\Exact\Decimal::readRequired($forecast, 'voucher_amount')->isEqualTo('1/5000000000000'));
            self::assertCount(0, $invoice->received_retentions);
            self::assertSame([], $provider->calls);

            $input['external_id'] = (string) \Illuminate\Support\Str::uuid();
            $input['islr_retention_prevision']['subtrahend'] = '0.000000000001';
            try {
                (new Facturalo())->save($input);
                self::fail('Una retención exactamente cero no admite una previsión.');
            } catch (\Illuminate\Validation\ValidationException $error) {
                self::assertArrayHasKey('customer_id', $error->errors());
            }
            self::assertSame(1, $db->table('documents')->count());
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }

    public static function retentionKinds(): array
    {
        return ['IVA' => ['IVA'], 'ISLR' => ['ISLR']];
    }
}
