<?php

namespace Tests\Unit;

use App\Support\Exact\{Decimal, LoyaltyPoints};
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
class ExactExtensionPersistenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['system','tenant'] as $connection) {
            DB::purge($connection);
            config(['database.connections.'.$connection=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'']]);
        }
        Schema::connection('system')->create('plans',function($table){
            // SQLite DECIMAL uses binary floating point. Text emulates MySQL's string transport here.
            $table->increments('id');$table->text('pricing');$table->text('sales_limit')->default('0');
            $table->json('calculation_snapshot')->nullable();$table->timestamps();
        });
        Schema::connection('tenant')->create('persons',function($table){
            $table->increments('id');$table->decimal('accumulated_points',30,12)->default(0);$table->json('calculation_snapshot')->nullable();$table->timestamps();
        });
        Schema::connection('tenant')->create('documents',function($table){
            $table->increments('id');$table->integer('customer_id');$table->string('document_type_id')->default('01');
            $table->string('state_type_id')->default('01');$table->boolean('point_system')->default(true);$table->integer('sale_note_id')->nullable();
            $table->decimal('total',30,12);$table->json('calculation_snapshot')->nullable();$table->json('point_system_data');$table->timestamps();
        });
        Schema::connection('tenant')->create('document_items',function($table){
            $table->increments('id');$table->integer('document_id');$table->integer('item_id')->nullable();$table->decimal('quantity',30,12);
            $table->json('item');$table->json('calculation_snapshot')->nullable();
        });
        Schema::connection('tenant')->create('items',function($table){
            $table->increments('id');$table->text('quantity_of_points');$table->json('calculation_snapshot')->nullable();
        });
        Schema::connection('system')->create('payment_orders',function($table){
            $table->increments('id');$table->string('uuid')->nullable();$table->integer('client_id');
            $table->integer('order_state_id');$table->text('amount');$table->json('calculation_snapshot')->nullable();
            $table->string('order')->nullable();$table->date('date_of_due')->nullable();
            $table->string('description')->nullable();$table->string('created_by')->nullable();
        });
        Schema::connection('tenant')->create('item_lots_group',function($table){
            $table->increments('id');$table->text('quantity');$table->json('calculation_snapshot')->nullable();$table->timestamps();
        });
        Schema::connection('tenant')->create('document_fee',function($table){
            $table->increments('id');$table->integer('document_id');$table->date('date');$table->string('currency_type_id');
            $table->text('amount');$table->json('calculation_snapshot')->nullable();$table->timestamps();
        });
    }

    public function test_central_models_keep_fractions_and_the_original_twenty_integer_digit_range(): void
    {
        $plan=new \App\Models\System\Plan(['pricing'=>'0','sales_limit'=>'99999999999999999999']);
        $plan->pricing=Decimal::of(1)->dividedBy(3);
        $plan->save();$plan=$plan->fresh();
        self::assertSame('0.333333333333',$plan->pricing);
        self::assertSame('1',Decimal::represent($plan->exactValue('pricing')->multipliedBy(3)));
        self::assertSame('99999999999999999999',$plan->sales_limit);
        $plan->pricing='0.4';
        self::assertSame('1.2',Decimal::represent($plan->exactValue('pricing')->multipliedBy(3)));
        $plan->save();
        self::assertSame('0.4',Decimal::represent($plan->fresh()->exactValue('pricing')));
        self::assertFalse(Schema::connection('tenant')->hasTable('plans'));
    }

    public function test_loyalty_awards_periodic_points_once_and_reverses_exactly_once(): void
    {
        DB::connection('tenant')->table('persons')->insert(['id'=>1,'accumulated_points'=>'0']);
        DB::connection('tenant')->table('documents')->insert(['id'=>1,'customer_id'=>1,'total'=>'1',
            'point_system_data'=>json_encode(['point_system_sale_amount'=>'3','quantity_of_points'=>'1','round_points_of_sale'=>true]),
            'calculation_snapshot'=>json_encode(Decimal::snapshot(['total'=>'1']))]);
        DB::connection('tenant')->table('document_items')->insert(['document_id'=>1,'quantity'=>'1','item'=>'{}']);
        $document=ExactLoyaltyDocument::findOrFail(1);
        LoyaltyPoints::synchronize($document);
        self::assertSame(['n'=>'1','d'=>'3'],Decimal::fraction(\App\Models\Tenant\Person::withoutEagerLoads()->findOrFail(1)->exactValue('accumulated_points')));
        LoyaltyPoints::synchronize($document);
        self::assertSame(['n'=>'1','d'=>'3'],Decimal::fraction(\App\Models\Tenant\Person::withoutEagerLoads()->findOrFail(1)->exactValue('accumulated_points')));
        LoyaltyPoints::synchronize($document,true);
        LoyaltyPoints::synchronize($document,true);
        self::assertSame('0',Decimal::represent(\App\Models\Tenant\Person::withoutEagerLoads()->findOrFail(1)->exactValue('accumulated_points')));
    }

    public function test_snapshot_cache_invalidates_and_sum_keeps_small_denominators(): void
    {
        $record=new \Illuminate\Support\Fluent(['calculation_snapshot'=>json_encode(Decimal::snapshot(['amount'=>Decimal::of(1)->dividedBy(3)]))]);
        self::assertSame(['n'=>'1','d'=>'3'],Decimal::fraction(Decimal::read($record,'amount')));
        $record->calculation_snapshot=json_encode(Decimal::snapshot(['amount'=>'2']));
        self::assertSame('2',Decimal::represent(Decimal::read($record,'amount')));
        self::assertSame(['n'=>'10000','d'=>'3'],Decimal::fraction(Decimal::sum(array_fill(0,10000,Decimal::of(1)->dividedBy(3)))));
    }

    public function test_central_totals_read_fractions_in_batches_and_preserve_client_groups(): void
    {
        for ($i=0;$i<501;$i++) {
            \App\Models\System\PaymentOrder::create(['client_id'=>$i===500 ? 2 : 1,'order_state_id'=>1,'amount'=>Decimal::of(1)->dividedBy(3)]);
        }
        $connection=DB::connection('system');$connection->enableQueryLog();$connection->flushQueryLog();
        self::assertSame('167',\App\Models\System\PaymentOrder::exactPaymentsTotals()->first()->total);
        self::assertCount(2,$connection->getQueryLog(),'Dos lotes para 501 filas; ninguna consulta por orden.');
        $connection->disableQueryLog();
        $totals=\App\Models\System\PaymentOrder::exactPaymentsTotals(null,true)->keyBy('client_id');
        self::assertSame(['n'=>'500','d'=>'3'],$totals[1]->calculation_snapshot['values']['total']);
        self::assertSame(['n'=>'1','d'=>'3'],$totals[2]->calculation_snapshot['values']['total']);
        self::assertSame(['n'=>'1','d'=>'3'],\App\Models\System\PaymentOrder::exactPaymentsTotals(2)->first()->calculation_snapshot['values']['total']);
    }

    public function test_plan_order_creation_copies_the_fraction_instead_of_the_visible_price(): void
    {
        $plan=new \App\Models\System\Plan();$plan->pricing=Decimal::of(1)->dividedBy(3);
        $client=new \App\Models\System\Client();$client->id=1;$client->setRelation('plan',$plan);
        $order=$client->createPayemtnOrder()->fresh();
        self::assertSame(['n'=>'1','d'=>'3'],Decimal::fraction($order->exactValue('amount')));
        $client->price=Decimal::of(2)->dividedBy(7);
        self::assertSame(['n'=>'2','d'=>'7'],Decimal::fraction($client->createPayemtnOrder()->fresh()->exactValue('amount')));
    }

    public function test_exact_overflow_is_rejected_even_when_its_projection_fits(): void
    {
        $plan=new \App\Models\System\Plan(['pricing'=>'0']);
        $plan->pricing=Decimal::of('999999999999999999.999999999999')->plus(Decimal::of(1)->dividedBy('1000000000000000000000000'));
        try {$plan->save();self::fail('El resultado exacto fuera de rango debe rechazarse.');}
        catch (\Illuminate\Validation\ValidationException $error) {self::assertArrayHasKey('pricing',$error->errors());}
        self::assertSame(0,\App\Models\System\Plan::count());
    }

    public function test_fractional_redemption_uses_catalog_points_and_reverts_without_drift(): void
    {
        $third=Decimal::of(1)->dividedBy(3);
        DB::connection('tenant')->table('persons')->insert(['id'=>1,'accumulated_points'=>Decimal::represent($third),
            'calculation_snapshot'=>json_encode(Decimal::snapshot(['accumulated_points'=>$third]))]);
        DB::connection('tenant')->table('items')->insert(['id'=>1,'quantity_of_points'=>'0.666666666667',
            'calculation_snapshot'=>json_encode(Decimal::snapshot(['quantity_of_points'=>$third->multipliedBy(2)]))]);
        DB::connection('tenant')->table('documents')->insert(['id'=>1,'customer_id'=>1,'total'=>'0',
            'point_system_data'=>json_encode(['point_system_sale_amount'=>'3','quantity_of_points'=>'1']),
            'calculation_snapshot'=>json_encode(Decimal::snapshot(['total'=>'0']))]);
        DB::connection('tenant')->table('document_items')->insert(['document_id'=>1,'item_id'=>1,'quantity'=>'0.5',
            'item'=>json_encode(['exchanged_for_points'=>true,'used_points_for_exchange'=>'0'])]);
        $document=ExactLoyaltyDocument::findOrFail(1);
        LoyaltyPoints::synchronize($document);
        self::assertSame('0',Decimal::represent(\App\Models\Tenant\Person::withoutEagerLoads()->findOrFail(1)->exactValue('accumulated_points')));
        // A later catalog edit cannot change an already accepted redemption.
        DB::connection('tenant')->table('items')->where('id',1)->update(['quantity_of_points'=>'999','calculation_snapshot'=>null]);
        LoyaltyPoints::synchronize($document);
        self::assertSame(['n'=>'1','d'=>'3'],$document->calculation_snapshot['loyalty']['spent']);
        LoyaltyPoints::synchronize($document,true);
        self::assertSame(['n'=>'1','d'=>'3'],Decimal::fraction(\App\Models\Tenant\Person::withoutEagerLoads()->findOrFail(1)->exactValue('accumulated_points')));
    }

    public function test_redemption_rejects_future_points_and_rolls_back(): void
    {
        DB::connection('tenant')->table('persons')->insert(['id'=>1,'accumulated_points'=>'0']);
        DB::connection('tenant')->table('items')->insert(['id'=>1,'quantity_of_points'=>'1']);
        DB::connection('tenant')->table('documents')->insert(['id'=>1,'customer_id'=>1,'total'=>'100',
            'point_system_data'=>json_encode(['point_system_sale_amount'=>'1','quantity_of_points'=>'1']),
            'calculation_snapshot'=>json_encode(Decimal::snapshot(['total'=>'100']))]);
        DB::connection('tenant')->table('document_items')->insert(['document_id'=>1,'item_id'=>1,'quantity'=>'1',
            'item'=>json_encode(['exchanged_for_points'=>true])]);
        try {LoyaltyPoints::synchronize(ExactLoyaltyDocument::findOrFail(1));self::fail('El canje no puede gastar puntos futuros.');}
        catch (\Illuminate\Validation\ValidationException $error) {self::assertArrayHasKey('items',$error->errors());}
        self::assertSame('0',Decimal::represent(\App\Models\Tenant\Person::withoutEagerLoads()->findOrFail(1)->exactValue('accumulated_points')));
        self::assertArrayNotHasKey('loyalty',ExactLoyaltyDocument::findOrFail(1)->calculation_snapshot);
    }

    public function test_installment_persistence_keeps_both_equal_shares_and_the_complete_cent_total(): void
    {
        DB::connection('tenant')->table('documents')->insert(['id'=>1,'customer_id'=>1,'total'=>'100',
            'point_system_data'=>'{}','calculation_snapshot'=>json_encode(Decimal::snapshot(['total'=>'100']))]);
        $owner=ExactLoyaltyDocument::findOrFail(1);
        $amounts=Decimal::allocate('100',['1','1','1']);
        $rows=array_map(fn($amount)=>['amount'=>$amount,'allocation_weight'=>'1','date'=>'2026-11-01','currency_type_id'=>'VES'],$amounts);
        \App\Support\Exact\Installments::save($owner,$rows);
        $fees=$owner->fee()->get();
        self::assertSame('100',Decimal::represent(\App\Support\Exact\Aggregate::records($fees,'amount')));
        foreach($fees as $fee) self::assertSame(['n'=>'100','d'=>'3'],Decimal::fraction($fee->exactValue('scheduled_exact')));
        self::assertSame('0',Decimal::represent(\App\Support\Exact\Aggregate::records($fees,'settlement_adjustment')));
    }

    public function test_lot_reversal_preserves_fractional_presentations(): void
    {
        $third=Decimal::of(1)->dividedBy(3);
        $lot=new \Modules\Item\Models\ItemLotsGroup();$lot->quantity=$third;$lot->save();
        $line=new \App\Models\Tenant\DocumentItem(['item'=>[
            'presentation'=>['quantity_unit'=>'0.333333333333','calculation_snapshot'=>Decimal::snapshot(['quantity_unit'=>$third])],
            'IdLoteSelected'=>[['id'=>$lot->id,'compromise_quantity'=>'2']],
        ]]);
        $service=new class {use \Modules\Inventory\Traits\InventoryTrait; public function restore($line){$this->deleteItemLots($line);}};
        $service->restore($line);
        self::assertSame('1',Decimal::represent($lot->fresh()->exactValue('quantity')));
    }

    public function test_recipe_capacity_uses_exact_pivot_quantities_before_discrete_unit_projection(): void
    {
        $supply=new \Modules\Restaurant\Models\Supply(['stock'=>'1']);
        $required=Decimal::of(1)->plus(Decimal::of(1)->dividedBy('1000000000000000000000000'));
        $supply->setRelation('pivot',new \Illuminate\Database\Eloquent\Relations\Pivot([
            'quantity'=>'1','calculation_snapshot'=>json_encode(Decimal::snapshot(['quantity'=>$required])),
        ]));
        $item=new \App\Models\Tenant\Item();$item->setRelation('restaurantSupplies',collect([$supply]));
        self::assertSame('0',$item->getRestaurantStock());
        $supply->pivot->calculation_snapshot=json_encode(Decimal::snapshot(['quantity'=>Decimal::of(1)->dividedBy(30)]));
        self::assertSame('30',$item->getRestaurantStock());
        self::assertSame('1',$supply->stock);
    }
}

class ExactLoyaltyDocument extends \App\Models\Tenant\Document
{
    protected $table='documents';
    protected $with=[];
    public function items(){return $this->hasMany(\App\Models\Tenant\DocumentItem::class,'document_id');}
    public function fee(){return $this->hasMany(\App\Models\Tenant\DocumentFee::class,'document_id');}
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
