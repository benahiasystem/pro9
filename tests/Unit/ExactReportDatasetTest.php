<?php

namespace Tests\Unit;

use App\Support\Exact\{Decimal, ReportDataset, SalesConsolidated};
use PHPUnit\Framework\TestCase;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
class ExactReportDatasetTest extends TestCase
{
    public function test_filters_and_sorting_use_fractions_before_pagination(): void
    {
        $rows = new ReportDataset([
            ['id'=>'lower', 'total'=>'0.333333333333', 'calculation_snapshot'=>Decimal::snapshot(['total'=>'3333333333331/10000000000000'])],
            ['id'=>'higher', 'total'=>'0.333333333333', 'calculation_snapshot'=>Decimal::snapshot(['total'=>'1/3'])],
            ['id'=>'zero', 'total'=>'0', 'calculation_snapshot'=>Decimal::snapshot(['total'=>'0'])],
        ]);
        $filtered = $rows->filter(fn($row)=>Decimal::readRequired($row,'total')->isGreaterThan('0'));
        $page = $filtered->sortExact('total', true)->paginate(1, 1);
        self::assertSame(2, $page->total());
        self::assertSame('higher', $page->items()[0]['id']);
        self::assertSame('1/3', (string)Decimal::readRequired($page->items()[0],'total'));
        self::assertCount(3, $rows);
        self::assertSame(iterator_to_array($rows), iterator_to_array($rows));
        $mapped = $rows->map(function ($row) { $row['label'] = 'Detalle'; return $row; });
        self::assertSame('1/3', (string)Decimal::readRequired($mapped->sortExact('total', true)->paginate(1,1)->items()[0], 'total'));
    }

    public function test_external_sort_is_stable_across_multiple_merge_passes_and_iterators(): void
    {
        $dataset = new ReportDataset((function () { for ($id=0; $id<1251; $id++) yield ['id'=>$id, 'group'=>$id%11]; })());
        $sorted = $dataset->sorted(fn($a,$b)=>$a['group'] <=> $b['group'], 13);
        $expected = iterator_to_array($dataset);
        usort($expected, fn($a,$b)=>$a['group'] <=> $b['group'] ?: $a['id'] <=> $b['id']);
        self::assertSame($expected, iterator_to_array($sorted));
        $a = $sorted->getIterator(); $b = $sorted->getIterator();
        $a->rewind(); $b->rewind(); $a->next();
        self::assertSame($expected[0], $b->current());
        self::assertSame($expected[1], $a->current());
    }

    public function test_streamed_grouping_matches_regular_grouping_without_reading_the_database_again(): void
    {
        $calls = 0;
        $dataset = new ReportDataset((function () use (&$calls) {
            for ($id=0; $id<501; $id++) {
                $calls++;
                yield SalesConsolidated::line(['id'=>$id, 'item_id'=>$id%3, 'currency_type_id'=>'VES', 'quantity'=>'0.333333333333', 'total'=>'0.333333333333',
                    'calculation_snapshot'=>Decimal::snapshot(['quantity'=>'1/3','total'=>'1/3']), 'item'=>['unit_type_id'=>'UND']]);
            }
        })());
        $grouped = SalesConsolidated::groupedDataset($dataset);
        self::assertCount(3, $grouped);
        self::assertSame(SalesConsolidated::summary($dataset), SalesConsolidated::summary($grouped));
        self::assertSame('167', SalesConsolidated::summary($grouped)['total_sale']);
        self::assertSame(501, $calls);
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
