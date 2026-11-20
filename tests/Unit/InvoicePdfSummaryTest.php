<?php

namespace Tests\Unit;

use App\Support\Exact\{Decimal as D, InvoicePdfSummary};
use Illuminate\Support\Fluent;
use PHPUnit\Framework\TestCase;

final class InvoicePdfSummaryTest extends TestCase
{
    public function test_ticket_keeps_large_discounts_igtf_and_subtotal_without_mutating_the_invoice(): void
    {
        $document=new Fluent(['subtotal'=>'100','total_discount'=>'9007199254740993.01',
            'total_discount_with_igv'=>'9007199254740993.02','total_charge'=>'0.03',
            'taxes'=>[['tax_kind'=>'IGTF','amount'=>'9007199254740993.01'],
                ['tax_kind'=>'IGTF','amount'=>'0.02'],['tax_kind'=>'IVA','amount'=>'100']]]);
        $before=$document->toArray();$third=InvoicePdfSummary::ticketTotals($document,true);
        self::assertSame('9007199254740993.01',D::represent($third['discount']));
        self::assertSame('9007199254740993.03',D::represent($third['igtf']));
        self::assertSame('9007199254741092.98',D::represent($third['subtotal']));
        $own=InvoicePdfSummary::ticketTotals($document,false);
        self::assertSame('9007199254741093.02',D::represent($own['subtotal']));
        self::assertTrue($own['igtf']->isZero());self::assertSame($before,$document->toArray());
    }

    public function test_package_totals_exclude_services_and_use_original_quantity_fractions(): void
    {
        $line=static fn($quantity,$type='01',$unit='UND')=>['quantity'=>'0.333333333333',
            'calculation_snapshot'=>D::snapshot(['quantity'=>D::of($quantity)]),
            'item'=>['item_type_id'=>$type,'unit_type_id'=>$unit]];
        $items=[$line('9007199254740993'),$line(D::of(1)->dividedBy(3)),$line(D::of(2)->dividedBy(3)),
            $line('100','02'),$line('100','01','SERV'),$line('100',2)];
        $before=$items;$packages=InvoicePdfSummary::packages($items);
        self::assertSame('9,007,199,254,740,994',InvoicePdfSummary::packageText($packages));
        self::assertSame('0.333333333333',InvoicePdfSummary::packageText(InvoicePdfSummary::packages([$items[1]])));
        self::assertSame($before,$items);
    }

    public function test_weight_reads_original_json_decimal_tokens_and_the_quantity_snapshot(): void
    {
        $line=['quantity'=>'0.333333333333','calculation_snapshot'=>D::snapshot(['quantity'=>D::of(1)->dividedBy(3)]),
            'attributes'=>'[{"attribute_type_id":"5032","value":9007199254740993.03},{"attribute_type_id":"5031","value":999}]'];
        $before=$line;
        self::assertSame('3002399751580331.01',D::represent(InvoicePdfSummary::netWeight([$line])));
        self::assertSame($before,$line);
    }

    public function test_optional_empty_weight_does_not_prevent_printing_or_hide_invalid_text(): void
    {
        $line=['quantity'=>'1','attributes'=>[['attribute_type_id'=>'5032','value'=>null],
            ['attribute_type_id'=>'5032','value'=>''],['attribute_type_id'=>'5032','value'=>'0.1']]];
        self::assertSame('0.1',D::represent(InvoicePdfSummary::netWeight([$line])));
        $line['attributes'][0]['value']='peso inválido';
        $this->expectException(\Brick\Math\Exception\NumberFormatException::class);InvoicePdfSummary::netWeight([$line]);
    }

    public function test_invoice_quantity_legends_preserve_subprecision_signs_and_group_original_fractions(): void
    {
        $tiny=D::of(1)->dividedBy('3000000000000');
        $line=static fn($quantity,$unit)=>['quantity'=>'0','item'=>['unit_type_id'=>$unit],
            'calculation_snapshot'=>D::snapshot(['quantity'=>$quantity])];
        self::assertSame('<0.000000000001',InvoicePdfSummary::quantity($line($tiny,'UND')));
        self::assertSame('>-0.000000000001',InvoicePdfSummary::quantity($line($tiny->negated(),'UND')));
        self::assertSame('<0.000000000001 UND, 1 KG',InvoicePdfSummary::quantities([
            $line($tiny,'UND'),$line(D::of(1)->dividedBy(3),'KG'),$line(D::of(2)->dividedBy(3),'KG')]));
        self::assertSame('0 UND',InvoicePdfSummary::quantities([$line($tiny,'UND'),$line($tiny->negated(),'UND')]));
        self::assertSame('0',InvoicePdfSummary::quantities([]));
        self::assertSame('<0.000000000001',InvoicePdfSummary::packageText($tiny));
    }
}
