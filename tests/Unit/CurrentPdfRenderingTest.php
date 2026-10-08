<?php

namespace Tests\Unit;

use App\CoreFacturalo\Template;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Fluent;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Tests\TestCase;

class CurrentPdfRenderingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        Schema::connection('tenant')->create('configurations', function ($table): void {
            $table->increments('id');
            $table->boolean('change_decimal_quantity_unit_price_pdf')->default(false);
            $table->unsignedTinyInteger('decimal_quantity_unit_price_pdf')->default(2);
            $table->boolean('show_seller_in_pdf')->default(false);
            $table->boolean('show_bank_accounts_in_pdf')->default(false);
            $table->boolean('legend_footer_sale')->default(false);
            $table->boolean('enabled_guarantee_fund')->default(false);
            $table->boolean('select_establishment_bank_account')->default(false);
            $table->boolean('print_new_line_to_observation')->default(false);
        });
        Schema::connection('tenant')->create('companies', function ($table): void {
            $table->increments('id');
            $table->string('fiscal_environment');
        });
        Schema::connection('tenant')->create('cat_identity_document_types', function ($table): void {
            $table->string('id')->primary();
        });
        Schema::connection('tenant')->create('bank_accounts', function ($table): void {
            $table->increments('id');
            $table->boolean('show_in_documents')->default(false);
            $table->unsignedInteger('establishment_id')->nullable();
        });

        DB::connection('tenant')->table('configurations')->insert(['id' => 1]);
        DB::connection('tenant')->table('companies')->insert([
            'id' => 1,
            'fiscal_environment' => 'demo',
        ]);
    }

    /** @dataProvider invoiceSeries */
    public function test_default_invoice_template_produces_a_readable_pdf(string $series): void
    {
        $company = new Fluent([
            'name' => 'Empresa Venezolana de Prueba, C.A.',
            'trade_name' => 'Pro9 Prueba',
            'number' => 'J-12345678-9',
            'logo' => null,
        ]);
        $document = $this->documentFixture();
        $document->series = $series;
        $document->number = 457;
        $document->number_full = (new \App\Models\Tenant\Document(['series' => $series, 'number' => 457]))->number_full;

        $html = (new Template())->pdf('default', 'invoice', $company, $document, 'a4', [
            'is_preview' => false,
            'enabled_price_items_dispatch' => false,
        ]);

        self::assertStringContainsString('FACTURA', $html);
        self::assertStringContainsString('<h3>'.($series === '' ? '457' : $series.'-457').'</h3>', $html);
        if ($series === '') self::assertStringNotContainsString('-457', $html);
        self::assertStringContainsString('J-12345678-9', $html);
        self::assertStringContainsString('IVA:', $html);
        self::assertStringNotContainsStringIgnoringCase('BOLETA', $html);
        self::assertStringNotContainsStringIgnoringCase('SUNAT', $html);
        self::assertStringNotContainsString('Código Hash:', $html);
        self::assertStringNotContainsString('data:image/png;base64', $html);

        $mpdf = new Mpdf([
            'tempDir' => sys_get_temp_dir(),
            'margin_top' => 15,
            'margin_right' => 15,
            'margin_bottom' => 15,
            'margin_left' => 15,
            'default_font' => 'arial',
        ]);
        $stylesheet = file_get_contents(app_path('CoreFacturalo/Templates/pdf/default/style.css'));
        $mpdf->WriteHTML($stylesheet, HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html, HTMLParserMode::HTML_BODY);
        $pdf = $mpdf->Output('', 'S');

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertGreaterThan(10000, strlen($pdf));

        $path = getenv('PRO9_PDF_FIXTURE_PATH') ?: sys_get_temp_dir().'/pro9-current-invoice.pdf';
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        file_put_contents($path, $pdf);
        self::assertFileExists($path);
    }

    public function test_issuer_igtf_retentions_and_ves_totals_use_persisted_snapshots(): void
    {
        $fixture=$this->documentFixture();
        $document=new CurrentFiscalPdfDocumentFixture();
        $relations=['person','invoice','note','currency_type','document_type','state_type','items','payments','fee','reference_guides','dispatch','transport','quotation','seller'];
        foreach ($fixture->getAttributes() as $key=>$value) {
            if (in_array($key,$relations,true)) $document->setRelation($key,$value);
            elseif ($key==='additional_information') $document->forceFill([$key=>'']);
            else $document->forceFill([$key=>$value]);
        }
        $document->forceFill(['issuer'=>['name'=>'Emisor conservado','trade_name'=>'Snapshot','number'=>'J-12345678-9','logo'=>null],
            'currency_type_id'=>'USD','total'=>119.48,'legends'=>[['code'=>'1000','value'=>\App\CoreFacturalo\Helpers\Number\NumberLetter::convertToLetter(119.48)]],'exchange_rate_sale'=>10.123,'exchange_rate_source'=>'manual','exchange_rate_date'=>'2026-09-10']);
        $document->setRelation('currency_type',new Fluent(['id'=>'USD','symbol'=>'$','description'=>'Dólares']));
        $document->setRelation('taxes',new Collection([new Fluent(['tax_kind'=>'IGTF','percentage'=>3,'amount'=>3.48])]));
        $document->setRelation('received_retentions',new Collection([new Fluent(['tax_kind'=>'IVA','voucher_number'=>'IVA-TEST-1','applied_amount'=>12])]));
        $document->setRelation('guarantee_fund',new Fluent(['amount'=>5]));
        $document->setRelation('currency_totals',new Fluent(['iva'=>161.97,'total'=>1209.50]));
        $receipt=new \App\Models\Tenant\DocumentPayment(['currency_type_id'=>'VES','exchange_rate'=>10.123,'original_amount'=>100,'payment'=>9.88,'tax_amount'=>0,'change'=>0]);
        $receipt->setRelation('payment_method_type',new Fluent(['description'=>'Efectivo']));
        $document->setRelation('payments',new Collection([$receipt]));
        $company=new Fluent(['name'=>'Nombre actual cambiado','number'=>'J-99999999-9','trade_name'=>'Actual','logo'=>null]);
        $html=(new Template())->pdf('default','invoice',$company,$document,'a4',['is_preview'=>false,'enabled_price_items_dispatch'=>false]);
        self::assertStringContainsString('Emisor conservado',$html);self::assertStringNotContainsString('Nombre actual cambiado',$html);
        self::assertStringContainsString('IGTF 3.00%',$html);self::assertStringContainsString('IVA-TEST-1',$html);self::assertStringContainsString('Fondo de garantía',$html);
        self::assertStringContainsString('1,209.50',$html);
        self::assertStringContainsString('Cobro recibido (VES)',$html);self::assertStringContainsString('Principal aplicado (USD)',$html);
        $pdf=new Mpdf(['tempDir'=>sys_get_temp_dir(),'default_font'=>'arial']);
        $pdf->WriteHTML(file_get_contents(app_path('CoreFacturalo/Templates/pdf/default/style.css')),HTMLParserMode::HEADER_CSS);
        $pdf->WriteHTML($html,HTMLParserMode::HTML_BODY);
        $path=getenv('PRO9_FISCAL_PDF_FIXTURE_PATH') ?: sys_get_temp_dir().'/pro9-fiscal-snapshot.pdf';
        file_put_contents($path,$pdf->Output('','S'));self::assertFileExists($path);
    }

    public function test_an_igtf_debit_note_renders_without_articles_or_iva(): void
    {
        $fixture=$this->documentFixture();$document=new CurrentFiscalPdfDocumentFixture();
        $relations=['person','invoice','note','currency_type','document_type','state_type','items','payments','fee','reference_guides','dispatch','transport','quotation','seller'];
        foreach ($fixture->getAttributes() as $key=>$value) {
            if (in_array($key,$relations,true)) $document->setRelation($key,$value);
            elseif ($key==='additional_information') $document->forceFill([$key=>'']);
            else $document->forceFill([$key=>$value]);
        }
        $document->forceFill(['document_type_id'=>'08','series'=>'FD01','number'=>1,'total'=>3.48,'total_value'=>0,'total_taxed'=>0,'total_igv'=>0,
            'total_other_taxes'=>3.48,'subtotal'=>0,'issuer'=>['name'=>'Emisor conservado','number'=>'J-12345678-9','logo'=>null],
            'legends'=>[['code'=>'1000','value'=>\App\CoreFacturalo\Helpers\Number\NumberLetter::convertToLetter(3.48)]]]);
        $document->setRelation('document_type',new Fluent(['id'=>'08','description'=>'Nota de débito']));
        $document->setRelation('note',new Fluent(['affected_document'=>null,'data_affected_document'=>(object)['series'=>'FF01','number'=>1],
            'note_type'=>'debit','note_debit_type'=>new Fluent(['description'=>'IGTF']),'note_description'=>'IGTF sobre pago posterior']));
        $document->setRelation('items',new Collection());
        $document->setRelation('taxes',new Collection([new Fluent(['tax_kind'=>'IGTF','percentage'=>3,'amount'=>3.48])]));
        $document->setRelation('received_retentions',new Collection());$document->setRelation('guarantee_fund',null);
        $company=new Fluent(['name'=>'Emisor actual','number'=>'J-99999999-9','logo'=>null]);
        $html=(new Template())->pdf('default','debit',$company,$document,'a4');
        self::assertStringContainsString('IGTF sobre pago posterior',$html);self::assertStringContainsString('FF01-1',$html);
        self::assertStringContainsString('IGTF 3.00%',$html);self::assertStringNotContainsString('Producto gravado de prueba',$html);
        $pdf=new Mpdf(['tempDir'=>sys_get_temp_dir(),'default_font'=>'arial']);
        $pdf->WriteHTML(file_get_contents(app_path('CoreFacturalo/Templates/pdf/default/style.css')),HTMLParserMode::HEADER_CSS);
        $pdf->WriteHTML($html,HTMLParserMode::HTML_BODY);
        $path=sys_get_temp_dir().'/pro9-fiscal-igtf-note.pdf';file_put_contents($path,$pdf->Output('','S'));self::assertFileExists($path);
    }

    public function invoiceSeries(): array
    {
        return [['FF01'], [''], ['AB-CD123456789012345']];
    }

    /** @dataProvider ticketWidths */
    public function test_download_ticket_preserves_existing_geometry_and_adds_one_qr_without_storage(bool $width80, bool $width70, float $expectedWidth): void
    {
        config(['tenant.enabled_template_ticket_80' => $width80, 'tenant.enabled_template_ticket_70' => $width70]);
        Schema::connection('tenant')->create('establishments', function ($table): void {
            $table->increments('id'); $table->string('template_pdf'); $table->string('template_ticket_pdf'); $table->string('logo')->nullable();
        });
        foreach (['countries', 'departments', 'provinces', 'districts'] as $table) {
            Schema::connection('tenant')->create($table, function ($table): void { $table->string('id')->primary(); });
        }
        DB::connection('tenant')->table('establishments')->insert(['id' => 1, 'template_pdf' => 'default', 'template_ticket_pdf' => 'default']);
        $fixture = $this->documentFixture();
        $fixture->items->first()->additional_information = [];
        $document = new CurrentFiscalPdfDocumentFixture();
        $relations = ['person', 'invoice', 'note', 'currency_type', 'document_type', 'state_type', 'items', 'payments', 'fee', 'reference_guides', 'dispatch', 'transport', 'quotation', 'seller'];
        foreach ($fixture->getAttributes() as $key => $value) {
            if (in_array($key, $relations, true)) $document->setRelation($key, $value);
            elseif ($key === 'additional_information') $document->forceFill([$key => '']);
            else $document->forceFill([$key => $value]);
        }
        $document->forceFill(['fiscal_emission_mode' => 'digital', 'legends' => [
            ['code' => '1000', 'value' => 'MONTO ANTERIOR'],
            ['code' => '1000', 'value' => 'CIENTO DIECISÉIS'],
        ]]);
        $document->setRelation('taxes', new Collection());
        $document->setRelation('received_retentions', new Collection());
        $document->setRelation('guarantee_fund', null);
        $company = new Fluent(['name' => 'Empresa Venezolana de Prueba, C.A.', 'trade_name' => 'Pro9 Prueba', 'number' => 'J-12345678-9', 'logo' => null]);
        $engine = new class($company) extends \App\CoreFacturalo\Facturalo {
            public function __construct($company) {
                $this->company = $company; $this->configuration = \App\Models\Tenant\Configuration::first(); $this->actions = [];
            }
            public function uploadFile($file_content, $file_type) { throw new \LogicException('Downloads must not overwrite stored PDFs'); }
        };
        $this->app->instance(\App\CoreFacturalo\Facturalo::class, $engine);
        $company->fiscal_emission_mode = 'digital';
        $document->setRelation('payments', new Collection());
        $document->exchange_rate_sale = '874.73210000';
        $engine->setPaymentsPreview($document, [['payment' => $document->total, 'payment_method_type_id' => '01']]);
        self::assertSame($document->currency_type_id, $document->payments->first()->currency_type_id);
        self::assertSame('874.73210000', $document->payments->first()->exchange_rate);
        $document->payments->first()->setRelation('payment_method_type', new Fluent(['description' => 'Efectivo Bolívares']));
        $response = $engine->previewPdf($document, 'invoice', 'a4');
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertStringStartsWith('%PDF-', $response->getContent());
        $previewReader = new \setasign\Fpdi\Fpdi();
        $previewReader->setSourceFile(\setasign\Fpdi\PdfParser\StreamReader::createByString($response->getContent()));
        self::assertLessThan(80, $previewReader->getTemplateSize($previewReader->importPage(1))['width']);

        $url = 'https://democonsulta.thefactoryhka.com.ve/?doc=original+query%2F==';
        $qr = (new \App\CoreFacturalo\Helpers\QrCode\QrCodeGenerate())->displayPNGBase64($url, 300, 'M');
        $printHtml = $engine->createPdf($document, 'invoice', 'ticket', 'html', ['hka_ticket_qr' => $qr]);
        self::assertStringNotContainsString('Consultar factura original en HKA', $printHtml);
        foreach ([1, 40] as $rows) {
            $document->setRelation('items', new Collection(array_fill(0, $rows, $fixture->items->first())));
            $baselineHtml = (new Template())->pdf('default', 'invoice', $company, $document, 'ticket');
            $downloadHtml = (new Template())->pdf('default', 'invoice', $company, $document, 'ticket', ['hka_ticket_qr' => $qr]);
            $block = view('pdf.partials.hka_ticket_qr', compact('qr'))->render();
            self::assertSame(str_replace('<!-- HKA_TICKET_QR -->', '', $baselineHtml), str_replace($block, '', $downloadHtml));
            self::assertSame(1, substr_count($downloadHtml, 'CIENTO DIECISÉIS'));
            self::assertStringNotContainsString('MONTO ANTERIOR', $downloadHtml);
            self::assertStringNotContainsString('Leyendas', $downloadHtml);
            self::assertStringNotContainsString('/buscar', $downloadHtml);
            self::assertStringNotContainsString('Representacion impresa', $downloadHtml);
            self::assertLessThan(strpos($downloadHtml, 'Consultar factura original en HKA'), strpos($downloadHtml, 'Son:'));
            self::assertLessThan(strpos($downloadHtml, 'CONDICIÓN DE PAGO:'), strpos($downloadHtml, 'Consultar factura original en HKA'));
            self::assertCount(2, (array) $document->legends);
            self::assertSame(1, substr_count($downloadHtml, 'Consultar factura original en HKA'));
            self::assertStringNotContainsString('Consultar factura original en HKA', $baselineHtml);
            $baseline = $engine->createPdf($document, 'invoice', 'ticket', 'string');
            $download = (new \App\Services\Fiscal\HkaTicketPdf())->render($document, $url);
            $reader = new \setasign\Fpdi\Fpdi();
            $baselinePages = $reader->setSourceFile(\setasign\Fpdi\PdfParser\StreamReader::createByString($baseline));
            $originalSize = $reader->getTemplateSize($reader->importPage(1));
            $pages = $reader->setSourceFile(\setasign\Fpdi\PdfParser\StreamReader::createByString($download));
            self::assertLessThanOrEqual($baselinePages, $pages);
            for ($page = 1; $page <= $pages; $page++) {
                $size = $reader->getTemplateSize($reader->importPage($page));
                self::assertEqualsWithDelta($expectedWidth, $size['width'], .01);
                self::assertEqualsWithDelta($originalSize['height'] + 42, $size['height'], .01);
            }
            if ($directory = getenv('PRO9_HKA_TICKET_FIXTURE_DIR')) {
                if (!is_dir($directory)) mkdir($directory, 0775, true);
                file_put_contents($directory.'/ticket-'.$expectedWidth.'mm-'.$rows.'rows.pdf', $download);
            }
        }
    }

    public function test_invoice_footer_uses_the_saved_document_mode(): void
    {
        $document = $this->documentFixture();
        foreach (['default', 'Plantilla_personalizable', 'marca_de_agua', 'modern-2027', 'nrus'] as $template) {
            $document->fiscal_emission_mode = 'digital';
            $html = (new Template())->pdfFooter($template, $document);
            self::assertStringNotContainsString('/buscar', $html);
            self::assertStringNotContainsString('Representacion impresa', $html);
            $document->fiscal_emission_mode = 'free_form';
            $html = (new Template())->pdfFooter($template, $document);
            self::assertStringContainsString('/buscar', $html);
            self::assertStringContainsString('Representacion impresa', $html);
        }
    }

    public function ticketWidths(): array
    {
        return [[false, false, 72], [true, false, 76], [false, true, 70]];
    }

    private function documentFixture(): CurrentPdfDocumentFixture
    {
        $location = new Fluent(['description' => 'Caracas']);
        $personType = new Fluent(['enabled_description_person_type' => false]);
        $customer = new Fluent([
            'name' => 'Cliente Venezolano, C.A.',
            'number' => 'J-87654321-0',
            'identity_document_type_id' => '6',
            'identity_document_type' => new Fluent(['description' => 'RIF']),
            'address' => 'Avenida Principal',
            'district_id' => '-',
            'department_id' => '14',
            'province_id' => '0229',
            'district' => $location,
            'province' => new Fluent(['description' => 'Libertador']),
            'department' => new Fluent(['description' => 'Distrito Capital']),
            'person_type' => $personType,
        ]);
        $item = new Fluent([
            'internal_id' => 'PRUEBA-001',
            'unit_type_id' => 'UND',
            'description' => 'Producto gravado de prueba',
            'model' => null,
            'lots' => null,
            'presentation' => null,
            'is_set' => false,
            'used_points_for_exchange' => null,
            'IdLoteSelected' => null,
        ]);
        $row = new CurrentPdfItemFixture([
            'item' => $item,
            'm_item' => new Fluent(['brand' => null]),
            'relation_item' => new Fluent(['date_of_due' => null]),
            'quantity' => 1,
            'unit_price' => 116,
            'total' => 116,
            'name_product_pdf' => null,
            'discounts' => null,
            'charges' => null,
            'attributes' => null,
        ]);

        return new CurrentPdfDocumentFixture([
            'establishment_id' => 1,
            'establishment' => new Fluent([
                'logo' => null,
                'address' => 'Avenida Principal',
                'district_id' => '-',
                'province_id' => '-',
                'department_id' => '-',
                'telephone' => '0212-5550000',
                'email' => 'facturacion@example.test',
            ]),
            'customer' => $customer,
            'person' => $customer,
            'invoice' => new Fluent(['date_of_due' => Carbon::parse('2026-09-10')]),
            'note' => null,
            'itinerant' => null,
            'series' => 'FF01',
            'number' => 1,
            'document_type_id' => '01',
            'document_type' => new Fluent(['id' => '01', 'description' => 'FACTURA']),
            'state_type' => new Fluent(['id' => '01']),
            'currency_type_id' => 'VES',
            'currency_type' => new Fluent(['id' => 'VES', 'symbol' => 'Bs.', 'description' => 'Bolívares']),
            'date_of_issue' => Carbon::parse('2026-09-10'),
            'time_of_issue' => '10:30:00',
            'items' => new Collection([$row]),
            'payments' => new Collection(),
            'fee' => new Collection(),
            'legends' => [new Fluent(['code' => '1000', 'value' => 'CIENTO DIECISÉIS'])],
            'additional_information' => [],
            'reference_guides' => new Collection(),
            'guides' => null,
            'prepayments' => null,
            'dispatch' => null,
            'transport' => null,
            'quotation' => null,
            'retention' => null,
            'perception' => null,
            'charges' => null,
            'payment_condition_id' => '01',
            'payment_condition' => null,
            'payment_method_type_id' => null,
            'total' => 116,
            'subtotal' => 116,
            'total_taxed' => 100,
            'total_igv' => 16,
            'total_exportation' => 0,
            'total_free' => 0,
            'total_unaffected' => 0,
            'total_exonerated' => 0,
            'total_discount' => 0,
            'total_discount_with_igv' => 0,
            'total_charge' => 0,
            'total_prepayment' => 0,
            'total_pending_payment' => 0,
            'has_prepayment' => false,
            'was_deducted_prepayment' => false,
            'purchase_order' => null,
            'quotation_id' => null,
            'consigned_id' => null,
            'reference_data' => null,
            'plate_number' => null,
            'folio' => null,
            'terms_condition' => null,
            'custom_fields_data' => null,
            'qr' => null,
            'hash' => null,
            'seller' => null,
        ]);
    }
}

class CurrentPdfDocumentFixture extends Fluent
{
    public function load($relations): self
    {
        return $this;
    }

    public function isPointSystem(): bool
    {
        return false;
    }

    public function getPointsBySale(): int
    {
        return 0;
    }
}

class CurrentPdfItemFixture extends Fluent
{
    public function getUnitPrice(bool $isPreview, $document): float
    {
        return (float) $this->unit_price;
    }

    public function generalApplyNumberFormat($value, int $decimals = 2): string
    {
        return number_format($value, $decimals, '.', '');
    }
}

class CurrentFiscalPdfDocumentFixture extends \App\Models\Tenant\Document
{
    public function load($relations) { return $this; }
    public function getBalanceAttribute() { return $this->total - $this->payments->sum('payment') - $this->retention_amount - $this->guarantee_amount; }
}
