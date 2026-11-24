<?php

namespace Tests\Feature;

use App\Http\Controllers\Tenant\CompanyController;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanySignatureUploadTest extends TestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(LoadConfiguration::class, static function ($app): void {
            $connection = ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''];
            $app['config']->set(['app.env' => 'testing', 'database.default' => 'system', 'database.connections.system' => $connection, 'database.connections.tenant' => $connection, 'tenancy.db.system-connection-name' => 'system', 'cache.default' => 'array', 'session.driver' => 'array', 'queue.default' => 'sync', 'mail.default' => 'array']);
        });
        $app->make(Kernel::class)->bootstrap();
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
        Http::preventStrayRequests();
        Schema::connection('tenant')->create('companies', function ($table): void {
            $table->increments('id');
            $table->string('number');
            $table->string('img_firm')->nullable();
            $table->timestamps();
        });
        \DB::connection('tenant')->table('companies')->insert(['number' => 'J123', 'img_firm' => 'old.png']);
        config(['filesystems.default' => 'signature-test']);
        Storage::fake('signature-test');
        Route::post('/test-company-signature', [CompanyController::class, 'uploadFile']);
    }

    public function test_signature_is_saved_as_a_decodable_png(): void
    {
        $this->postJson('/test-company-signature', ['type' => 'img_firm', 'file' => UploadedFile::fake()->image('firma.png')])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('name', 'img_firm_J123.png');
        Storage::assertExists('public/uploads/firms/img_firm_J123.png');
        $this->assertSame('image/png', getimagesizefromstring(Storage::get('public/uploads/firms/img_firm_J123.png'))['mime']);
        $this->assertSame('img_firm_J123.png', \DB::connection('tenant')->table('companies')->value('img_firm'));
    }

    public function test_invalid_image_does_not_replace_signature(): void
    {
        $this->postJson('/test-company-signature', ['type' => 'img_firm', 'file' => UploadedFile::fake()->createWithContent('firma.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>')])
            ->assertUnprocessable();
        $this->assertSame('old.png', \DB::connection('tenant')->table('companies')->value('img_firm'));
        $this->assertSame([], Storage::allFiles());
    }
}
