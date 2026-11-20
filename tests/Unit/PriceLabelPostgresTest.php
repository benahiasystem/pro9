<?php

namespace Tests\Unit;

/** @group postgres-integration */
final class PriceLabelPostgresTest extends \PHPUnit\Framework\TestCase
{
    private $fixture;

    protected function setUp(): void
    {
        $this->fixture=new class extends FiscalEmissionSchemaTest {
            public function start(): void {parent::setUp();}
            public function stop(): void {parent::tearDown();}
        };
        $this->fixture->start();
        (new \ReflectionMethod($this->fixture,'prepareFiscalFixtureSchema'))->invoke($this->fixture);
    }

    protected function tearDown(): void
    {
        try {if ($this->fixture) $this->fixture->stop();}
        finally {parent::tearDown();}
    }

    public function test_presentation_default_price_is_a_signed_numeric_selector_with_default_two(): void
    {
        $item=\App\Models\Tenant\Item::where('internal_id','MOCK-ITEM-VES-001')->firstOrFail();
        $unit=\App\Models\Tenant\ItemUnitType::create(['item_id'=>$item->id,'description'=>'Selector PostgreSQL temporal',
            'unit_type_id'=>'UND','quantity_unit'=>'1']);
        self::assertSame(2,$unit->fresh()->price_default);
        $db=$unit->getConnection();
        foreach ([-128,0,1,2,3,127] as $choice) {
            $unit->update(['price_default'=>$choice]);
            self::assertSame($choice,$unit->fresh()->price_default);
            self::assertSame($choice,json_decode($unit->fresh()->toJson(),true,512,JSON_THROW_ON_ERROR)['price_default']);
        }
        foreach ([-129,128] as $invalid) {
            try {
                $db->transaction(fn ()=>$db->table('item_unit_types')->where('id',$unit->id)->update(['price_default'=>$invalid]));
                self::fail('Se debe conservar el rango signed tinyint declarado en MySQL.');
            } catch (\Illuminate\Database\QueryException $exception) {
                self::assertSame('23514',$exception->getCode());
            }
            self::assertSame(127,$unit->fresh()->price_default);
        }
    }

    public function test_invoice_presentation_keeps_the_original_label_price_beside_its_caption(): void
    {
        $item = \App\Models\Tenant\Item::where('internal_id', 'MOCK-ITEM-VES-001')->firstOrFail();
        $presentation = \App\Models\Tenant\ItemUnitType::create([
            'item_id' => $item->id, 'description' => 'Precio de factura temporal',
            'unit_type_id' => 'UND', 'quantity_unit' => '3',
        ]);
        $position = \App\Models\Tenant\PriceLabel::max('position');
        $label = \App\Models\Tenant\PriceLabel::create(['position' => $position + 1, 'label' => 'Factura temporal', 'is_active' => true]);
        $price = new \App\Models\Tenant\ItemUnitTypePrice([
            'item_unit_type_id' => $presentation->id, 'price_label_id' => $label->id, 'is_active' => true,
        ]);
        $price->setExactValues(['price' => \App\Support\Exact\Decimal::of('1/3')])->save();

        $serialize = new \ReflectionMethod($item, 'getItemUnitTypesWithPrices');
        $item->load('item_unit_types.prices.priceLabel');
        $forInvoice = collect($serialize->invoke($item, true))->firstWhere('id', $presentation->id);
        $legacy = collect($serialize->invoke($item))->firstWhere('id', $presentation->id);
        $labelPrice = collect($forInvoice['prices'])->firstWhere('id', $price->id);
        $legacyPrice = collect($legacy['prices'])->firstWhere('id', $price->id);
        self::assertSame('0.33', $legacyPrice['price']);
        self::assertSame($legacyPrice['price'], $labelPrice['price']);
        self::assertArrayNotHasKey('calculation_snapshot', $legacyPrice);
        self::assertSame(['n' => '1', 'd' => '3'], $labelPrice['calculation_snapshot']['values']['price'] ?? null);
    }

