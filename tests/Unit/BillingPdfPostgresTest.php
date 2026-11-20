<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\Support\Exact\Decimal as D;
use Illuminate\Support\Str;

/** Local billing PDFs from persisted, fictitious PostgreSQL documents. */
final class BillingPdfPostgresTest extends \PHPUnit\Framework\TestCase
{
    private $fixture;

    protected function setUp(): void
    {
        $this->fixture = new class extends FiscalEmissionSchemaTest {
            public function start(): void { parent::setUp(); }
            public function stop(): void { parent::tearDown(); }
        };
        $this->fixture->start();
    }

    protected function tearDown(): void
    {
        try { $this->fixture?->stop(); }
        finally { parent::tearDown(); }
    }

    public static function currencies(): array { return [['VES'], ['USD']]; }

    public function test_invoice_pdf_installments_and_item_expiry_render_from_persisted_and_preview_models(): void
    {
        [$db, $app, $input] = (new \ReflectionMethod($this->fixture, 'bootHkaFixture'))->invoke($this->fixture, true);
        \Tests\Support\PostgresTestEnvironment::assertServer($db);
        $app->instance('url', new \Illuminate\Routing\UrlGenerator($app['router']->getRoutes(), $app['request'], public_path()));
        \Illuminate\Support\Facades\Facade::clearResolvedInstance('url');
        $views = sys_get_temp_dir().'/'.$db->getDatabaseName().'/invoice-schedule-views';
        mkdir($views, 0700, true);
        config(['view.compiled' => $views, 'view.paths' => [resource_path('views')],
            'filesystems.disks.public' => ['driver' => 'local', 'root' => sys_get_temp_dir()]]);
        $app->register(\Illuminate\View\ViewServiceProvider::class);
        $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
        $db->table('configurations')->update(['auto_send_hka' => false]);
        $app->make(\Illuminate\Http\Client\Factory::class)->fake();

        $input['external_id'] = (string) Str::uuid();
        $input['payment_condition_id'] = '02';
        $input['payments'] = [];
        $input['currency_type_id'] = 'VES';
        $input['items'][0]['quantity'] = '1';
        $input['items'][0]['unit_price'] = '200000000000000.03';
        $input['items'][0]['affectation_igv_type_id'] = '20';
        $input['fee'] = [
            ['date' => '2027-01-06', 'amount' => '100000000000000.01', 'currency_type_id' => 'VES'],
            ['date' => '2027-02-07', 'amount' => '100000000000000.02', 'currency_type_id' => 'VES'],
        ];
        $engine = (new Facturalo())->forWebDocuments();
        $invoice = $engine->save($input)->getDocument()->fresh();
        $db->table('items')->where('id', $invoice->items->first()->item_id)->update(['date_of_due' => '2031-03-19']);

        $line = $invoice->items->first();
        $line->item = array_replace((array) $line->item, [
            'IdLoteSelected' => [['code'=>'LOTE-TEMPORAL', 'date_of_due'=>null]],
        ]);
        self::assertInstanceOf(\Carbon\CarbonInterface::class, $line->relation_item->date_of_due);
        self::assertSame('2031-03-19', $line->relation_item->date_of_due->format('Y-m-d'));
        $lotLookup = $app->make(\App\Services\ItemLotsGroupService::class);
        self::assertSame('', $lotLookup->getInvoiceLotDateOfDue([(object) ['date_of_due'=>'']]));
        self::assertSame('//2027-11-30', $lotLookup->getInvoiceLotDateOfDue([
            (object) ['date_of_due'=>null], (object) ['date_of_due'=>'2027-11-30'],
        ]));

        $columns = \App\Models\Tenant\TemplateColumnsConfig::getOrCreateConfig($invoice->establishment_id);
        $columns->update(['columns_config' => array_replace($columns->columns_config, ['lote' => true, 'fecha_vencimiento' => true])]);
        $draft = $invoice->replicate();
        $draft->setRelation('fee', $draft->newCollection());
        $draftLine = new \App\Models\Tenant\DocumentItem();
        $draftLine->setRawAttributes($line->getAttributes());
        $draft->setRelation('items', $draft->newCollection([$draftLine]));
        $engine->setFeePreview($draft, $input['fee']);

        $company = \App\Models\Tenant\Company::firstOrFail();
        $template = new \App\CoreFacturalo\Template();
        foreach (['persisted' => $invoice, 'preview' => $draft] as $context => $document) {
            self::assertSame([0, 1], array_keys($document->fee->all()));
            self::assertSame(array_column($input['fee'], 'amount'), $document->fee->pluck('amount')->all());
            self::assertInstanceOf(\Carbon\CarbonInterface::class, $document->items->first()->relation_item->date_of_due);
            foreach (['default', 'modern-2027', 'marca_de_agua', 'Plantilla_personalizable', 'nrus'] as $design) {
                $formats = $design === 'nrus' ? ['ticket', 'ticket_58'] : ['a4', 'a5', 'ticket', 'ticket_58'];
                foreach ($formats as $format) {
                    $label = "$context $design $format";
                    $options = $context === 'preview' ? ['is_preview' => true] : [];
                    $html = $template->pdf($design, 'invoice', $company, $document, $format, $options);
                    self::assertStringContainsString('Cuota #1', $html, $label);
                    self::assertStringContainsString('Cuota #2', $html, $label);
                    $visibleAmounts = str_replace(',', '', strip_tags($html));
                    self::assertStringContainsString('100000000000000.01', $visibleAmounts, $label);
                    self::assertStringContainsString('100000000000000.02', $visibleAmounts, $label);
                    if ($design === 'marca_de_agua' && str_starts_with($format, 'ticket')) continue;
                    $expiry = match ($format) {
                        'a4' => '2031-03-19',
                        'a5' => $design === 'marca_de_agua' ? '19-03-2031' : '2031-03-19',
                        default => '31-03-19',
                    };
                    self::assertStringContainsString($expiry, $html, $label);
                }
            }
        }
    }

