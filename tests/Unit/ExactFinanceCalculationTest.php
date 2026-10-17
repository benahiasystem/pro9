<?php

namespace Tests\Unit;

use App\Support\Exact\Decimal;
use App\Models\Tenant\DocumentPayment;
use PHPUnit\Framework\TestCase;

class ExactFinanceCalculationTest extends TestCase
{
    public function test_cash_boundary_keeps_the_requested_value_and_exposes_the_adjustment(): void
    {
        $payment=new \App\Models\Tenant\SaleNotePayment();
        $payment->payment='1.004';
        \App\Support\Exact\Settlement::prepare($payment);
        self::assertSame('1',Decimal::represent($payment->exactValue('payment')));
        self::assertSame('1.004',Decimal::represent(Decimal::read($payment,'requested_payment')));
        self::assertSame('-0.004',Decimal::represent(Decimal::read($payment,'cash_adjustment')));
    }
    public function test_report_totals_add_original_fractions_instead_of_formatted_rows(): void
    {
        $report = new class { use \Modules\Finance\Traits\FinanceTrait; };
        $third = Decimal::of(1)->dividedBy(3);
        $rows = collect(array_fill(0, 3, ['document_payment'=>'0.333333333333',
            'calculation_snapshot'=>Decimal::snapshot(['document_payment'=>$third])]));
        $totals = $report->getTotalsPaymentMethodType($rows, collect());
        self::assertSame('1', $totals['t_documents']);
    }

    public function test_cash_conversion_includes_real_igtf_and_retains_the_full_exchange_result(): void
    {
        $report = new class { use \Modules\Finance\Traits\FinanceTrait; };
        $payment = new DocumentPayment;
        $payment->setRawAttributes(['currency_type_id'=>'USD', 'original_amount'=>'1.00', 'tax_amount'=>'0.03',
            'exchange_rate'=>'10.12340000', 'receipt_parent_id'=>null, 'reversed_at'=>null]);
        $rows = collect([(object)['payment_type'=>DocumentPayment::class, 'payment'=>$payment]]);
        self::assertSame('10.427102', Decimal::represent($report->getSumPayment($rows, DocumentPayment::class, 'VES')));
    }
}
