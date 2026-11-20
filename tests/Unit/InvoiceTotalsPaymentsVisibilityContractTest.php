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
        self::assertStringContainsString('<template v-else><strong>Total a pagar</strong></template>', $source);
        self::assertStringContainsString('<strong v-if="fiscalPaymentsApplicable && !isUpdateDocument">Total de la venta</strong>', $source);
        self::assertStringContainsString('v-if="hasPositiveInvoiceTotal && fiscalPaymentsApplicable && !isUpdateDocument" class="invoice-total-due" data-testid="invoice-total-with-igtf"', $source);
        self::assertStringContainsString('v-if="invoiceTotalWithIgtf !== null"', $source);
        self::assertStringContainsString('igtfQuoteLoading || (!!fiscalQuoteKey && igtfQuoteKey !== fiscalQuoteKey)', $source);
        self::assertStringContainsString('<document-tax-summary', $source);
        self::assertStringContainsString(':quote="displayFiscalQuote"', $source);
        self::assertStringContainsString('form.payment_condition_id ===', $source);
        self::assertStringContainsString('clickAddPayment', $source);
        self::assertStringContainsString('class="table-responsive payment mt-4"', $source);
        self::assertStringContainsString('class="text-start table payment-method"', $source);
        self::assertStringContainsString('style="min-width: 140px"', $source);
        self::assertStringContainsString('style="min-width: 90px"', $source);
        self::assertStringContainsString('style="min-width: 40px"', $source);

        self::assertDoesNotMatchRegularExpression(
            '/class="d-none"[^>]*>\s*<div[^>]*>.*?<strong>Total a pagar:?<\/strong>/is',
            $source
        );
    }
}
