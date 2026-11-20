<?php
namespace Tests\Unit;

use App\Models\Tenant\{EcommerceCampaign, Item};
use App\Support\Exact\Decimal as D;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Ecommerce\Http\Controllers\DiscountCampaignController;
use Modules\Ecommerce\Services\CampaignPriceService;

/** @group postgres-integration */
final class StorefrontCampaignPostgresTest extends \PHPUnit\Framework\TestCase
{
    private $fixture;

    protected function setUp(): void
    {
        $this->fixture=new class extends FiscalEmissionSchemaTest {
            public function start(): void {parent::setUp();}
            public function stop(): void {parent::tearDown();}
        };
        $this->fixture->start();
    }

    protected function tearDown(): void
    {
        try {if ($this->fixture) $this->fixture->stop();}
        finally {parent::tearDown();}
    }

    private function catalog(string $price='0.333333333333'): array
    {
        [$db,$app,$input]=(new \ReflectionMethod($this->fixture,'bootHkaFixture'))->invoke($this->fixture,false);
        $item=Item::findOrFail($input['items'][0]['item_id']);
        $item->setExactValues(['sale_unit_price'=>$price==='0.333333333333' ? D::of(1)->dividedBy(3) : D::of($price),
            'purchase_unit_price'=>D::of('999.123456789012')]);
        $item->saveQuietly();
        Request::macro('validate',function (array $rules) {return validator($this->all(),$rules)->validate();});
        return [$db,$app,$item->fresh()];
    }

    private function campaign($db, Item $item, string $percentage): int
    {
        $id=$db->table('discount_campaigns')->insertGetId(['name'=>'Campaña temporal '.$percentage,
            'discount_type'=>'percentage','value'=>$percentage,'is_active'=>true]);
        $db->table('campaign_product')->insert(['discount_campaign_id'=>$id,'item_id'=>$item->id]);
        return $id;
    }

    public function test_public_price_projection_keeps_catalog_and_campaign_fractions_without_exposing_costs(): void
    {
        [$db,,$item]=$this->catalog();
        $id=$this->campaign($db,$item,'10.000000000002');
        $price=(new CampaignPriceService())->forItem($item);
        self::assertSame('0.333333333333',$price['base_price']);
        self::assertSame('10.000000000002',$price['real_discount_percentage']);
        $expected=D::of(1)->dividedBy(3)->multipliedBy(D::of(100)->minus('10.000000000002'))->dividedBy(100);
        self::assertTrue(D::readRequired($price,'final_price')->isEqualTo($expected));
        self::assertSame($id,$price['discount_campaign_id']);
        self::assertTrue($price['has_real_discount']);
        self::assertFalse($price['has_social_proof_price']);
        self::assertSame(D::fraction(D::of(1)->dividedBy(3)),$price['calculation_snapshot']['values']['base_price']);
        self::assertSame(['base_price','final_price','real_discount','real_discount_percentage','compare_at_price'],array_keys($price['calculation_snapshot']['values']));
        self::assertArrayNotHasKey('purchase_unit_price',$price['calculation_snapshot']['values']);
        self::assertStringNotContainsString('999.123456789012',json_encode($price,JSON_THROW_ON_ERROR));
        self::assertSame(0,$db->transactionLevel());
    }

    public function test_social_proof_only_raises_the_crossed_anchor_and_does_not_discount_the_catalog(): void
    {
        [$db,,$item]=$this->catalog('9007199254740993.01');
        $social=$db->table('ecommerce_campaigns')->insertGetId(['title'=>'Temporal','discount_type'=>'percentage',
            'discount_value'=>'10.000000000001','status'=>true,'sp_discount_price'=>true]);
        foreach (['percentage','fixed'] as $type) {
            $db->table('ecommerce_campaigns')->where('id',$social)->update(['discount_type'=>$type]);
            EcommerceCampaign::forgetActiveCache();
            $price=(new CampaignPriceService())->forItem($item);
            $base=D::of('9007199254740993.01');
            $anchor=$type==='percentage' ? $base->multipliedBy(D::of(1)->plus(D::of('10.000000000001')->dividedBy(100))) : $base->plus('10.000000000001');
            self::assertTrue(D::readRequired($price,'compare_at_price')->isEqualTo($anchor));
            self::assertSame('9007199254740993.01',$price['final_price']);
            self::assertTrue($price['has_social_proof_price']);
            self::assertFalse($price['has_real_discount']);
            self::assertTrue(D::readRequired($price,'real_discount')->isZero());
        }
    }

