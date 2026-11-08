<?php

namespace Tests\Unit;

use App\Support\Exact\Decimal as D;
use Modules\Report\Services\BrandSalesAmounts;
use PHPUnit\Framework\TestCase;

class BrandSalesAmountsTest extends TestCase
{
    public function test_projection_separates_units_and_preserves_signed_fractions(): void
    {
        $row = static function ($id, $unit, $quantity, $sale, $profit) {
            return ['id'=>$id, 'report_key'=>'brand:'.$id, 'brand_name'=>'Prueba', 'unit_type_id'=>$unit,
                'quantity_sold'=>D::represent($quantity), 'total_sale_value'=>D::represent($sale),
                'total_profit'=>D::represent($profit),
                'calculation_snapshot'=>D::snapshot(['quantity_sold'=>$quantity,'total_sale_value'=>$sale,'total_profit'=>$profit])];
        };
        $report=BrandSalesAmounts::presentation([
            $row('a','KG',D::of(1)->dividedBy(3),D::of(1)->dividedBy(3),D::of(1)->dividedBy(6)),
            $row('b','UND',D::of(2)->dividedBy(3),D::of(2)->dividedBy(3),D::of(1)->dividedBy(3)),
            $row('c',null,'1','0','0'),
        ]);
        self::assertSame('1',D::represent($report['totals']['total_sale_value']));
        self::assertSame('0.5',D::represent($report['totals']['total_profit']));
        self::assertSame(['n'=>'1','d'=>'3'],D::fraction($report['units']['KG']));
        self::assertSame(['n'=>'2','d'=>'3'],D::fraction($report['units']['UND']));
        self::assertTrue($report['unknown']);
        self::assertSame(['0.33','0.67','0.00'],$report['rows']->pluck('display.total_sale_value')->all());
        self::assertSame(['0.17','0.33','0.00'],$report['rows']->pluck('display.total_profit')->all());
    }
}