    public function test_price_label_fill_copies_exact_snapshots_without_float_or_stale_overwrites(): void
    {
        $D=\App\Support\Exact\Decimal::class;
        $third=$D::of(1)->dividedBy(3);
        $item=\App\Models\Tenant\Item::where('internal_id','MOCK-ITEM-VES-001')->firstOrFail()->replicate();
        $item->internal_id='PG-EXACT-PRICE-LABELS';
        $item->description='Precio exacto temporal';
        $item->setExactValues(['sale_unit_price'=>$third])->save();
        self::assertSame(0,$item->item_unit_types()->count());
        $position=\App\Models\Tenant\PriceLabel::max('position')+1;
        $active=\App\Models\Tenant\PriceLabel::create(['position'=>$position,'label'=>'Precisión temporal','is_active'=>true]);
        $inactive=\App\Models\Tenant\PriceLabel::create(['position'=>$position+1,'label'=>'Inactiva temporal','is_active'=>false]);
        $run=static function (bool $dry=false,bool $overwrite=false): void {
            $command=new \App\Console\Commands\FillItemPriceLabelsCommand();
            $input=new \Symfony\Component\Console\Input\ArrayInput(['--dry'=>$dry,'--overwrite'=>$overwrite],$command->getDefinition());
            $command->setInput($input);
            $command->setOutput(new \Illuminate\Console\OutputStyle($input,new \Symfony\Component\Console\Output\BufferedOutput()));
            (new \ReflectionMethod($command,'fillForCurrentTenant'))->invoke($command);
        };
        $before=\App\Models\Tenant\ItemUnitTypePrice::count();
        $run(true);
        self::assertSame(0,$item->item_unit_types()->count());
        self::assertSame($before,\App\Models\Tenant\ItemUnitTypePrice::count());
        $run();
        $unit=$item->item_unit_types()->firstOrFail();
        self::assertSame($D::fraction($third),$D::fraction($D::read($unit,'price1')));
        self::assertSame('1',$D::represent($D::read($unit,'quantity_unit')));
        $prices=\App\Models\Tenant\ItemUnitTypePrice::where('item_unit_type_id',$unit->id)->get();
        self::assertSame(\App\Models\Tenant\PriceLabel::where('is_active',true)->count(),$prices->count());
        self::assertFalse($prices->contains('price_label_id',$inactive->id));
        foreach ($prices as $price) {
            self::assertSame('0.333333333333',$D::represent($D::read($price,'price')));
            self::assertSame($D::fraction($third),$price->calculation_snapshot['values']['price']);
        }
        $custom=$prices->firstWhere('price_label_id',$active->id);
        $custom->setExactValues(['price'=>$third->multipliedBy(2)])->save();
        $run();
        self::assertSame($D::fraction($third->multipliedBy(2)),$custom->fresh()->calculation_snapshot['values']['price']);
        self::assertSame($prices->count(),\App\Models\Tenant\ItemUnitTypePrice::where('item_unit_type_id',$unit->id)->count());
        $large=$D::of('123456789012345678.123456789012');
        $unit->setExactValues(['price1'=>$large])->save();
        $run(true,true);
        self::assertSame($D::fraction($third->multipliedBy(2)),$custom->fresh()->calculation_snapshot['values']['price']);
        $run(false,true);
        foreach ($prices->fresh() as $price) {
            self::assertSame('123456789012345678.123456789012',$D::represent($D::read($price,'price')));
            self::assertSame($D::fraction($large),$price->calculation_snapshot['values']['price']);
        }
        // A nonpositive presentation price falls back to the exact catalog price.
        $unit->setExactValues(['price1'=>'0'])->save();
        $item->setExactValues(['sale_unit_price'=>'0.000000000001'])->save();
        $run(false,true);
        self::assertSame(['n'=>'1','d'=>'1000000000000'],$custom->fresh()->calculation_snapshot['values']['price']);
        // Nonpositive catalog prices never replace an existing list price.
        $item->setExactValues(['sale_unit_price'=>'0'])->save();
        $run(false,true);
        self::assertSame(['n'=>'1','d'=>'1000000000000'],$custom->fresh()->calculation_snapshot['values']['price']);
    }

}
