<?php
namespace Tests\Unit;

use App\Support\Exact\{Decimal, PdfAmounts};

final class PdfAdjustmentPercentTest extends \PHPUnit\Framework\TestCase
{
    public function test_multiple_charge_percentages_are_added_once_without_reusing_rounded_labels(): void
    {
        $document=(object)['charges'=>'[{"factor":0.02,"amount":10},{"factor":0.03,"amount":15}]'];
        $rows=PdfAmounts::adjustments($document,'charges');
        self::assertSame('0.02',$rows[0]->factor);
        $percent=Decimal::sum(array_map([PdfAmounts::class,'adjustmentPercentValue'],$rows));
        self::assertSame('5',Decimal::represent($percent));
        $third=['factor'=>['n'=>'1','d'=>'3']];
        $sum=Decimal::sum(array_map([PdfAmounts::class,'adjustmentPercentValue'],[$third,$third,$third]));
        self::assertSame(['n'=>'100','d'=>'1'],Decimal::fraction($sum));
    }

    public function test_explicit_percentage_takes_precedence_and_tiny_factors_stay_exact_until_display(): void
    {
        self::assertSame('2.000000000001',Decimal::represent(PdfAmounts::adjustmentPercentValue(['percentage'=>'2.000000000001','factor'=>'1'])));
        self::assertSame('3',PdfAmounts::adjustmentPercent(['percentage'=>null,'factor'=>'0.03']));
        $row=PdfAmounts::adjustments((object)['charges'=>'[{"factor":0.000000000000001}]'],'charges')[0];
        self::assertSame(['n'=>'1','d'=>'10000000000000'],Decimal::fraction(PdfAmounts::adjustmentPercentValue($row)));
        self::assertSame('0',PdfAmounts::adjustmentPercent($row));
    }

    public function test_line_adjustments_and_rate_labels_retain_exact_operands(): void
    {
        $line=(object)['percentage_igv'=>'16','discounts'=>'[{"discount_type_id":"00","amount_exact":{"n":"1","d":"3"}},{"discount_type_id":"01","from_global_distribution":true,"amount":0.1}]'];
        self::assertSame(['n'=>'29','d'=>'75'],Decimal::fraction(PdfAmounts::lineDiscount($line)));
        self::assertSame(['n'=>'73','d'=>'150'],Decimal::fraction(PdfAmounts::lineDiscount($line,false)));
        self::assertSame('0.33',PdfAmounts::adjustmentAmount(['amount_exact'=>['n'=>'1','d'=>'3'],'amount'=>'0.34']));
        self::assertSame('16',PdfAmounts::singleTaxRate([['percentage_igv'=>'0'],['percentage_igv'=>'16.00000000']]));
        self::assertNull(PdfAmounts::singleTaxRate([['percentage_igv'=>'16'],['percentage_igv'=>'8']]));
    }

    public function test_display_components_reconcile_and_dirty_snapshots_invalidate_the_cache_without_database_access(): void
    {
        $previous=\Illuminate\Database\Eloquent\Model::getConnectionResolver();
        \Illuminate\Database\Eloquent\Model::setConnectionResolver(new class implements \Illuminate\Database\ConnectionResolverInterface {
            public function connection($name=null) {throw new \LogicException('No database access is allowed in this projection test.');}
            public function getDefaultConnection() {throw new \LogicException('No database access is allowed in this projection test.');}
            public function setDefaultConnection($name) {throw new \LogicException('No database access is allowed in this projection test.');}
        });
        try {
            $values=['total_value'=>Decimal::of(1)->dividedBy(3),'total_igv'=>'0.1','total_other_taxes'=>Decimal::of(1)->dividedBy(7),
                'total_prepayment'=>Decimal::of(1)->dividedBy(3),'total'=>Decimal::of(17)->dividedBy(70),
                'total_taxed'=>Decimal::of(1)->dividedBy(3),'total_exonerated'=>'0','total_exportation'=>'0',
                'total_base_igv'=>Decimal::of(1)->dividedBy(3),'subtotal'=>'0.1'];
            $attributes=Decimal::values($values)+['calculation_snapshot'=>json_encode(Decimal::snapshot($values),JSON_THROW_ON_ERROR)];
            $record=new class extends \Illuminate\Database\Eloquent\Model {};
            $record->setRawAttributes($attributes,true);
            $record->setRelation('taxes',collect([(object)['tax_kind'=>'IGTF','amount'=>'0.142857142857',
                'calculation_snapshot'=>Decimal::snapshot(['amount'=>Decimal::of(1)->dividedBy(7)])]]));
            self::assertSame('0.24',Decimal::represent(PdfAmounts::read($record,'total')));
            self::assertSame('0.1',Decimal::represent(PdfAmounts::read($record,'subtotal')));
            $gross=Decimal::sum(array_map(fn($field)=>PdfAmounts::read($record,$field),['total_value','total_igv','total_other_taxes']));
            self::assertTrue($gross->minus('0.33')->isEqualTo(PdfAmounts::read($record,'total')));
            $bases=Decimal::sum(array_map(fn($field)=>PdfAmounts::read($record,$field),['total_taxed','total_exonerated','total_exportation']));
            self::assertTrue($bases->isEqualTo(PdfAmounts::read($record,'total_value')));
            self::assertSame(['n'=>'17','d'=>'70'],Decimal::fraction(Decimal::readRequired($record,'total')));
            self::assertSame($attributes,$record->getAttributes());
            $next=array_fill_keys(array_keys($values),'0');
            foreach (['total','total_value','total_taxed','total_base_igv','subtotal'] as $field) $next[$field]='500';
            $record->setRawAttributes(Decimal::values($next)+['calculation_snapshot'=>json_encode(Decimal::snapshot($next),JSON_THROW_ON_ERROR)],true);
            $record->setRelation('taxes',collect());
            self::assertSame('500',Decimal::represent(PdfAmounts::read($record,'total')));
            self::assertSame('500',Decimal::represent(PdfAmounts::read($record,'total_value')));
        } finally {
            if ($previous) \Illuminate\Database\Eloquent\Model::setConnectionResolver($previous);
            else \Illuminate\Database\Eloquent\Model::unsetConnectionResolver();
        }
    }
}
