<?php

namespace Tests\Unit;

use App\Models\Tenant\Document;
use App\Models\Tenant\DocumentPayment;
use App\Services\Payments\DocumentPaymentReport;
use Tests\TestCase;

class DocumentPaymentHistoricalReportTest extends TestCase
{
    public function test_invalid_historical_rates_remain_unavailable_for_the_dialog(): void
    {
        self::assertNull(\App\Services\ExchangeRates\ExchangeRateMath::optionalRate(null));
        self::assertNull(\App\Services\ExchangeRates\ExchangeRateMath::optionalRate('0'));
        self::assertSame('400.12500000', \App\Services\ExchangeRates\ExchangeRateMath::optionalRate('400.125'));
    }

    public function test_unavailable_original_amount_and_rate_are_not_reported_as_zero(): void
    {
        config()->set('database.connections.tenant', ['driver'=>'sqlite', 'database'=>':memory:', 'prefix'=>'']);
        $document = new Document(['state_type_id'=>'01', 'document_type_id'=>'01', 'currency_type_id'=>'VES',
            'total'=>'100', 'series'=>'FF01', 'number'=>1]);
        $payment = new DocumentPayment(['date_of_payment'=>'2026-10-29', 'currency_type_id'=>'USD',
            'payment'=>'100', 'change'=>'0', 'payment_method_snapshot'=>['description'=>'Histórico']]);
        $payment->setRelation('document', $document);
        $payment->setRelation('global_payment', null);
        $payment->setRelation('payment_method_type', null);
        $row = DocumentPaymentReport::row($payment);
        self::assertNull($row['payment']);
        self::assertNull($row['change']);
        self::assertNull($row['exchange_rate']);
        self::assertSame('100', $row['applied_payment']);
        $html = view('tenant.document_payments.report', ['records'=>collect([$row])])->render();
        self::assertGreaterThanOrEqual(2, substr_count($html, 'No disponible'));
        $payment->exchange_rate='0';
        self::assertNull(DocumentPaymentReport::row($payment)['exchange_rate']);
    }
}
