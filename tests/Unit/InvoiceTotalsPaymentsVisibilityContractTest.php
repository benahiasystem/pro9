<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class InvoiceTotalsPaymentsVisibilityContractTest extends TestCase
{
    /** @test */
    public function invoice_creation_keeps_totals_and_payment_methods_visible(): void
    {
        $root = dirname(__DIR__, 2);
        $source = file_get_contents($root.'/resources/js/views/tenant/documents/invoice_generate.vue');

        self::assertNotFalse($source);
        self::assertStringContainsString('data-testid="invoice-totals-payments"', $source);
        self::assertStringContainsString('class="row justify-content-end mt-3"', $source);
        self::assertStringContainsString('<strong>TOTAL A PAGAR</strong>', $source);
        self::assertStringContainsString('form.payment_condition_id ===', $source);
        self::assertStringContainsString('clickAddPayment', $source);
        self::assertStringContainsString('class="table-responsive payment mt-4"', $source);
        self::assertStringContainsString('class="text-start table payment-method"', $source);
        self::assertStringContainsString('style="min-width: 140px"', $source);
        self::assertStringContainsString('style="min-width: 90px"', $source);
        self::assertStringContainsString('style="min-width: 40px"', $source);

        self::assertDoesNotMatchRegularExpression(
            '/class="d-none"[^>]*>\s*<div[^>]*>.*?<strong>TOTAL A PAGAR<\/strong>/s',
            $source
        );
    }
}
