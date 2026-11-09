<?php

namespace Tests\Feature;

use App\Http\Controllers\System\ConfigurationController;
use App\Models\System\Configuration;
use App\Services\System\MozoConfigurationService;
use App\Services\System\MozoLogoService;
use App\Services\System\VendeyaConfigurationService;
use App\Services\System\VendeyaLogoService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemConfigurationImageUploadTest extends TestCase
{
    private array $temporaryUploads = [];
    private string $diskRoot;

    public function createApplication()
    {
        $app = require __DIR__ . '/../../bootstrap/app.php';
        // Isolate both connections before any application provider can query them.
        $app->afterBootstrapping(LoadConfiguration::class, static function ($app): void {
            $connection = ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''];
            $app['config']->set([
                'app.env' => 'testing',
                'app.url' => 'http://localhost',
                'database.default' => 'system',
                'database.connections.system' => $connection,
                'database.connections.tenant' => $connection,
                'tenancy.db.system-connection-name' => 'system',
                'cache.default' => 'array',
                'session.driver' => 'array',
                'queue.default' => 'sync',
                'mail.default' => 'array',
            ]);
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
        Http::preventStrayRequests();
        Schema::connection('system')->create('configurations', function ($table): void {
            $table->increments('id');
            $table->text('login')->nullable();
            $table->string('tenant_image_ads')->nullable();
            $table->timestamps();
        });
        Configuration::create([
            'login' => (object) ['image' => 'old.png', 'logo' => 'old-logo.png'],
            'tenant_image_ads' => 'old-ad.png',
        ]);
        $disk = 'configuration-image-test-' . bin2hex(random_bytes(6));
        config(['filesystems.default' => $disk]);
        Storage::fake($disk);
        $this->diskRoot = Storage::path('');
        $this->mock(MozoConfigurationService::class)->shouldReceive('get')->andReturn(['useSystemLogo' => false]);
        $this->mock(VendeyaConfigurationService::class)->shouldReceive('get')->andReturn(['useSystemLogo' => false]);
        Route::post('/test-system-image/bg', [ConfigurationController::class, 'storeBgLogin']);
        Route::post('/test-system-image/ads', [ConfigurationController::class, 'uploadTenantAds']);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryUploads as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        if (isset($this->diskRoot)) {
            $this->app['files']->deleteDirectory($this->diskRoot);
        }
        parent::tearDown();
    }

    public static function modernUploads(): array
    {
        return [
            ['webp', 'bg'], ['avif', 'bg'],
            ['webp', 'logo'], ['avif', 'logo'],
            ['webp', 'ads'], ['avif', 'ads'],
            ['webp', 'bg', 'WEBP'], ['avif', 'ads', 'AVIF'],
        ];
    }

    /** @dataProvider modernUploads */
    public function test_modern_images_are_processed_saved_and_selected(string $format, string $target, ?string $extension = null): void
    {
        $file = $this->upload('brand.' . ($extension ?? $format), $this->fixture($format));
        if ($target === 'ads') {
            $response = $this->postJson('/test-system-image/ads', ['file' => $file]);
            $response->assertOk()->assertJsonPath('success', true);
            $name = Configuration::first()->tenant_image_ads;
            $response->assertJsonPath('name', $name);
            $path = 'public/uploads/system_ads/' . $name;
        } else {
            $response = $this->postJson('/test-system-image/bg', ['type' => $target, 'image' => $file]);
            $response->assertOk()->assertJsonPath('success', true);
            $login = Configuration::first()->login;
            $url = $target === 'bg' ? $login->image : $login->logo;
            $response->assertJsonPath('url', $url);
            $name = basename($url);
            $path = 'public/uploads/login/' . $name;
            if ($target === 'bg') {
                $this->assertSame('image', $login->type);
                $this->assertSame('old-logo.png', $login->logo);
            } else {
                $this->assertSame('old.png', $login->image);
            }
        }

        $this->assertStringEndsWith('.' . $format, $name);
        Storage::assertExists($path);
        $stored = Storage::get($path);
        $dimensions = getimagesizefromstring($stored);
        $this->assertSame('image/' . $format, $dimensions['mime']);
        $this->assertSame(16, $dimensions[0]);
        $this->assertSame(12, $dimensions[1]);
        $decoded = imagecreatefromstring($stored);
        $this->assertNotFalse($decoded);
        $alpha = (imagecolorat($decoded, 0, 0) >> 24) & 0x7f;
        $this->assertGreaterThanOrEqual(120, $alpha, 'The transparent background must be preserved.');
        imagedestroy($decoded);
    }

    public static function formats(): array
    {
        return [['webp'], ['avif']];
    }

    /** @dataProvider formats */
    public function test_reencoding_removes_trailing_non_image_content(string $format): void
    {
        $marker = 'PRO9_TRAILING_UPLOAD_TEST_CONTENT';
        $this->postJson('/test-system-image/bg', [
            'type' => 'bg',
            'image' => $this->upload('image.' . $format, $this->fixture($format) . $marker),
        ])->assertOk();
        $name = basename(Configuration::first()->login->image);
        $this->assertStringNotContainsString($marker, Storage::get('public/uploads/login/' . $name));
    }

    public static function invalidUploads(): array
    {
        return [
            ['avif', 'wrong-mime', 'bg'],
            ['webp', 'wrong-mime', 'ads'],
            ['avif', 'truncated', 'bg'],
            ['webp', 'truncated', 'ads'],
            ['avif', 'oversized', 'bg'],
            ['webp', 'oversized', 'ads'],
        ];
    }

    /** @dataProvider invalidUploads */
    public function test_invalid_uploads_do_not_replace_configuration_or_publish_files(string $format, string $kind, string $target): void
    {
        $contents = $this->fixture($format);
        if ($kind === 'wrong-mime') {
            $contents = $this->fixture($format === 'webp' ? 'avif' : 'webp');
        } elseif ($kind === 'truncated') {
            $contents = substr($contents, 0, 32);
        } else {
            $contents .= str_repeat('x', 2 * 1024 * 1024);
        }
        $field = $target === 'ads' ? 'file' : 'image';
        $payload = [$field => $this->upload('invalid.' . $format, $contents)];
        if ($target === 'bg') {
            $payload['type'] = 'bg';
        }
        $response = $this->postJson('/test-system-image/' . $target, $payload)->assertStatus(422);
        $errors = $response->json('errors') ?? $response->json('message') ?? $response->json();
        $this->assertArrayHasKey($field, $errors);
        $configuration = Configuration::first();
        $this->assertSame('old.png', $configuration->login->image);
        $this->assertSame('old-logo.png', $configuration->login->logo);
        $this->assertSame('old-ad.png', $configuration->tenant_image_ads);
        $this->assertSame([], Storage::allFiles());
    }

    public function test_avif_system_logo_uses_the_correct_mime_in_both_inherited_logos(): void
    {
        foreach ([MozoLogoService::class, VendeyaLogoService::class] as $service) {
            $method = new \ReflectionMethod($service, 'buildSvgFromFile');
            $method->setAccessible(true);
            $svg = $method->invoke(new $service(), __DIR__ . '/../Fixtures/images/configuration.avif');
            $this->assertStringContainsString('data:image/avif;base64,', $svg);
            $this->assertStringContainsString('viewBox="0 0 16 12"', $svg);
        }
    }

    private function fixture(string $format): string
    {
        return file_get_contents(__DIR__ . '/../Fixtures/images/configuration.' . $format);
    }

    private function upload(string $name, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pro9-image-test-');
        $this->temporaryUploads[] = $path;
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, null, null, true);
    }
}
