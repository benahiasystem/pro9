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
            $table->string('unit_type_id');
            $table->string('image')->nullable();
            $table->string('image_small')->nullable();
        });
        Storage::fake('garage-image-test');
        $this->tenant('garage_a');
        $this->employee = new User();
        $this->employee->forceFill(['id' => 7, 'active' => true, 'establishment_id' => 1]);
        $this->actingAs($this->employee);
        Route::get('pos/garage/image/{itemId}/{variant}/{token}', [PosController::class, 'garageImage'])
            ->middleware([StartWebSession::class, 'auth'])->name('tenant.pos.garage.image');
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
        self::assertSame('max-age=300, private', $response->headers->get('Cache-Control'));
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
        $response->assertOk()->assertHeader('Cache-Control', 'max-age=300, private');
        self::assertSame('image', $response->streamedContent());
        self::assertSame([], $response->headers->getCookies());

        $conditional = ['Accept' => 'application/json', 'If-None-Match' => $response->headers->get('ETag')];
        $this->get($url, $conditional)->assertStatus(304);
        DB::connection('tenant')->table('items')->where('id', 1)->update(['active' => false]);
        $this->get($url, $conditional)->assertNotFound()->assertHeader('Cache-Control', 'no-store, private');
        $this->employee->active = false;
        $this->get($url, $conditional)->assertForbidden()->assertHeader('Cache-Control', 'no-store, private');
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
