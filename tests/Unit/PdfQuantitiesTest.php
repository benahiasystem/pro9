<?php

namespace Tests\Unit;

use App\Support\Exact\Decimal;
use App\Support\Exact\PdfQuantities;
use PHPUnit\Framework\TestCase;

final class PdfQuantitiesTest extends TestCase
{
    public function test_quantities_use_snapshots_and_remain_separate_by_frozen_unit(): void
    {
        $items = [
            ['quantity'=>'0.333333333333', 'item'=>['unit_type_id'=>'KG'], 'calculation_snapshot'=>Decimal::snapshot(['quantity'=>'1/3'])],
            ['quantity'=>'0.666666666667', 'item'=>['unit_type_id'=>'KG'], 'calculation_snapshot'=>Decimal::snapshot(['quantity'=>'2/3'])],
            ['quantity'=>'0.000000000001', 'item'=>['unit_type_id'=>'UND']],
            ['quantity'=>'2', 'item'=>[]],
        ];
        self::assertSame('1 KG, 0.000000000001 UND, 2 sin unidad', PdfQuantities::summary($items));
        self::assertSame(25, PdfQuantities::extraHeight58($items));
        self::assertSame('0', PdfQuantities::summary([]));
    }
}
