<?php

namespace Tests\Unit;

use App\Support\Exact\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Marketplace\Models\{Category, Item, Store};
use Modules\Marketplace\Services\{CatalogCounters, MarketplaceCache, Settings, StoreSyncService};
use Modules\Marketplace\Support\PublicPresenter;

/** @group postgres-integration */
final class MarketplacePostgresTest extends \PHPUnit\Framework\TestCase
{
    private ?PostgresImplementationTest $fixture = null;

    protected function setUp(): void
    {
        $this->fixture = new PostgresImplementationTest();
        (new \ReflectionMethod($this->fixture, 'setUp'))->invoke($this->fixture);
        (new \ReflectionMethod($this->fixture, 'install'))->invoke($this->fixture);
        app()->instance('marketplace.cache', new \Illuminate\Cache\Repository(new \Illuminate\Cache\ArrayStore()));
        Settings::forget();
    }

    protected function tearDown(): void
    {
        try {
            Settings::forget();
            if ($this->fixture) (new \ReflectionMethod($this->fixture, 'tearDown'))->invoke($this->fixture);
        } finally { parent::tearDown(); }
    }

    public function test_sync_preserves_twelve_decimals_public_contract_and_moderation_on_postgresql(): void
    {
        $payload = ['store' => ['external_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Catálogo temporal', 'whatsapp' => '584121234567', 'show_prices' => true],
            'items' => [
                ['external_id' => '12', 'name' => 'Café temporal', 'price' => '12345678.123456789012', 'category' => 'Herramientas Eléctricas'],
                ['external_id' => 'tiny', 'name' => 'Pequeño', 'price' => '0.000000000001', 'category' => 'herramientas electricas'],
                ['external_id' => 'free', 'name' => 'Gratis', 'price' => '0'],
                ['external_id' => 'unknown', 'name' => 'Consultar', 'price' => null],
                ['external_id' => 'max', 'name' => 'Máximo', 'price' => '99999999.99'],
            ]];
        $service = app(StoreSyncService::class);
        $store = $service->sync($payload, '192.0.2.17');
        self::assertSame(Store::STATUS_PENDING, $store->status);
        self::assertSame(0, Item::count());
        self::assertSame(1, Category::count());
        $column = DB::connection('system')->selectOne("SELECT numeric_precision,numeric_scale FROM information_schema.columns WHERE table_schema='public' AND table_name='marketplace_items' AND column_name='price'");
        self::assertSame(30, $column->numeric_precision);
        self::assertSame(12, $column->numeric_scale);
        $item = Item::unscoped()->where('external_id', '12')->firstOrFail();
        self::assertSame('12345678.123456789012', $item->price);
        self::assertSame(Decimal::fraction('12345678.123456789012'), $item->calculation_snapshot['values']['price']);
        self::assertArrayNotHasKey('calculation_snapshot', $item->toArray());
        self::assertSame('0.000000000001', Item::unscoped()->where('external_id', 'tiny')->value('price'));
        self::assertNull(Item::unscoped()->where('external_id', 'unknown')->value('price'));

        $store->update(['status' => Store::STATUS_APPROVED]);
        CatalogCounters::refreshAll($store->id);
        self::assertSame(5, Item::count());
        self::assertSame(2, Category::firstOrFail()->items_count);
        $item->refresh()->load(['store', 'category']);
        $public = PublicPresenter::item($item);
        self::assertSame(12345678.12, $public['price']);
        self::assertArrayNotHasKey('whatsapp', $public['store']);
        self::assertArrayNotHasKey('calculation_snapshot', $public);
        $store->update(['show_prices' => false]);
        $item->unsetRelation('store');
        self::assertNull(PublicPresenter::item($item)['price']);
        self::assertSame('12345678.123456789012', $item->fresh()->price);

        $store->update(['show_prices' => true]);
        foreach (['tiny'=>0.0,'free'=>0.0,'unknown'=>null,'max'=>99999999.99] as $external=>$expected) {
            $publicItem=Item::unscoped()->where('external_id',$external)->firstOrFail()->load(['store','category']);
            self::assertSame($expected,PublicPresenter::item($publicItem)['price']);
            self::assertArrayNotHasKey('calculation_snapshot',PublicPresenter::item($publicItem));
        }
        foreach (['-0.000000000001','99999999.990000000001'] as $invalidStoredPrice) {
            try {
                DB::connection('system')->table('marketplace_items')->where('id',$item->id)->update(['price'=>$invalidStoredPrice]);
                self::fail('El motor debe proteger el rango comercial incluso fuera del formulario.');
            } catch (\Illuminate\Database\QueryException $error) { self::assertSame('23514',$error->getCode()); }
            self::assertSame('12345678.123456789012',$item->fresh()->price);
        }

        $blocked = Item::unscoped()->where('external_id', 'tiny')->firstOrFail();
        $blocked->update(['status' => Item::STATUS_BLOCKED, 'blocked_at' => now(), 'blocked_reason' => 'Prueba de moderación']);
        $version = MarketplaceCache::version();
        $payload['items'][0]['price'] = '1.005';
        $payload['items'] = [$payload['items'][0]];
        $service->sync($payload);
        self::assertGreaterThan($version, MarketplaceCache::version());
        self::assertSame(Item::STATUS_BLOCKED, $blocked->fresh()->status);
        self::assertSame('Prueba de moderación', $blocked->fresh()->blocked_reason);
        self::assertSame(Item::STATUS_INACTIVE, Item::unscoped()->where('external_id', 'free')->value('status'));
        self::assertSame('1.005', $item->fresh()->price);
        self::assertSame(1.01, PublicPresenter::item($item->fresh()->load(['store', 'category']))['price']);

        // All three correlated UPDATE statements must execute on the actual engine.
        DB::connection('system')->table('marketplace_recommendations')->insert([
            'store_id' => $store->id, 'item_id' => $item->id, 'visitor_id' => (string) \Illuminate\Support\Str::uuid(),
        ]);
        CatalogCounters::refreshRecommendations($item);
        self::assertSame(1, $item->fresh()->recommendations_count);
        self::assertSame(1, $store->fresh()->recommendations_count);
        $request = \Illuminate\Http\Request::create('/marketplace/feed', 'GET', ['q' => 'CAFE']);
        $feed = app(\Modules\Marketplace\Http\Controllers\Web\FeedController::class)->index($request)->getData(true);
        self::assertSame(1, $feed['products']['total']);
        self::assertSame(1.01, $feed['products']['data'][0]['price']);
        // The protected moderation field is deliberately not mass assignable.
        $store->refresh();
        $store->hidden_at = now();
        $store->save();
        CatalogCounters::refreshAll($store->id);
        self::assertSame(0, Item::count());
        self::assertSame(0, Category::firstOrFail()->items_count);

        $version = MarketplaceCache::version();
        $before = $item->fresh()->calculation_snapshot;
        $invalid = $payload;
        $invalid['store']['name'] = 'No debe persistir';
        $invalid['items'][] = ['external_id' => 'invalid', 'name' => 'Rechazado', 'price' => '0.0000000000001', 'category' => 'No crear'];
        try { $service->sync($invalid); self::fail('No debe redondear un precio de trece decimales.'); }
        catch (ValidationException $error) { self::assertArrayHasKey('items.1.price', $error->errors()); }
        self::assertSame('Catálogo temporal', $store->fresh()->name);
        self::assertSame($before, $item->fresh()->calculation_snapshot);
        self::assertSame($version, MarketplaceCache::version());
        self::assertSame(1, Category::count());
        self::assertNotNull($store->fresh()->hidden_at);
        $service->sync($payload);
        self::assertNull($store->fresh()->hidden_at);
        self::assertSame(1, Item::count());

        $rules = (new \Modules\Marketplace\Http\Requests\SyncRequest())->rules();
        foreach (['-0.000000000001', '99999999.990000000001', '100000000', true, ['n' => '1', 'd' => '3']] as $invalidPrice) {
            $candidate = $payload; $candidate['items'][0]['price'] = $invalidPrice;
            self::assertTrue(\Illuminate\Support\Facades\Validator::make($candidate, $rules)->fails());
        }
        self::assertFalse(\Illuminate\Support\Facades\Validator::make($payload, $rules)->fails());
    }
}
