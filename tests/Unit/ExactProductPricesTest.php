<?php

namespace Tests\Unit;

use App\Models\Tenant\Item;
use App\Support\Exact\Decimal;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
class ExactProductPricesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\DB::purge('tenant');
        config(['database.connections.tenant'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'']]);
    }

    public function test_warehouse_and_purchase_prices_keep_fractions_in_the_selector(): void
    {
        config(['venezuela.tax.rate'=>'0.16']);
        $item = new Item(['id'=>1, 'sale_unit_price'=>'500', 'purchase_unit_price'=>'500', 'purchase_has_igv'=>true]);
        $item->forceFill(['id'=>1]);
        $price = new class extends \Illuminate\Database\Eloquent\Model {};
        $price->setRawAttributes(['item_id'=>1,'warehouse_id'=>2,'price'=>'0.333333333333','calculation_snapshot'=>json_encode(Decimal::snapshot(['price'=>'1/3']))]);
        $item->setRelation('warehousePrices', new Collection([$price]));
        $values = $item->modalPriceValues(2);
        self::assertSame('1/3', (string)Decimal::readRequired($values, 'sale_unit_price'));
        self::assertSame('1/3', (string)Decimal::readRequired($values, 'unit_price'));
        self::assertSame('500', Decimal::represent(Decimal::readRequired($values, 'purchase_unit_value')->multipliedBy('1.16')));
        self::assertSame('500', Item::getSaleUnitPriceByWarehouse($item, 3));
        self::assertSame('0.333333333333', Item::getSaleUnitPriceByWarehouse($item, 2));
    }

    public function test_purchase_price_without_iva_is_multiplied_once_without_intermediate_rounding(): void
    {
        config(['venezuela.tax.rate'=>'0.16']);
        $item = new Item(['sale_unit_price'=>'500','purchase_unit_price'=>'0.333333333333','purchase_has_igv'=>false]);
        $item->purchase_unit_price = Decimal::of(1)->dividedBy(3);
        $item->stock = Decimal::of(10);
        $item->setRelation('warehousePrices', new Collection());
        $values = $item->modalPriceValues(2);
        self::assertSame('29/75', (string)Decimal::readRequired($values, 'purchase_unit_price'));
        self::assertSame('1/3', (string)Decimal::readRequired($values, 'purchase_unit_value'));
        $rawSelection = $item->selectionPriceSnapshot(2);
        self::assertSame('1/3', (string)Decimal::of($rawSelection['values']['purchase_unit_price']));
        self::assertArrayNotHasKey('stock', $rawSelection['values']);
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
