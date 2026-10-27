<?php

namespace Tests\Feature;

use App\Http\Controllers\System\ConfigurationController;
use App\Models\System\Configuration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LoginSvgUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
        config([
            'database.connections.system' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'tenancy.db.system-connection-name' => 'system',
            'filesystems.default' => 'local',
            'queue.default' => 'sync',
            'mail.default' => 'array',
        ]);
        DB::purge('system');
        Schema::connection('system')->create('configurations', function ($table) {
            $table->increments('id');
            $table->text('login')->nullable();
            $table->timestamps();
        });
        Configuration::create(['login' => (object) ['image' => 'old.png']]);
        Storage::fake('local');
        Route::post('/test-login-upload', [ConfigurationController::class, 'storeBgLogin']);
    }

    private function upload(string $name, string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    public function test_svg_is_sanitized_saved_and_selected_as_background(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 100 100">'
            . '<defs><linearGradient id="a"><stop stop-color="#fff"/></linearGradient><symbol id="shape"><circle r="10"/></symbol></defs>'
            . '<rect width="100" height="100" fill="url(#a)"/><use href="#shape"/>'
            . '<script>alert(1)</script><a href="https://example.com"><circle onload="alert(1)"/></a>'
            . '<image xlink:href="https://example.com/tracker.png"/></svg>';
        $this->post('/test-login-upload', ['type' => 'bg', 'image' => $this->upload('test.svg', $svg)])->assertOk();

        $login = Configuration::first()->login;
        $this->assertSame('image', $login->type);
        $name = basename($login->image);
        $this->assertStringEndsWith('.svg', $name);
        $saved = Storage::get('public/uploads/login/' . $name);
        $this->assertStringContainsString('linearGradient', $saved);
        $this->assertStringContainsString('url(#a)', $saved);
        $this->assertStringContainsString('href="#shape"', $saved);
        $this->assertStringNotContainsString('<script', $saved);
        $this->assertStringNotContainsString('onload', $saved);
        $this->assertStringNotContainsString('example.com', $saved);
    }

    public function test_invalid_svg_does_not_replace_previous_image(): void
    {
        $this->postJson('/test-login-upload', ['type' => 'bg', 'image' => $this->upload('invalid.svg', '<svg xmlns="http://www.w3.org/2000/svg"><path>')])->assertStatus(422);
        $this->assertSame('old.png', Configuration::first()->login->image);
        $this->assertSame([], Storage::allFiles('public/uploads/login'));
    }

    public function test_png_upload_still_works(): void
    {
        $image = imagecreatetruecolor(2, 2);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);
        $this->post('/test-login-upload', ['type' => 'bg', 'image' => $this->upload('test.png', $png)])->assertOk();
        $name = basename(Configuration::first()->login->image);
        $this->assertStringEndsWith('.png', $name);
        Storage::assertExists('public/uploads/login/' . $name);
    }
}
