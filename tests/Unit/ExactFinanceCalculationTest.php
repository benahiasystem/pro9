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

    public function test_account_balances_include_igtf_only_in_the_received_currency(): void
    {
        $report = new class { use \Modules\Finance\Traits\FinanceTrait; };
        $payment = new DocumentPayment;
        $payment->setRawAttributes(['currency_type_id'=>'USD', 'original_amount'=>'1.00', 'tax_amount'=>'0.03',
            'exchange_rate'=>'10.12340000', 'receipt_parent_id'=>null, 'reversed_at'=>null]);
        // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
        $payment->setRelation('associated_record_payment', new \App\Models\Tenant\Document);
        $global = new \Modules\Finance\Models\GlobalPayment;
        $global->forceFill(['id'=>1, 'payment_id'=>1, 'payment_type'=>DocumentPayment::class,
            'destination_id'=>1, 'destination_type'=>\App\Models\Tenant\BankAccount::class,
            'report_context'=>['version'=>2, 'method'=>['collection_kind'=>'account'], 'currency_type_id'=>'USD',
                'exchange_rate'=>'10.12340000', 'direction'=>'income', 'opening'=>false, 'date'=>'2026-10-26', 'number'=>'F001-1']]);
        $global->setRelation('payment', $payment);
        $rows = collect([$global]);
        self::assertSame('0', Decimal::represent($report->getSumPayment($rows, DocumentPayment::class, 'VES')));
        self::assertSame('1.03', Decimal::represent($report->getSumPayment($rows, DocumentPayment::class, 'USD')));
        $global->destination_type = \App\Models\Tenant\Cash::class;
        self::assertSame('0', Decimal::represent($report->getSumPayment($rows, DocumentPayment::class, 'VES')));
        // ######## FIN MONEDA DEL MEDIO DE COBRO ########
    }
}
