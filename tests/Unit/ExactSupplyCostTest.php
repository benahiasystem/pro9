<?php

namespace Tests\Unit;

use App\Support\Exact\Decimal;
use Modules\Restaurant\Models\Supply;
use Tests\TestCase;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
class ExactSupplyCostTest extends TestCase
{
    public function test_cost_waste_and_minimum_stock_compare_preserved_fractions(): void
    {
        $supply = new Supply(['cost'=>'0.333333333333', 'waste_percentage'=>'10', 'stock'=>'0.333333333333', 'minimum_stock'=>'0.333333333333']);
        $supply->cost = Decimal::of(1)->dividedBy(3);
        $supply->stock = Decimal::of('3333333333331/10000000000000');
        $supply->minimum_stock = Decimal::of(1)->dividedBy(3);
        self::assertSame('11/30', (string)$supply->getEffectiveCost());
        self::assertTrue($supply->isBelowMinimumStock());
        self::assertSame($supply->stock, $supply->minimum_stock);
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
