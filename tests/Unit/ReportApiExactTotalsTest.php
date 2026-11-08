<?php

namespace Tests\Unit;

use App\Support\Exact\Decimal;
use Modules\Report\Http\Controllers\Api\ReportController;
use PHPUnit\Framework\TestCase;

class ReportApiExactTotalsTest extends TestCase
{
    public function test_document_report_keeps_signed_fractions_and_zeroes_all_voided_types(): void
    {
        $record=(object)[
            'state_type_id'=>'05', 'total'=>'0.333333333333', 'total_charge'=>'0',
            'total_exonerated'=>'0', 'total_taxed'=>'0.333333333333', 'total_discount'=>'0', 'total_igv'=>'0',
            'calculation_snapshot'=>Decimal::snapshot(['total'=>['n'=>'1','d'=>'3'], 'total_taxed'=>['n'=>'1','d'=>'3']]),
        ];
        $method=new \ReflectionMethod(ReportController::class,'totalesDocuments');
        $method->setAccessible(true);
        $controller=new ReportController();
        $invoice=$method->invoke($controller,$record,'01');
        self::assertSame('0.333333333333',$invoice['total']);
        self::assertSame(['n'=>'1','d'=>'3'],$invoice['fractions']['total']);
        $credit=$method->invoke($controller,$record,'07');
        self::assertSame('-0.333333333333',$credit['total']);
        self::assertSame(['n'=>'-1','d'=>'3'],$credit['fractions']['total']);
        foreach (['01','07','08','80'] as $type) foreach (['09','11'] as $state) {
            $record->state_type_id=$state;
            $voided=$method->invoke($controller,$record,$type);
            self::assertSame('0',$voided['total']);
            self::assertSame(['n'=>'0','d'=>'1'],$voided['fractions']['total']);
        }
    }
}
