<?php

namespace Tests\Unit;

use App\Support\Exact\Decimal as D;
use Modules\Report\Services\DocumentReportLines as Lines;
use Modules\Report\Services\DocumentCurrencyTotals;
use PHPUnit\Framework\TestCase;

final class DocumentReportLinesTest extends TestCase
{
    public function test_categories_and_quantities_use_exact_values_and_historical_units(): void
    {
        $line=fn ($unit,$quantity,$total)=>(object)['item'=>(object)['unit_type_id'=>$unit],
            'relation_item'=>(object)['category_id'=>5], 'quantity'=>D::represent($quantity), 'total'=>D::represent($total),
            'calculation_snapshot'=>D::snapshot(['quantity'=>$quantity,'total'=>$total])];
        $document=new class {
            public $id=1, $state_type_id='05', $type='01', $items, $calculation_snapshot;
            public function getDocumentType() { return (object)['id'=>$this->type]; }
        };
        $document->items=collect([$line('KG',['n'=>'1','d'=>'3'],'0.1'),
            $line('KG',['n'=>'2','d'=>'3'],'0.2'),$line('SERV',['n'=>'1','d'=>'3'],'0.4')]);
        $document->calculation_snapshot=D::snapshot(array_fill_keys(Lines::MONETARY_FIELDS,'1/3'));
        self::assertSame('0.7',D::represent(Lines::categoryAmount($document,5)));
        self::assertSame(['n'=>'1','d'=>'3'],D::fraction(Lines::serviceQuantity($document,5)));
        self::assertSame('1 KG; 0.333333333333 SERV',Lines::quantityText($document));
        self::assertSame(['n'=>'1','d'=>'3'],Lines::monetaryRow($document)['calculation_snapshot']['values']['total']);
        $document->type='07';self::assertSame('-0.7',D::represent(Lines::categoryAmount($document,5)));
        self::assertSame('-0.33',D::format(Lines::monetary($document,'total'),2,'.',''));
        $document->state_type_id='11';self::assertSame('0',D::represent(Lines::categoryAmount($document,5)));
        self::assertSame('0',Lines::quantityText($document));self::assertSame('0',D::represent(Lines::monetary($document,'total')));
    }

    public function test_rounding_adjustment_reconciles_exact_total_to_displayed_rows(): void
    {
        $doc=new class {
            public $currency_type_id='VES',$state_type_id='05',$calculation_snapshot;
            public function getDocumentType() { return (object)['id'=>'01']; }
        };
        $doc->calculation_snapshot=D::snapshot(array_fill_keys(Lines::MONETARY_FIELDS,['n'=>'1','d'=>'3']));
        $exact=DocumentCurrencyTotals::empty();$display=DocumentCurrencyTotals::empty();
        for ($i=0;$i<3;$i++) { $exact=DocumentCurrencyTotals::add($exact,$doc);$display=DocumentCurrencyTotals::addDisplayed($display,$doc); }
        self::assertSame('0.01',DocumentCurrencyTotals::adjustment($exact,$display,'VES','total'));
        self::assertTrue(DocumentCurrencyTotals::hasAdjustment($exact,$display,'VES',['total']));
        self::assertFalse(DocumentCurrencyTotals::hasAdjustment($exact,$display,'USD',['total']));
    }
}
