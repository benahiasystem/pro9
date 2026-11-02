<?php

namespace Tests\Unit;

use App\Support\Exact\{Decimal, PdfPrice, PdfAmounts, LineCalculation};
use PHPUnit\Framework\TestCase;

class PdfPricePrecisionTest extends TestCase
{
    public function test_purchase_order_templates_render_exact_line_discounts(): void
    {
        $blade = new \Illuminate\View\Compilers\BladeCompiler(new \Illuminate\Filesystem\Filesystem(), sys_get_temp_dir());
        $row = new \Illuminate\Support\Fluent(['percentage_igv'=>'16', 'discounts'=>[
            ['discount_type_id'=>'00', 'amount'=>'0.10'], ['discount_type_id'=>'00', 'amount'=>'0.20'],
        ]]);
        $decimals = 2;
        foreach (['default', 'marca_de_agua', 'Plantilla_personalizable'] as $template) {
            $source = file_get_contents(dirname(__DIR__, 2).'/app/CoreFacturalo/Templates/pdf/'.$template.'/purchase_order_a4.blade.php');
            self::assertMatchesRegularExpression('/(@php\s+\$total_discount_line\s*=.*?@endphp\s*\{\{.*?\}\})/s', $source);
            preg_match('/(@php\s+\$total_discount_line\s*=.*?@endphp\s*\{\{.*?\}\})/s', $source, $match);
            $compiled = $blade->compileString($match[1]);
            ob_start();
            try { eval('?>'.$compiled); $rendered = ob_get_contents(); }
            finally { ob_end_clean(); }
            self::assertSame('0.35', trim($rendered), $template);
        }
    }

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
