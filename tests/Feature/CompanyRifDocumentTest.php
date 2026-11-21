<?php

namespace Tests\Feature;

use App\Http\Controllers\Tenant\CompanyRifDocumentController;
use App\Models\Tenant\User;
use App\Tenancy\Contracts\Tenant;
use App\Tenancy\Models\Website;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Route, Schema, Storage};
use Tests\TestCase;

class CompanyRifDocumentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
        config(['database.connections.tenant' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('tenant');
        Schema::connection('tenant')->create('companies', function (Blueprint $table) {
            $table->increments('id');
            $table->string('identity_document_type_id')->nullable();
            $table->json('rif_document')->nullable();
            $table->timestamps();
        });
        DB::connection('tenant')->table('companies')->insert(['id' => 1]);
        Storage::fake('tenancy-default');
        $this->tenant('tenancy_bbc');
        $this->actingAs((new User())->forceFill(['id' => 1, 'type' => 'admin']));
        Route::get('/test-companies/{id}/rif', [CompanyRifDocumentController::class, 'show']);
        Route::post('/test-companies/{id}/rif', [CompanyRifDocumentController::class, 'store']);
        Route::get('/test-companies/{id}/rif/download', [CompanyRifDocumentController::class, 'download']);
    }

    private function tenant(string $uuid): void
    {
        $this->app->instance(Tenant::class, (new Website())->forceFill(['uuid' => $uuid]));
    }

    private function png(string $name = 'rif.png'): UploadedFile
    {
        $image = imagecreatetruecolor(2, 2);
        ob_start(); imagepng($image); $content = ob_get_clean(); imagedestroy($image);
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_upload_download_and_replace_in_active_tenant(): void
    {
        $this->post('/test-companies/1/rif', ['file' => $this->png()])->assertOk()->assertJsonPath('data.name', 'rif.png');
        $old = json_decode(DB::connection('tenant')->table('companies')->value('rif_document'), true);
        $disk = Storage::disk('tenancy-default');
        $oldPath = 'tenancy_bbc/rif_impresa/'.$old['file'];
        $this->assertTrue($disk->exists($oldPath));
        $this->get('/test-companies/1/rif')->assertJsonPath('data.name', 'rif.png');
        $this->get('/test-companies/1/rif/download')->assertOk()->assertHeader('content-disposition', 'attachment; filename=rif.png');

        $this->post('/test-companies/1/rif', ['file' => UploadedFile::fake()->createWithContent('rif.pdf', "%PDF-1.4\n%%EOF")])->assertOk();
        $new = json_decode(DB::connection('tenant')->table('companies')->value('rif_document'), true);
        $this->assertNotSame($old['file'], $new['file']);
        $this->assertFalse($disk->exists($oldPath));
        $this->assertTrue($disk->exists('tenancy_bbc/rif_impresa/'.$new['file']));
        $this->tenant('tenancy_other');
        $this->get('/test-companies/1/rif')->assertJsonPath('data', null);
        $this->get('/test-companies/1/rif/download')->assertNotFound();
    }

    public function test_invalid_files_and_non_admin_do_not_replace_current_document(): void
    {
        $this->post('/test-companies/1/rif', ['file' => $this->png()])->assertOk();
        $old = DB::connection('tenant')->table('companies')->value('rif_document');
        $invalid = tmpfile();
        fwrite($invalid, 'not a pdf');
        $badFile = new UploadedFile(stream_get_meta_data($invalid)['uri'], 'bad.pdf', null, null, true);
        $this->postJson('/test-companies/1/rif', ['file' => $badFile])->assertStatus(422);
        fclose($invalid);
        $this->postJson('/test-companies/1/rif', ['file' => UploadedFile::fake()->create('huge.pdf', 10241, 'application/pdf')])->assertStatus(422);
        $this->actingAs((new User())->forceFill(['id' => 2, 'type' => 'seller']));
        $this->postJson('/test-companies/1/rif', ['file' => $this->png()])->assertForbidden();
        $this->assertSame($old, DB::connection('tenant')->table('companies')->value('rif_document'));
        $this->assertCount(1, Storage::disk('tenancy-default')->allFiles('tenancy_bbc/rif_impresa'));
    }

    public function test_database_failure_removes_new_upload_and_keeps_previous_file(): void
    {
        $this->post('/test-companies/1/rif', ['file' => $this->png()])->assertOk();
        $oldFiles = Storage::disk('tenancy-default')->allFiles('tenancy_bbc/rif_impresa');
        DB::connection('tenant')->statement("CREATE TRIGGER reject_rif BEFORE UPDATE ON companies BEGIN SELECT RAISE(FAIL, 'no update'); END");
        $this->post('/test-companies/1/rif', ['file' => $this->png('new.png')])->assertStatus(500);
        $this->assertSame($oldFiles, Storage::disk('tenancy-default')->allFiles('tenancy_bbc/rif_impresa'));
        $this->get('/test-companies/1/rif')->assertJsonPath('data.name', 'rif.png');
    }
}
