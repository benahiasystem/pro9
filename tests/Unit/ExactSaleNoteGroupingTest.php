<?php

namespace Tests\Unit;

use App\Support\Exact\{Decimal as D,LineCalculation,SaleNoteConversionLines as Lines};
use Tests\TestCase;

class ExactSaleNoteGroupingTest extends TestCase
{
    private function line(int $id,string $price='500',string $quantity='1',array $changes=[]): array
    {
        return array_replace(LineCalculation::calculate(array_replace([
            'item_id'=>1,'item'=>['unit_type_id'=>'UND','presentation'=>[]],'quantity'=>$quantity,'unit_price'=>$price,
            'affectation_igv_type_id'=>'10','currency_type_id'=>'VES','exchange_rate_sale'=>'1.00000000',
        ],$changes),'16'),['id'=>$id,'sale_note_id'=>1]);
    }

    public function test_same_product_with_different_prices_or_conditions_stays_on_separate_rows(): void
    {
        $rows=Lines::group([$this->line(1),$this->line(2,'499'),$this->line(3),
            $this->line(4,'500','1',['item'=>['unit_type_id'=>'KG']]),
            $this->line(5,'500','1',['affectation_igv_type_id'=>'20'])]);
        self::assertCount(4,$rows);
        self::assertSame('2',(string)D::readRequired($rows[0],'quantity'));
        self::assertSame('1000',(string)D::readRequired($rows[0],'total'));
        self::assertSame([1,3],array_column($rows[0]['source_sale_note_items'],'sale_note_item_id'));
        self::assertSame('499',(string)D::readRequired($rows[1],'unit_price'));
    }

    public function test_periodic_quantities_and_prices_are_added_from_fractions_not_twelve_place_text(): void
    {
        $a=LineCalculation::calculate($this->line(1,'1','0.333333333333'),'16',['quantity'=>D::of('1/3'),'unit_price'=>D::of('1/3')]);
        $b=$a;$b['id']=2;$c=$a;$c['id']=3;
        $rows=Lines::group([$a,$b,$c]);
        self::assertSame('1',(string)D::readRequired($rows[0],'quantity'));
        self::assertSame('1/3',(string)D::readRequired($rows[0],'unit_price'));
        self::assertArrayNotHasKey('id',$rows[0]);
        self::assertArrayNotHasKey('quantity_input',$rows[0]['calculation_snapshot']['inputs']);
    }

    public function test_fixed_discounts_are_accumulated_and_percentages_remain_conditions(): void
    {
        $fixed=['discounts'=>[['discount_type_id'=>'00','is_amount'=>true,'amount'=>'1']]];
        $rows=Lines::group([$this->line(1,'116','1',$fixed),$this->line(2,'116','1',$fixed)]);
        self::assertCount(1,$rows);
        self::assertSame('2',(string)D::of($rows[0]['discounts'][0]['amount_exact']));
        self::assertSame('229.68',D::represent(D::readRequired($rows[0],'total')));
        $percent=['discounts'=>[['discount_type_id'=>'00','percentage'=>'10']]];
        $rows=Lines::group([$this->line(1,'116','1',$percent),$this->line(2,'116','2',$percent)]);
        self::assertCount(1,$rows);
        self::assertSame('313.2',D::represent(D::readRequired($rows[0],'total')));
        self::assertSame('10',$rows[0]['discounts'][0]['percentage']);
    }

    public function test_prices_that_only_differ_below_the_projection_remain_distinct(): void
    {
        $a=$this->line(1,'1');$b=$this->line(2,'1');
        $b['calculation_snapshot']['values']['unit_price']=D::fraction(D::of(1)->plus(D::of('1/100000000000000000000')));
        self::assertCount(2,Lines::group([$a,$b]));
    }

    public function test_operand_cache_does_not_hide_corrupt_snapshots_or_missing_fields(): void
    {
        $a=$this->line(1);$b=$this->line(2);$b['calculation_snapshot']['version']=99;
        try { Lines::group([$a,$b]);self::fail('Un valor ya conocido no autoriza otra versión del snapshot.'); }
        catch (\UnexpectedValueException $error) { self::assertStringContainsString('Snapshot',$error->getMessage()); }
        $b=$this->line(2);unset($b['total'],$b['calculation_snapshot']['values']['total']);
        try { Lines::group([$a,$b]);self::fail('Una consulta parcial no puede omitir el total.'); }
        catch (\UnexpectedValueException $error) { self::assertStringContainsString('total',$error->getMessage()); }
        $b=$this->line(2);$b['calculation_snapshot']=json_encode($b['calculation_snapshot']);
        self::assertSame('1000',(string)D::readRequired(Lines::group([$a,$b])[0],'total'));
    }

    public function test_stock_cache_is_not_a_condition_but_tracked_sources_are_preserved_separately(): void
    {
        $a=$this->line(1);$b=$this->line(2);$a['item']['stock']='10';$b['item']['stock']='9';
        self::assertCount(1,Lines::group([$a,$b]));
        $b['item']=array_reverse($b['item'],true);
        self::assertCount(1,Lines::group([$a,$b]));
        $a['item']['lots']=[['id'=>1]];$b['item']['lots']=[['id'=>1]];
        self::assertCount(2,Lines::group([$a,$b]));
    }
}
