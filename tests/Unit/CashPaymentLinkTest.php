<?php

namespace Tests\Unit;

use App\Models\Tenant\DocumentPayment;
use App\Services\Payments\CashPaymentLink;
use Modules\Finance\Models\GlobalPayment;
use PHPUnit\Framework\TestCase;

class CashPaymentLinkTest extends TestCase
{
    private function payment(?int $session, array $snapshot = [], ?int $parent = null): DocumentPayment
    {
        $payment = new DocumentPayment(['payment_method_snapshot'=>$snapshot, 'receipt_parent_id'=>$parent]);
        $payment->setRelation('global_payment', new GlobalPayment(['report_context'=>['version'=>2], 'cash_session_id'=>$session]));
        return $payment;
    }

    public function test_only_original_movements_from_the_current_session_are_linked(): void
    {
        self::assertTrue(CashPaymentLink::belongsToSession($this->payment(7), 7));
        self::assertFalse(CashPaymentLink::belongsToSession($this->payment(8), 7));
        self::assertFalse(CashPaymentLink::belongsToSession($this->payment(null), 7));
        self::assertFalse(CashPaymentLink::belongsToSession($this->payment(7, ['receipt_origin'=>['id'=>'4']]), 7));
        self::assertFalse(CashPaymentLink::belongsToSession($this->payment(7, [], 4), 7));
    }

    public function test_legacy_movements_keep_the_existing_association_behavior(): void
    {
        $payment = new DocumentPayment();
        $payment->setRelation('global_payment', null);
        self::assertTrue(CashPaymentLink::belongsToSession($payment, 7));
    }
}
