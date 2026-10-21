<?php

namespace Tests\Unit;

use App\Support\Exact\{Decimal as D, OutstandingSummary};
use PHPUnit\Framework\TestCase;

class ExactOutstandingSummaryTest extends TestCase
{
    public function test_identity_equivalents_and_historical_usd_rates_conserve_periodic_sums(): void
    {
        $rows = [];
        foreach ([['VES','1.00000000'],['USD','3.00000000'],['USD','6.00000000']] as [$currency,$rate]) {
            $values = array_merge(['total'=>D::of('1/3'),'total_to_pay'=>D::of('1/3')],OutstandingSummary::equivalentValues('1/3','1/3',$currency,$rate));
            $rows[] = ['currency_type_id'=>$currency,'exchange_rate_sale'=>$rate,'calculation_snapshot'=>D::snapshot($values)] + D::values($values);
        }
        $summary = OutstandingSummary::summarize($rows);
        self::assertSame('10/3',(string)D::readRequired($summary,'consolidated_total_ves'));
        self::assertSame('10/3',(string)D::readRequired($summary,'consolidated_balance_ves'));
        self::assertSame('2/3',(string)D::readRequired($summary,'USD_balance'));
        self::assertSame('1/3',(string)D::readRequired($summary,'VES_balance'));
        self::assertSame(3,$summary['count']);
        // The compatibility path gives the same result without precomputed equivalent fields.
        foreach ($rows as &$row) {
            unset($row['total_ves_equivalent'],$row['balance_ves_equivalent']);
            unset($row['calculation_snapshot']['values']['total_ves_equivalent'],$row['calculation_snapshot']['values']['balance_ves_equivalent']);
        }
        unset($row);
        self::assertSame($summary,OutstandingSummary::summarize($rows));
    }
}