    public function test_campaign_cart_accepts_fractional_quantities_and_sums_before_rounding(): void
    {
        [$db,,$item]=$this->catalog();
        $this->campaign($db,$item,'10.000000000002');
        $controller=new DiscountCampaignController();
        $lines=[['item_id'=>$item->id,'subtotal'=>'999999','quantity'=>'3'],
            ['item_id'=>$item->id,'subtotal'=>'0','quantity'=>'0.000000000001']];
        $response=$controller->validateCart(Request::create('/','POST',['items'=>$lines]));
        $base=D::of(1)->dividedBy(3)->multipliedBy('3.000000000001');
        $discount=$base->multipliedBy('10.000000000002')->dividedBy(100);
        self::assertTrue(D::readRequired($response,'subtotal')->isEqualTo($base));
        self::assertTrue(D::readRequired($response,'discount')->isEqualTo($discount));
        self::assertTrue(D::readRequired($response,'total')->isEqualTo($base->minus($discount)));
        self::assertTrue(D::readRequired($response['items'][0],'final_unit_price')->isEqualTo(D::of(1)->dividedBy(3)->multipliedBy('0.89999999999998')));
        self::assertSame('0.000000000001',$response['items'][1]['quantity']);
        foreach (['0','-1','0.0000000000001','1000000000000000000','invalid'] as $quantity) {
            $lines[0]['quantity']=$quantity;
            try {
                $controller->validateCart(Request::create('/','POST',['items'=>[$lines[0]]]));
                self::fail('Debe rechazar la cantidad '.$quantity.' sin redondear.');
            } catch (ValidationException $error) {
                self::assertArrayHasKey('items.0.quantity',$error->errors());
            }
        }
        self::assertSame(0,$db->transactionLevel());
        self::assertSame(0,$db->table('orders')->count());
    }

    public function test_campaign_cart_converts_savings_to_ves_without_rounding_each_currency(): void
    {
        [$db,,$ves]=$this->catalog();
        $usd=$ves->replicate(); $usd->internal_id='TEMP-USD'; $usd->currency_type_id='USD'; $usd->saveQuietly();
        $campaign=$this->campaign($db,$ves,'10.000000000002');
        $db->table('campaign_product')->insert(['discount_campaign_id'=>$campaign,'item_id'=>$usd->id]);
        $db->table('exchange_rates')->updateOrInsert(['date'=>date('Y-m-d')],['date_original'=>date('Y-m-d'),
            'sale'=>'40.12345678','purchase'=>'40.12345678','sale_original'=>'40.12345678','purchase_original'=>'40.12345678']);
        $response=(new DiscountCampaignController())->validateCart(Request::create('/','POST',['items'=>[
            ['item_id'=>$ves->id,'subtotal'=>'0','quantity'=>'3'],
            ['item_id'=>$usd->id,'subtotal'=>'0','quantity'=>'3'],
        ]]));
        self::assertSame('VES',$response['currency_type_id']);
        self::assertTrue(D::readRequired($response,'subtotal')->isEqualTo('41.12345678'));
        self::assertTrue(D::readRequired($response,'discount')->isEqualTo(D::of('41.12345678')->multipliedBy('10.000000000002')->dividedBy(100)));
        foreach ($response['items'] as $line) {
            self::assertSame($line['item_id']===$ves->id ? 'VES' : 'USD',$line['currency_type_id']);
            self::assertTrue(D::readRequired($line,'base_unit_price')->isEqualTo(D::of(1)->dividedBy(3)));
        }
    }

