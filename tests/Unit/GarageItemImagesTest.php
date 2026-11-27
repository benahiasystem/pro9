<?php

namespace Tests\Unit;

use App\Http\Controllers\Tenant\PosController;
use App\Http\Middleware\EnsureTenantConnection;
use App\Http\Middleware\StartWebSession;
use App\Models\Storage\StorageObject;
use App\Models\Tenant\Item;
use App\Models\Tenant\User;
use App\Services\Storage\GarageItemImages;
use App\Tenancy\Contracts\Tenant;
use App\Tenancy\Models\Website;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

final class GarageItemImagesTest extends TestCase
{
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('La prueba requiere SQLite en memoria.');
        }
        config([
            'app.key' => 'garage-image-test-key',
            'tenant_storage.disk' => 'garage-image-test',
            'database.connections.system' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'database.connections.tenant' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        Schema::connection('tenant')->create('storage_objects', function (Blueprint $table) {
            $table->string('path')->primary();
            $table->string('object_key');
            $table->string('mime');
            $table->string('version');
            $table->integer('size');
            $table->timestamps();
        });
        Schema::connection('tenant')->create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->integer('establishment_id');
        });
        Schema::connection('tenant')->create('items', function (Blueprint $table) {
            $table->id();
            $table->boolean('active');
            $table->boolean('apply_store')->default(false);
            $table->boolean('apply_restaurant')->default(false);
            $table->string('unit_type_id');
            $table->string('image')->nullable();
            $table->string('image_small')->nullable();
            $table->string('image_medium')->nullable();
        });
        Storage::fake('garage-image-test');
        $this->tenant('garage_a');
        $this->employee = new User();
        $this->employee->forceFill(['id' => 7, 'active' => true, 'establishment_id' => 1]);
        $this->actingAs($this->employee);
        Route::get('pos/garage/image/{itemId}/{variant}/{token}', [PosController::class, 'garageImage'])
            ->middleware([StartWebSession::class, 'auth'])->name('tenant.pos.garage.image');
        Route::get('media/item/{itemId}/{variant}/{token}', [\App\Http\Controllers\Tenant\PrivateMediaController::class, 'item'])
            ->middleware([StartWebSession::class, 'auth'])->name('tenant.media.item');
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_chooses_catalogued_images_and_retains_the_original_as_fallback(): void
    {
        $this->object('large.jpg');
        $this->object('small.jpg');
        $this->object('historical.jpg');
        $service = app(GarageItemImages::class);
        $items = [
            $this->item(1, 'large.jpg', 'small.jpg'),
            $this->item(2, 'historical.jpg', 'missing.jpg'),
            $this->item(3, 'missing.jpg', null),
        ];
        $request = $this->imageRequest('a');
        $urls = $service->urls($request, $items);

        self::assertStringContainsString('/1/small/', $urls[1]['garage_thumbnail_url']);
        self::assertStringContainsString('/1/original/', $urls[1]['garage_original_url']);
        self::assertStringContainsString('/2/original/', $urls[2]['garage_thumbnail_url']);
        self::assertNull($urls[2]['garage_original_url']);
        self::assertArrayNotHasKey(3, $urls);
        self::assertSame($urls, $service->urls($request, $items));
        self::assertNotSame($urls, $service->urls($this->imageRequest('b'), $items));

        $this->employee->active = false;
        self::assertSame([], $service->urls($request, $items));
    }

    public function test_reads_the_authorized_version_and_revalidates_without_reading_the_file(): void
    {
        $object = $this->object('small.jpg');
        $disk = Storage::disk('garage-image-test');
        $file = 'garage_a/'.$object->object_key;
        $disk->put($file, 'image');
        $item = $this->item(1, 'large.jpg', 'small.jpg');
        $request = $this->imageRequest('a');
        $service = app(GarageItemImages::class);
        $url = $service->urls($request, [$item])[1]['garage_thumbnail_url'];
        $token = basename($url);
        $response = $service->response($request, $item, 'small', $token);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('max-age=1800, private', $response->headers->get('Cache-Control'));
        self::assertSame('no-store', $response->headers->get('CDN-Cache-Control'));
        self::assertSame('"version-one"', $response->getEtag());
        ob_start();
        $response->sendContent();
        self::assertSame('image', ob_get_clean());

        $disk->delete($file);
        $request->headers->set('If-None-Match', $response->getEtag());
        self::assertSame(304, $service->response($request, $item, 'small', $token)->getStatusCode());

        $object->update(['version' => 'version-two']);
        self::assertNotSame($url, $service->urls($request, [$item])[1]['garage_thumbnail_url']);
        $this->expectException(NotFoundHttpException::class);
        $service->response($request, $item, 'small', $token);
    }

    /** @dataProvider changedContext */
    public function test_rejects_conditional_requests_from_another_context(string $change): void
    {
        $this->object('small.jpg');
        $service = app(GarageItemImages::class);
        $item = $this->item(1, null, 'small.jpg');
        $request = $this->imageRequest('a');
        $token = basename($service->urls($request, [$item])[1]['garage_thumbnail_url']);
        if ($change === 'tenant') {
            $this->tenant('garage_b');
        } else {
            $request = $this->imageRequest('b');
        }
        $request->headers->set('If-None-Match', '"version-one"');
        $this->expectException(NotFoundHttpException::class);
        $service->response($request, $item, 'small', $token);
    }

    public static function changedContext(): array
    {
        return [['tenant'], ['session']];
    }

    public function test_http_delivery_does_not_refresh_session_and_revoked_access_cannot_revalidate(): void
    {
        $this->withoutMiddleware(EnsureTenantConnection::class);
        $object = $this->object('small.jpg');
        Storage::disk('garage-image-test')->put('garage_a/'.$object->object_key, 'image');
        DB::connection('tenant')->table('items')->insert([
            'id' => 1, 'active' => true, 'unit_type_id' => 'UND', 'image_small' => 'small.jpg',
        ]);
        $request = $this->imageRequest('a');
        $url = app(GarageItemImages::class)->urls($request, [$this->item(1, null, 'small.jpg')])[1]['garage_thumbnail_url'];
        $this->withUnencryptedCookie($request->session()->getName(), $request->session()->getId());
        $response = $this->get($url);
        $response->assertOk()->assertHeader('Cache-Control', 'max-age=1800, private');
        self::assertSame('image', $response->streamedContent());
        self::assertSame([], $response->headers->getCookies());

        $conditional = ['Accept' => 'application/json', 'If-None-Match' => $response->headers->get('ETag')];
        $this->get($url, $conditional)->assertStatus(304);
        DB::connection('tenant')->table('items')->where('id', 1)->update(['active' => false]);
        $this->get($url, $conditional)->assertNotFound()->assertHeader('Cache-Control', 'no-store, private');
        $this->employee->active = false;
        $this->get($url, $conditional)->assertForbidden()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_employee_item_variants_use_versioned_urls_and_head_without_reading_storage(): void
    {
        $this->withoutMiddleware(EnsureTenantConnection::class);
        $object = $this->object('medium.jpg');
        $item = $this->item(2, null, null)->forceFill(['image_medium'=>'medium.jpg']);
        DB::connection('tenant')->table('items')->insert([
            'id'=>2, 'active'=>true, 'unit_type_id'=>'SERV', 'image_medium'=>'medium.jpg',
        ]);
        $request = $this->imageRequest('a');
        $service = app(\App\Services\Storage\ItemImageDelivery::class);
        $url = $service->variantUrls($request, [$item])[2]['medium'];
        $this->withUnencryptedCookie($request->session()->getName(), $request->session()->getId());
        $this->head($url)->assertOk()->assertHeader('Cache-Control', 'max-age=1800, private')
            ->assertHeader('CDN-Cache-Control', 'no-store')->assertHeader('ETag', '"version-one"');
        $this->get($url, ['If-None-Match'=>'"version-one"'])->assertStatus(304);
        $object->update(['version'=>'version-two']);
        self::assertNotSame($url, $service->variantUrls($request, [$item])[2]['medium']);
        $this->head($url)->assertNotFound()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_public_catalogs_recheck_publication_before_revalidation(): void
    {
        $this->withoutMiddleware(EnsureTenantConnection::class);
        auth()->forgetGuards();
        $schema = Schema::connection('tenant');
        $schema->create('users', fn (Blueprint $t) => $t->id());
        $schema->create('modules', function (Blueprint $t) {
            $t->id();
            $t->string('value');
        });
        $schema->create('module_user', function (Blueprint $t) {
            $t->integer('user_id');
            $t->integer('module_id');
        });
        $schema->create('configurations', function (Blueprint $t) {
            $t->id();
            $t->boolean('locked_tenant');
        });
        $schema->create('item_images', function (Blueprint $t) {
            $t->integer('item_id');
            $t->string('image');
        });
        $db = DB::connection('tenant');
        $db->table('users')->insert(['id'=>1]);
        $db->table('modules')->insert(['id'=>1, 'value'=>'ecommerce']);
        $db->table('module_user')->insert(['module_id'=>1, 'user_id'=>1]);
        $db->table('configurations')->insert(['id'=>1, 'locked_tenant'=>false]);
        $db->table('items')->insert([
            'id'=>1, 'active'=>true, 'unit_type_id'=>'UND', 'image'=>'original.jpg',
            'apply_store'=>true, 'apply_restaurant'=>false,
        ]);
        $this->object('original.jpg');
        $controller = \App\Http\Controllers\Tenant\PrivateMediaController::class;
        Route::get('media/images/{folder}/{filename}', [$controller, 'image'])
            ->middleware(StartWebSession::class)->name('tenant.media.image');
        Route::get('media/restaurant/{folder}/{filename}', [$controller, 'restaurantImage'])
            ->middleware(StartWebSession::class)->name('tenant.media.restaurantImage');
        Route::getRoutes()->refreshNameLookups();
        $service = app(\App\Services\Storage\VersionedImage::class);
        $items = [$this->item(1, 'original.jpg', null)];
        $url = $service->catalogUrls($items)['original.jpg'];
        $restaurant = $service->catalogUrls($items, true)['original.jpg'];

        $this->get('/media/images/items/original.jpg')->assertRedirect($url)->assertHeader('Cache-Control', 'no-store, private');
        $this->head($url)->assertOk()->assertHeader('Cache-Control', 'max-age=1800, private');
        $conditional = ['If-None-Match'=>'"version-one"'];
        $this->get($restaurant, $conditional)->assertNotFound();
        $db->table('items')->update(['apply_store'=>false, 'apply_restaurant'=>true]);
        $this->get($url, $conditional)->assertNotFound()->assertHeader('Cache-Control', 'no-store, private');
        $this->get($restaurant, $conditional)->assertStatus(304);
    }

    public function test_json_resolves_variants_by_record_when_products_share_a_filename(): void
    {
        $this->object('shared.jpg');
        $this->object('small.jpg');
        foreach ([1, 2] as $id) {
            DB::connection('tenant')->table('items')->insert([
                'id'=>$id, 'active'=>true, 'unit_type_id'=>'UND', 'image'=>'shared.jpg',
                'image_small'=>$id === 2 ? 'small.jpg' : null,
            ]);
        }
        $request = $this->imageRequest('a');
        $response = app(\App\Http\Middleware\RefreshPrivateMedia::class)->handle($request, fn () => response()->json([
            'items'=>[
                ['id'=>1, 'image_url'=>'/media/images/items/shared.jpg'],
                ['id'=>2, 'image_url'=>'/media/images/items/shared.jpg'],
            ],
        ]));
        [$first, $second] = $response->getData(true)['items'];
        self::assertStringContainsString('/1/original/', $first['image_url']);
        self::assertArrayNotHasKey('image_url_small', $first);
        self::assertStringContainsString('/2/original/', $second['image_url']);
        self::assertStringContainsString('/2/small/', $second['image_url_small']);
    }

    public function test_blade_catalog_emits_src_only_for_the_initial_images(): void
    {
        $directory = sys_get_temp_dir().'/sab-catalog-view-'.bin2hex(random_bytes(8));
        mkdir($directory);
        config(['view.compiled'=>$directory]);
        $this->app->forgetInstance('blade.compiler');
        try {
            foreach ([0, 3, 4] as $index) {
                $html = \Illuminate\Support\Facades\Blade::render(
                    '<x-catalog-image :small="$small" :original="$original" :eager="$index < 4" alt="Foto" />',
                    ['small'=>'/small.jpg', 'original'=>'/original.jpg', 'index'=>$index]
                );
                $dom = new \DOMDocument();
                $dom->loadHTML($html);
                $image = $dom->getElementsByTagName('img')->item(0);
                self::assertSame('/small.jpg', $image->getAttribute('data-catalog-src'));
                self::assertSame($index < 4, $image->hasAttribute('src'));
            }
        } finally {
            (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($directory);
        }
    }

    private function tenant(string $uuid): void
    {
        $website = new Website();
        $website->uuid = $uuid;
        $this->app->instance(Tenant::class, $website);
    }

    private function object(string $name): StorageObject
    {
        return StorageObject::create([
            'path' => 'uploads/items/'.$name, 'object_key' => 'uploads/items/versioned-'.$name,
            'mime' => 'image/jpeg', 'size' => 5, 'version' => 'version-one',
        ]);
    }

    private function item(int $id, ?string $original, ?string $small): Item
    {
        return (new Item())->forceFill(['id' => $id, 'image' => $original, 'image_small' => $small]);
    }

    private function imageRequest(string $session): Request
    {
        $request = Request::create('/pos/items?garage_thumbnails=1');
        $store = app('session')->driver();
        $store->setId(str_repeat($session, 40));
        $request->setLaravelSession($store);
        $request->setUserResolver(fn () => $this->employee);

        return $request;
    }
}
