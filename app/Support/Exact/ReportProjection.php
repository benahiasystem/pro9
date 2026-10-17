<?php

namespace App\Support\Exact;

use Brick\Math\RoundingMode;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Disposable signed largest-remainder projection. Original report fractions are untouched. */
final class ReportProjection
{
    public static function money(ReportDataset $rows, array $fields): ReportDataset
    {
        foreach ($fields as $field) {
            $sum = $truncatedSum = Decimal::of(0);
            $prepared = new ReportDataset((function () use ($rows, $field, &$sum, &$truncatedSum) {
                $position = 0;
                foreach ($rows as $row) {
                    $units = Decimal::readRequired($row, $field)->multipliedBy(100);
                    $base = $units->toScale(0, RoundingMode::DOWN)->toBigInteger();
                    $sum = $sum->plus($units)->simplified();
                    $truncatedSum = $truncatedSum->plus($base);
                    $row['_report_position'] = $position++;
                    $row['_projection'] = ['base'=>(string)$base, 'residue'=>Decimal::fraction($units->minus($base))];
                    yield $row;
                }
            })());
            // This integer is a number of rows receiving one cent, bounded by the row count.
            $left = $sum->toScale(0, RoundingMode::HALF_UP)->toBigInteger()->minus($truncatedSum->toBigInteger())->toInt();
            $direction = $left < 0 ? -1 : 1;
            $ranked = $left === 0 ? $prepared : $prepared->sorted(static function ($a, $b) use ($direction) {
                return Decimal::of($b['_projection']['residue'])->compareTo(Decimal::of($a['_projection']['residue'])) * $direction;
            });
            $rows = new ReportDataset((function () use ($ranked, $field, $left, $direction) {
                $position = 0;
                foreach ($ranked as $row) {
                    $base = Decimal::of($row['_projection']['base']);
                    if ($position++ < abs($left)) {
                        $residue = Decimal::of($row['_projection']['residue']);
                        if (($direction > 0 && !$residue->isPositive()) || ($direction < 0 && !$residue->isNegative())) {
                            throw new \LogicException('El reparto de presentación excede las partidas elegibles.');
                        }
                        $base = $base->plus($direction);
                    }
                    $row['display'][$field] = Decimal::represent($base->dividedBy(100), 2, true);
                    unset($row['_projection']);
                    yield $row;
                }
            })());
            if ($left !== 0) $rows = $rows->sortBy('_report_position');
        }
        return $rows;
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
