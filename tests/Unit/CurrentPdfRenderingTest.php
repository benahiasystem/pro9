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
        Schema::connection('tenant')->create('template_columns_config', function ($table): void {
            $table->increments('id');
            $table->unsignedInteger('establishment_id');
            $table->string('template_name');
        });

        DB::connection('tenant')->table('configurations')->insert(['id' => 1]);
        DB::connection('tenant')->table('companies')->insert([
            'id' => 1,
            'fiscal_environment' => 'demo',
        ]);
    }

    /** @dataProvider invoiceSeries */
    public function test_default_invoice_template_produces_a_readable_pdf(string $series, int $precision = 2): void
    {
        $company = new Fluent([
            'name' => 'Empresa Venezolana de Prueba, C.A.',
            'trade_name' => 'SAB Prueba',
            'number' => 'J-12345678-9',
            'logo' => null,
        ]);
        $document = $this->documentFixture();
        if ($precision > 2) {
            DB::connection('tenant')->table('configurations')->update(['change_decimal_quantity_unit_price_pdf'=>true, 'decimal_quantity_unit_price_pdf'=>$precision]);
            $calculated = \App\Support\Exact\LineCalculation::calculate(['quantity'=>'1','unit_price'=>'116.123456789123'], '16');
            foreach (['unit_price','unit_value','total_value','total_base_igv','total_igv','total'] as $field) $document->items->first()->$field = $calculated[$field];
            foreach (['total_value','total_igv','total'] as $field) $document->$field = $calculated[$field];
            $document->total_taxed = $calculated['total_base_igv'];
            $document->subtotal = $calculated['total'];
            $document->legends=[new Fluent(['code'=>'1000','value'=>\App\CoreFacturalo\Helpers\Number\NumberLetter::convertToLetter(
                \App\Support\Exact\Decimal::represent($calculated['total'],2,true))])];
        }
        $document->series = $series;
        $document->number = 457;
        $document->number_full = (new \App\Models\Tenant\Document(['series' => $series, 'number' => 457]))->number_full;

        $html = (new Template())->pdf('default', 'invoice', $company, $document, 'a4', [
            'is_preview' => false,
            'enabled_price_items_dispatch' => false,
        ]);

        self::assertStringContainsString('FACTURA', $html);
        self::assertStringNotContainsStringIgnoringCase('Sin serie', $html);
        self::assertStringNotContainsString('SIN_SERIE', $html);
        self::assertStringContainsString('N° de documento: <strong>00000457</strong>', $html);
        if ($series === '') self::assertStringNotContainsString('-457', $html);
        self::assertStringContainsString('J-12345678-9', $html);
        self::assertStringContainsString('IVA:', $html);
        self::assertStringContainsString($precision === 2 ? '116.00' : ($precision === 6 ? '116.123457' : '116.123456789123'), $html);
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

        $path = getenv('SAB_PDF_FIXTURE_PATH') ?: sys_get_temp_dir().'/sab-current-invoice.pdf';
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        file_put_contents($path, $pdf);
        self::assertFileExists($path);
    }

    public static function localA4Designs(): array
    {
        $cases=[];
        foreach (['default','modern-2027','marca_de_agua','Plantilla_personalizable'] as $design) {
            foreach (['a4','a5'] as $format) {
                foreach ([false,true] as $fiscal) $cases[$design.'-'.$format.($fiscal ? '-fiscal' : '-simple')]=[$design,$fiscal,$format];
            }
        }
        return $cases;
    }

    private function paperPdf(string $format): Mpdf
    {
        return new Mpdf($format === 'a5'
            ? ['format'=>[210,148], 'tempDir'=>sys_get_temp_dir(), 'default_font'=>'arial', 'margin_top'=>2,
                'margin_bottom'=>10, 'margin_left'=>5, 'margin_right'=>5, 'margin_footer'=>3, 'shrink_tables_to_fit'=>1]
            : ['format'=>'A4', 'tempDir'=>sys_get_temp_dir(), 'default_font'=>'arial',
                'margin_top'=>15, 'margin_bottom'=>15, 'margin_left'=>15, 'margin_right'=>15]);
    }

    public function test_a5_keeps_the_physical_font_sizes_of_a4(): void
    {
        $document=$this->documentFixture();
        $document->number_full='00000034';
        $company=new Fluent(['name'=>'Empresa de prueba', 'number'=>'J087897598', 'logo'=>null]);
        $heights=[];
        foreach (['a4','a5'] as $format) {
            $pdf=$this->paperPdf($format);
            $pdf->SetHTMLFooter((new Template())->pdfFooter('default',$document));
            $pdf->WriteHTML((new Template())->pdfStylesheet('default','invoice',$document,$format),HTMLParserMode::HEADER_CSS);
            $pdf->WriteHTML((new Template())->pdf('default','invoice',$company,$document,$format),HTMLParserMode::HTML_BODY);
            $path=tempnam(sys_get_temp_dir(),'sab-paper-font-');
            try {
                file_put_contents($path,$pdf->Output('','S'));
                $process=new \Symfony\Component\Process\Process(['pdftotext','-bbox',$path,'-']);
                $process->mustRun();
                $xml=simplexml_load_string($process->getOutput(),\SimpleXMLElement::class,LIBXML_NONET);
                foreach (['FACTURA','CLIENTE','P.UNIT'] as $word) {
                    $matches=$xml->xpath('//*[local-name()="word" and (text()="'.$word.'" or text()="'.$word.':")]');
                    self::assertNotEmpty($matches,$format.' '.$word);
                    $heights[$format][$word]=(float) $matches[0]['yMax'] - (float) $matches[0]['yMin'];
                }
                $pages=$xml->xpath('//*[local-name()="page"]');
                self::assertEqualsWithDelta($format === 'a5' ? 419.527559 : 841.889764,(float) $pages[0]['height'],0.1);
                self::assertEqualsWithDelta(595.275591,(float) $pages[0]['width'],0.1);
            } finally { unlink($path); }
        }
        foreach ($heights['a4'] as $word=>$height) {
            self::assertEqualsWithDelta($height,$heights['a5'][$word],0.02,'Physical font size: '.$word);
        }
    }

    /** @dataProvider localA4Designs */
    public function test_local_a4_design_and_fiscal_sections_render_real_pages(string $design, bool $fiscal, string $format): void
    {
        $D=\App\Support\Exact\Decimal::class;
        $company=new Fluent(['name'=>'Empresa de prueba, C.A.','trade_name'=>'bbc','number'=>'J087897598','logo'=>null]);
        $document=$this->documentFixture();
        $document->series='FF01'; $document->number=34; $document->number_full='FF01-00000034';
        $document->exchange_rate_sale='40.12345678';
        $document->payment_condition=new Fluent(['name'=>'Contado']);
        $document->total=$document->subtotal='5000'; $document->total_taxed='4310.34'; $document->total_igv='689.66';
        $document->items->first()->unit_price=$document->items->first()->total='5000';
        $document->legends=[new Fluent(['code'=>'1000','value'=>'CINCO MIL CON 00/100'])];
        $payment=new \App\Models\Tenant\DocumentPayment(['currency_type_id'=>'VES','original_amount'=>'4700',
            'payment'=>'4700','tax_amount'=>'0','change'=>'300','exchange_rate'=>'40.12345678',
            'calculation_snapshot'=>$D::snapshot(['tendered_original_amount'=>'5000','received_change'=>'300'])]);
        $payment->setRelation('change_disbursements', new \Illuminate\Database\Eloquent\Collection([
            new \App\Models\Tenant\DocumentChangeDisbursement(['currency_type_id'=>'VES','original_amount'=>'300'])]));
        $second=new \App\Models\Tenant\DocumentPayment(['currency_type_id'=>'VES','original_amount'=>'300',
            'payment'=>'300','tax_amount'=>'0','change'=>'0','exchange_rate'=>'40.12345678']);
        $second->setRelation('change_disbursements',new \Illuminate\Database\Eloquent\Collection());
        $document->payments=new Collection([$payment,$second]);
        if ($fiscal) {
            \Tests\Support\InvoiceA4Ledger::create();
            $fixture=$document; $document=new CurrentFiscalPdfDocumentFixture();
            foreach ($fixture->getAttributes() as $key=>$value) {
                if (in_array($key,['person','invoice','note','currency_type','document_type','state_type','items','payments','fee','reference_guides','dispatch','transport','quotation','seller','payment_condition'],true)) $document->setRelation($key,$value);
                else $document->forceFill([$key=>$key==='additional_information' ? '' : $value]);
            }
            $document->forceFill(['id'=>1,'customer_id'=>1,'establishment_id'=>1,'issuer'=>['name'=>'Empresa conservada, C.A.','trade_name'=>'bbc','number'=>'J087897598','logo'=>null],
                'control_number'=>'00-00000034','fiscal_environment'=>'demo','fiscal_emission_mode'=>'free_form',
                'total'=>'118.40','subtotal'=>'116','total_value'=>'100','total_taxed'=>'100','total_igv'=>'16',
                'total_other_taxes'=>'2.40','total_payable'=>'118.40','payment_condition_id'=>'02',
                'legends'=>[['code'=>'1000','value'=>'CIENTO DIECIOCHO CON 40/100']]]);
            $document->exists=true;
            $document->setRelation('payment_condition',new Fluent(['name'=>'Crédito']));
            $document->items->first()->unit_price=$document->items->first()->total='116';
            $payment->forceFill(['id'=>1,'document_id'=>1,'original_amount'=>'80','payment'=>'80','tax_amount'=>'2.40','change'=>'27.60',
                'calculation_snapshot'=>$D::snapshot(['total_tendered'=>'110','tendered_original_amount'=>'107.60','received_change'=>'27.60'])]);
            $payment->change_disbursements->first()->original_amount='27.60';
            $derived=new \App\Models\Tenant\DocumentPayment(['receipt_parent_id'=>1,'currency_type_id'=>'VES','payment'=>'2.40','tax_amount'=>'0']);
            $derived->setRelation('change_disbursements',new \Illuminate\Database\Eloquent\Collection());
            $document->setRelation('payments',new \Illuminate\Database\Eloquent\Collection([$payment,$derived]));
            DB::connection('tenant')->table('document_payments')->insert([
                ['id'=>1,'document_id'=>1,'payment'=>'80','receipt_parent_id'=>null],
                ['id'=>2,'document_id'=>1,'payment'=>'2.40','receipt_parent_id'=>1]]);
            $document->setRelation('taxes',new \Illuminate\Database\Eloquent\Collection([
                new \App\Models\Tenant\DocumentTax(['tax_kind'=>'IGTF','percentage'=>'3','amount'=>'2.40'])]));
            $document->setRelation('received_retentions',new \Illuminate\Database\Eloquent\Collection([
                new \App\Models\Tenant\DocumentReceivedRetention(['tax_kind'=>'IVA','voucher_number'=>'20261100000001','voucher_date'=>'2026-11-14',
                    'base'=>'16','taxable_base'=>'100','percentage'=>'75','amount'=>'12','applied_amount'=>'12','application_status'=>'applied','currency_type_id'=>'VES']),
                new \App\Models\Tenant\DocumentReceivedRetention(['tax_kind'=>'ISLR','voucher_number'=>'ISLR-2026-34','voucher_date'=>'2026-11-14',
                    'base'=>'100','subtrahend'=>'0','percentage'=>'4','amount'=>'4','applied_amount'=>'4','application_status'=>'applied','currency_type_id'=>'VES'])]));
            $document->setRelation('fiscal_data',new \App\Models\Tenant\DocumentFiscalData(['islr_retention_prevision'=>[
                'eligible'=>true,'amount'=>'8','percentage'=>'4.125','currency_type_id'=>'VES']]));
            $document->setRelation('guarantee_fund',null); $document->setRelation('currency_totals',null);
            $document->setRelation('fee',new Collection([new class(['date'=>Carbon::parse('2026-12-14'),'amount'=>'20.00','currency_type'=>$document->currency_type]) extends Fluent {
                public function getStringPaymentMethodType(): string { return ''; }
            }]));
        }
        if ($format === 'a5') {
            DB::connection('tenant')->table('configurations')->update(['show_seller_in_pdf'=>true]);
            if ($document instanceof \App\Models\Tenant\Document) $document->setRelation('seller',new Fluent(['name'=>'Administrador']));
            else $document->seller=new Fluent(['name'=>'Administrador']);
            $amounts=$fiscal ? ['50','50','16'] : ['500','1500','3000'];
            $document->items=collect($amounts)->map(function ($amount, $index) use ($document) {
                $row=clone $document->items->first(); $row->item=clone $row->item;
                $row->item->internal_id='P-'.($index + 1); $row->item->description='Producto de prueba '.($index + 1);
                $row->unit_price=$row->total=$amount;
                return $row;
            });
        }
        $html=(new Template())->pdf($design,'invoice',$company,$document,$format,['is_preview'=>false]);
        self::assertSame(1,substr_count($html,'data-invoice-'.$format.'-identity'));
        self::assertSame(1,substr_count($html,'R.I.F. J-08789759-8'));
        self::assertSame(1,substr_count($html,'Tasa de Cambio: Bs. 40.12345678'));
        if ($format === 'a5') self::assertStringContainsString('Administrador',$html);
        self::assertStringNotContainsString('DEMO — SIN VALIDEZ FISCAL',$html);
        self::assertStringNotContainsString('Rif fiscal',$html);
        self::assertDoesNotMatchRegularExpression('/<td[^>]*>\s*(?:<strong>)?MONEDA/i',$html);
        self::assertStringContainsString('N° de documento: <strong>'.($format === 'a4' ? '00000034' : 'FF01-00000034').'</strong>',$html);
        self::assertStringContainsString('N° de control: <strong>'.($fiscal ? '00-00000034' : 'No asignado').'</strong>',$html);
        self::assertTrue(strpos($html,'Esta factura es solo de prueba') < strpos($html,'data-invoice-'.$format.'-identity'));
        if ($fiscal) {
            self::assertSame(1,substr_count($html,'IGTF 3.00%'));
            self::assertTrue(strpos($html,'IGTF 3.00%') < strpos($html,'TOTAL A PAGAR'));
            self::assertStringContainsString('20261100000001',$html);
            self::assertStringContainsString('ISLR-2026-34',$html);
            self::assertStringContainsString('RETENCIONES PREVISTAS',$html);
            self::assertStringContainsString('Recibido VES 110.00',$html);
            self::assertStringContainsString('Vuelto VES 27.60',$html);
            self::assertStringContainsString('<strong>VES 20.00</strong>',$html);
        } else {
            self::assertStringNotContainsString('data-invoice-'.$format.'-igtf',$html);
            self::assertStringContainsString('Recibido VES 5,000.00',$html);
            self::assertStringContainsString('Recibido VES 300.00',$html);
            self::assertStringContainsString('Vuelto VES 300.00',$html);
        }
        $pdf=new Mpdf($format === 'a5'
            ? ['format'=>[210,148],'tempDir'=>sys_get_temp_dir(),'default_font'=>'arial','margin_top'=>2,
                'margin_bottom'=>10,'margin_left'=>5,'margin_right'=>5,'margin_footer'=>3,'shrink_tables_to_fit'=>1]
            : ['format'=>'A4','tempDir'=>sys_get_temp_dir(),'default_font'=>'arial',
                'margin_top'=>15,'margin_bottom'=>15,'margin_left'=>15,'margin_right'=>15]);
        $pdf->SetHTMLFooter((new Template())->pdfFooter($design,$document));
        $pdf->WriteHTML((new Template())->pdfStylesheet($design,'invoice',$document,$format),HTMLParserMode::HEADER_CSS);
        $pdf->WriteHTML($html,HTMLParserMode::HTML_BODY);
        $bytes=$pdf->Output('','S');
        self::assertStringStartsWith('%PDF-',$bytes);
        if ($directory=getenv($format === 'a5' ? 'SAB_A5_FIXTURE_DIR' : 'SAB_A4_FIXTURE_DIR')) {
            if (!is_dir($directory)) mkdir($directory,0775,true);
            file_put_contents($directory.'/'.$design.($fiscal ? '-fiscal' : '-simple').'.pdf',$bytes);
        }
        self::assertSame(1,$pdf->page, $design.' '.$format.' must fit a representative invoice on one page');

    }

    public function test_watermark_invoice_expands_short_items_without_padding_complex_or_long_documents(): void
    {
        $company = new Fluent(['name'=>'Empresa de prueba', 'number'=>'J-12345678-9', 'logo'=>null]);
        foreach (['short' => 1, 'several' => 5, 'long_description' => 1, 'many' => 45] as $case => $count) {
            $document = $this->documentFixture();
            $prototype = $document->items->first();
            $document->items = collect(range(1, $count))->map(function ($index) use ($prototype, $case) {
                $row = clone $prototype;
                $row->item = clone $prototype->item;
                $row->item->description = $case === 'long_description' ? str_repeat('Descripción extensa ', 15) : 'Producto '.$index;
                $row->item->internal_id = 'ITEM-'.$index;
                return $row;
            });
            $html = (new Template())->pdf('marca_de_agua', 'invoice', $company, $document, 'a4', ['is_preview'=>false]);
            $padding = substr_count($html, 'data-item-padding');
            self::assertSame(in_array($case, ['short', 'several'], true) ? 19 - $count : 0, $padding, $case);
            self::assertStringContainsString('ITEM-'.$count, $html);
            self::assertStringContainsString('TOTAL A PAGAR', $html);
            $pdf = new Mpdf(['format'=>'A4', 'tempDir'=>sys_get_temp_dir(), 'default_font'=>'arial',
                'margin_top'=>15, 'margin_bottom'=>15, 'margin_left'=>15, 'margin_right'=>15]);
            $pdf->WriteHTML((new Template())->pdfStylesheet('marca_de_agua', 'invoice', $document, 'a4'), HTMLParserMode::HEADER_CSS);
            $pdf->WriteHTML($html, HTMLParserMode::HTML_BODY);
            self::assertStringStartsWith('%PDF-', $pdf->Output('', 'S'));
            if ($case !== 'many') self::assertSame(1, $pdf->page, $case);
            else self::assertGreaterThan(1, $pdf->page);
        }
    }

    public function test_a4_logo_header_and_production_notice_are_isolated_from_other_formats(): void
    {
        $public=public_path(); $disk=config('filesystems.disks.public');
        $logoDirectory=sys_get_temp_dir().'/sab-a4-logo-'.bin2hex(random_bytes(6));
        mkdir($logoDirectory.'/storage/uploads/logos',0775,true);
        mkdir($logoDirectory.'/views');
        config()->set('view.compiled',$logoDirectory.'/views');
        $this->app->forgetInstance('blade.compiler');
        copy($public.'/logo/tulogo.png',$logoDirectory.'/storage/uploads/logos/a4-test.png');
        $this->app->usePublicPath($logoDirectory);
        config()->set('filesystems.disks.public',['driver'=>'local','root'=>$logoDirectory.'/storage']);
        \Illuminate\Support\Facades\Storage::forgetDisk('public');
        try {
            $company=new Fluent(['name'=>'Emisor','trade_name'=>'Prueba','number'=>'J087897598','logo'=>'a4-test.png']);
            $document=$this->documentFixture();
            $document->series='AB-CD123456789012345'; $document->number=457;
            $document->number_full='AB-CD123456789012345-00000457'; $document->fiscal_environment='production';
            foreach (['a4','a5'] as $format) {
            foreach (['default','modern-2027','marca_de_agua','Plantilla_personalizable'] as $design) {
                $html=(new Template())->pdf($design,'invoice',$company,$document,$format,['is_preview'=>false]);
                self::assertStringContainsString('data:image/png;base64',$html);
                self::assertStringNotContainsString('Esta factura es solo de prueba',$html);
                self::assertSame(1,substr_count($html,'R.I.F. J-08789759-8'));
                self::assertSame(1,substr_count($html,'data-invoice-'.$format.'-identity'));
                $pdf=$this->paperPdf($format);
                $pdf->SetHTMLFooter((new Template())->pdfFooter($design,$document));
                $pdf->WriteHTML((new Template())->pdfStylesheet($design,'invoice',$document,$format),HTMLParserMode::HEADER_CSS);
                $pdf->WriteHTML($html,HTMLParserMode::HTML_BODY);
                self::assertSame(1,$pdf->page);
                $path=tempnam(sys_get_temp_dir(),'sab-company-alignment-');
                try {
                    file_put_contents($path,$pdf->Output('','S'));
                    $process=new \Symfony\Component\Process\Process(['pdftotext','-bbox',$path,'-']);
                    $process->mustRun();
                    $xml=simplexml_load_string($process->getOutput(),\SimpleXMLElement::class,LIBXML_NONET);
                    $positions=[];
                    foreach (['Prueba','Emisor','R.I.F.','AVENIDA','Email:'] as $word) {
                        $matches=$xml->xpath('//*[local-name()="word" and text()="'.$word.'"]');
                        self::assertNotEmpty($matches,$design.' '.$format.' '.$word);
                        $positions[$word]=(float) $matches[0]['xMin'];
                    }
                    foreach ($positions as $word=>$position) {
                        self::assertEqualsWithDelta($positions['Prueba'],$position,0.5,$design.' '.$format.' '.$word);
                    }
                } finally { unlink($path); }
                if ($fixtureDirectory=getenv($format === 'a5' ? 'SAB_A5_FIXTURE_DIR' : 'SAB_A4_FIXTURE_DIR')) {
                    if (!is_dir($fixtureDirectory)) mkdir($fixtureDirectory,0775,true);
                    file_put_contents($fixtureDirectory.'/'.$design.'-logo.pdf',$pdf->Output('','S'));
                }
            }
            }
        } finally {
            $this->app->usePublicPath($public);
            config()->set('filesystems.disks.public',$disk);
            \Illuminate\Support\Facades\Storage::forgetDisk('public');
            (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($logoDirectory);
        }
        $document->fiscal_environment='demo'; $company->logo=null;
        $document->items->first()->additional_information=[];
        foreach (['ticket','ticket_58'] as $format) {
            $html=(new Template())->pdf('default','invoice',$company,$document,$format,['is_preview'=>false]);
            self::assertStringNotContainsString('data-invoice-'.$format.'-identity',$html);
            self::assertStringNotContainsString('data-invoice-a4-rate',$html);
            self::assertStringContainsString('J-08789759-8',$html);
        }
    }

    public static function paperFormats(): array { return [['a4'], ['a5']]; }

    /** @dataProvider paperFormats */
    public function test_long_a4_preserves_the_last_item_retentions_and_payments_across_pages(string $format): void
    {
        $document=$this->documentFixture();
        $document->number_full='FF01-00000001'; $document->exchange_rate_sale='40.12345678';
        $document->total=$document->subtotal='5000'; $document->total_taxed='4310.34'; $document->total_igv='689.66';
        $document->items=collect(range(1,40))->map(function ($number) use ($document) {
            $row=clone $document->items->first(); $row->item=clone $row->item;
            $row->item->internal_id='P-'.$number;
            $row->item->description='Producto '.$number.' con descripción extensa para verificar el salto de página y conservar todos los datos impresos.';
            $row->unit_price=$row->total='125';
            return $row;
        });
        $document->legends=[new Fluent(['code'=>'1000','value'=>'CINCO MIL CON 00/100'])];
        $document->payment_condition_id='02'; $document->payment_condition=new Fluent(['name'=>'Crédito']);
        $document->payments=collect([new Fluent(['currency_type_id'=>'VES','original_amount'=>'4988','payment'=>'4988',
            'tax_amount'=>'0','change'=>'300','exchange_rate'=>'40.12345678','calculation_snapshot'=>\App\Support\Exact\Decimal::snapshot([
                'tendered_original_amount'=>'5288','received_change'=>'300'])])]);
        $document->received_retentions=collect([new Fluent(['tax_kind'=>'IVA','voucher_number'=>'RETENCION-ULTIMA-PAGINA',
            'application_status'=>'applied','applied_amount'=>'12','amount'=>'12','currency_type_id'=>'VES'])]);
        $company=new Fluent(['name'=>'Empresa de prueba','trade_name'=>'bbc','number'=>'J087897598','logo'=>null]);
        $html=(new Template())->pdf('default','invoice',$company,$document,$format,['is_preview'=>false]);
        self::assertStringContainsString('Producto 40 con descripción extensa',$html);
        self::assertSame(1,substr_count($html,'RETENCION-ULTIMA-PAGINA'));
        self::assertSame(1,substr_count($html,'PAGOS:'));
        $pdf=$this->paperPdf($format);
        $pdf->SetHTMLFooter((new Template())->pdfFooter('default',$document));
        $pdf->WriteHTML((new Template())->pdfStylesheet('default','invoice',$document,$format),HTMLParserMode::HEADER_CSS);
        $pdf->WriteHTML($html,HTMLParserMode::HTML_BODY);
        self::assertGreaterThanOrEqual(2,$pdf->page);
        $bytes=$pdf->Output('','S');
        $path=tempnam(sys_get_temp_dir(),'sab-paper-long-');
        try {
            file_put_contents($path,$bytes);
            $process=new \Symfony\Component\Process\Process(['pdftotext','-layout',$path,'-']);
            $process->mustRun();
            $text=$process->getOutput();
            self::assertStringContainsString('Producto 40 con descripción extensa',$text);
            self::assertSame(1,substr_count($text,'RETENCION-ULTIMA-PAGINA'));
            self::assertSame(1,substr_count($text,'Recibido VES 5,288.00'));
            self::assertStringContainsString('Vuelto VES 300.00',$text);
            self::assertStringContainsString('Tasa de Cambio: Bs. 40.12345678',$text);
            foreach (explode("\f",$text) as $pageText) {
                if (str_contains($pageText,'PAGOS:')) self::assertStringContainsString('Recibido VES',$pageText,'Keep payment heading with its first row.');
            }
        } finally { unlink($path); }
        if ($fixtureDirectory=getenv($format === 'a5' ? 'SAB_A5_FIXTURE_DIR' : 'SAB_A4_FIXTURE_DIR')) {
            if (!is_dir($fixtureDirectory)) mkdir($fixtureDirectory,0775,true);
            file_put_contents($fixtureDirectory.'/default-long.pdf',$bytes);
        }
    }

    public function test_a5_many_retentions_receipts_and_installments_flow_without_losing_the_last_rows(): void
    {
        config(['tenant.public_document_search_enabled' => true]);
        $document=$this->documentFixture();
        $document->number_full='00000034'; $document->payment_condition_id='02';
        $document->payment_condition=new Fluent(['name'=>'Crédito']);
        $document->total=$document->subtotal='200'; $document->total_value=$document->total_taxed='172.41'; $document->total_igv='27.59';
        $document->items->first()->unit_price=$document->items->first()->total='200';
        $document->legends=[new Fluent(['code'=>'1000','value'=>'DOSCIENTOS CON 00/100'])];
        $document->payments=collect(range(1,60))->map(fn ($number)=>new Fluent([
            'currency_type_id'=>'VES','original_amount'=>$number === 60 ? '97' : '1',
            'payment'=>$number === 60 ? '97' : '1','tax_amount'=>'0','change'=>'0','exchange_rate'=>'40.12345678']));
        $document->received_retentions=collect(range(1,20))->map(fn ($number)=>new Fluent([
            'tax_kind'=>'IVA','voucher_number'=>'RET-LARGA-'.$number,'application_status'=>'applied',
            'applied_amount'=>'0.60','amount'=>'0.60','currency_type_id'=>'VES']));
        $document->fee=collect(range(1,40))->map(fn ($number)=>new class([
            'date'=>Carbon::parse('2026-12-01')->addDays($number),'amount'=>'0.8','currency_type'=>$document->currency_type,
            'calculation_snapshot'=>\App\Support\Exact\Decimal::snapshot(['amount'=>'4/5'])]) extends Fluent {
                public function getStringPaymentMethodType(): string { return ''; }
            });
        $company=new Fluent(['name'=>'Emisor', 'number'=>'J087897598', 'logo'=>null]);
        $pdf=$this->paperPdf('a5');
        $pdf->SetHTMLFooter((new Template())->pdfFooter('default',$document));
        $pdf->WriteHTML((new Template())->pdfStylesheet('default','invoice',$document,'a5'),HTMLParserMode::HEADER_CSS);
        $pdf->WriteHTML((new Template())->pdf('default','invoice',$company,$document,'a5'),HTMLParserMode::HTML_BODY);
        self::assertGreaterThan(2,$pdf->page);
        $bytes=$pdf->Output('','S'); $path=tempnam(sys_get_temp_dir(),'sab-a5-ledger-');
        try {
            file_put_contents($path,$bytes);
            $process=new \Symfony\Component\Process\Process(['pdftotext','-layout',$path,'-']);
            $process->mustRun(); $text=$process->getOutput();
            self::assertSame(60,substr_count($text,'Recibido VES'));
            self::assertStringContainsString('Recibido VES 97.00',$text);
            self::assertStringContainsString('RET-LARGA-20',$text);
            self::assertStringContainsString('Cuota #40',$text);
            self::assertMatchesRegularExpression('/Cuota #40.*Monto: Bs\.0\.80/s',$text);
            self::assertSame($pdf->page,substr_count($text,'Para consultar el comprobante'));
            if ($directory=getenv('SAB_A5_FIXTURE_DIR')) file_put_contents($directory.'/default-ledger-long.pdf',$bytes);
        } finally { unlink($path); }
    }

    public function test_missing_design_uses_default_template_and_styles(): void
    {
        $document = $this->documentFixture();
        $document->number_full = '00000034';
        $company = new Fluent(['name' => 'Emisor', 'number' => 'J087897598', 'logo' => null]);
        $template = new Template();
        self::assertSame($template->pdf('default', 'invoice', $company, $document, 'a5'),
            $template->pdf('missing-design', 'invoice', $company, $document, 'a5'));
        self::assertSame($template->pdfStylesheet('default', 'invoice', $document, 'a5'),
            $template->pdfStylesheet('missing-design', 'invoice', $document, 'a5'));
    }

    public function test_a5_preserves_historical_unaffected_and_free_operation_amounts(): void
    {
        $document=$this->documentFixture();
        $document->number_full='00000034';
        $document->total=$document->subtotal='126';
        $document->total_value='110'; $document->total_unaffected='10'; $document->total_free='5';
        $company=new Fluent(['name'=>'Emisor', 'number'=>'J087897598', 'logo'=>null]);
        foreach (['default','modern-2027','marca_de_agua','Plantilla_personalizable'] as $design) {
            $html=(new Template())->pdf($design,'invoice',$company,$document,'a5');
            self::assertMatchesRegularExpression('/Total no sujeto:.*?10\.00/s',$html);
            self::assertMatchesRegularExpression('/OP\. GRATUITAS:.*?5\.00/s',$html);
            self::assertMatchesRegularExpression('/TOTAL A PAGAR:.*?126\.00/s',$html);
        }
    }

    public function test_a5_edit_preview_uses_the_authorized_source_without_changing_tickets(): void
    {
        \Tests\Support\InvoiceA4Ledger::create();
        $fixture=$this->documentFixture();
        $draft=new CurrentFiscalPdfDocumentFixture();
        foreach ($fixture->getAttributes() as $key=>$value) {
            if (in_array($key,['person','invoice','note','currency_type','document_type','state_type','items','payments','fee','reference_guides','dispatch','transport','quotation','seller','payment_condition'],true)) $draft->setRelation($key,$value);
            else $draft->forceFill([$key=>$key === 'additional_information' ? '' : $value]);
        }
        $receipt=new \App\Models\Tenant\DocumentPayment(['id'=>1,'currency_type_id'=>'VES','original_amount'=>'80',
            'payment'=>'80','tax_amount'=>'0','change'=>'10','exchange_rate'=>'40.12345678',
            'calculation_snapshot'=>\App\Support\Exact\Decimal::snapshot(['total_tendered'=>'90','received_change'=>'10'])]);
        $receipt->exists=true;
        $draft->setRelation('payments',new \Illuminate\Database\Eloquent\Collection([$receipt]));
        $draft->setRelation('received_retentions',new Collection());
        $draft->setRelation('fiscal_data',null);
        $draft->setRelation('taxes',new Collection());
        $draft->setRelation('guarantee_fund',null);
        $draft->setRelation('currency_totals',null);
        $source=clone $draft;
        $source->forceFill(['id'=>1,'series'=>'AUT','number'=>99,'control_number'=>'00-00000099',
            'total'=>'116','total_payable'=>'116','fiscal_environment'=>'production','fiscal_emission_mode'=>'free_form',
            'issuer'=>['name'=>'Emisor autorizado','number'=>'J087897598','logo'=>null]]);
        $source->exists=true;
        $original=clone $receipt;
        $original->setRelation('change_disbursements',new \Illuminate\Database\Eloquent\Collection([
            new \App\Models\Tenant\DocumentChangeDisbursement(['currency_type_id'=>'VES','original_amount'=>'10'])]));
        $source->setRelation('payments',new \Illuminate\Database\Eloquent\Collection([$original]));
        $source->setRelation('received_retentions',new \Illuminate\Database\Eloquent\Collection([
            new \App\Models\Tenant\DocumentReceivedRetention(['tax_kind'=>'IVA','voucher_number'=>'RET-AUTORIZADA',
                'applied_amount'=>'12','amount'=>'12','application_status'=>'applied','currency_type_id'=>'VES'])]));
        $source->setRelation('fiscal_data',new \App\Models\Tenant\DocumentFiscalData(['islr_retention_prevision'=>[
            'eligible'=>true,'amount'=>'1','percentage'=>'1','currency_type_id'=>'VES'], 'transaction_type_id'=>'99',
            'third_party'=>['rif'=>'J-11111111-1','name'=>'Tercero conservado','address'=>'Dirección del tercero'],
            'special_tax_regime_id'=>1,'catalog_snapshot'=>['special_tax_regime_id'=>['description'=>'Régimen conservado']]]));
        DB::connection('tenant')->table('document_payments')->insert(['id'=>1,'document_id'=>1,'payment'=>'80']);
        $draft->setRelation('invoice_source_document',$source);
        $company=new Fluent(['name'=>'Emisor actual','number'=>'J999999999','logo'=>null]);
        foreach (['a4','a5'] as $format) {
            $html=(new Template())->pdf('default','invoice',$company,$draft,$format);
            self::assertStringContainsString('N° de documento: <strong>'.($format === 'a4' ? '00000099' : 'AUT-00000099').'</strong>',$html);
            self::assertStringContainsString('N° de control: <strong>00-00000099</strong>',$html);
            self::assertStringContainsString('Emisor autorizado',$html);
            self::assertStringContainsString('RET-AUTORIZADA',$html);
            self::assertStringContainsString('DEL DOCUMENTO GUARDADO',$html);
            self::assertStringContainsString('Recibido VES 90.00',$html);
            self::assertStringContainsString('Vuelto VES 10.00',$html);
            self::assertStringContainsString('Tercero conservado',$html);
            self::assertStringContainsString('Régimen conservado',$html);
            self::assertTrue(strpos($html,'data-invoice-'.$format.'-identity') < strpos($html,'VENTA POR CUENTA DE TERCEROS'));
            self::assertStringNotContainsString('Esta factura es solo de prueba',$html);
        }
        self::assertFalse($draft->payments->first()->relationLoaded('change_disbursements'), 'Rendering must not mutate the caller preview.');
        $draft->items->first()->additional_information=[];
        $ticket=(new Template())->pdf('default','invoice',$company,$draft,'ticket');
        self::assertStringNotContainsString('RET-AUTORIZADA',$ticket);
        self::assertStringNotContainsString('Emisor autorizado',$ticket);
        self::assertStringNotContainsString('data-invoice-a5-',$ticket);
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
        Schema::connection('tenant')->create('payment_method_types', function ($table): void {
            $table->string('id')->primary(); $table->string('description');
        });
        Schema::connection('tenant')->create('document_payments', function ($table): void {
            $table->increments('id'); $table->string('payment_method_type_id'); $table->string('currency_type_id');
            $table->string('exchange_rate'); $table->string('original_amount'); $table->string('payment');
            $table->string('tax_amount'); $table->string('change'); $table->text('payment_method_snapshot');
        });
        DB::connection('tenant')->table('payment_method_types')->insert(['id'=>'01','description'=>'Nombre actual']);
        DB::connection('tenant')->table('document_payments')->insert(['id'=>1,'payment_method_type_id'=>'01',
            'currency_type_id'=>'VES','exchange_rate'=>'10.123','original_amount'=>'100','payment'=>'9.88',
            'tax_amount'=>'0','change'=>'0','payment_method_snapshot'=>json_encode(['description'=>'Medio conservado'])]);
        $receipt=\App\Models\Tenant\DocumentPayment::findOrFail(1);
        $receipt->setRelation('change_disbursements', new Collection());
        $document->setRelation('payments',new Collection([$receipt]));
        $company=new Fluent(['name'=>'Nombre actual cambiado','number'=>'J-99999999-9','trade_name'=>'Actual','logo'=>null]);
        $html=(new Template())->pdf('default','invoice',$company,$document,'a4',['is_preview'=>false,'enabled_price_items_dispatch'=>false]);
        self::assertStringContainsString('Emisor conservado',$html);self::assertStringNotContainsString('Nombre actual cambiado',$html);
        self::assertStringContainsString('IGTF 3.00%',$html);self::assertStringContainsString('IVA-TEST-1',$html);self::assertStringContainsString('Fondo de garantía',$html);
        self::assertStringContainsString('1,209.50',$html);
        self::assertStringNotContainsString('Medio conservado',$html);
        self::assertStringContainsString('Recibido VES 100.00',$html);
        self::assertStringContainsString('Aplicado USD 9.88',$html);
        self::assertStringNotContainsString('USD 100.00',$html);
        $document->items->first()->additional_information = [];
        $ticket = (new Template())->pdf('default', 'invoice', $company, $document, 'ticket', ['is_preview'=>false]);
        self::assertStringContainsString('Recibido VES 100.00',$ticket);
        self::assertStringContainsString('Aplicado USD 9.88',$ticket);
        // ######## INICIO TASA Y REPRESENTACIÓN FINAL EN TICKETS ########
        $document->fiscal_emission_mode = 'free_form';
        foreach (self::invoice58Designs() as [$design]) {
            foreach (['ticket', 'ticket_58'] as $format) {
                $rendered = (new Template())->pdf($design, 'invoice', $company, $document, $format, ['is_preview'=>false]);
                self::assertSame(0, substr_count($rendered, 'Tasa del cobro (VES/USD)'), $design.' '.$format);
                self::assertSame(1, substr_count($rendered, 'Tasa del cobro VES/USD'), $design.' '.$format);
                self::assertLessThan(strpos($rendered, 'Tasa del cobro VES/USD'), strpos($rendered, 'Cobro recibido (VES)'), $design.' '.$format);
                self::assertSame(1, substr_count($rendered, 'Representacion impresa de la Factura'), $design.' '.$format);
                $plainText = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($rendered), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                self::assertStringEndsWith('Representacion impresa de la Factura', $plainText, $design.' '.$format);
            }
        }
        $originalDocumentCurrency = $document->currency_type_id;
        $originalReceiptCurrency = $receipt->currency_type_id;
        $originalRate = $receipt->exchange_rate;
        $document->currency_type_id = 'VES';
        $receipt->currency_type_id = 'USD';
        $receipt->exchange_rate = '922.56370000';
        foreach (self::invoice58Designs() as [$design]) {
            foreach (['ticket', 'ticket_58'] as $format) {
                $rendered = (new Template())->pdf($design, 'invoice', $company, $document, $format, ['is_preview'=>false]);
                self::assertSame(0, substr_count($rendered, 'Tasa del cobro (VES/USD)'), $design.' '.$format);
                self::assertSame(1, substr_count($rendered, 'Tasa del cobro VES/USD'), $design.' '.$format);
                self::assertStringContainsString('922,56370000', $rendered, $design.' '.$format);
                self::assertLessThan(strpos($rendered, 'Tasa del cobro VES/USD'), strpos($rendered, 'Cobro recibido (USD)'), $design.' '.$format);
            }
        }
        $document->currency_type_id = $originalDocumentCurrency;
        $receipt->currency_type_id = $originalReceiptCurrency;
        $receipt->exchange_rate = $originalRate;
        $secondReceipt = clone $receipt;
        $secondReceipt->id = 2;
        $secondReceipt->exchange_rate = '20.12345678';
        $document->setRelation('payments', new Collection([$receipt, $secondReceipt]));
        foreach (self::invoice58Designs() as [$design]) {
            foreach (['ticket', 'ticket_58'] as $format) {
                $rendered = (new Template())->pdf($design, 'invoice', $company, $document, $format, ['is_preview'=>false]);
                self::assertSame(2, substr_count($rendered, 'Tasa del cobro VES/USD'), $design.' '.$format);
                self::assertSame(0, substr_count($rendered, 'Tasa del cobro (VES/USD)'), $design.' '.$format);
                self::assertStringContainsString('20,12345678', $rendered, $design.' '.$format);
            }
        }
        $secondReceipt->receipt_parent_id = 1;
        foreach (self::invoice58Designs() as [$design]) {
            foreach (['ticket', 'ticket_58'] as $format) {
                $rendered = (new Template())->pdf($design, 'invoice', $company, $document, $format, ['is_preview'=>false]);
                self::assertSame(1, substr_count($rendered, 'Tasa del cobro VES/USD'), $design.' '.$format);
                self::assertSame(1, substr_count($rendered, 'Tasa del cobro (VES/USD)'), $design.' '.$format);
            }
        }
        $document->setRelation('payments', new Collection([$receipt]));
        $preview = $this->documentFixture();
        $preview->currency_type_id = 'USD';
        $preview->items->first()->additional_information = [];
        $preview->payments = new Collection([$receipt]);
        foreach (self::invoice58Designs() as [$design]) {
            foreach (['ticket', 'ticket_58'] as $format) {
                $rendered = (new Template())->pdf($design, 'invoice', $company, $preview, $format, ['is_preview'=>true]);
                self::assertSame(1, substr_count($rendered, 'Tasa del cobro (VES/USD)'), $design.' '.$format.' sin resumen');
                self::assertSame(0, substr_count($rendered, 'Tasa del cobro VES/USD'), $design.' '.$format.' sin resumen');
            }
        }
        // ######## FIN TASA Y REPRESENTACIÓN FINAL EN TICKETS ########
        foreach (['ticket_58'] as $format) {
            $otherFormat = (new Template())->pdf('default', 'invoice', $company, $document, $format, ['is_preview'=>false]);
            self::assertStringContainsString('Medio conservado', $otherFormat);
            self::assertStringContainsString('Recibido VES 100.00', $otherFormat);
            self::assertStringContainsString('Aplicado USD 9.88', $otherFormat);
        }
        foreach (['modern-2027'=>['a4','a5','ticket','ticket_58'],
                  'Plantilla_personalizable'=>['a4','a5','ticket','ticket_58'],
                  'marca_de_agua'=>['a4','a5','ticket','ticket_58','ticket_50'],] as $template => $formats) {
            foreach ($formats as $format) {
                $rendered = (new Template())->pdf($template, 'invoice', $company, $document, $format, ['is_preview'=>false]);
                if (!in_array($format, ['a4','a5'], true)) self::assertStringContainsString('Medio conservado', $rendered, "$template $format");
                else self::assertStringNotContainsString('Medio conservado', $rendered, "$template $format");
                self::assertStringContainsString('Recibido VES 100.00', $rendered, "$template $format");
                self::assertStringContainsString('Aplicado USD 9.88', $rendered, "$template $format");
            }
        }
        // A receipt's real change stays in the received currency; a reversed receipt disappears.
        $receipt->change='3';
        $receipt->calculation_snapshot=\App\Support\Exact\Decimal::snapshot(['tendered_original_amount'=>'103', 'received_change'=>'3']);
        foreach (['default'=>['a4','a5','ticket','ticket_58'], 'modern-2027'=>['a4','a5','ticket','ticket_58'],
                  'Plantilla_personalizable'=>['a4','a5','ticket','ticket_58'],
                  'marca_de_agua'=>['a4','a5','ticket','ticket_58','ticket_50'],] as $template=>$formats) {
            foreach ($formats as $format) {
                $rendered=(new Template())->pdf($template,'invoice',$company,$document,$format,['is_preview'=>false]);
                self::assertStringContainsString('Vuelto VES 3.00',$rendered,"$template $format cambio");
                self::assertStringNotContainsString('VUELTO:',$rendered,"$template $format no deriva vuelto del saldo");
            }
        }
        $receipt->reversed_at='2026-09-11 00:00:00';
        foreach (['default'=>['a4','a5','ticket','ticket_58'], 'modern-2027'=>['a4','a5','ticket','ticket_58'],
                  'Plantilla_personalizable'=>['a4','a5','ticket','ticket_58'],
                  'marca_de_agua'=>['a4','a5','ticket','ticket_58','ticket_50'],] as $template=>$formats) {
            foreach ($formats as $format) {
                $rendered=(new Template())->pdf($template,'invoice',$company,$document,$format,['is_preview'=>false]);
                self::assertStringNotContainsString('Medio conservado',$rendered,"$template $format pago revertido");
                self::assertStringNotContainsString('Cobro recibido (VES)',$rendered,"$template $format resumen sin pago revertido");
            }
        }
        $receipt->reversed_at=null; $receipt->change='0';
        self::assertSame(1, substr_count($ticket, 'Tasa VES/USD'));
        self::assertSame(1, substr_count($ticket, 'Total en VES'));
        self::assertSame(1, substr_count($ticket, 'IGTF 3.00%'));
        $receipt->payment_method_snapshot=['description'=>'Medio conservado','receipt_origin'=>['type'=>'test','id'=>'1']];
        $reused=(new Template())->pdf('default','invoice',$company,$document,'a4',['is_preview'=>false,'enabled_price_items_dispatch'=>false]);
        self::assertStringContainsString('(cobro previo)', $reused);
        $receipt->original_amount=null;
        $receipt->exchange_rate=null;
        $receipt->calculation_snapshot=null;
        $legacy=(new Template())->pdf('default','invoice',$company,$document,'a4',['is_preview'=>false,'enabled_price_items_dispatch'=>false]);
        self::assertStringContainsString('Importe recibido no disponible', $legacy);
        self::assertStringNotContainsString('Cobro previo recibido (VES)', $legacy);
        $pdf=new Mpdf(['tempDir'=>sys_get_temp_dir(),'default_font'=>'arial']);
        $pdf->WriteHTML(file_get_contents(app_path('CoreFacturalo/Templates/pdf/default/style.css')),HTMLParserMode::HEADER_CSS);
        $pdf->WriteHTML($html,HTMLParserMode::HTML_BODY);
        $path=getenv('SAB_FISCAL_PDF_FIXTURE_PATH') ?: sys_get_temp_dir().'/sab-fiscal-snapshot.pdf';
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
        self::assertStringContainsString('IGTF sobre pago posterior',$html);self::assertStringContainsString('FF01-00000001',$html);
        self::assertStringContainsString('IGTF 3.00%',$html);self::assertStringNotContainsString('Producto gravado de prueba',$html);
        $compact=(new Template())->pdf('default','debit',$company,$document,'ticket_58');
        self::assertStringContainsString('Otros impuestos',$compact); self::assertStringContainsString('3.48',$compact);
        self::assertStringContainsString('TOTAL NOTA DE DÉBITO',$compact);
        $pdf=new Mpdf(['tempDir'=>sys_get_temp_dir(),'default_font'=>'arial']);
        $pdf->WriteHTML(file_get_contents(app_path('CoreFacturalo/Templates/pdf/default/style.css')),HTMLParserMode::HEADER_CSS);
        $pdf->WriteHTML($html,HTMLParserMode::HTML_BODY);
        $path=sys_get_temp_dir().'/sab-fiscal-igtf-note.pdf';file_put_contents($path,$pdf->Output('','S'));self::assertFileExists($path);
    }

    public function invoiceSeries(): array
    {
        return [['FF01',2], ['',2], ['AB-CD123456789012345',2], ['FF01',6], ['FF01',12]];
    }

    public function test_existing_ticket_50_template_keeps_exact_quantities_in_its_original_cell(): void
    {
        $document=$this->documentFixture();$row=$document->items->first();$row->additional_information=[];
        $company=new Fluent(['name'=>'Empresa de prueba','number'=>'J-12345678-9','trade_name'=>'Prueba','logo'=>null]);
        foreach (['9007199254740993.01',\App\Support\Exact\Decimal::of(1)->dividedBy('3000000000000'),'3'] as $quantity) {
            $row->quantity=\App\Support\Exact\Decimal::represent($quantity);
            $row->calculation_snapshot=\App\Support\Exact\Decimal::snapshot(['quantity'=>$quantity]);
            $before=$row->getAttributes();
            $html=(new Template())->pdf('marca_de_agua','invoice',$company,$document,'ticket_50',['is_preview'=>false]);
            $expected=\App\Support\Exact\PdfQuantities::line($row);
            $dom=new \DOMDocument();@$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
            $xpath=new \DOMXPath($dom);$found=false;
            foreach ($xpath->query('//td') as $cell) {
                if (trim($cell->textContent)!==$expected) continue;
                $unit=$xpath->query('following-sibling::td[1]',$cell)->item(0);
                if ($unit && trim($unit->textContent)==='UND') {
                    self::assertCount(5,$xpath->query('./td',$cell->parentNode));
                    $found=true;
                }
            }
            self::assertTrue($found,'La cantidad exacta se conserva en la celda original anterior a la unidad.');
            self::assertSame($before,$row->getAttributes());
        }
    }

    public function test_invoice_pdfs_do_not_print_a_positive_subprecision_quantity_as_zero(): void
    {
        $document=$this->documentFixture();$row=$document->items->first();$row->additional_information=[];
        $row->quantity='0';$row->calculation_snapshot=\App\Support\Exact\Decimal::snapshot([
            'quantity'=>\App\Support\Exact\Decimal::of(1)->dividedBy('3000000000000')]);
        $company=new Fluent(['name'=>'Empresa de prueba','number'=>'J-12345678-9','trade_name'=>'Prueba','logo'=>null]);
        $manifest=[];$run=getenv('SAB_POSTGRES_RUN_ID');
        $directory=preg_match(\Tests\Support\PostgresTestEnvironment::RUN_ID_PATTERN,(string)$run)
            ? base_path('informes/evidencias/postgresql_17_implementacion/resultados/invoice-subprecision-pdf-'.$run) : null;
        if ($directory && !is_dir($directory)) mkdir($directory,0755,true);
        foreach (['default'=>['a4','a5','ticket','ticket_58'], 'modern-2027'=>['a4','a5','ticket','ticket_58'],
            'Plantilla_personalizable'=>['a4','a5','ticket','ticket_58'],
            'marca_de_agua'=>['a4','a5','ticket','ticket_58','ticket_50'],] as $template=>$formats) {
            foreach ($formats as $format) {
                $html=(new Template())->pdf($template,'invoice',$company,$document,$format,['is_preview'=>false]);
                self::assertStringContainsString('&lt;0.000000000001',$html,$template.'/'.$format);
                if ($format==='a5') self::assertStringContainsString('Productos: &lt;0.000000000001 UND',$html,$template.' resumen A5');
                preg_match_all('~<td\b[^>]*>(.*?)</td>~s',$html,$cells);
                $detail=false;
                foreach ($cells[1] as $cell) {
                    $text=trim(preg_replace('/\s+/',' ',html_entity_decode(strip_tags($cell),ENT_QUOTES,'UTF-8')));
                    if (preg_match('~^(?:Cantidad:\s*)?<0\.000000000001(?:$|\s+(?:UND|KG|SERV)\b)~',$text)) $detail=true;
                }
                self::assertTrue($detail,$template.'/'.$format.': quantity must appear in the line, independently of the summary');
                // The application downloads A5 landscape (210 x 148 mm).
                $width=match ($format) {'ticket_50'=>50,'ticket_58'=>56,'ticket'=>72,default=>210};
                $pdf=new Mpdf(['tempDir'=>sys_get_temp_dir(),'format'=>[$width,400],
                    'margin_left'=>2,'margin_right'=>2,'margin_top'=>2,'margin_bottom'=>2]);
                $pdf->WriteHTML(file_get_contents(app_path('CoreFacturalo/Templates/pdf/'.$template.'/style.css')),HTMLParserMode::HEADER_CSS);
                $pdf->WriteHTML($html,HTMLParserMode::HTML_BODY);$bytes=$pdf->Output('','S');
                $path=tempnam(sys_get_temp_dir(),'pro9-quantity-');file_put_contents($path,$bytes);
                try {
                    $process=proc_open(['pdftotext','-layout',$path,'-'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
                    fclose($pipes[0]);$text=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
                    self::assertSame(0,proc_close($process),$errors);
                    \Tests\Support\PdfPrintedValue::assertNumber('<0.000000000001',$text,$template.'/'.$format.' cantidad',$path);
                } finally {unlink($path);}
                if ($directory) {
                    $name=$template.'-'.$format.'.pdf';file_put_contents($directory.'/'.$name,$bytes);
                    $manifest[]=['file'=>$name,'sha256'=>hash('sha256',$bytes),'quantity_fraction'=>['n'=>'1','d'=>'3000000000000'],
                        'printed_quantity'=>'<0.000000000001','pages'=>$pdf->page];
                }
            }
        }
        if ($directory) file_put_contents($directory.'/manifest.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL);
    }

    public function test_quotations_render_all_designs_without_a_missing_logo(): void
    {
        $document = $this->documentFixture();
        $document->pdf_title = 'COT-00000001';
        $document->items->first()->additional_information = [];
        $document->items->first()->percentage_igv = '16';
        $company = new Fluent(['name'=>'Empresa de prueba', 'trade_name'=>'Prueba', 'number'=>'J234534534', 'logo'=>'no-existe.png']);
        foreach (self::invoice58Designs() as [$design]) {
            foreach (['a4','a5','ticket'] as $format) {
                $html=(new Template())->pdf($design,'quotation',$company,$document,$format,['is_preview'=>false]);
                self::assertStringContainsString('COTIZACIÓN', mb_strtoupper($html), $design.' '.$format);
                self::assertStringNotContainsString('data:image/', $html, $design.' '.$format);
                $size = $format === 'ticket' ? [56,400] : ($format === 'a5' ? [210,148] : 'A4');
                $pdf = new Mpdf(['tempDir'=>sys_get_temp_dir(), 'format'=>$size, 'margin_left'=>2,'margin_right'=>2,'margin_top'=>2,'margin_bottom'=>2]);
                $pdf->WriteHTML(file_get_contents(app_path('CoreFacturalo/Templates/pdf/'.$design.'/style.css')), HTMLParserMode::HEADER_CSS);
                $pdf->WriteHTML($html, HTMLParserMode::HTML_BODY);
                self::assertStringStartsWith('%PDF-', $pdf->Output('', 'S'));
            }
        }
    }

    public function test_transfer_and_claim_pdf_render_without_a_configured_logo_file(): void
    {
        $company = new Fluent(['name'=>'Empresa de prueba', 'number'=>'J234534534', 'logo'=>'ausente.png']);
        $warehouse = new class { public function getDescription() { return 'Almacén de prueba'; } };
        $user = new class { public function getName() { return 'Usuario de prueba'; } };
        $transfer = view()->file(base_path('modules/Inventory/Resources/views/transfers/export/partial/header.blade.php'), [
            'pdf'=>true, 'data'=>['company'=>$company,'warehouse_from'=>$warehouse,'warehouse_to'=>$warehouse,'user'=>$user],
        ])->render();
        self::assertStringContainsString('NOTA DE TRASLADO', $transfer);
        self::assertStringNotContainsString('data:image/', $transfer);
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($transfer); $dompdf->render();
        self::assertStringStartsWith('%PDF-', $dompdf->output());
        $claim = new Fluent(['code'=>'REC-001', 'claim_type'=>'reclamo', 'created_at'=>Carbon::parse('2026-01-01')]);
        $claimHtml = view()->file(base_path('modules/ClaimsBook/Resources/views/pdf/claim.blade.php'), [
            'company'=>$company,'claim'=>$claim,'establishment'=>null,'channelEstablishment'=>null,
        ])->render();
        self::assertStringContainsString('REC-001', $claimHtml);
        self::assertStringNotContainsString('data:image/', $claimHtml);
        $pdf = new Mpdf(['tempDir'=>sys_get_temp_dir()]); $pdf->WriteHTML($claimHtml, HTMLParserMode::HTML_BODY);
        self::assertStringStartsWith('%PDF-', $pdf->Output('', 'S'));
    }

    public function test_representative_commercial_pdfs_render_without_a_missing_logo(): void
    {
        $document = $this->documentFixture();
        $document->number_full = 'DOC-00000001';
        $document->pdf_title = 'DOC-00000001';
        $document->prefix = 'PR';
        $document->id = 1;
        $document->user = new Fluent(['name'=>'Usuario de prueba']);
        $document->seller = new Fluent(['name'=>'']);
        $document->supplier = $document->customer;
        $document->items->first()->additional_information = [];
        $document->items->first()->percentage_igv = '16';
        $company = new Fluent(['name'=>'Empresa de prueba', 'trade_name'=>'Prueba', 'number'=>'J234534534', 'logo'=>'no-existe.png']);
        foreach ([['pedido','ticket'], ['order_note','ticket'], ['purchase','a4'], ['contract','a4']] as [$type,$format]) {
            foreach (self::invoice58Designs() as [$design]) {
                $html=(new Template())->pdf($design,$type,$company,$document,$format,['is_preview'=>false]);
                self::assertStringNotContainsString('data:image/', $html, $design.' '.$type);
                $pdf = new Mpdf(['tempDir'=>sys_get_temp_dir(), 'format'=>$format === 'ticket' ? [72,400] : 'A4']);
                $pdf->WriteHTML(file_get_contents(app_path('CoreFacturalo/Templates/pdf/'.$design.'/style.css')), HTMLParserMode::HEADER_CSS);
                $pdf->WriteHTML($html, HTMLParserMode::HTML_BODY);
                self::assertStringStartsWith('%PDF-', $pdf->Output('', 'S'));
            }
        }
        $document->customer->address = '';
        $document->delivery_date = Carbon::parse('2026-12-14');
        $contract = (new Template())->pdf('marca_de_agua','contract',$company,$document,'a4',['is_preview'=>false]);
        self::assertStringContainsString('Fecha de entrega', $contract);
        self::assertStringContainsString('2026-12-14', $contract);
    }

    public function test_delivery_order_pdfs_render_without_a_missing_logo(): void
    {
        Schema::connection('tenant')->create('districts', function ($table): void { $table->string('id')->primary(); });
        $document = $this->documentFixture();
        $document->number_full = 'OE-00000001';
        $document->document_type_id = '09';
        $document->document_type = new Fluent(['id'=>'09', 'description'=>'ORDEN DE ENTREGA']);
        $document->date_of_shipping = Carbon::parse('2026-09-10');
        $document->transport_mode_type_id = '02';
        $document->transport_mode_type = new Fluent(['description'=>'Transporte privado']);
        $document->driver = new Fluent(['name'=>'Conductor', 'number'=>'V12345678', 'license'=>'123']);
        $document->user = new Fluent(['name'=>'Usuario de prueba']);
        $document->items->first()->additional_information = [];
        $company = new Fluent(['name'=>'Empresa de prueba', 'trade_name'=>'Prueba', 'number'=>'J234534534', 'logo'=>'no-existe.png']);
        foreach (self::invoice58Designs() as [$design]) {
            foreach (['a4','ticket','ticket_58'] as $format) {
                $html=(new Template())->pdf($design,'dispatch',$company,$document,$format,['is_preview'=>false,'enabled_price_items_dispatch'=>false]);
                self::assertStringContainsString('ORDEN DE ENTREGA', mb_strtoupper($html), $design.' '.$format);
                $pdf = new Mpdf(['tempDir'=>sys_get_temp_dir(), 'format'=>$format === 'a4' ? 'A4' : [56,400]]);
                $pdf->WriteHTML(file_get_contents(app_path('CoreFacturalo/Templates/pdf/'.$design.'/style.css')), HTMLParserMode::HEADER_CSS);
                $pdf->WriteHTML($html, HTMLParserMode::HTML_BODY);
                self::assertStringStartsWith('%PDF-', $pdf->Output('', 'S'));
            }
        }
    }

    public function test_invoice_tickets_handle_missing_and_available_logos(): void
    {
        $public = public_path();
        $disk = config('filesystems.disks.public');
        $directory = sys_get_temp_dir().'/sab-ticket-logo-'.bin2hex(random_bytes(6));
        mkdir($directory.'/storage/uploads/logos', 0775, true);
        $companyImage = file_get_contents($public.'/logo/tulogo.png');
        $branchImage = $companyImage."\nbranch";
        file_put_contents($directory.'/storage/uploads/logos/company.png', $companyImage);
        file_put_contents($directory.'/storage/uploads/logos/branch.png', $branchImage);
        file_put_contents($directory.'/storage/uploads/logos/broken.png', 'invalid image');
        file_put_contents($directory.'/storage/uploads/logos/truncated.png', substr($companyImage, 0, 33));
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="60" height="30"><rect width="60" height="30" fill="#123456"/></svg>';
        file_put_contents($directory.'/storage/uploads/logos/logo.svg', $svg);
        file_put_contents($directory.'/storage/uploads/logos/external.svg', '<svg xmlns="http://www.w3.org/2000/svg"><image href="file:///etc/passwd"/></svg>');
        file_put_contents($directory.'/storage/uploads/logos/empty.svg', '');
        $this->app->usePublicPath($directory);
        config()->set('filesystems.disks.public', ['driver'=>'local', 'root'=>$directory.'/storage']);
        \Illuminate\Support\Facades\Storage::forgetDisk('public');
        try {
            $document = $this->documentFixture();
            $document->number_full = 'FF01-00000012';
            $document->items->first()->additional_information = [];
            $company = new Fluent(['name'=>'Empresa', 'trade_name'=>'Prueba', 'number'=>'J234534534', 'logo'=>'missing.png']);
            foreach (self::invoice58Designs() as [$design]) {
                foreach (['ticket','ticket_58'] as $format) {
                    $template = new Template();
                    $html = $template->pdf($design, 'invoice', $company, $document, $format, ['is_preview'=>false]);
                    self::assertStringContainsString('FACTURA', $html, $design.' '.$format);
                    self::assertStringNotContainsString('data:image/', $html, $design.' '.$format);
                    $pdf = new Mpdf(['tempDir'=>sys_get_temp_dir(), 'format'=>[$format === 'ticket' ? 72 : 56,400], 'margin_left'=>2,'margin_right'=>2,'margin_top'=>2,'margin_bottom'=>2]);
                    $pdf->WriteHTML(file_get_contents(app_path('CoreFacturalo/Templates/pdf/'.$design.'/style.css')), HTMLParserMode::HEADER_CSS);
                    $pdf->WriteHTML($html, HTMLParserMode::HTML_BODY);
                    self::assertStringStartsWith('%PDF-', $pdf->Output('', 'S'));
                }
            }
            foreach (self::invoice58Designs() as [$design]) {
                foreach (['ticket','ticket_58'] as $format) {
                    $html = (new Template())->pdf($design, 'sale_note', $company, $document, $format, ['is_preview'=>false]);
                    self::assertStringContainsString('NOTA DE VENTA', mb_strtoupper($html), $design.' '.$format);
                    self::assertStringNotContainsString('data:image/', $html, $design.' '.$format);
                }
            }
            $helper = \App\CoreFacturalo\Helpers\Template\TemplateHelper::class;
            $company->logo = 'company.png';
            $document->establishment->logo = 'storage/uploads/logos/branch.png';
            self::assertSame('data:image/png;base64,'.base64_encode($branchImage), $helper::ticketLogoDataUri($company, $document->establishment));
            foreach (array_merge([['default', 'ticket']], array_map(fn ($entry) => [$entry[0], 'ticket_58'], self::invoice58Designs())) as [$design, $format]) {
                $html = view('pdf.'.$design.'.invoice_'.$format, [
                    'company'=>$company, 'document'=>$document, 'configuration'=>['is_preview'=>false],
                    'ticketInvoice'=>true, 'ticketFiscal'=>['environment'=>'demo', 'number_full'=>'FF01-00000012', 'control_number'=>'00-00000009'],
                ])->render();
                self::assertStringContainsString(base64_encode($branchImage), $html);
                self::assertSame(1, substr_count($html, 'DEMO — SIN VALIDEZ FISCAL'));
                self::assertLessThan(strpos($html, 'DEMO — SIN VALIDEZ FISCAL'), strpos($html, base64_encode($branchImage)), $design.' '.$format);
                self::assertLessThan(strpos($html, 'Empresa'), strpos($html, 'DEMO — SIN VALIDEZ FISCAL'), $design.' '.$format);
                $pdf = new Mpdf(['tempDir'=>sys_get_temp_dir(), 'format'=>[$format === 'ticket' ? 72 : 56,400], 'margin_left'=>2,'margin_right'=>2,'margin_top'=>2,'margin_bottom'=>2]);
                $pdf->WriteHTML(file_get_contents(app_path('CoreFacturalo/Templates/pdf/'.$design.'/style.css')), HTMLParserMode::HEADER_CSS);
                $pdf->WriteHTML($html, HTMLParserMode::HTML_BODY);
                self::assertStringStartsWith('%PDF-', $pdf->Output('', 'S'));
            }
            $document->pdf_title = 'COT-00000001';
            $document->items->first()->percentage_igv = '16';
            foreach (self::invoice58Designs() as [$design]) {
                foreach (['a4','a5','ticket'] as $format) {
                    $expectedLogo = $format === 'a5' && $design !== 'marca_de_agua' ? $companyImage : $branchImage;
                    $quotation = (new Template())->pdf($design,'quotation',$company,$document,$format,['is_preview'=>false]);
                    self::assertStringContainsString(base64_encode($expectedLogo), $quotation, $design.' '.$format);
                }
            }
            $document->establishment->logo = 'storage/uploads/logos/missing.png';
            self::assertSame('data:image/png;base64,'.base64_encode($companyImage), $helper::ticketLogoDataUri($company, $document->establishment));
            $document->establishment->logo = 'storage/uploads/logos/broken.png';
            self::assertSame('data:image/png;base64,'.base64_encode($companyImage), $helper::ticketLogoDataUri($company, $document->establishment));
            $document->establishment->logo = 'storage/uploads/logos/truncated.png';
            self::assertSame('data:image/png;base64,'.base64_encode($companyImage), $helper::ticketLogoDataUri($company, $document->establishment));
            $document->establishment->logo = 'storage/uploads/logos/../../../fuera.png';
            self::assertSame('data:image/png;base64,'.base64_encode($companyImage), $helper::ticketLogoDataUri($company, $document->establishment));
            $company->logo = null;
            $document->establishment->logo = 'storage/uploads/logos/branch.png';
            $html = (new Template())->pdf('default','invoice',$company,$document,'ticket',['is_preview'=>false]);
            self::assertStringContainsString(base64_encode($branchImage), $html);
            $document->establishment->logo = 'storage/uploads/logos/broken.png';
            self::assertNull($helper::ticketLogoDataUri($company, $document->establishment));
            $company->logo = 'logo.svg';
            $document->establishment->logo = null;
            self::assertSame('data:image/svg+xml;base64,'.base64_encode($svg), $helper::ticketLogoDataUri($company, $document->establishment));
            $document->establishment->logo = 'storage/uploads/logos/external.svg';
            self::assertSame('data:image/svg+xml;base64,'.base64_encode($svg), $helper::ticketLogoDataUri($company, $document->establishment));
            $document->establishment->logo = 'storage/uploads/logos/empty.svg';
            self::assertSame('data:image/svg+xml;base64,'.base64_encode($svg), $helper::ticketLogoDataUri($company, $document->establishment));
            $document->establishment->logo = null;
            $svgHtml = (new Template())->pdf('default','invoice',$company,$document,'ticket',['is_preview'=>false]);
            self::assertStringContainsString(base64_encode($svg), $svgHtml);
            $pdf = new Mpdf(['tempDir'=>sys_get_temp_dir(), 'format'=>[72,400], 'margin_left'=>2,'margin_right'=>2,'margin_top'=>2,'margin_bottom'=>2]);
            $pdf->WriteHTML($svgHtml, HTMLParserMode::HTML_BODY);
            self::assertStringStartsWith('%PDF-', $pdf->Output('', 'S'));
        } finally {
            $this->app->usePublicPath($public);
            config()->set('filesystems.disks.public', $disk);
            \Illuminate\Support\Facades\Storage::forgetDisk('public');
            (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($directory);
        }
    }

    public function test_default_invoice_ticket_places_identity_totals_and_payment_as_requested(): void
    {
        $document = $this->documentFixture();
        $document->number_full = 'FF01-00000012';
        $document->items->first()->additional_information = [];
        $company = new Fluent(['name'=>'Empresa de prueba', 'number'=>'J234534534', 'trade_name'=>'Prueba', 'logo'=>null]);
        $html = (new Template())->pdf('default', 'invoice', $company, $document, 'ticket', ['is_preview'=>false]);
        self::assertSame(1, substr_count($html, 'R.I.F. J-23453453-4'));
        self::assertSame(1, substr_count($html, 'N° :'));
        self::assertSame(1, substr_count($html, 'N° de control:'));
        self::assertStringContainsString('N° : <strong>FF01-00000012</strong>', $html);
        self::assertStringContainsString('N° de control: <strong>No asignado</strong>', $html);
        self::assertTrue(strpos($html, 'R.I.F. J-23453453-4') < strpos($html, 'FACTURA'));
        self::assertTrue(strpos($html, 'FACTURA') < strpos($html, 'N° :'));
        self::assertTrue(strpos($html, 'N° :') < strpos($html, 'N° de control:'));
        self::assertTrue(strpos($html, 'SUBTOTAL:') < strpos($html, 'TOTAL A PAGAR:'));
        self::assertTrue(strpos($html, 'TOTAL A PAGAR:') < strpos($html, 'Productos:'));
        self::assertMatchesRegularExpression('~<td colspan="2" class="text-left align-top">\s*<p class="desc pt-5">\s*<strong>CONDICIÓN DE PAGO:~', $html);
        self::assertStringContainsString('<!-- HKA_TICKET_QR -->', $html);
        $withControl = view('pdf.default.invoice_ticket', [
            'company'=>$company, 'document'=>$document, 'configuration'=>['is_preview'=>false],
            'ticketInvoice'=>true, 'ticketFiscal'=>['environment'=>'demo', 'number_full'=>'FF01-00000012', 'control_number'=>'00-00000009'],
        ])->render();
        self::assertSame(1, substr_count($withControl, 'DEMO — SIN VALIDEZ FISCAL'));
        self::assertStringContainsString('N° : <strong>FF01-00000012</strong>', $withControl);
        self::assertStringContainsString('N° de control: <strong>00-00000009</strong>', $withControl);
        $pdf = new Mpdf(['tempDir'=>sys_get_temp_dir(), 'format'=>[72, 400], 'margin_left'=>2, 'margin_right'=>2, 'margin_top'=>2, 'margin_bottom'=>2]);
        $pdf->WriteHTML(file_get_contents(app_path('CoreFacturalo/Templates/pdf/default/style.css')), HTMLParserMode::HEADER_CSS);
        $pdf->WriteHTML($html, HTMLParserMode::HTML_BODY);
        self::assertStringStartsWith('%PDF-', $pdf->Output('', 'S'));
    }

    /** @dataProvider invoice58Designs */
    public function test_invoice_58mm_design_has_one_fiscal_identity_and_left_payment(string $design): void
    {
        $document = $this->documentFixture();
        $document->number_full = 'FF01-00000012';
        $document->items->first()->additional_information = [];
        $document->payment_condition = new Fluent(['name'=>'Contado']);
        $company = new Fluent(['name'=>'Empresa de prueba', 'number'=>'J234534534', 'trade_name'=>'Prueba', 'logo'=>null]);
        $template = new Template();
        $html = $template->pdf($design, 'invoice', $company, $document, 'ticket_58', ['is_preview'=>false]);
        self::assertSame(1, substr_count($html, 'R.I.F. J-23453453-4'));
        self::assertSame(1, substr_count($html, 'N° :'));
        self::assertSame(1, substr_count($html, 'N° de control:'));
        self::assertStringContainsString('N° : <strong>FF01-00000012</strong>', $html);
        self::assertStringContainsString('N° de control: <strong>No asignado</strong>', $html);
        self::assertLessThan(strpos($html, 'FACTURA'), strpos($html, 'R.I.F. J-23453453-4'));
        self::assertLessThan(strpos($html, 'N° :'), strpos($html, 'FACTURA'));
        self::assertLessThan(strpos($html, 'N° de control:'), strpos($html, 'N° :'));
        self::assertMatchesRegularExpression('/IVA(?:\s|\(|:)/i', $html);
        self::assertLessThan(stripos($html, 'subtotal'), stripos($html, 'IVA'));
        self::assertLessThan(stripos($html, 'TOTAL A PAGAR:'), stripos($html, 'subtotal'));
        self::assertMatchesRegularExpression('/CONDICIÓN DE PAGO:\s*(?:<\/td>\s*<td[^>]*>)?\s*Contado/iu', $html);
        self::assertStringNotContainsString('DEMO — SIN VALIDEZ FISCAL', $html);

        foreach (['demo','production'] as $environment) {
            $persisted = view('pdf.'.$design.'.invoice_ticket_58', [
                'company'=>$company, 'document'=>$document, 'configuration'=>['is_preview'=>false],
                'ticketInvoice'=>true, 'ticketFiscal'=>['environment'=>$environment, 'number_full'=>'FF01-00000012', 'control_number'=>'00-00000009'],
            ])->render();
            self::assertSame($environment === 'demo' ? 1 : 0, substr_count($persisted, 'DEMO — SIN VALIDEZ FISCAL'));
            self::assertStringContainsString('N° : <strong>FF01-00000012</strong>', $persisted);
            self::assertStringContainsString('N° de control: <strong>00-00000009</strong>', $persisted);
        }
        $document->payment_condition_id = '02';
        $document->payment_condition = new Fluent(['name'=>'Crédito']);
        $document->fee = new Collection([new class(['date'=>Carbon::parse('2026-12-14'), 'amount'=>'20.00', 'currency_type'=>$document->currency_type]) extends Fluent {
            public function getStringPaymentMethodType(): string { return ''; }
        }]);
        $document->total_discount_with_igv = '5';
        $document->total_charge = '2';
        $credit = $template->pdf($design, 'invoice', $company, $document, 'ticket_58', ['is_preview'=>false]);
        self::assertMatchesRegularExpression('/CONDICIÓN DE PAGO:\s*(?:<\/td>\s*<td[^>]*>)?\s*Crédito/iu', $credit);
        self::assertStringContainsString('Cuota #1', $credit);
        self::assertLessThan(stripos($credit, 'total a pagar:'), stripos($credit, 'descuento'));
        self::assertLessThan(stripos($credit, 'total a pagar:'), stripos($credit, 'cargos'));
        $pdf = new Mpdf(['tempDir'=>sys_get_temp_dir(), 'format'=>[56, 400], 'margin_left'=>2, 'margin_right'=>2, 'margin_top'=>2, 'margin_bottom'=>2]);
        $pdf->WriteHTML(file_get_contents(app_path('CoreFacturalo/Templates/pdf/'.$design.'/style.css')), HTMLParserMode::HEADER_CSS);
        $pdf->WriteHTML($credit, HTMLParserMode::HTML_BODY);
        self::assertSame(1, $pdf->page);
        self::assertStringStartsWith('%PDF-', $pdf->Output('', 'S'));
    }

    public static function invoice58Designs(): array
    {
        return array_map(fn ($design) => [$design], ['default', 'modern-2027', 'marca_de_agua', 'Plantilla_personalizable']);
    }

    // ######## INICIO DISEÑO DE FACTURAS EN TICKET ########
    /** @dataProvider invoice58Designs */
    public function test_invoice_ticket_design_keeps_demo_identity_and_two_column_items(string $design): void
    {
        $document = $this->documentFixture();
        $document->number_full = 'FF01-00000012';
        $document->items->first()->additional_information = [];
        $document->payment_condition = new Fluent(['name'=>'Contado']);
        $company = new Fluent(['name'=>'Empresa de prueba', 'number'=>'J234534534', 'trade_name'=>'Prueba', 'logo'=>null]);

        foreach (['ticket', 'ticket_58'] as $format) {
            foreach (['demo', 'production'] as $environment) {
                $html = view('pdf.'.$design.'.invoice_'.$format, [
                    'company'=>$company, 'document'=>$document, 'configuration'=>['is_preview'=>false],
                    'ticketInvoice'=>true, 'ticketFiscal'=>['environment'=>$environment, 'number_full'=>'FF01-00000012', 'control_number'=>'00-00000009'],
                ])->render();
                self::assertSame($environment === 'demo' ? 1 : 0, substr_count($html, 'DEMO — SIN VALIDEZ FISCAL'), $design.' '.$format);
                self::assertSame(1, substr_count($html, 'N° :'), $design.' '.$format);
                self::assertSame(1, substr_count($html, 'N° de control:'), $design.' '.$format);
                // ######## INICIO DATOS DEL ENCABEZADO DE TICKET ########
                self::assertStringContainsString('N° : <strong>FF01-00000012</strong>', $html, $design.' '.$format);
                self::assertStringContainsString('Teléfono:', $html, $design.' '.$format);
                self::assertStringContainsString('0212-5550000', $html, $design.' '.$format);
                self::assertStringNotContainsString('facturacion@example.test', $html, $design.' '.$format);
                self::assertStringNotContainsString('Central telefónica:', $html, $design.' '.$format);
                self::assertStringNotContainsString('CENTRAL TELEFÓNICA:', $html, $design.' '.$format);
                // ######## FIN DATOS DEL ENCABEZADO DE TICKET ########
                self::assertStringContainsString('DESCRIPCIÓN', $html, $design.' '.$format);
                self::assertStringContainsString('IMPORTE', $html, $design.' '.$format);
                self::assertStringContainsString('Producto gravado de prueba', $html, $design.' '.$format);
                self::assertSame(1, preg_match('/(?:F\.?\s*Emisión|Fecha(?: de Emisión)?):/iu', $html, $dateMatch, PREG_OFFSET_CAPTURE), $design.' '.$format);
                self::assertLessThan($dateMatch[0][1], strpos($html, 'Cliente:'), $design.' '.$format);
                self::assertSame(1, preg_match_all('/condición de pago:/iu', $html), $design.' '.$format);
                self::assertLessThan(mb_stripos($html, 'condición de pago:'), mb_stripos($html, 'IMPORTE'), $design.' '.$format);
                $pdf = new Mpdf(['tempDir'=>sys_get_temp_dir(), 'format'=>[$format === 'ticket' ? 72 : 56, 400], 'margin_left'=>2,'margin_right'=>2,'margin_top'=>2,'margin_bottom'=>2]);
                $pdf->WriteHTML(file_get_contents(app_path('CoreFacturalo/Templates/pdf/'.$design.'/style.css')), HTMLParserMode::HEADER_CSS);
                $pdf->WriteHTML($html, HTMLParserMode::HTML_BODY);
                self::assertSame(1, $pdf->page, $design.' '.$format);
                self::assertStringStartsWith('%PDF-', $pdf->Output('', 'S'));
            }
        }
    }
    // ######## FIN DISEÑO DE FACTURAS EN TICKET ########

    // ######## INICIO ESTILO VISUAL DE FACTURA EN TICKET ########
    /** @dataProvider invoice58Designs */
    public function test_invoice_ticket_visual_style_is_applied_only_to_invoice_tickets(string $design): void
    {
        $document = $this->documentFixture();
        $document->items->first()->additional_information = [];
        $company = new Fluent(['name'=>'Empresa', 'number'=>'J234534534', 'trade_name'=>'Prueba', 'logo'=>null]);
        $template = new Template();
        foreach (['ticket'=>80, 'ticket_58'=>58] as $format=>$width) {
            $css = $template->pdfStylesheet($design, 'invoice', $document, $format);
            $html = $template->pdf($design, 'invoice', $company, $document, $format, ['is_preview'=>false]);
            self::assertStringContainsString('INICIO DISEÑO VISUAL DE FACTURA EN TICKET', $css, $design.' '.$format);
            self::assertStringContainsString('invoice-header-'.$width, $html, $design.' '.$format);
            self::assertStringContainsString('invoice-customer-'.$width, $html, $design.' '.$format);
            $pdf = new Mpdf(['tempDir'=>sys_get_temp_dir(), 'format'=>[$width === 80 ? 72 : 56, 400], 'margin_left'=>2,'margin_right'=>2,'margin_top'=>2,'margin_bottom'=>2]);
            $pdf->WriteHTML($css, HTMLParserMode::HEADER_CSS);
            $pdf->WriteHTML($html, HTMLParserMode::HTML_BODY);
            self::assertSame(1, $pdf->page, $design.' '.$format);
        }
        self::assertStringNotContainsString('INICIO DISEÑO VISUAL DE FACTURA EN TICKET', $template->pdfStylesheet($design, 'invoice', $document, 'a4'));
        self::assertStringNotContainsString('INICIO DISEÑO VISUAL DE FACTURA EN TICKET', $template->pdfStylesheet($design, 'sale_note', $document, 'ticket'));
    }
    // ######## FIN ESTILO VISUAL DE FACTURA EN TICKET ########

    // ######## INICIO ENCABEZADO DE OTROS DOCUMENTOS ########
    /** @dataProvider invoice58Designs */
    public function test_other_ticket_documents_keep_existing_contact_header(string $design): void
    {
        $document = $this->documentFixture();
        $document->document_type_id = '03';
        $document->items->first()->additional_information = [];
        $company = new Fluent(['name'=>'Empresa', 'number'=>'J234534534', 'trade_name'=>'Prueba', 'logo'=>null]);
        foreach (['ticket', 'ticket_58'] as $format) {
            $html = (new Template())->pdf($design, 'invoice', $company, $document, $format, ['is_preview'=>false]);
            self::assertStringContainsString('facturacion@example.test', $html, $design.' '.$format);
            self::assertStringContainsString('0212-5550000', $html, $design.' '.$format);
            self::assertStringNotContainsString('Teléfono:', $html, $design.' '.$format);
        }
    }
    // ######## FIN ENCABEZADO DE OTROS DOCUMENTOS ########

    // ######## INICIO COLUMNAS DE FACTURA PERSONALIZABLE ########
    public function test_personalized_ticket_keeps_visible_code_when_description_is_hidden(): void
    {
        Schema::connection('tenant')->table('template_columns_config', function ($table): void {
            $table->text('columns_config')->nullable();
        });
        DB::connection('tenant')->table('template_columns_config')->insert([
            'establishment_id'=>1, 'template_name'=>'Plantilla_personalizable_ticket',
            'columns_config'=>json_encode(['codigo'=>true, 'descripcion'=>false]),
        ]);
        $document = $this->documentFixture();
        $document->items->first()->additional_information = [];
        $document->items->first()->name_product_pdf = null;
        $document->items->first()->item->internal_id = 'P001';
        $document->items->first()->item->description = 'P001 Café de prueba';
        $company = new Fluent(['name'=>'Empresa', 'number'=>'J234534534', 'trade_name'=>'Prueba', 'logo'=>null]);
        foreach (['ticket', 'ticket_58'] as $format) {
            $html = (new Template())->pdf('Plantilla_personalizable', 'invoice', $company, $document, $format, ['is_preview'=>false]);
            self::assertStringContainsString('P001', $html, $format);
            self::assertStringNotContainsString('Café de prueba', $html, $format);
        }
    }
    // ######## FIN COLUMNAS DE FACTURA PERSONALIZABLE ########

    public function test_invoice_58mm_persisted_identity_uses_document_snapshot(): void
    {
        \Tests\Support\InvoiceA4Ledger::create();
        $fixture = $this->documentFixture();
        $fixture->items->first()->additional_information = [];
        $document = new CurrentFiscalPdfDocumentFixture();
        $relations = ['person','invoice','note','currency_type','document_type','state_type','items','payments','fee','reference_guides','dispatch','transport','quotation','seller'];
        foreach ($fixture->getAttributes() as $key=>$value) {
            if (in_array($key, $relations, true)) $document->setRelation($key, $value);
            elseif ($key === 'additional_information') $document->forceFill([$key=>'']);
            else $document->forceFill([$key=>$value]);
        }
        $document->forceFill(['series'=>'FF01', 'number'=>12, 'control_number'=>'00-00000009',
            'fiscal_environment'=>'demo', 'fiscal_emission_mode'=>'free_form',
            'issuer'=>['name'=>'Emisor conservado', 'trade_name'=>'Prueba', 'number'=>'J234534534', 'logo'=>null],
            'legends'=>[['code'=>'1000', 'value'=>'CIENTO DIECISÉIS']]]);
        $document->setRelation('taxes', new Collection());
        $document->setRelation('received_retentions', new Collection());
        $document->setRelation('guarantee_fund', null);
        $document->setRelation('fiscal_data', null);
        $document->setRelation('currency_totals', null);
        $document->exists = true;
        $company = new Fluent(['name'=>'Emisor actual', 'number'=>'J999999999', 'logo'=>null]);
        foreach (self::invoice58Designs() as [$design]) {
            foreach (['ticket', 'ticket_58'] as $format) {
                $html = (new Template())->pdf($design, 'invoice', $company, $document, $format, ['is_preview'=>false]);
                self::assertSame(1, substr_count($html, 'DEMO — SIN VALIDEZ FISCAL'), $design);
                self::assertSame(1, substr_count($html, 'N° :'), $design);
                self::assertSame(1, substr_count($html, 'N° de control:'), $design);
                self::assertStringContainsString('R.I.F. J-23453453-4', $html, $design);
                self::assertStringContainsString('N° : <strong>FF01-00000012</strong>', $html, $design);
                self::assertStringContainsString('N° de control: <strong>00-00000009</strong>', $html, $design);
            }
        }
        // ######## INICIO IGTF ÚNICO EN FACTURA PERSISTIDA ########
        $document->setRelation('fiscal_data', new Fluent(['transaction_type_id'=>'99', 'third_party'=>[
            'rif'=>'V234567891', 'name'=>'TITULAR CONSERVADO', 'address'=>'DOMICILIO CONSERVADO',
        ]]));
        $document->setRelation('taxes', new \Illuminate\Database\Eloquent\Collection([
            new \App\Models\Tenant\DocumentTax(['tax_kind'=>'IGTF', 'percentage'=>'3', 'amount'=>'7.12']),
        ]));
        foreach (self::invoice58Designs() as [$design]) {
            foreach (['ticket', 'ticket_58'] as $format) {
                $html = (new Template())->pdf($design, 'invoice', $company, $document, $format, ['is_preview'=>false]);
                self::assertSame(1, substr_count($html, 'data-ticket-inline-igtf'), $design.' '.$format);
                self::assertSame(1, substr_count($html, '7.12'), $design.' '.$format);
                self::assertSame(1, substr_count($html, 'IGTF 3.00%'), $design.' '.$format);
            }
        }
        $document->setRelation('fiscal_data', null);
        $document->setRelation('taxes', new \Illuminate\Database\Eloquent\Collection([
            new \App\Models\Tenant\DocumentTax(['tax_kind'=>'IGTF', 'percentage'=>'3', 'amount'=>'7.12']),
            new \App\Models\Tenant\DocumentTax(['tax_kind'=>'IGTF', 'percentage'=>'2', 'amount'=>'1.25']),
        ]));
        foreach (self::invoice58Designs() as [$design]) {
            foreach (['ticket', 'ticket_58'] as $format) {
                $html = (new Template())->pdf($design, 'invoice', $company, $document, $format, ['is_preview'=>false]);
                self::assertSame(2, substr_count($html, 'data-ticket-inline-igtf'), $design.' '.$format);
                self::assertSame(1, substr_count($html, 'IGTF 3.00%'), $design.' '.$format);
                self::assertSame(1, substr_count($html, 'IGTF 2.00%'), $design.' '.$format);
            }
        }
        $document->setRelation('fiscal_data', null);
        $document->setRelation('taxes', new Collection());
        // ######## FIN IGTF ÚNICO EN FACTURA PERSISTIDA ########
        // ######## INICIO DEMO EN VISTA PREVIA DE FACTURA ########
        $document->exists = false;
        foreach (self::invoice58Designs() as [$design]) {
            foreach (['ticket', 'ticket_58'] as $format) {
                foreach (['demo', 'production'] as $environment) {
                    $company->fiscal_environment = $environment;
                    $html = (new Template())->pdf($design, 'invoice', $company, $document, $format, ['is_preview'=>true]);
                    self::assertSame($environment === 'demo' ? 1 : 0, substr_count($html, 'DEMO — SIN VALIDEZ FISCAL'), $design.' '.$format);
                }
            }
        }
        // ######## FIN DEMO EN VISTA PREVIA DE FACTURA ########
    }

    public function test_invoice_58mm_engine_preserves_width_and_content_for_all_designs(): void
    {
        config(['tenant.enabled_template_ticket_80'=>true, 'tenant.enabled_template_ticket_70'=>true]);
        Schema::connection('tenant')->create('establishments', function ($table): void {
            $table->increments('id'); $table->string('template_pdf'); $table->string('template_ticket_pdf'); $table->string('logo')->nullable();
        });
        foreach (['countries', 'departments', 'provinces', 'districts'] as $table) {
            Schema::connection('tenant')->create($table, function ($table): void { $table->string('id')->primary(); });
        }
        DB::connection('tenant')->table('establishments')->insert(['id'=>1, 'template_pdf'=>'default', 'template_ticket_pdf'=>'default']);
        $fixture = $this->documentFixture();
        $fixture->items->first()->additional_information = [];
        $document = new CurrentFiscalPdfDocumentFixture();
        $relations = ['person','invoice','note','currency_type','document_type','state_type','items','payments','fee','reference_guides','dispatch','transport','quotation','seller'];
        foreach ($fixture->getAttributes() as $key=>$value) {
            if (in_array($key, $relations, true)) $document->setRelation($key, $value);
            elseif ($key === 'additional_information') $document->forceFill([$key=>'']);
            else $document->forceFill([$key=>$value]);
        }
        $document->forceFill(['fiscal_emission_mode'=>'free_form', 'legends'=>[['code'=>'1000', 'value'=>'CIENTO DIECISÉIS']]]);
        $document->setRelation('taxes', new Collection());
        $document->setRelation('received_retentions', new Collection());
        $document->setRelation('guarantee_fund', null);
        $document->setRelation('payment_condition', new Fluent(['name'=>'Contado']));
        $document->setRelation('seller', new Fluent(['name'=>'Vendedor de prueba']));
        DB::connection('tenant')->table('configurations')->update(['show_seller_in_pdf'=>true]);
        $company = new Fluent(['name'=>'Empresa Venezolana de Prueba, C.A.', 'trade_name'=>'SAB Prueba', 'number'=>'J234534534', 'logo'=>null, 'fiscal_emission_mode'=>'free_form']);
        $engine = new class($company) extends \App\CoreFacturalo\Facturalo {
            public function __construct($company) { $this->company=$company; $this->configuration=\App\Models\Tenant\Configuration::first(); $this->actions=[]; }
            public function uploadFile($content, $type) { throw new \LogicException('PDF fixture must not write tenant storage'); }
        };
        foreach (self::invoice58Designs() as [$design]) {
            DB::connection('tenant')->table('establishments')->where('id', 1)->update(['template_ticket_pdf'=>$design]);
            foreach ([1,12] as $rows) {
                $document->setRelation('items', new Collection(array_fill(0, $rows, $fixture->items->first())));
                $bytes = $engine->createPdf($document, 'invoice', 'ticket_58', 'string');
                $reader = new \setasign\Fpdi\Fpdi();
                $pages = $reader->setSourceFile(\setasign\Fpdi\PdfParser\StreamReader::createByString($bytes));
                self::assertSame(1, $pages, $design.' '.$rows.' filas');
                self::assertEqualsWithDelta(56, $reader->getTemplateSize($reader->importPage(1))['width'], .01, $design);
                $path=tempnam(sys_get_temp_dir(), 'sab-58-'); file_put_contents($path, $bytes);
                try {
                    $process=proc_open(['pdftotext', '-layout', $path, '-'], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
                    fclose($pipes[0]); $text=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
                    self::assertSame(0, proc_close($process), $error);
                    self::assertStringContainsString('J-23453453-4', $text, $design);
                    self::assertStringContainsString('N° de control:', $text, $design);
                    self::assertStringContainsString('TOTAL A PAGAR:', $text, $design);
                    self::assertStringContainsString('CONDICIÓN DE PAGO:', mb_strtoupper($text), $design);
                    self::assertStringContainsString('CIENTO DIECISÉIS', $text, $design);
                    // ######## INICIO REPRESENTACIÓN FINAL EN PDF 58 MM ########
                    $plainText = trim(preg_replace('/\s+/u', ' ', $text));
                    self::assertStringEndsWith('Representacion impresa de la Factura', $plainText, $design.' '.$rows.' filas');
                    // ######## FIN REPRESENTACIÓN FINAL EN PDF 58 MM ########
                    if ($design !== 'modern-2027') {
                        self::assertLessThan(mb_stripos($text, 'VENDEDOR:'), mb_stripos($text, 'CONDICIÓN DE PAGO:'), $design);
                    }
                    if (($directory=getenv('SAB_58_TICKET_FIXTURE_DIR')) && $rows===1) {
                        if (!is_dir($directory)) mkdir($directory, 0775, true);
                        file_put_contents($directory.'/'.$design.'.pdf', $bytes);
                    }
                } finally { unlink($path); }
            }
            // ######## INICIO REPRESENTACIÓN FINAL EN PDF 80 MM ########
            $document->setRelation('items', new Collection([$fixture->items->first()]));
            $bytes = $engine->createPdf($document, 'invoice', 'ticket', 'string');
            $reader = new \setasign\Fpdi\Fpdi();
            self::assertSame(1, $reader->setSourceFile(\setasign\Fpdi\PdfParser\StreamReader::createByString($bytes)), $design.' 80 mm');
            self::assertEqualsWithDelta(70, $reader->getTemplateSize($reader->importPage(1))['width'], .01, $design.' 80 mm');
            $path = tempnam(sys_get_temp_dir(), 'sab-80-');
            file_put_contents($path, $bytes);
            try {
                $process = proc_open(['pdftotext', '-layout', $path, '-'], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
                fclose($pipes[0]); $text = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
                self::assertSame(0, proc_close($process), $error);
                self::assertStringEndsWith('Representacion impresa de la Factura', trim(preg_replace('/\s+/u', ' ', $text)), $design.' 80 mm');
            } finally { unlink($path); }
            // ######## FIN REPRESENTACIÓN FINAL EN PDF 80 MM ########
            $preview = $engine->previewPdf($document, 'invoice', 'ticket_58');
            $reader = new \setasign\Fpdi\Fpdi();
            $reader->setSourceFile(\setasign\Fpdi\PdfParser\StreamReader::createByString($preview->getContent()));
            self::assertEqualsWithDelta(56, $reader->getTemplateSize($reader->importPage(1))['width'], .01, $design.' vista previa');
        }
    }

    public function test_ticket_unit_prices_follow_the_configuration_without_using_catalog_prices(): void
    {
        $document=$this->documentFixture();
        $document->number_full='FF01-00000001';
        $row=$document->items->first();
        $row->additional_information=[];
        $row->unit_price='116.123456789123';
        $row->item->unit_price='999.99';
        $before=json_encode($row->getAttributes(),JSON_THROW_ON_ERROR);
        $company=new Fluent(['name'=>'Empresa de prueba','number'=>'J-12345678-9','trade_name'=>'Prueba','logo'=>null]);
        foreach (['default','modern-2027'] as $template) {
            foreach ([[false,12,'116.12'],[true,2,'116.12'],[true,6,'116.123457'],[true,12,'116.123456789123']] as [$enabled,$scale,$expected]) {
                DB::connection('tenant')->table('configurations')->update([
                    'change_decimal_quantity_unit_price_pdf'=>$enabled,'decimal_quantity_unit_price_pdf'=>$scale,
                ]);
                $html=(new Template())->pdf($template,'invoice',$company,$document,'ticket',['is_preview'=>false]);
                self::assertStringContainsString($expected,$html);
                self::assertStringNotContainsString('999.99',$html);
                self::assertSame($before,json_encode($row->getAttributes(),JSON_THROW_ON_ERROR));
            }
            $third=(object)['esFusionado'=>true,'cantidad'=>'1','total_item'=>'0.333333333333',
                'calculation_snapshot'=>\App\Support\Exact\Decimal::snapshot(['cantidad'=>'1','total_item'=>\App\Support\Exact\Decimal::of(1)->dividedBy(3)])];
            $document->data_json=(object)['items'=>[$third,clone $third]];
            $grouped=(new Template())->pdf($template,'invoice',$company,$document,'ticket',['is_preview'=>false]);
            self::assertStringNotContainsString('Por consumo',$grouped);
            self::assertStringNotContainsString('0.67',$grouped);
            self::assertStringContainsString('116.123456789123',$grouped);
            unset($document->data_json);
        }
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
        $company = new Fluent(['name' => 'Empresa Venezolana de Prueba, C.A.', 'trade_name' => 'SAB Prueba', 'number' => 'J-12345678-9', 'logo' => null]);
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
        // ######## INICIO INTERMEDIACION AUTOMATICA DEL COBRO ########
        Schema::connection('tenant')->create('payment_method_types', function ($table): void {
            $table->string('id')->primary(); $table->string('collection_kind'); $table->boolean('is_active');
        });
        DB::connection('tenant')->table('payment_method_types')->insert(['id'=>'01','collection_kind'=>'cash','is_active'=>true]);
        // ######## FIN INTERMEDIACION AUTOMATICA DEL COBRO ########
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
            $baseline = (new \App\Services\Fiscal\HkaTicketPdf())->render($document);
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
            if ($directory = getenv('SAB_HKA_TICKET_FIXTURE_DIR')) {
                if (!is_dir($directory)) mkdir($directory, 0775, true);
                file_put_contents($directory.'/ticket-'.$expectedWidth.'mm-'.$rows.'rows.pdf', $download);
            }
        }
    }

    public function test_grouped_ticket_keeps_unmerged_items_and_uses_saved_amounts(): void
    {
        $document=$this->documentFixture(); $document->number_full='FF01-00000001';
        $first=$document->items->first(); $first->quantity='3'; $first->unit_price='12.666666666667';
        $first->total='38'; $first->additional_information=[];
        $second=clone $first; $second->item=clone $first->item;
        $second->item->internal_id='PRUEBA-002'; $second->item->description='ARTICULO SIN FUSION';
        $second->quantity='1'; $second->unit_price='10'; $second->total='10';
        $document->items=new Collection([$first,$second]);
        $document->data_json=(object)['items'=>[
            (object)['codigo_interno'=>'PRUEBA-001','esFusionado'=>true,'cantidad'=>'3','total_item'=>'999'],
            (object)['codigo_interno'=>'PRUEBA-002','esFusionado'=>false,'cantidad'=>'1','total_item'=>'0.01'],
        ]];
        $company=new Fluent(['name'=>'Empresa','number'=>'J123456789','logo'=>null]);
        foreach (['default','modern-2027','marca_de_agua','Plantilla_personalizable'] as $design) {
            $html=(new Template())->pdf($design,'invoice',$company,$document,'ticket',['is_preview'=>false]);
            self::assertStringContainsString('Por consumo',$html,$design);
            self::assertStringContainsString('ARTICULO SIN FUSION',$html,$design);
            self::assertStringContainsString('38.00',$html,$design);
            self::assertStringContainsString('10.00',$html,$design);
            self::assertStringNotContainsString('999.00',$html,$design);
            self::assertStringContainsString('precio unitario mostrado está redondeado',$html,$design);
        }
        // The hidden underlying unit price is fractional, but the displayed group price is exactly 13.
        $first->total='39';
        foreach (['default','modern-2027','marca_de_agua','Plantilla_personalizable'] as $design) {
            $html=(new Template())->pdf($design,'invoice',$company,$document,'ticket',['is_preview'=>false]);
            self::assertStringContainsString('Por consumo',$html,$design);
            self::assertStringNotContainsString('precio unitario mostrado está redondeado',$html,$design);
        }
        $first->total='38';
        foreach ([
            [(object)['codigo_interno'=>'PRUEBA-001','esFusionado'=>true]],
            [(object)['codigo_interno'=>'PRUEBA-001','esFusionado'=>true],(object)['codigo_interno'=>'PRUEBA-001','esFusionado'=>true]],
            [(object)['codigo_interno'=>'PRUEBA-001','esFusionado'=>true],(object)['codigo_interno'=>'DESCONOCIDO','esFusionado'=>true]],
        ] as $ambiguous) {
            $document->data_json=(object)['items'=>$ambiguous];
            foreach (['default','modern-2027','marca_de_agua','Plantilla_personalizable'] as $design) {
                $html=(new Template())->pdf($design,'invoice',$company,$document,'ticket',['is_preview'=>false]);
                self::assertStringNotContainsString('Por consumo',$html,$design);
                self::assertStringContainsString('ARTICULO SIN FUSION',$html,$design);
                self::assertStringContainsString('38.00',$html,$design);
                self::assertStringContainsString('10.00',$html,$design);
            }
        }
        $first->quantity='9007199254740993';
        $document->data_json=(object)['items'=>[]];
        foreach (['default','modern-2027','marca_de_agua','Plantilla_personalizable'] as $design) {
            $html=(new Template())->pdf($design,'invoice',$company,$document,'ticket',['is_preview'=>false]);
            self::assertStringContainsString('9007199254740993',$html,$design);
            self::assertStringNotContainsString('9,007,199,254,740,992',$html,$design);
        }
    }

    public function test_invoice_footer_uses_the_saved_document_mode(): void
    {
        $document = $this->documentFixture();
        foreach (['default', 'Plantilla_personalizable', 'marca_de_agua', 'modern-2027'] as $template) {
            $document->fiscal_emission_mode = 'digital';
            config(['tenant.public_document_search_enabled' => true]);
            $html = (new Template())->pdfFooter($template, $document);
            self::assertStringNotContainsString('/buscar', $html);
            self::assertStringNotContainsString('Representacion impresa', $html);
            $document->fiscal_emission_mode = 'free_form';
            foreach ([false, true] as $enabled) {
                config(['tenant.public_document_search_enabled' => $enabled]);
                $html = (new Template())->pdfFooter($template, $document);
                self::assertSame($enabled, str_contains($html, '/buscar'), $template);
                self::assertStringContainsString('Representacion impresa', $html, $template);
            }
        }
    }

    // ######## INICIO REPRESENTACIÓN FINAL DE FACTURA EN TICKET ########
    /** @dataProvider invoice58Designs */
    public function test_invoice_ticket_representation_is_last_only_for_local_mode(string $design): void
    {
        $document = $this->documentFixture();
        $document->items->first()->additional_information = [];
        $document->number_full = 'FF01-00000012';
        $company = new Fluent(['name'=>'Empresa', 'number'=>'J234534534', 'trade_name'=>'Prueba', 'logo'=>null]);
        foreach (['ticket', 'ticket_58'] as $format) {
            foreach ([true, false] as $searchEnabled) {
                config(['tenant.public_document_search_enabled'=>$searchEnabled]);
                $document->fiscal_emission_mode = 'free_form';
                $html = (new Template())->pdf($design, 'invoice', $company, $document, $format, ['is_preview'=>false]);
                self::assertSame(1, substr_count($html, 'Representacion impresa de la Factura'), $design.' '.$format);
                $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                self::assertStringEndsWith('Representacion impresa de la Factura', $text, $design.' '.$format);
                if ($design === 'modern-2027') {
                    self::assertLessThan(strpos($html, 'data-ticket-representation'), strpos($html, 'm27-thanks'), $design.' '.$format);
                    self::assertLessThan(strpos($html, 'data-ticket-representation'), strpos($html, '<barcode'), $design.' '.$format);
                }
                $document->fiscal_emission_mode = 'digital';
                $html = (new Template())->pdf($design, 'invoice', $company, $document, $format, ['is_preview'=>false]);
                self::assertStringNotContainsString('Representacion impresa de la Factura', $html, $design.' '.$format);
                self::assertStringNotContainsString('data-ticket-representation', $html, $design.' '.$format);
            }
        }
    }
    // ######## FIN REPRESENTACIÓN FINAL DE FACTURA EN TICKET ########

    public function ticketWidths(): array
    {
        return [[false, false, 72], [true, false, 76], [false, true, 70]];
    }

    public function test_third_party_ticket_identifies_the_frozen_titular_and_balances_adjustments_and_igtf(): void
    {
        $document=$this->documentFixture();
        $document->items->first()->additional_information=[];
        $document->fiscal_data=new Fluent(['transaction_type_id'=>'99','third_party'=>[
            'rif'=>'V234567891','name'=>'TITULAR CONSERVADO','address'=>'DOMICILIO CONSERVADO']]);
        $document->total_discount=30;$document->total_discount_with_igv=23.2;$document->total_charge=15;
        $document->subtotal=237.45;$document->total=244.57;
        $document->taxes=new Collection([new Fluent(['tax_kind'=>'IGTF','amount'=>7.12])]);
        $company=new Fluent(['name'=>'Emisor de prueba','number'=>'J123456789','logo'=>null]);
        $html=(new Template())->pdf('default','invoice',$company,$document,'ticket');
        self::assertStringContainsString('VENTA POR CUENTA DE TERCEROS',$html);
        self::assertStringContainsString('V234567891 · TITULAR CONSERVADO',$html);
        self::assertStringContainsString('DOMICILIO CONSERVADO',$html);
        self::assertStringContainsString('252.45</td>',$html);
        self::assertStringContainsString('30.00</td>',$html);
        self::assertStringContainsString('7.12</td>',$html);
        self::assertStringContainsString('244.57</td>',$html);
        self::assertStringNotContainsString('260.65</td>',$html);
        // ######## INICIO TOTALES DE FACTURAS EN TICKET ########
        foreach (self::invoice58Designs() as [$design]) {
            foreach (['ticket', 'ticket_58'] as $format) {
                $html = (new Template())->pdf($design, 'invoice', $company, $document, $format);
                self::assertStringContainsString('252.45</td>', $html, $design.' '.$format);
                self::assertStringContainsString('30.00</td>', $html, $design.' '.$format);
                self::assertStringContainsString('7.12</td>', $html, $design.' '.$format);
                self::assertStringContainsString('244.57</td>', $html, $design.' '.$format);
                self::assertSame(1, substr_count($html, 'data-ticket-inline-igtf'), $design.' '.$format);
                self::assertLessThan(strpos($html, 'TOTAL A PAGAR'), strpos($html, 'data-ticket-inline-igtf'), $design.' '.$format);
            }
        }
        // ######## FIN TOTALES DE FACTURAS EN TICKET ########
    }

    public function test_invoice_layouts_render_large_discounts_and_exact_package_totals(): void
    {
        $document=$this->documentFixture();
        $document->items->first()->additional_information=[];
        $document->total_discount_with_igv='9007199254740993.01';
        $document->subtotal='100';$document->total='100';$document->total_charge='0';
        $company=new Fluent(['name'=>'Emisor de prueba','number'=>'J123456789','logo'=>null]);
        $html=(new Template())->pdf('default','invoice',$company,$document,'ticket');
        self::assertStringContainsString('9,007,199,254,740,993.01</td>',$html);
        self::assertStringContainsString('9,007,199,254,741,093.01</td>',$html);

        $document->total_discount_with_igv='0';$document->items->first()->quantity='9007199254740993';
        $document->items->first()->calculation_snapshot=\App\Support\Exact\Decimal::snapshot([
            'quantity'=>\App\Support\Exact\Decimal::of('9007199254740993')->plus(\App\Support\Exact\Decimal::of(1)->dividedBy(3))]);
        foreach (['a4','a5'] as $format) {
            $html=(new Template())->pdf('marca_de_agua','invoice',$company,$document,$format);
            $summary=$format==='a4' ? '/Total bultos:<\/strong>\s*9007199254740993\.333333333333/'
                : '/Productos:\s*9007199254740993\.333333333333 UND<\/strong>/';
            self::assertMatchesRegularExpression($summary,$html,$format);
            self::assertStringNotContainsString('9,007,199,254,740,992',$html,$format);
        }
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
    public function items() { return $this->items; }

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
    public function getSaleLotGroupCodeDescription() { return ''; }
    public function getTemplateDescription() { return $this->item->description; }
    public function exactValue(string $name)
    {
        return \App\Support\Exact\Decimal::of($this->$name);
    }

    public function formatPdfUnitPrice($configuration = null): string
    {
        $configuration ??= \App\Models\Tenant\Configuration::getDataDecimalQuantity();
        return \App\Support\Exact\PdfPrice::format($this->unit_price, $configuration);
    }

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
    public function getForeignKey() { return 'document_id'; }
    public function load($relations) { return $this; }
    public function getBalanceAttribute() { return $this->total - $this->payments->sum('payment') - $this->retention_amount - $this->guarantee_amount; }
}
