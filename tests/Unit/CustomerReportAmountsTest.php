<?php

namespace Tests\Unit;

use App\Support\Exact\Decimal as D;
use Modules\Report\Services\CustomerReportAmounts;
use PHPUnit\Framework\TestCase;

final class CustomerReportAmountsTest extends TestCase
{
    public function test_projects_signed_fractions_per_currency_and_excludes_voids(): void
    {
        $make=fn ($id,$currency,$type,$state='05') => (object)['id'=>$id,'currency_type_id'=>$currency,
            'document_type_id'=>$type,'state_type_id'=>$state,'total'=>'0.333333333333',
            'calculation_snapshot'=>D::snapshot(['total'=>['n'=>'1','d'=>'3']])];
        $records=collect([$make(1,'VES','01'),$make(2,'VES','01'),$make(3,'VES','01'),
            $make(4,'VES','07'),$make(5,'USD','08'),$make(6,'VES','01','11')]);
        $report=CustomerReportAmounts::presentation($records);
        self::assertSame('0.67',D::format($report['totals']['VES'],2,'.',''));
        self::assertSame('0.33',D::format($report['totals']['USD'],2,'.',''));
        self::assertSame(['0.34','0.33','0.33','-0.33','0.33','0.00'],$report['rows']->pluck('display.total')->all());
        self::assertSame(['n'=>'-1','d'=>'3'],$report['rows'][3]['calculation_snapshot']['values']['total']);
        $page=CustomerReportAmounts::displayForPage($records,[1,4]);
        self::assertSame('0.34',$page['document:1']['total']);
        self::assertSame('-0.33',$page['document:4']['total']);
    }
}
