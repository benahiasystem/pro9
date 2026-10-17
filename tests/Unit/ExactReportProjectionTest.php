<?php

namespace Tests\Unit;

use App\Support\Exact\{Decimal, ReportDataset, ReportProjection};
use PHPUnit\Framework\TestCase;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
class ExactReportProjectionTest extends TestCase
{
    private function dataset(array $values): ReportDataset
    {
        return new ReportDataset((function () use ($values) {
            foreach ($values as $id=>$value) yield ['id'=>$id, 'total'=>Decimal::represent($value), 'calculation_snapshot'=>Decimal::snapshot(['total'=>$value])];
        })());
    }

    public function test_thirds_keep_the_displayed_sum_and_original_fractions(): void
    {
        $rows = $this->dataset(['1/3','1/3','1/3']);
        $projected = iterator_to_array(ReportProjection::money($rows, ['total']));
        self::assertSame(['0.34','0.33','0.33'], array_column(array_column($projected, 'display'), 'total'));
        self::assertSame('1', Decimal::represent(Decimal::sum(array_map(fn($r)=>$r['display']['total'], $projected))));
        foreach ($projected as $row) self::assertSame('1/3', (string)Decimal::readRequired($row, 'total'));
    }

    public function test_signed_projection_conserves_sums_and_never_changes_a_line_sign(): void
    {
        foreach ([['-1/3','-1/3','-1/3'], ['1/3','-1/3','0.0000000000004'], ['0.004','0.004','-0.003'],
            ['-0.004','-0.004','0.003'], ['0.0049999999996'], ['100000000000000000.123456789012','-99999999999999999.1']] as $values) {
            $original = $this->dataset($values);
            $projected = ReportProjection::money($original, ['total']);
            self::assertSame(Decimal::represent($original->sum('total'), 2, true),
                Decimal::represent(Decimal::sum((function () use ($projected) {foreach ($projected as $row) yield $row['display']['total'];})()), 2, true));
            foreach ($projected as $row) {
                $before = Decimal::readRequired($row, 'total'); $after = Decimal::of($row['display']['total']);
                self::assertFalse($before->isNegative() && $after->isPositive());
                self::assertFalse($before->isPositive() && $after->isNegative());
                self::assertTrue($after->minus($before)->abs()->isLessThan('0.01'));
            }
        }
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
