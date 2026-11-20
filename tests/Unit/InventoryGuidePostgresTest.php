<?php

namespace Tests\Unit;

use App\Support\Exact\Decimal;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Helpers\GuideStore;
use Modules\Inventory\Models\Guide;
use PHPUnit\Framework\TestCase;

/** @group postgres-integration */
final class InventoryGuidePostgresTest extends TestCase
{
    private $fixture;

    protected function setUp(): void
    {
        $this->fixture=new class extends FiscalEmissionSchemaTest {
            public function start(): void {parent::setUp();}
            public function stop(): void {parent::tearDown();}
        };
        $this->fixture->start();
        (new \ReflectionMethod($this->fixture,'bootHkaFixture'))->invoke($this->fixture,false);
        Guide::observe(\Modules\Inventory\Observers\GuideObserver::class);
    }

    protected function tearDown(): void
    {
        try {if ($this->fixture) $this->fixture->stop();}
        finally {parent::tearDown();}
    }

    public function test_warehouse_guide_quantities_keep_fractions_and_reject_invalid_lines_atomically(): void
    {
        $db=DB::connection('tenant');
        $warehouse=\Modules\Inventory\Models\Warehouse::firstOrFail();
        $item=\App\Models\Tenant\Item::where('internal_id','MOCK-ITEM-VES-001')->firstOrFail();
        $transaction=$db->table('inventory_transactions')->where('type','input')->value('id');
        $stock=$db->table('item_warehouse')->where('item_id',$item->id)->get()->toJson();
        $base=['establishment_id'=>$warehouse->establishment_id,'warehouse_id'=>$warehouse->id,
            'date_of_issue'=>'2026-11-11','time_of_issue'=>'12:00:00','inventory_transaction_id'=>$transaction];
        $row=['id'=>$item->id,'name'=>$item->description,'unit_cost'=>'999',
            'calculation_snapshot'=>Decimal::snapshot(['quantity'=>'999'])];
        foreach ([Decimal::of(1)->dividedBy(3),'123456789012345678.123456789012','0.000000000001'] as $quantity) {
            $guide=$db->transaction(fn()=>(new GuideStore())->save($base+['items'=>[$row+['stock_add'=>$quantity]]]));
            $line=$guide->items()->firstOrFail();
            self::assertIsString($line->quantity);
            self::assertSame(Decimal::represent($quantity),$line->quantity);
            self::assertSame(Decimal::fraction($quantity),$line->calculation_snapshot['values']['quantity']);
            self::assertSame(Decimal::fraction($quantity),Decimal::fraction(Decimal::read($line,'quantity')));
            self::assertSame('0',$line->unit_cost);
            self::assertSame('0',$line->total);
            self::assertSame(['n'=>'0','d'=>'1'],$line->calculation_snapshot['values']['unit_cost']);
        }
        self::assertSame($stock,$db->table('item_warehouse')->where('item_id',$item->id)->get()->toJson());
        $guides=Guide::count();$lines=\Modules\Inventory\Models\GuideItem::count();
        // The controller owns the transaction: a valid first line and its new
        // guide must disappear when a later line exceeds the input precision.
        $result=(new \Modules\Inventory\Http\Controllers\GuideController())->storeWithData($base+[
            'items'=>[$row+['stock_add'=>'0.1'],$row+['stock_add'=>'0.0000000000001']]
        ]);
        self::assertFalse($result['success']);
        self::assertSame($guides,Guide::count());
        self::assertSame($lines,\Modules\Inventory\Models\GuideItem::count());
        self::assertSame(0,$db->transactionLevel());
        self::assertSame($stock,$db->table('item_warehouse')->where('item_id',$item->id)->get()->toJson());
    }
}
