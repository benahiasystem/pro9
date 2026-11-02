<?php

namespace Tests\Unit;

use Modules\Sale\Models\QuotationPayment;
use Tests\TestCase;

class QuotationReceiptPdfTest extends TestCase
{
    public function test_cross_currency_receipt_displays_saved_amount_rate_and_method(): void
    {
        $row = new QuotationPayment([
            'payment' => '100', 'currency_type_id' => 'USD', 'original_amount' => '1',
            'exchange_rate' => '100.00000000', 'payment_method_snapshot' => ['description' => 'Método original'],
        ]);
        $document = (object) ['currency_type_id' => 'VES'];
        $rendered = view('pdf.partials.quotation_receipt', compact('row', 'document'))->render();

        $this->assertStringContainsString('Método original', $rendered);
        $this->assertStringContainsString('Aplicado VES 100.00', $rendered);
        $this->assertStringContainsString('Recibido USD 1.00', $rendered);
        $this->assertStringContainsString('100.00000000', $rendered);
    }
}
