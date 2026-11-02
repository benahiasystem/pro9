<?php

namespace Tests\Unit;

use App\Models\Tenant\PaymentMethodType;
use App\Services\Payments\ReceiptAmounts;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReceiptAmountsTest extends TestCase
{
    private function method(string $currency): PaymentMethodType
    {
        return (new PaymentMethodType())->forceFill([
            'id'=>'13', 'description'=>'Transferencia', 'hka_code'=>'99',
            'currency_type_id'=>$currency, 'collection_kind'=>'account', 'is_active'=>true,
        ]);
    }

    public function test_converts_received_dollars_to_document_bolivars(): void
    {
        $result = ReceiptAmounts::prepare($this->method('USD'), 'VES', '400', [
            'currency_type_id'=>'USD', 'original_amount'=>'10', 'exchange_rate'=>'400.00000000',
            'payment'=>'1', 'date_of_payment'=>'2026-10-28',
        ]);
        self::assertSame('USD', $result['currency_type_id']);
        self::assertSame('10.00', $result['original_amount']);
        self::assertEquals('4000', $result['payment']);
        self::assertSame('USD', $result['payment_method_snapshot']['currency_type_id']);
    }

    public function test_rejects_mismatched_method_currency(): void
    {
        $this->expectException(ValidationException::class);
        ReceiptAmounts::prepare($this->method('VES'), 'VES', '400', [
            'currency_type_id'=>'USD', 'original_amount'=>'10', 'exchange_rate'=>'400',
        ]);
    }

    public function test_converts_received_bolivars_to_document_dollars(): void
    {
        $result = ReceiptAmounts::prepare($this->method('VES'), 'USD', '400', [
            'currency_type_id'=>'VES', 'original_amount'=>'4000', 'exchange_rate'=>'400.00000000',
        ]);
        self::assertSame('VES', $result['currency_type_id']);
        self::assertSame('4000.00', $result['original_amount']);
        self::assertEquals('10', $result['payment']);
    }

    public function test_existing_receipt_rejects_a_change_of_received_currency(): void
    {
        $record=(new \App\Models\Tenant\SaleNotePayment())->forceFill(['currency_type_id'=>'USD','original_amount'=>'10','exchange_rate'=>'400']);
        \App\Services\Payments\ExistingReceipts::assertCurrencyFields($record,['currency_type_id'=>'USD','original_amount'=>'10.00','exchange_rate'=>'400.00000000']);
        $this->expectException(ValidationException::class);
        \App\Services\Payments\ExistingReceipts::assertCurrencyFields($record,['currency_type_id'=>'VES']);
    }
}
