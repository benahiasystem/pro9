<?php

namespace Tests\Unit;

use App\Support\Exact\Decimal as D;
use Modules\Report\Services\SaleNoteReportAmounts;
use PHPUnit\Framework\TestCase;

class SaleNoteReportAmountsTest extends TestCase
{
    public function test_projects_exact_notes_by_currency_and_excludes_voids(): void
    {
        $make = function ($id, $currency, $state = '01') {
            $values = array_fill_keys(SaleNoteReportAmounts::FIELDS, '0');
            $values['total'] = ['n'=>'1', 'd'=>'3'];
            return (object) (['id'=>$id, 'currency_type_id'=>$currency, 'state_type_id'=>$state,
                'calculation_snapshot'=>D::snapshot($values)] + D::values($values));
        };
        $notes = collect([$make(1,'VES'), $make(2,'VES'), $make(3,'VES'), $make(4,'USD'),
            $make(5,'VES','09'), $make(6,'USD','11')]);
        $report = SaleNoteReportAmounts::presentation($notes);
        self::assertSame('1.00', D::format($report['totals']['VES']['total'],2,'.',''));
        self::assertSame('0.33', D::format($report['totals']['USD']['total'],2,'.',''));
        self::assertSame(['0.34','0.33','0.33','0.33','0.00','0.00'], $report['rows']->pluck('display.total')->all());
        self::assertSame(['n'=>'1','d'=>'3'], $report['rows'][0]['calculation_snapshot']['values']['total']);
        self::assertSame(['n'=>'0','d'=>'1'], $report['rows'][4]['calculation_snapshot']['values']['total']);
        $page = SaleNoteReportAmounts::displayForPage($notes, [2,4]);
        self::assertSame('0.33', $page['sale_note:2']['total']);
        self::assertSame('0.33', $page['sale_note:4']['total']);
        self::assertCount(2, $page);
    }
}
