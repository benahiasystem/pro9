<?php

namespace Tests\Unit;

use App\Support\Exact\{DashboardDebtors, DashboardStock, Decimal as D, ReportDataset};
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ExactDashboardConsumersTest extends TestCase
{
    public function test_debtor_ordering_and_totals_use_fractions_before_limiting_customers(): void
    {
        Carbon::setTestNow('2026-10-19');
        try {
            $rows = [];
            foreach ([['document:1',1,'1/3','2026-10-17'],['sale_note:1',1,'2/3',null],
                ['document:2',2,'10000000000001/10000000000000','2026-10-19'],
                ['document:3',3,'1/10000000000000',null]] as [$key,$customer,$balance,$due]) {
                $value = D::of($balance);
                $rows[] = ['id'=>1,'report_key'=>$key,'number_full'=>$key,'customer_id'=>$customer,'customer_name'=>'Cliente '.$customer,
                    'due_date_iso'=>$due,'currency_type_id'=>'VES','exchange_rate_sale'=>'1.00000000']
                    + D::values(['total'=>$value,'total_ves_equivalent'=>$value,'total_to_pay'=>$value,'balance_ves_equivalent'=>$value])
                    + ['calculation_snapshot'=>D::snapshot(['total'=>$value,'total_to_pay'=>$value,'total_ves_equivalent'=>$value,'balance_ves_equivalent'=>$value])];
            }
            $result = DashboardDebtors::fromRows(new ReportDataset($rows),2);
            self::assertSame([2,1],array_column($result['items'],'customer_id'));
            self::assertSame(3,$result['count']);
            self::assertSame('10000000000001/5000000000000',(string)D::readRequired($result,'total'));
            self::assertSame('vencido',$result['items'][1]['status']);
            self::assertSame(2,$result['items'][1]['debts_count']);
            self::assertSame('1/3',(string)D::readRequired($result['items'][1]['debts'][0],'total_to_pay'));
            self::assertSame($result['summary']['consolidated_balance_ves'],$result['total']);
            self::assertCount(2,array_unique(array_column($result['items'][1]['debts'],'report_key')));
        } finally { Carbon::setTestNow(); }
    }

    public function test_stock_aggregation_threshold_and_order_do_not_use_projections(): void
    {
        $rows = [];
        foreach ([[1,'1/3'],[1,'2/3'],[2,'100000000000001/10000000000000'],[3,'9999999999999/10000000000000'],[4,'-1/3']] as [$id,$stock]) {
            $rows[] = ['item_id'=>$id,'description'=>'Producto '.$id,'stock'=>D::represent($stock),
                'calculation_snapshot'=>D::snapshot(['stock'=>$stock]),'stock_min'=>'0.333333333333',
                'item_snapshot'=>D::snapshot(['stock_min'=>'1/3'])];
        }
        $result = iterator_to_array(DashboardStock::fromRows(new ReportDataset($rows)));
        self::assertSame([4,3,1],array_column($result,'item_id'));
        self::assertSame('1',(string)D::readRequired($result[2],'stock'));
        self::assertSame('1/3',(string)D::readRequired($result[2],'stock_min'));
        self::assertSame('9999999999999/10000000000000',(string)D::readRequired($result[1],'stock'));
        self::assertSame('-1/3',(string)D::readRequired($result[0],'stock'));
    }
}
