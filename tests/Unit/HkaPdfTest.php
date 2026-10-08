<?php

namespace Tests\Unit;

use App\Http\Controllers\Tenant\DocumentPdfController;
use App\Models\Tenant\{Company, Document, User};
use App\Services\Fiscal\{HkaAuthentication, HkaPdf, HkaTicketPdf};
use Illuminate\Support\Facades\{DB, Http, Schema, Storage};
use Illuminate\Validation\ValidationException;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfParser\Type\{PdfDictionary, PdfToken, PdfType};
use setasign\Fpdi\PdfReader\PdfReader;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class HkaPdfTest extends TestCase
{
    private string $original;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('tenant');
        config(['database.connections.tenant' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        $tenancy = $this->getMockBuilder(\Hyn\Tenancy\Database\Connection::class)->disableOriginalConstructor()->onlyMethods(['tenantName'])->getMock();
        $tenancy->method('tenantName')->willReturn('tenant');
        $this->app->instance(\Hyn\Tenancy\Database\Connection::class, $tenancy);
        DB::purge('tenant');
        Schema::connection('tenant')->create('companies', function ($table) {
            $table->increments('id');
            foreach (['number', 'fiscal_environment', 'fiscal_emission_mode', 'fiscal_credentials'] as $column) $table->text($column);
        });
        Schema::connection('tenant')->create('documents', function ($table) {
            $table->increments('id'); $table->integer('establishment_id');
            foreach (['external_id', 'document_type_id', 'fiscal_emission_mode', 'fiscal_environment', 'state_type_id', 'control_number', 'issuer', 'series', 'number'] as $column) $table->text($column)->nullable();
        });
        Schema::connection('tenant')->create('document_emissions', function ($table) {
            $table->increments('id'); $table->integer('document_id');
            foreach (['status', 'control_number', 'payload', 'response'] as $column) $table->text($column);
            $table->text('consulta_url')->nullable(); $table->text('operation_key')->nullable();
        });
        foreach (['cat_identity_document_types', 'users', 'fiscal_environments', 'state_types', 'cat_document_types', 'cat_currency_types', 'groups', 'document_items', 'invoices', 'notes', 'document_payments', 'document_fee'] as $name) {
            Schema::connection('tenant')->create($name, function ($table) {
                $table->increments('id'); $table->integer('document_id')->nullable();
            });
        }
        $company = (new Company())->forceFill(['number' => 'J123456789', 'fiscal_environment' => 'demo', 'fiscal_emission_mode' => 'digital']);
        $company->fiscal_credentials = json_encode(['usuario' => 'pdf-user', 'clave' => 'pdf-private-secret']);
        DB::connection('tenant')->table('companies')->insert(['id' => 1] + $company->getAttributes());
        $context = ['issuer' => $company->number, 'environment' => 'demo',
            'credentials_fingerprint' => hash_hmac('sha256', json_encode(HkaAuthentication::credentials($company)), (string) config('app.key'))];
        DB::connection('tenant')->table('documents')->insert(['id' => 1, 'external_id' => 'invoice-pdf', 'document_type_id' => '01',
            'fiscal_emission_mode' => 'digital', 'fiscal_environment' => 'demo', 'state_type_id' => '01', 'establishment_id' => 10,
            'control_number' => '00-00000016', 'issuer' => json_encode(['number' => 'J123456789']), 'series' => '', 'number' => '16']);
        DB::connection('tenant')->table('document_emissions')->insert(['document_id' => 1, 'operation_key' => '12345678-1234-1234-1234-123456789abc', 'status' => 'confirmed', 'control_number' => '00-00000016',
            'consulta_url' => 'https://democonsulta.thefactoryhka.com.ve/?doc=test+token%2F==',
            'payload' => json_encode(['documentoElectronico' => ['encabezado' => ['identificacionDocumento' => ['serie' => '', 'tipoDocumento' => '01', 'numeroDocumento' => '16']]]]),
            'response' => json_encode(['context' => $context, 'mail' => ['automatic' => ['status' => 'requested']]])]);
        $this->actingAs(new User(['type' => 'seller', 'establishment_id' => 10]));
        $pdf = new \FPDF(); $pdf->SetAutoPageBreak(false);
        foreach (['P', 'L', 'P'] as $index => $orientation) {
            $pdf->AddPage($orientation, 'Letter'); $pdf->SetFont('Helvetica', '', 12);
            $pdf->Text(10, 15, 'HKA test page '.($index + 1));
            $pdf->Rect(1, 1, $pdf->GetPageWidth() - 2, $pdf->GetPageHeight() - 2);
        }
        $this->original = $pdf->Output('S');
        Http::preventStrayRequests();
    }

    private function fakeDownload($body = null): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        Http::fake(function ($request, $options) use ($body) {
            self::assertSame(0, DB::connection('tenant')->transactionLevel());
            self::assertTrue($options['verify']); self::assertFalse($options['allow_redirects']);
            self::assertSame(5, $options['connect_timeout']);
            if (str_ends_with($request->url(), '/Autenticacion')) {
                return Http::response(['token' => 'pdf-private-token', 'expiracion' => date('c', time() + 3600)]);
            }
            self::assertSame('https://demoemisionv2.thefactoryhka.com.ve/api/DescargaArchivo', $request->url());
            self::assertSame(20, $options['timeout']);
            self::assertSame(['Bearer pdf-private-token'], $request->header('Authorization'));
            self::assertSame(['serie' => '', 'tipoDocumento' => '01', 'numeroDocumento' => '16', 'tipoArchivo' => 'PDF'], $request->data());
            if ($body instanceof \Throwable) throw $body;
            if ($body instanceof \Closure) return Http::response($body($request));
            return Http::response($body ?? ['codigo' => '200', 'archivo' => base64_encode($this->original)]);
        });
    }

    public function test_a4_download_is_original_bytes_and_does_not_mutate_sale_or_email(): void
    {
        $before = $this->snapshot(); $this->fakeDownload();
        $response = (new DocumentPdfController())->download('invoice-pdf', 'a4');
        self::assertSame($this->original, $response->getContent());
        self::assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        self::assertSame($before, $this->snapshot());
    }

    public function test_a5_fills_every_landscape_sheet_with_mixed_orientation_source_pages(): void
    {
        $this->fakeDownload();
        $result = (new HkaPdf())->download(Document::findOrFail(1), 'a5');
        $this->assertA5($result, 3);
        $original = new Fpdi(); $original->setSourceFile(StreamReader::createByString($this->original));
        self::assertEqualsWithDelta(215.9, $original->getTemplateSize($original->importPage(1))['width'], 0.01);
        $source = new PdfReader(new PdfParser(StreamReader::createByString($this->original)));
        $converted = new PdfReader(new PdfParser(StreamReader::createByString($result)));
        for ($page = 1; $page <= 3; $page++) {
            $sourceSize = $original->getTemplateSize($original->importPage($page));
            // The imported page's bounds must reach all four edges of the output sheet.
            self::assertSame(1, preg_match('/([\d.]+) 0 0 ([\d.]+) ([\d.-]+) ([\d.-]+) cm \/(\w+) Do/',
                $converted->getPage($page)->getContentStream(), $placement));
            self::assertEqualsWithDelta(210, (float) $placement[1] * $sourceSize['width'], 0.02);
            self::assertEqualsWithDelta(148, (float) $placement[2] * $sourceSize['height'], 0.02);
            self::assertEqualsWithDelta(0, (float) $placement[3], 0.01);
            self::assertEqualsWithDelta(0, (float) $placement[4], 0.01);
            $resources = PdfType::resolve($converted->getPage($page)->getAttribute('Resources'), $converted->getParser());
            $objects = PdfType::resolve(PdfDictionary::get($resources, 'XObject'), $converted->getParser());
            $imported = PdfType::resolve($objects->value[$placement[5]], $converted->getParser());
            $content = $imported->getUnfilteredStream();
            self::assertEquals($this->contentOperands($source->getPage($page)->getContentStream()), $this->contentOperands($content));
            // Glyph axes must have equal scale, even though the sheet uses different scales.
            self::assertSame(1, preg_match('/([\d.-]+) ([\d.-]+) ([\d.-]+) ([\d.-]+) [\d.-]+ [\d.-]+ Tm/', $content, $glyph));
            self::assertEqualsWithDelta((float) $glyph[1] * (float) $placement[1], (float) $glyph[4] * (float) $placement[2], 0.0001);
            self::assertEqualsWithDelta(0, (float) $glyph[2], 0.0001);
            self::assertEqualsWithDelta(0, (float) $glyph[3], 0.0001);
        }
        if ($path = getenv('PRO9_HKA_A5_FIXTURE_PATH')) file_put_contents($path, $result);
        // Imported content keeps vector text streams, rather than rasterizing the invoice.
        self::assertStringNotContainsString('/Subtype /Image', $result);
    }

    private function contentOperands(string $content): array
    {
        $parser = new PdfParser(StreamReader::createByString($content));
        $result = $operands = [];
        while (($value = $parser->readValue()) !== false) {
            if (!$value instanceof PdfToken) { $operands[] = $value; continue; }
            if (in_array($value->value, ['Tj', 'TJ', 'Tf', 're', 'm', 'l', 'Do'], true)) $result[] = [$value->value, $operands];
            $operands = [];
        }
        return $result;
    }

    public function test_nested_text_forms_are_normalized_for_each_page_orientation(): void
    {
        $source = new Fpdi();
        $source->setSourceFile(StreamReader::createByString($this->original));
        $template = $source->importPage(1);
        foreach (['P', 'L'] as $orientation) {
            $source->AddPage($orientation, 'Letter');
            $source->useImportedPage($template, 10, 10, 120);
        }
        $this->fakeDownload(['codigo' => '200', 'archivo' => base64_encode($source->Output('S'))]);
        $result = (new HkaPdf())->download(Document::findOrFail(1), 'a5');
        $this->assertA5($result, 2);
        $reader = new PdfReader(new PdfParser(StreamReader::createByString($result)));
        for ($page = 1; $page <= 2; $page++) {
            preg_match('/([\d.]+) 0 0 ([\d.]+) [\d.-]+ [\d.-]+ cm \/(\w+) Do/', $reader->getPage($page)->getContentStream(), $placement);
            $resources = PdfType::resolve($reader->getPage($page)->getAttribute('Resources'), $reader->getParser());
            $objects = PdfType::resolve(PdfDictionary::get($resources, 'XObject'), $reader->getParser());
            $parent = PdfType::resolve($objects->value[$placement[3]], $reader->getParser());
            preg_match('/\/(A5Form\d+) Do/', $parent->getUnfilteredStream(), $form);
            $resources = PdfType::resolve(PdfDictionary::get($parent->value, 'Resources'), $reader->getParser());
            $objects = PdfType::resolve(PdfDictionary::get($resources, 'XObject'), $reader->getParser());
            $child = PdfType::resolve($objects->value[$form[1]], $reader->getParser());
            preg_match('/([\d.-]+) 0 0 ([\d.-]+) [\d.-]+ [\d.-]+ Tm/', $child->getUnfilteredStream(), $glyph);
            self::assertEqualsWithDelta((float) $glyph[1] * (float) $placement[1], (float) $glyph[2] * (float) $placement[2], 0.0001);
            self::assertStringContainsString('(HKA test page 1) Tj', $child->getUnfilteredStream());
        }
        if ($path = getenv('PRO9_HKA_A5_NESTED_FIXTURE_PATH')) file_put_contents($path, $result);
    }

    private function assertA5(string $pdf, int $pages): void
    {
        $reader = new Fpdi();
        self::assertSame($pages, $reader->setSourceFile(StreamReader::createByString($pdf)));
        for ($page = 1; $page <= $pages; $page++) {
            $size = $reader->getTemplateSize($reader->importPage($page));
            self::assertEqualsWithDelta(210, $size['width'], 0.01);
            self::assertEqualsWithDelta(148, $size['height'], 0.01);
            self::assertSame('L', $size['orientation']);
        }
    }

    public function test_response_errors_are_sanitized_and_never_fall_back_to_local_pdf(): void
    {
        foreach ([['codigo' => '203', 'mensaje' => 'pdf-private-secret'], ['codigo' => '200', 'archivo' => 'https://evil.example/pdf'],
            ['codigo' => '200', 'archivo' => base64_encode('%PDF-1.4 fake %%EOF')],
            new \Illuminate\Http\Client\ConnectionException('pdf-private-token')] as $failure) {
            $this->fakeDownload($failure);
            try { (new HkaPdf())->download(Document::findOrFail(1), 'a4'); self::fail('Must reject download'); }
            catch (ValidationException $exception) {
                self::assertArrayHasKey('pdf', $exception->errors());
                self::assertStringNotContainsString('private', json_encode($exception->errors()));
            }
        }
    }

    public function test_confirmation_control_environment_and_original_context_are_required_before_http(): void
    {
        foreach ([['document_emissions', 'status', 'pending'], ['document_emissions', 'status', 'uncertain'],
            ['document_emissions', 'control_number', 'different'], ['documents', 'control_number', null],
            ['documents', 'fiscal_environment', 'production'], ['documents', 'state_type_id', '11'],
            ['documents', 'issuer', json_encode(['number' => 'J999999999'])], ['companies', 'fiscal_environment', 'production'],
            ['companies', 'fiscal_emission_mode', 'free_form'], ['document_emissions', 'response', '{}']] as [$table, $column, $value]) {
            $db = DB::connection('tenant'); $old = $db->table($table)->where('id', 1)->value($column);
            $db->table($table)->where('id', 1)->update([$column => $value]);
            foreach (['a5', 'ticket'] as $format) {
                try { (new HkaPdf())->download(Document::findOrFail(1), $format); self::fail('Must block ineligible invoice'); }
                catch (ValidationException $exception) { self::assertArrayHasKey('pdf', $exception->errors()); }
            }
            $db->table($table)->where('id', 1)->update([$column => $old]);
        }
        Http::assertNothingSent();
    }

    public function test_branch_scope_rejects_direct_download_and_contract_but_allows_admin(): void
    {
        $this->actingAs(new User(['type' => 'seller', 'establishment_id' => 20]));
        self::assertFalse(HkaPdf::view(Document::findOrFail(1))['a4']['available']);
        self::assertFalse(HkaPdf::view(Document::findOrFail(1))['ticket']['available']);
        foreach (['a5', 'ticket'] as $format) {
            try { (new DocumentPdfController())->download('invoice-pdf', $format); self::fail('Must reject other branch'); }
            catch (HttpException $exception) { self::assertSame(403, $exception->getStatusCode()); }
        }
        Http::assertNothingSent();
        $this->actingAs(new User(['type' => 'admin', 'establishment_id' => 20]));
        self::assertTrue(HkaPdf::view(Document::findOrFail(1))['a5']['available']);
        self::assertTrue(HkaPdf::view(Document::findOrFail(1))['ticket']['available']);
        $this->fakeDownload(); $this->assertA5((new HkaPdf())->download(Document::findOrFail(1), 'a5'), 3);
    }

    public function test_tenant_isolation_with_coincident_document_ids_prevents_remote_data_access(): void
    {
        config(['database.connections.other_pdf_tenant' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        foreach (['companies', 'documents', 'document_emissions', 'cat_identity_document_types', 'users', 'fiscal_environments', 'state_types', 'cat_document_types', 'cat_currency_types', 'groups', 'document_items', 'invoices', 'notes', 'document_payments', 'document_fee'] as $table) {
            $sql = DB::connection('tenant')->selectOne('SELECT sql FROM sqlite_master WHERE name = ?', [$table])->sql;
            DB::connection('other_pdf_tenant')->statement($sql);
        }
        $attributes = (array) DB::connection('tenant')->table('documents')->first();
        $attributes['external_id'] = 'other-invoice';
        DB::connection('other_pdf_tenant')->table('documents')->insert($attributes);
        $tenancy = $this->getMockBuilder(\Hyn\Tenancy\Database\Connection::class)->disableOriginalConstructor()->onlyMethods(['tenantName'])->getMock();
        $tenancy->method('tenantName')->willReturn('other_pdf_tenant');
        $this->app->instance(\Hyn\Tenancy\Database\Connection::class, $tenancy);
        foreach (['a4', 'ticket'] as $format) {
            try { (new DocumentPdfController())->download('invoice-pdf', $format); self::fail('Must not read first tenant'); }
            catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) { self::assertTrue(true); }
        }
        self::assertFalse(HkaPdf::view(Document::findOrFail(1))['a5']['available']);
        self::assertFalse(HkaPdf::view(Document::findOrFail(1))['ticket']['available']);
        Http::assertNothingSent();
    }

    public function test_bulk_a5_uses_converted_hka_pages_and_fails_on_provider_error(): void
    {
        Schema::connection('tenant')->create('configurations', function ($table) { $table->increments('id'); $table->string('formats'); });
        DB::connection('tenant')->table('configurations')->insert(['formats' => 'default']);
        auth()->user()->setRelation('establishment', (new \App\Models\Tenant\Establishment())->forceFill(['template_pdf' => 'default']));
        $report = new class { use \Modules\Report\Traits\MassiveDownloadTrait; };
        $this->fakeDownload();
        $before = $this->snapshot();
        $this->assertA5($report->createPdf(['documents_01' => [Document::findOrFail(1), Document::findOrFail(1)]], 'a5'), 6);
        self::assertSame($before, $this->snapshot());
        Storage::disk('tenant')->deleteDirectory('pdf/hka');
        $this->fakeDownload(['codigo' => '203']);
        $this->expectException(ValidationException::class);
        $report->createPdf(['documents_01' => [Document::findOrFail(1)]], 'a5');
    }

    public function test_cache_reuses_original_and_conversion_without_http_and_recovers_corruption(): void
    {
        $document = Document::findOrFail(1);
        $service = new HkaPdf();
        $store = new \App\Services\Fiscal\HkaPdfStore();
        $this->fakeDownload();
        $a4 = $service->download($document, 'a4');
        $a5 = $service->download($document, 'a5');
        Http::assertSentCount(2);
        self::assertSame($this->original, Storage::disk('tenant')->get($store->path($document, 'a4')));
        self::assertSame($a5, Storage::disk('tenant')->get($store->path($document, 'a5')));
        self::assertStringEndsWith('/a4/J123456789-01-SIN_SERIE_S10-16.pdf', $store->path($document, 'a4'));
        self::assertSame($a4, $service->download($document, 'a4'));
        self::assertSame($a5, $service->download($document, 'a5'));
        Http::assertSentCount(2);
        Storage::disk('tenant')->put($store->path($document, 'a4'), 'broken');
        self::assertSame($a4, $service->download($document, 'a4'));
        Http::assertSentCount(3);
        Storage::disk('tenant')->put($store->path($document, 'a5'), 'broken');
        $this->assertA5($service->download($document, 'a5'), 3);
        Http::assertSentCount(3);
        self::assertEmpty(glob(dirname(Storage::disk('tenant')->path($store->path($document, 'a4'))).'/.hka-*'));
    }

    public function test_public_links_only_read_stored_hka_and_never_fetch_a_missing_file(): void
    {
        $document = Document::findOrFail(1);
        $service = new HkaPdf();
        $this->fakeDownload();
        $service->download($document, 'a4');
        auth()->logout();
        self::assertSame($this->original, $service->stored($document, 'a4'));
        Http::assertSentCount(2);
        try { $service->stored($document, 'a5'); self::fail('Missing file must not trigger remote download'); }
        catch (ValidationException $exception) { self::assertStringContainsString('sesión autorizada', $exception->errors()['pdf'][0]); }
        Http::assertSentCount(2);
        $response = (new \App\Http\Controllers\Tenant\DownloadController())->toPrint('document', 'invoice-pdf', 'a4');
        self::assertSame($this->original, $response->getContent());
        self::assertStringContainsString('inline;', $response->headers->get('Content-Disposition'));
    }

    public function test_late_download_cannot_save_bytes_under_a_different_operation(): void
    {
        $this->fakeDownload(function () {
            DB::connection('tenant')->table('document_emissions')->where('document_id', 1)
                ->update(['operation_key' => '87654321-1234-1234-1234-123456789abc']);
            return ['codigo' => '200', 'archivo' => base64_encode($this->original)];
        });
        try { (new HkaPdf())->download(Document::findOrFail(1), 'a4'); self::fail('Stale operation must be rejected'); }
        catch (ValidationException $exception) { self::assertStringContainsString('identidad fiscal cambió', $exception->errors()['pdf'][0]); }
        self::assertSame([], Storage::disk('tenant')->allFiles());
        self::assertSame('confirmed', Document::findOrFail(1)->emission->status);
    }

    public function test_facturalo_digital_a4_is_remote_and_defers_inside_transaction(): void
    {
        $engine = new class extends \App\CoreFacturalo\Facturalo { public function __construct() { $this->actions = []; } };
        $document = Document::findOrFail(1);
        DB::connection('tenant')->transaction(function () use ($engine, $document) {
            self::assertSame($engine, $engine->createPdf($document, 'invoice', 'a4'));
            Http::assertNothingSent();
        });
        self::assertSame([], Storage::disk('tenant')->allFiles());
        $this->fakeDownload();
        self::assertSame($engine, $engine->createPdf($document, 'invoice', 'a4'));
        self::assertSame($this->original, $engine->createPdf($document, 'invoice', 'a4', 'string'));
        Http::assertSentCount(2);
        self::assertCount(1, Storage::disk('tenant')->allFiles());
    }

    public function test_digital_local_a4_template_is_blocked_even_before_confirmation(): void
    {
        $document = Document::findOrFail(1);
        $this->expectException(ValidationException::class);
        (new \App\CoreFacturalo\Template())->pdf('default', 'invoice', new \Illuminate\Support\Fluent(), $document, 'a4');
    }

    public function test_bulk_a4_preserves_all_original_hka_pages(): void
    {
        Schema::connection('tenant')->create('configurations', function ($table) { $table->increments('id'); $table->string('formats'); });
        DB::connection('tenant')->table('configurations')->insert(['formats' => 'default']);
        auth()->user()->setRelation('establishment', (new \App\Models\Tenant\Establishment())->forceFill(['template_pdf' => 'default']));
        $report = new class { use \Modules\Report\Traits\MassiveDownloadTrait; };
        $this->fakeDownload();
        $bytes = $report->createPdf(['documents_01' => [Document::findOrFail(1), Document::findOrFail(1)]], 'a4');
        $reader = new Fpdi();
        self::assertSame(6, $reader->setSourceFile(StreamReader::createByString($bytes)));
        self::assertEqualsWithDelta(215.9, $reader->getTemplateSize($reader->importPage(1))['width'], 0.01);
        Http::assertSentCount(2);
    }

    public function test_storage_failure_is_sanitized_and_does_not_change_sale_or_confirmation(): void
    {
        $before = $this->snapshot();
        Storage::disk('tenant')->put('pdf', 'Directory unavailable');
        $this->fakeDownload();
        try { (new HkaPdf())->download(Document::findOrFail(1), 'a4'); self::fail('Storage failure must be visible'); }
        catch (ValidationException $exception) { self::assertStringContainsString('No se pudo guardar', $exception->errors()['pdf'][0]); }
        self::assertSame($before, $this->snapshot());
        self::assertSame(['pdf'], Storage::disk('tenant')->allFiles());
    }

    public function test_mixed_bulk_a4_keeps_local_documents_and_hka_originals(): void
    {
        Schema::connection('tenant')->create('configurations', function ($table) { $table->increments('id'); $table->string('formats'); });
        DB::connection('tenant')->table('configurations')->insert(['formats' => 'default']);
        auth()->user()->setRelation('establishment', (new \App\Models\Tenant\Establishment())->forceFill(['template_pdf' => 'default']));
        $report = new class {
            use \Modules\Report\Traits\MassiveDownloadTrait;
            public function addRecordToPdf($document, $format_pdf = 'a4', $base_pdf_template = '', $type = '', $stylesheet = '', $format = []): \Mpdf\Mpdf
            {
                \PHPUnit\Framework\Assert::assertNotEmpty($stylesheet);
                \PHPUnit\Framework\Assert::assertSame(15, $format['margin_top']);
                $pdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir()]);
                $pdf->WriteHTML('Local document');
                return $pdf;
            }
        };
        $this->fakeDownload();
        $local = new Document(['document_type_id' => '01', 'fiscal_emission_mode' => 'free_form']);
        $bytes = $report->createPdf(['documents_01' => [Document::findOrFail(1), $local]], 'a4');
        $reader = new Fpdi();
        self::assertSame(4, $reader->setSourceFile(StreamReader::createByString($bytes)));
        Http::assertSentCount(2);
    }

    private function snapshot(): array
    {
        return array_map(fn ($name) => json_encode(DB::connection('tenant')->table($name)->get()->all()), ['documents', 'document_emissions']);
    }

    public function test_ticket_download_uses_the_exact_saved_query_url_without_http_or_mutation(): void
    {
        $before = $this->snapshot();
        $url = Document::findOrFail(1)->emission->consulta_url;
        $ticket = \Mockery::mock(HkaTicketPdf::class);
        $ticket->shouldReceive('render')->once()->withArgs(fn ($document, $queryUrl) => $document->id === 1 && $queryUrl === $url)
            ->andReturn($this->original);
        $this->app->instance(HkaTicketPdf::class, $ticket);
        $entry = HkaPdf::view(Document::findOrFail(1), true)['ticket'];
        self::assertSame('local', $entry['provider']);
        self::assertTrue($entry['available']);
        self::assertStringEndsWith('/api/documents/invoice-pdf/download-pdf/ticket', $entry['url']);
        $response = (new DocumentPdfController())->download('invoice-pdf', 'ticket');
        self::assertSame($this->original, $response->getContent());
        self::assertStringContainsString('factura-1-80mm.pdf', $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        self::assertSame($before, $this->snapshot());
        Http::assertNothingSent();
    }

    public function test_ticket_renderer_uses_the_existing_ticket_engine_and_qr_generator(): void
    {
        $document = Document::findOrFail(1);
        $url = $document->emission->consulta_url;
        $qr = (new \App\CoreFacturalo\Helpers\QrCode\QrCodeGenerate())->displayPNGBase64($url, 300, 'M');
        $engine = \Mockery::mock(\App\CoreFacturalo\Facturalo::class);
        $engine->shouldReceive('createPdf')->once()->with($document, 'invoice', 'ticket', 'string', ['hka_ticket_qr' => $qr])
            ->andReturn($this->original);
        $this->app->instance(\App\CoreFacturalo\Facturalo::class, $engine);
        self::assertSame($this->original, (new HkaTicketPdf())->render($document, $url));
        self::assertSame([300, 300], array_slice(getimagesizefromstring(base64_decode($qr)), 0, 2));
        Http::assertNothingSent();
    }

    public function test_missing_or_invalid_query_url_blocks_only_ticket_download_without_http(): void
    {
        foreach ([null, '', 'http://democonsulta.thefactoryhka.com.ve/?doc=test', 'not-a-url', 'https://example.test/'.str_repeat('x', 256)] as $url) {
            DB::connection('tenant')->table('document_emissions')->update(['consulta_url' => $url]);
            $document = Document::findOrFail(1);
            $downloads = HkaPdf::view($document);
            self::assertFalse($downloads['ticket']['available']);
            self::assertStringContainsString('Consulte el estado HKA', $downloads['ticket']['message']);
            self::assertTrue($downloads['a4']['available']);
            self::assertTrue($downloads['a5']['available']);
            $before = $this->snapshot();
            try { (new HkaPdf())->download($document, 'ticket'); self::fail('A ticket requires its HKA QR'); }
            catch (ValidationException $exception) { self::assertStringContainsString('Consulte el estado HKA', $exception->errors()['pdf'][0]); }
            self::assertSame($before, $this->snapshot());
        }
        Http::assertNothingSent();
    }

    public function test_ticket_generation_failure_is_sanitized_and_allows_retry(): void
    {
        $before = $this->snapshot();
        $ticket = \Mockery::mock(HkaTicketPdf::class);
        $ticket->shouldReceive('render')->once()->andThrow(new \RuntimeException('pdf-private-secret'));
        $ticket->shouldReceive('render')->once()->andReturn($this->original);
        $this->app->instance(HkaTicketPdf::class, $ticket);
        try { (new HkaPdf())->download(Document::findOrFail(1), 'ticket'); self::fail('Must not return an incomplete ticket'); }
        catch (ValidationException $exception) {
            self::assertStringNotContainsString('private', json_encode($exception->errors()));
            self::assertStringContainsString('venta sigue guardada', $exception->errors()['pdf'][0]);
        }
        self::assertSame($this->original, (new HkaPdf())->download(Document::findOrFail(1), 'ticket'));
        self::assertSame($before, $this->snapshot());
        Http::assertNothingSent();
    }

    public function test_local_download_route_stays_local_and_invalid_formats_are_rejected(): void
    {
        DB::connection('tenant')->table('documents')->where('id', 1)->update(['fiscal_emission_mode' => 'free_form']);
        $local = \Mockery::mock(\App\Http\Controllers\Tenant\DownloadController::class);
        $local->shouldReceive('toPrint')->once()->with('document', 'invoice-pdf', 'a5')->andReturn(response('local-pdf'));
        $this->app->instance(\App\Http\Controllers\Tenant\DownloadController::class, $local);
        self::assertSame('local-pdf', (new DocumentPdfController())->download('invoice-pdf', 'a5')->getContent());
        $local->shouldReceive('toPrint')->once()->with('document', 'invoice-pdf', 'ticket')->andReturn(response('local-ticket'));
        self::assertSame('local-ticket', (new DocumentPdfController())->download('invoice-pdf', 'ticket')->getContent());
        Http::assertNothingSent();
        $this->expectException(HttpException::class);
        (new DocumentPdfController())->download('invoice-pdf', 'ticket_58');
    }

    public function test_registered_web_and_api_routes_require_authentication_and_accept_a4_a5_ticket(): void
    {
        $this->app->instance(\Hyn\Tenancy\Contracts\CurrentHostname::class, (new \Hyn\Tenancy\Models\Hostname())->forceFill(['fqdn' => 'pdf.example.test']));
        \Illuminate\Support\Facades\Route::namespace('App\\Http\\Controllers')->group(base_path('routes/web.php'));
        \Illuminate\Support\Facades\Route::prefix('api')->namespace('App\\Http\\Controllers')->group(base_path('routes/api.php'));
        foreach (['' => 'auth', '/api' => 'auth:api'] as $prefix => $middleware) {
            $route = app('router')->getRoutes()->match(\Illuminate\Http\Request::create('http://pdf.example.test'.$prefix.'/documents/invoice-pdf/download-pdf/a5'));
            self::assertContains($middleware, $route->gatherMiddleware());
            self::assertSame(DocumentPdfController::class.'@download', $route->getActionName());
            self::assertSame('a4|a5|ticket', $route->wheres['format']);
            $ticketRoute = app('router')->getRoutes()->match(\Illuminate\Http\Request::create('http://pdf.example.test'.$prefix.'/documents/invoice-pdf/download-pdf/ticket'));
            self::assertContains($middleware, $ticketRoute->gatherMiddleware());
        }
        $this->app['auth']->guard()->forgetUser();
        try { (new DocumentPdfController())->download('invoice-pdf', 'a4'); self::fail('Guest cannot download'); }
        catch (HttpException $exception) { self::assertSame(403, $exception->getStatusCode()); }
        Http::assertNothingSent();
    }

    public function test_credentials_frozen_identity_and_ambiguous_numbers_block_before_http(): void
    {
        $db = DB::connection('tenant');
        $company = Company::firstOrFail();
        $original = $db->table('companies')->where('id', 1)->value('fiscal_credentials');
        $company->fiscal_credentials = json_encode(['usuario' => 'other-user', 'clave' => 'other-private-secret']);
        $db->table('companies')->where('id', 1)->update(['fiscal_credentials' => $company->getAttributes()['fiscal_credentials']]);
        try { (new HkaPdf())->download(Document::findOrFail(1), 'a4'); self::fail('Credentials changed'); }
        catch (ValidationException $exception) { self::assertArrayHasKey('pdf', $exception->errors()); }
        $db->table('companies')->where('id', 1)->update(['fiscal_credentials' => $original]);
        $payload = $db->table('document_emissions')->where('id', 1)->value('payload');
        $db->table('document_emissions')->where('id', 1)->update(['payload' => '{}']);
        try { (new HkaPdf())->download(Document::findOrFail(1), 'a4'); self::fail('Missing frozen identity'); }
        catch (ValidationException $exception) { self::assertArrayHasKey('pdf', $exception->errors()); }
        $db->table('document_emissions')->where('id', 1)->update(['payload' => $payload]);
        $duplicate = (array) $db->table('documents')->first(); $duplicate['id'] = 2;
        $duplicate['external_id'] = 'ambiguous-pdf'; $duplicate['establishment_id'] = 20;
        $db->table('documents')->insert($duplicate);
        try { (new HkaPdf())->download(Document::findOrFail(1), 'a4'); self::fail('Ambiguous fiscal identity'); }
        catch (ValidationException $exception) { self::assertArrayHasKey('pdf', $exception->errors()); }
        Http::assertNothingSent();
    }

    public function test_http_business_and_file_validation_and_conversion_failure_are_sanitized(): void
    {
        foreach ([[401, ['codigo' => '200']], [200, ['codigo' => []]], [200, ['codigo' => '200', 'archivo' => base64_encode($this->original), 'validaciones' => ['private']]],
            [200, ['codigo' => '200', 'archivo' => base64_encode('%PDF-1.4 no-eof')]]] as [$status, $body]) {
            try { HkaPdf::decode($status, $body); self::fail('Must reject invalid result'); }
            catch (ValidationException $exception) { self::assertStringNotContainsString('private', json_encode($exception->errors())); }
        }
        try { HkaPdf::toLandscapeA5('invalid'); self::fail('Must not deliver an invalid conversion'); }
        catch (ValidationException $exception) { self::assertArrayHasKey('pdf', $exception->errors()); }
        DB::connection('tenant')->beginTransaction();
        try { (new HkaPdf())->download(Document::findOrFail(1), 'a5'); self::fail('No HTTP in transaction'); }
        catch (ValidationException $exception) { self::assertArrayHasKey('pdf', $exception->errors()); }
        finally { DB::connection('tenant')->rollBack(); }
        Http::assertNothingSent();
    }

    public function test_failed_authentication_stops_before_download_and_uses_a_safe_pdf_error(): void
    {
        Http::fake(fn () => Http::response(['codigo' => '401', 'mensaje' => 'pdf-private-secret']));
        try { (new HkaPdf())->download(Document::findOrFail(1), 'a5'); self::fail('Authentication must block download'); }
        catch (ValidationException $exception) {
            self::assertArrayHasKey('pdf', $exception->errors());
            self::assertStringNotContainsString('private', json_encode($exception->errors()));
        }
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/DescargaArchivo'));
    }
}
