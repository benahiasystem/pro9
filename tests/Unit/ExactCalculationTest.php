<?php

namespace Tests\Unit;

use App\Support\Exact\{Decimal, LineCalculation};
use PHPUnit\Framework\TestCase;

class ExactCalculationTest extends TestCase
{
    public function test_shared_php_javascript_cases_preserve_normalized_fractions(): void
    {
        foreach (json_decode(file_get_contents(__DIR__.'/../Fixtures/exact-calculations.json'),true,512,JSON_THROW_ON_ERROR) as $case) {
            $line=LineCalculation::calculate($case['input'],$case['percentage']);
            foreach ($case['expected'] as $field=>$expected) {
                self::assertSame($expected,Decimal::fraction(Decimal::read($line,$field)),$case['name'].': '.$field);
            }
        }
    }

    public function test_weighted_cost_retains_periodic_components_until_the_next_receipt(): void
    {
        $helper=new \Modules\Purchase\Helpers\WeightedAverageCostHelper();
        $first=$helper->calculateWeightedCost('0','0','100','3');
        $record=['weighted_cost'=>Decimal::represent($first),'calculation_snapshot'=>Decimal::snapshot(['weighted_cost'=>$first])];
        self::assertSame('100',Decimal::represent(Decimal::read($record,'weighted_cost')->multipliedBy(3)));
        $next=$helper->calculateWeightedCost('3',Decimal::read($record,'weighted_cost'),'200','6');
        self::assertSame('50',Decimal::represent($next));
    }
    public function test_inclusive_price_and_grouping_preserve_the_full_amount(): void
    {
        $line = LineCalculation::calculate(['quantity'=>'1','unit_price'=>'500'], '16');
        self::assertSame('500', $line['total']);
        self::assertSame('431.034482758621', $line['total_value']);
        self::assertSame('68.965517241379', $line['total_igv']);
        self::assertSame('50000', Decimal::represent(Decimal::read($line, 'total')->multipliedBy(100)));
        self::assertSame('50000', LineCalculation::calculate(['quantity'=>'100','unit_price'=>'500'], '16')['total']);
    }

    public function test_database_json_round_trip_does_not_turn_a_fraction_into_a_decimal_approximation(): void
    {
        $third = Decimal::of(1)->dividedBy(3);
        $record = ['amount'=>Decimal::represent($third),'calculation_snapshot'=>Decimal::snapshot(['amount'=>$third])];
        $loaded = json_decode(json_encode($record, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('1', Decimal::represent(Decimal::read($loaded, 'amount')->multipliedBy(3)));
    }

    public function test_largest_remainders_preserve_totals_and_are_stable_even_for_small_totals(): void
    {
        self::assertSame(['33.34','33.33','33.33'], Decimal::allocate('100', [1,1,1]));
        self::assertSame(['333.34','333.33','333.33'], Decimal::allocate('1000', [1,1,1]));
        self::assertSame(['0.34','0.33','0.33'], Decimal::allocate('1', [1,1,1]));
        self::assertSame('0.06', Decimal::represent(Decimal::sum(Decimal::allocate('0.06', array_fill(0, 10, 1))), 2, true));
        self::assertSame(['-0.34','-0.33','-0.33'], Decimal::allocate('-1', [1,1,1]));
    }

    public function test_exact_discount_survives_the_storage_representation(): void
    {
        $third = Decimal::fraction(Decimal::of(1)->dividedBy(3));
        $line = LineCalculation::calculate(['quantity'=>'1','unit_price'=>'100','discounts'=>[
            ['discount_type_id'=>'00','is_amount'=>true,'amount_exact'=>$third],
        ]], '0');
        self::assertSame('299', Decimal::represent(Decimal::read($line, 'total')->multipliedBy(3)));
    }

    public function test_installments_keep_original_shares_and_discard_stale_weights_after_manual_changes(): void
    {
        $amounts=Decimal::allocate('100',[1,1,1]);
        $rows=array_map(fn($amount)=>['amount'=>$amount,'allocation_weight'=>'1'],$amounts);
        $fees=\App\Support\Exact\Installments::calculate('100',$rows);
        self::assertSame($amounts,array_column($fees,'amount'));
        foreach ($fees as $fee) self::assertSame(['n'=>'100','d'=>'3'],Decimal::fraction(Decimal::read($fee,'scheduled_exact')));
        self::assertTrue(Decimal::sum(array_map(fn($fee)=>Decimal::read($fee,'settlement_adjustment'),$fees))->isZero());
        $rows[0]['amount']='60';$rows[1]['amount']='20';$rows[2]['amount']='20';
        $manual=\App\Support\Exact\Installments::calculate('100',$rows);
        self::assertSame('60',Decimal::represent(Decimal::read($manual[0],'scheduled_exact')));
    }

    public function test_currency_round_trip_and_large_values_are_exact(): void
    {
        $usd = Decimal::convert('500', 'VES', 'USD', '873.86712345');
        self::assertSame('500', Decimal::represent(Decimal::convert($usd, 'USD', 'VES', '873.86712345')));
        self::assertSame('9007199254740993.000000000001', Decimal::represent('9007199254740993.000000000001'));
    }

    public function test_amount_based_quantity_is_not_truncated_before_multiplying_by_price(): void
    {
        $line = LineCalculation::calculate(['quantity'=>'166.666666666667', 'unit_price'=>'3',
            'quantity_input'=>['amount'=>'500','unit_price'=>'3']], '0');
        self::assertSame('500', $line['total']);
        self::assertSame(['n'=>'500','d'=>'3'], $line['calculation_snapshot']['values']['quantity']);
        $edited=LineCalculation::calculate(['quantity'=>$line['quantity'],'unit_price'=>'6'],'0', ['quantity'=>Decimal::read($line,'quantity')]);
        self::assertSame('1000',$edited['total']);
        $serialized=json_decode(json_encode($line,JSON_THROW_ON_ERROR),true,512,JSON_THROW_ON_ERROR);
        unset($serialized['quantity_input']);
        $converted=LineCalculation::fromRecord($serialized,'0');
        self::assertSame('500',$converted['total']);
        $serialized['unit_price']='6';
        self::assertSame('1000',LineCalculation::fromRecord($serialized,'0')['total']);
    }
}
