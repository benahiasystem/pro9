<?php

namespace Tests\Unit;

use App\Services\Fiscal\HkaPayloadBuilder;
use App\Support\Exact\Decimal;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Tests\Support\PostgresTestEnvironment;

/** @group postgres-integration */
final class HkaReceiptIdentityPostgresTest extends TestCase
{
    public function test_fiscal_receipt_identity_survives_postgresql_and_mixed_hka_snapshots(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);

        try {
            [$db, , , $invoice] = (new ReflectionMethod($fixture, 'noteFixture'))->invoke($fixture, true);
            PostgresTestEnvironment::assertServer($db);
            $stored = json_decode(json_encode($invoice->load(['payments', 'taxes'])->toArray(), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            $tax = collect($stored['taxes'])->sole('tax_kind', 'IGTF');
            $receipt = collect($stored['payments'])->sole(fn ($payment) => empty($payment['receipt_parent_id']));
            self::assertIsInt($receipt['id']);
            self::assertSame($receipt['id'], $tax['document_payment_id']);

            // La ND ordinaria nueva se crea sin cobros. Este snapshot sintético
            // representa una nota que ya conserva en común un cobro y su IGTF.
            $debit = json_decode(file_get_contents(__DIR__.'/../Fixtures/Hka/debit.json'), true, 512, JSON_THROW_ON_ERROR);
            $debit['fiscal_emission_mode'] = 'digital';
            $debit['fiscal_environment'] = 'demo';
            $debit['payment_condition_id'] = '02';
            $debit['currency_type_id'] = 'VES';
            $debit['exchange_rate_sale'] = '1';
            $debit['items'][0]['total_base_igv'] = '100';
            $debit['taxes'][0]['base_ves'] = '100';
            $debit['taxes'][0]['amount_ves'] = '16';
            $debit['reference']['document_type_id'] = '01';
            $debit['note'] += ['creation_key' => 'sintetica', 'note_debit_type_id' => '02'];
            $debit['fiscal_data']['transaction_type_id'] = '02';
            $debit['taxes'][] = [
                'tax_kind' => 'IGTF', 'document_payment_id' => PHP_INT_MAX,
                'percentage' => '3', 'base' => '116', 'amount' => '3.48', 'amount_ves' => '3.48',
            ];
            $debit['payments'][0]['id'] = PHP_INT_MAX;
            $debit['payments'][0]['payment'] = '116';
            $debit['payments'][0]['currency_type_id'] = 'VES';
            $debit['payments'][0]['exchange_rate'] = '1';
            $debit['payments'][0]['tax_amount'] = '3.48';
            $debit['total'] = '119.48';
            $debit['calculation_snapshot'] = Decimal::snapshot(['total' => '119.48']);

            $forms = static fn (array $snapshot) => (new HkaPayloadBuilder())->build($snapshot)
                ['documentoElectronico']['encabezado']['totales']['formasPago'];

            $native = $forms($debit);
            self::assertCount(1, $native);
            self::assertSame('119.48', $native[0]['monto']);

            $debit['taxes'][1]['document_payment_id'] = (string) PHP_INT_MAX;
            self::assertSame($native, $forms($debit));

            foreach ([(string) (PHP_INT_MAX - 1), '9223372036854775808', null] as $unrelatedId) {
                $debit['taxes'][1]['document_payment_id'] = $unrelatedId;
                $unmatched = $forms($debit);
                self::assertSame('116.00', $unmatched[0]['monto']);
                self::assertSame('99', $unmatched[1]['forma']);
                self::assertSame('3.48', $unmatched[1]['monto']);
            }
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }
}