    public function test_checkout_preview_exposes_only_exact_sale_document_values_from_the_server(): void
    {
        [$db,$app,$item]=$this->catalog();
        $item->apply_store=true; $item->currency_type_id='VES'; $item->sale_affectation_igv_type_id='10'; $item->saveQuietly();
        config(['view.paths'=>[resource_path('views')],'view.compiled'=>sys_get_temp_dir().'/'.$db->getDatabaseName().'/campaign-views']);
        $app->register(\Illuminate\View\ViewServiceProvider::class);
        $controller=new \Modules\Ecommerce\Http\Controllers\EcommerceController();
        config(['venezuela.tax.rate'=>'0.08']);
        $request=Request::create('/','POST',['is_guest'=>true,'delivery'=>['mode'=>'pickup'],
            'items'=>[['id'=>$item->id,'cantidad'=>'3','sale_unit_price'=>'0','calculation_snapshot'=>D::snapshot(['sale_unit_price'=>'0'])]],
            'purchase'=>['totales'=>['total_venta'=>'999999']]]);
        $preview=$controller->checkoutPreview($request);
        self::assertTrue($preview['success']);
        self::assertSame('1.00',$preview['total']);
        self::assertTrue(D::readRequired($preview['items'][0],'unit_price')->isEqualTo(D::of(1)->dividedBy(3)));
        $document=$preview['document']; $line=$document['items'][0];
        self::assertSame('VES',$document['codigo_tipo_moneda']);
        self::assertSame('8',$line['porcentaje_igv']);
        self::assertTrue(D::readRequired($line,'unit_price')->isEqualTo(D::of(1)->dividedBy(3)));
        self::assertTrue(D::readRequired($document,'total')->isEqualTo(1));
        self::assertSame([], $document['descuentos']);
        self::assertStringNotContainsString('purchase_unit_price',json_encode($preview,JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('999.123456789012',json_encode($preview,JSON_THROW_ON_ERROR));
        self::assertSame(0,$db->transactionLevel());
        self::assertSame(0,$db->table('orders')->count());
    }

    public function test_public_search_keeps_exact_sale_price_and_hides_costs_and_quote_only_prices(): void
    {
        [$db,$app,$item]=$this->catalog('9007199254740993.01');
        $request=Request::create('http://store.example.test/ecommerce/items-bar');
        $app->instance('request',$request);
        $app->register(\Illuminate\Routing\RoutingServiceProvider::class);
        $resource=new \Modules\Ecommerce\Http\Resources\ItemBarCollection(collect([$item]));
        $row=$resource->toArray($request)->first();
        self::assertSame('9007199254740993.01',$row['amount_sale_unit_price']);
        self::assertStringContainsString('9,007,199,254,740,993.01',$row['sale_unit_price']);
        self::assertArrayNotHasKey('purchase_unit_price',$row);
        self::assertStringNotContainsString('999.123456789012',json_encode($row,JSON_THROW_ON_ERROR));
        self::assertTrue(D::of($row['campaign_pricing_snapshot']['values']['final_price'])->isEqualTo('9007199254740993.01'));
        $config=\App\Models\Tenant\ConfigurationEcommerce::firstOrNew();
        $config->fill(['quotation_enabled'=>true,'quotation_mode'=>'quote_only','quotation_show_prices'=>false]);
        $config->saveQuietly();
        $hidden=(new \Modules\Ecommerce\Http\Resources\ItemBarCollection(collect([$item->fresh()])))->toArray($request)->first();
        foreach (['sale_unit_price','amount_sale_unit_price','original_price','compare_at_price','campaign_pricing_snapshot'] as $field) self::assertNull($hidden[$field],$field);
        self::assertStringNotContainsString('9007199254740993',json_encode($hidden,JSON_THROW_ON_ERROR));
        try {
            (new DiscountCampaignController())->validateCart(Request::create('/','POST',['items'=>[['item_id'=>$item->id,'quantity'=>'1','subtotal'=>'0']]]));
            self::fail('La validación de promociones no puede revelar un precio oculto.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) {self::assertSame(403,$error->getStatusCode());}
        self::assertSame(0,$db->transactionLevel());
    }

    public function test_catalog_blade_renders_exact_prices_and_structured_fractions_twice_without_global_functions(): void
    {
        [$db,$app,$item]=$this->catalog();
        $below=D::of(['n'=>'10049999999999999','d'=>'10000000000000000']);
        $item->setExactValues(['sale_unit_price'=>$below]); $item->apply_store=true; $item->saveQuietly();
        $request=Request::create('http://store.example.test/_test/catalog');
        $app->instance('request',$request);
        config(['view.paths'=>[resource_path('views')],'view.compiled'=>sys_get_temp_dir().'/'.$db->getDatabaseName().'/catalog-views']);
        $app->register(\Illuminate\View\ViewServiceProvider::class);
        $app->register(\Illuminate\Routing\RoutingServiceProvider::class);
        if (!class_exists('Route')) class_alias(\Illuminate\Support\Facades\Route::class,'Route');
        $app->make('view')->addNamespace('ecommerce',base_path('modules/Ecommerce/Resources/views'));
        $router=$app->make('router');
        $router->get('/item-partial/{id}',fn()=>'unused')->name('item_partial');
        $router->get('/_test/catalog',fn()=>view('ecommerce::layouts.partials_ecommerce.list_products',[
            'dataPaginate'=>collect([$item]),'storefront_show_prices'=>true,'preferences'=>[]]))->name('tenant.ecommerce.index');
        // RouteServiceProvider refreshes named lookups after normal application boot.
        $router->getRoutes()->refreshNameLookups();
        foreach ([1,2] as $cycle) {
            $response=$router->dispatch($request); $html=$response->getContent();
            self::assertSame(200,$response->getStatusCode());
            self::assertMatchesRegularExpression('/product-price-ecommerce[^>]*>[^<]*1\.00</',$html);
            self::assertSame(1,preg_match("/data-product='([^']+)'/",$html,$match));
            $product=json_decode(html_entity_decode($match[1],ENT_QUOTES,'UTF-8'),true,512,JSON_THROW_ON_ERROR);
            self::assertSame('1.005',$product['sale_unit_price']);
            self::assertSame(D::fraction($below),$product['campaign_pricing_snapshot']['values']['final_price']);
            self::assertStringNotContainsString('purchase_unit_price',json_encode($product,JSON_THROW_ON_ERROR));
        }
        self::assertSame(0,$db->transactionLevel());
    }
}
