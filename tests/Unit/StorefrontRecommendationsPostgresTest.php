<?php

namespace Tests\Unit;

use App\Models\Tenant\{ConfigurationEcommerce,Item};
use App\Services\Tenant\FrequentlyBoughtTogetherService;
use App\Support\Exact\Decimal as D;
use Illuminate\Http\Request;
use Modules\Ecommerce\Http\Controllers\SocialProofController;

/** @group postgres-integration */
final class StorefrontRecommendationsPostgresTest extends \PHPUnit\Framework\TestCase
{
    public function test_public_recommendations_keep_adjacent_bigint_keys_exact_prices_and_hidden_price_policy_after_cache_warmup(): void
    {
        $fixture=new class extends FiscalEmissionSchemaTest {
            public function start(): void {parent::setUp();}
            public function stop(): void {parent::tearDown();}
        };
        try {
            $fixture->start();
            [$db,$app,$input]=(new \ReflectionMethod($fixture,'bootHkaFixture'))->invoke($fixture,false);
            \Tests\Support\PostgresTestEnvironment::assertServer($db);
            $app->instance('request',Request::create('http://recommendations.example.test/'));
            $app->register(\Illuminate\Routing\RoutingServiceProvider::class);
            $base=Item::findOrFail($input['items'][0]['item_id']);$items=[];
            foreach (['9007199254740993','9007199254740994','9007199254740995'] as $index=>$id) {
                $item=$base->replicate();$item->id=$id;$item->description='Producto temporal '.$index;$item->apply_store=true;
                $item->internal_id='TEMP-'.$id;
                $item->setExactValues(['sale_unit_price'=>$index===2 ? D::of('9007199254740993.01') : D::of(['n'=>'10049999999999999','d'=>'10000000000000000'])]);
                $item->saveQuietly();$items[]=$item;
            }
            $db->table('orders')->insert(['external_id'=>'00000000-0000-4000-8000-000000000041','customer'=>'{}','reference_payment'=>'test',
                'items'=>json_encode(array_map(fn($item)=>['id'=>(string)$item->id],$items),JSON_THROW_ON_ERROR),'total'=>'1']);
            $controller=new SocialProofController();$service=new FrequentlyBoughtTogetherService();
            $request=Request::create('/','GET',['item_ids'=>'9007199254740993']);
            $rows=$controller->frequentlyBoughtTogether($request,$service)['data'];
            self::assertCount(2,$rows);
            $byId=array_column($rows,null,'id');
            self::assertArrayHasKey('9007199254740994',$byId);
            self::assertArrayHasKey('9007199254740995',$byId);
            foreach($items as $index=>$item) {
                if($index===0)continue;
                $row=$byId[(string)$item->id];
                self::assertSame((string)$item->id,$row['id']);
                self::assertSame(D::represent(D::readRequired($item,'sale_unit_price')),$row['sale_unit_price']);
                self::assertSame(D::fraction(D::readRequired($item,'sale_unit_price')),$row['calculation_snapshot']['values']['sale_unit_price']);
                self::assertArrayNotHasKey('purchase_unit_price',$row);
                self::assertSame(['sale_unit_price'],array_keys($row['calculation_snapshot']['values']));
                self::assertSame(1,$row['times_bought_together']);
            }
            $batch=$controller->frequentlyBoughtTogether(Request::create('/','GET',['item_ids'=>'9007199254740993,9007199254740994']),$service)['data'];
            self::assertCount(1,$batch);self::assertSame('9007199254740995',$batch[0]['id']);
            self::assertSame(2,$batch[0]['times_bought_together']);
            self::assertTrue(\Illuminate\Support\Facades\Cache::has('fbt:item:9007199254740993:limit:16'));
            $config=ConfigurationEcommerce::firstOrNew();
            $config->fill(['quotation_enabled'=>true,'quotation_mode'=>'quote_only','quotation_show_prices'=>false]);$config->saveQuietly();
            self::assertSame([],$controller->frequentlyBoughtTogether($request,$service)['data']);
            self::assertSame([],$controller->frequentlyBoughtTogether(Request::create('/'),$service,'9007199254740993')['data']);
            self::assertSame(0,$db->transactionLevel());
        } finally {$fixture->stop();}
    }
}