    /** @dataProvider currencies */
    public function test_actual_invoice_credit_and_debit_pdfs_keep_exact_data_and_fit_active_formats(string $currency): void
    {
        [$db,$app,$input] = (new \ReflectionMethod($this->fixture,'bootHkaFixture'))->invoke($this->fixture,true);
        $app->instance('url',new \Illuminate\Routing\UrlGenerator($app['router']->getRoutes(),$app['request'],public_path()));
        \Illuminate\Support\Facades\Facade::clearResolvedInstance('url');
        self::assertSame(public_path('logo/logo.jpg'),asset('logo/logo.jpg'));
        self::assertFileExists(asset('logo/logo.jpg'));
        \Tests\Support\PostgresTestEnvironment::assertServer($db);
        $run = getenv('SAB_POSTGRES_RUN_ID');
        self::assertMatchesRegularExpression('/^sab-pgtest-[a-f0-9]{12}$/D',$run);
        $directory = base_path('informes/evidencias/postgresql_17_implementacion/resultados/billing-pdf-'.$run.'/'.$currency);
        self::assertDirectoryDoesNotExist($directory);
        self::assertTrue(mkdir($directory,0755,true));
        $views = sys_get_temp_dir().'/'.$db->getDatabaseName().'/billing-pdf-views';
        mkdir($views,0700,true);
        config(['view.compiled'=>$views,'view.paths'=>[resource_path('views')],
            'tenant.enabled_template_ticket_80'=>false,'tenant.enabled_template_ticket_70'=>false,
            'tenant.pdf_template_footer'=>false,'filesystems.disks.public'=>['driver'=>'local','root'=>sys_get_temp_dir()]]);
        $app->register(\Illuminate\View\ViewServiceProvider::class);
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form','name'=>'Empresa ficticia PostgreSQL','trade_name'=>'SIN VALIDEZ FISCAL']);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $app->make(\Illuminate\Http\Client\Factory::class)->fake();
        $input['external_id']=(string)Str::uuid();$input['currency_type_id']=$currency;
        $input['exchange_rate_sale']='10.12345678';$input['payment_condition_id']='02';$input['payments']=[];
        $input['items'][0]['quantity']='0.333333333333';
        $input['items'][0]['quantity_input']=['amount'=>'1','unit_price'=>'3'];
        $invoice=(new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh();
        self::assertSame(['n'=>'1','d'=>'3'],D::fraction($invoice->items->first()->exactValue('quantity')));
        $noteInput=new \ReflectionMethod($this->fixture,'noteInput');
        $credit=(new Facturalo)->forWebDocuments()->save($noteInput->invoke($this->fixture,$input,$invoice,'07','09',
            [['affected_document_item_id'=>$invoice->items->first()->id,'adjustment_amount'=>'3.20']]))->getDocument()->fresh();
        $debit=(new Facturalo)->forWebDocuments()->save($noteInput->invoke($this->fixture,$input,$invoice,'08','02',
            [['affected_document_item_id'=>$invoice->items->first()->id,'adjustment_amount'=>'11.60']]))->getDocument()->fresh();
        \App\Services\Fiscal\FiscalDocumentPersistence::payment($debit,[
            'operation_key'=>(string)Str::uuid(),'date_of_payment'=>'2026-09-10','payment_method_type_id'=>'10',
            'payment_destination_id'=>'cash','currency_type_id'=>'USD','original_amount'=>'1','exchange_rate'=>'10.12345678',
            'igtf_status'=>'not_applicable','financial_intermediation'=>'none']);
        $documents=['invoice'=>$invoice,'credit'=>$credit,'debit'=>$debit->fresh()];
        $input['external_id']=(string)Str::uuid();$input['items'][0]['quantity']='1';$input['items'][0]['quantity_input']=null;
        $integral=(new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh();
        $tinyInput=$input;$tinyInput['external_id']=(string)Str::uuid();
        $tinyInput['items'][0]['quantity']='0';$tinyInput['items'][0]['unit_price']='3000000000000';
        $tinyInput['items'][0]['quantity_input']=['amount'=>'1','unit_price'=>'3000000000000'];
        unset($tinyInput['items'][0]['price_input']);
        $tiny=(new Facturalo)->forWebDocuments()->save($tinyInput)->getDocument()->fresh();
        self::assertSame(['n'=>'1','d'=>'3000000000000'],D::fraction($tiny->items->first()->exactValue('quantity')));
        foreach ($documents+['integral'=>$integral,'tiny'=>$tiny] as $document) {
            $customer=(array)$document->customer;$customer['address']=$currency==='USD' ? null : 'Direccion ficticia para revision de PDF';
            $customer['identity_document_type']=['description'=>'Venezolano'];
            $db->table('documents')->where('id',$document->id)->update(['customer'=>json_encode($customer,JSON_THROW_ON_ERROR)]);
        }
        $before=$db->table('documents')->orderBy('id')->get()->toArray();
        $linesBefore=$db->table('document_items')->orderBy('id')->get()->toArray();
        $fiscalBefore=[];
        foreach (['document_payments','document_taxes','document_currency_totals','document_received_retentions',
            'document_guarantee_funds','document_fiscal_data','document_emissions'] as $table) {
            $fiscalBefore[$table]=$db->table($table)->orderBy('id')->get()->toArray();
        }
        $manifest=[];
        $integralManifest=[];
        $integralDirectory=dirname($directory).'/integral-'.$currency;
        self::assertTrue(mkdir($integralDirectory,0755,true));
        $tinyManifest=[];$tinyDirectory=dirname($directory).'/subprecision-'.$currency;
        self::assertTrue(mkdir($tinyDirectory,0755,true));
        foreach (['default'=>2,'modern-2027'=>6,'marca_de_agua'=>12,'Plantilla_personalizable'=>12] as $theme=>$precision) {
            $db->table('establishments')->update(['template_pdf'=>$theme,'template_ticket_pdf'=>$theme]);
            $db->table('configurations')->update(['change_decimal_quantity_unit_price_pdf'=>true,'decimal_quantity_unit_price_pdf'=>$precision]);
            foreach ($documents as $kind=>$source) foreach ($currency==='VES' ? ['a4','a5','ticket','ticket_58'] : ['a4','ticket_58'] as $format) {
                $document=$source->fresh();
                $bytes=(new Facturalo)->createPdf($document,$kind,$format,'string');
                self::assertStringStartsWith('%PDF-',$bytes,"$theme $kind $format");
                if ($kind!=='invoice' && $format==='ticket'
                    && in_array($theme,['default','marca_de_agua','Plantilla_personalizable'],true)) {
                    self::assertSame(1,preg_match('~/Subtype\s*/Image\b~',$bytes),"$theme $kind $format incorpora el logo local");
                }
                $filename=$kind.'-'.$theme.'-'.$format.'.pdf';$path=$directory.'/'.$filename;
                self::assertSame(strlen($bytes),file_put_contents($path,$bytes));
                $process=proc_open(['pdftotext','-layout',$path,'-'],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
                $text=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
                self::assertSame(0,proc_close($process),$errors);
                $labels=array_unique([$currency,$document->currency_type->symbol,$document->currency_type->description]);
                self::assertMatchesRegularExpression('/(?:'.implode('|',array_map(static fn($label)=>preg_quote($label,'/'),$labels)).')/ui',$text,"$theme $kind $format moneda");
                self::assertStringContainsString(D::format($document->exactValue('total'),2,'.',','),$text,"$theme $kind $format total");
                if ($kind!=='invoice') self::assertStringContainsString('FF01',preg_replace('/\s+/','',$text),"$theme $kind $format referencia");
                $pdf=new \setasign\Fpdi\Fpdi();$pages=$pdf->setSourceFile($path);self::assertGreaterThanOrEqual(1,$pages);
                \Tests\Support\PdfPrintedValue::assertNumber($document->items->first()->formatPdfUnitPrice(),$text,"$theme $kind $format precio unitario",$path);
                if ($kind==='invoice' && in_array($format,['a4','a5'],true)) \Tests\Support\PdfPrintedValue::assertNumber(\App\Support\Exact\PdfQuantities::line($document->items->first()),$text,"$theme $format cantidad",$path);
                $size=$pdf->getTemplateSize($pdf->importPage(1));
                // The existing A5 download is landscape (210 × 148 mm).
                $width=match($format) {'a4','a5'=>210,'ticket'=>72,'ticket_58'=>56};
                self::assertEqualsWithDelta($width,$size['width'],0.4,"$theme $kind $format ancho");
                $manifest[]=['file'=>$filename,'sha256'=>hash('sha256',$bytes),'pages'=>$pages,'width_mm'=>$size['width'],
                    'height_mm'=>$size['height'],'total_fraction'=>D::fraction($document->exactValue('total')),'precision'=>$precision];
            }
            foreach (['ticket','ticket_58'] as $format) {
                $document=$integral->fresh();$bytes=(new Facturalo)->createPdf($document,'invoice',$format,'string');
                $filename='invoice-'.$theme.'-'.$format.'.pdf';$path=$integralDirectory.'/'.$filename;
                self::assertSame(strlen($bytes),file_put_contents($path,$bytes));
                $process=proc_open(['pdftotext','-layout',$path,'-'],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
                $text=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);self::assertSame(0,proc_close($process),$errors);
                \Tests\Support\PdfPrintedValue::assertNumber($document->items->first()->formatPdfUnitPrice(),$text,"$theme $format precio con cantidad entera",$path);
                if ($format==='ticket') self::assertStringContainsString($document->items->first()->item->internal_id,$text,"$theme código con cantidad entera");
                $pdf=new \setasign\Fpdi\Fpdi();$pages=$pdf->setSourceFile($path);self::assertGreaterThanOrEqual(1,$pages,"$theme $format cantidad entera");
                $integralManifest[]=['file'=>$filename,'sha256'=>hash('sha256',$bytes),'pages'=>$pages,'quantity'=>'1'];
            }
            $tinyFormats=$currency==='VES' ? ['a4','a5','ticket','ticket_58'] : ['a4','ticket_58'];
            foreach ($tinyFormats as $format) {
                $document=$tiny->fresh();$bytes=(new Facturalo)->createPdf($document,'invoice',$format,'string');
                $filename='invoice-'.$theme.'-'.$format.'.pdf';$path=$tinyDirectory.'/'.$filename;
                self::assertSame(strlen($bytes),file_put_contents($path,$bytes));
                $process=proc_open(['pdftotext','-layout',$path,'-'],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
                $text=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);self::assertSame(0,proc_close($process),$errors);
                \Tests\Support\PdfPrintedValue::assertNumber('<0.000000000001',$text,"$theme $format positive subprecision quantity",$path);
                if ($format==='a5') self::assertMatchesRegularExpression('/Productos:\s*<0\.000000000001\s+UND/u',$text,$theme.' resumen A5 debe conservar una cantidad positiva');
                $pdf=new \setasign\Fpdi\Fpdi();$pages=$pdf->setSourceFile($path);self::assertGreaterThanOrEqual(1,$pages,"$theme $format PDF válido");
                $tinyManifest[]=['file'=>$filename,'sha256'=>hash('sha256',$bytes),'pages'=>$pages,
                    'quantity_fraction'=>['n'=>'1','d'=>'3000000000000'],'printed_quantity'=>'<0.000000000001'];
            }
        }
        self::assertEquals($before,$db->table('documents')->orderBy('id')->get()->toArray());
        self::assertEquals($linesBefore,$db->table('document_items')->orderBy('id')->get()->toArray());
        foreach ($fiscalBefore as $table=>$rows) self::assertEquals($rows,$db->table($table)->orderBy('id')->get()->toArray(),$table.' no mutado por impresión');
        self::assertSame(0,$db->transactionLevel());
        file_put_contents($directory.'/manifest.json',json_encode(['run_id'=>$run,'currency'=>$currency,'fictitious'=>true,
            'database_version'=>$db->selectOne('SHOW server_version')->server_version,'files'=>$manifest],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL);
        file_put_contents($integralDirectory.'/manifest.json',json_encode(['run_id'=>$run,'currency'=>$currency,'fictitious'=>true,
            'database_version'=>$db->selectOne('SHOW server_version')->server_version,'files'=>$integralManifest],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL);
        file_put_contents($tinyDirectory.'/manifest.json',json_encode(['run_id'=>$run,'currency'=>$currency,'fictitious'=>true,
            'database_version'=>$db->selectOne('SHOW server_version')->server_version,'files'=>$tinyManifest],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL);
    }

}
