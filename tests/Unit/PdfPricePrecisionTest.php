<?php

namespace Tests\Unit;

use App\Support\Exact\{Decimal, PdfPrice, PdfAmounts, LineCalculation};
use PHPUnit\Framework\TestCase;

class PdfPricePrecisionTest extends TestCase
{
    public function test_displayed_base_and_tax_add_up_to_the_represented_total(): void
    {
        $line=LineCalculation::calculate(['quantity'=>'1','unit_price'=>'116.123456789123'],'16');
        $values=[];
        foreach (['total','total_value','total_igv','total_other_taxes'] as $field) $values[$field]=Decimal::read($line,$field);
        $values['total_taxed']=Decimal::read($line,'total_base_igv');
        $record=Decimal::values($values)+['calculation_snapshot'=>Decimal::snapshot($values)];
        $before=json_encode($record,JSON_THROW_ON_ERROR);
        self::assertSame('100.10',Decimal::format(PdfAmounts::read($record,'total_taxed')));
        self::assertSame('16.02',Decimal::format(PdfAmounts::read($record,'total_igv')));
        self::assertSame('116.12',Decimal::format(PdfAmounts::read($record,'total_taxed')->plus(PdfAmounts::read($record,'total_igv'))));
        self::assertSame($before,json_encode($record,JSON_THROW_ON_ERROR));
        $object=new \Illuminate\Support\Fluent($record);
        self::assertSame('100.10',Decimal::format(PdfAmounts::read($object,'total_taxed')));
        $object->calculation_snapshot=Decimal::snapshot(['total'=>'232','total_value'=>'200','total_taxed'=>'200','total_igv'=>'32']);
        self::assertSame('200.00',Decimal::format(PdfAmounts::read($object,'total_taxed')));
    }
    public function test_default_and_disabled_pdf_price_have_two_decimal_places(): void
    {
        self::assertSame('500.00', PdfPrice::format('500', []));
        self::assertSame('431.03', PdfPrice::format(Decimal::of(500)->dividedBy('1.16'), [
            'change_decimal_quantity_unit_price_pdf'=>false, 'decimal_quantity_unit_price_pdf'=>12]));
    }

    public function test_rendering_2_6_and_12_decimal_places_does_not_change_the_exact_value(): void
    {
        $price = Decimal::of(500)->dividedBy('1.16');
        foreach ([2=>'431.03', 6=>'431.034483', 12=>'431.034482758621'] as $scale=>$expected) {
            self::assertSame($expected, PdfPrice::format($price, [
                'change_decimal_quantity_unit_price_pdf'=>true, 'decimal_quantity_unit_price_pdf'=>$scale]));
            self::assertSame('500', Decimal::represent($price->multipliedBy('1.16')));
        }
    }
}
