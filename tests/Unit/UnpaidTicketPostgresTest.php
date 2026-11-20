<?php
namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\Support\Exact\Decimal;
use Modules\Finance\Http\Controllers\UnpaidController;

/** @group postgres-integration */
final class UnpaidTicketPostgresTest extends \PHPUnit\Framework\TestCase
{
    private $fixture;
    private ?string $viewDirectory=null;

    protected function setUp(): void
    {
        $this->fixture=new class extends FiscalEmissionSchemaTest {
            public function start(): void {parent::setUp();}
            public function stop(): void {parent::tearDown();}
        };
        $this->fixture->start();
    }

    protected function tearDown(): void
    {
        try {
            if ($this->viewDirectory) (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($this->viewDirectory);
            if ($this->fixture) $this->fixture->stop();
        }
        finally {parent::tearDown();}
    }

    public function test_outstanding_pdf_formats_keep_fractions_and_nullable_addresses_on_postgresql(): void
    {
        [$db,$app,$base]=(new \ReflectionMethod($this->fixture,'bootHkaFixture'))->invoke($this->fixture);
        $this->viewDirectory=sys_get_temp_dir().'/pro9-unpaid-views-'.bin2hex(random_bytes(8));
        mkdir($this->viewDirectory,0700,true);
        config(['view.compiled'=>$this->viewDirectory,'view.paths'=>[base_path('resources/views')],
            'filesystems.disks.public'=>['driver'=>'local','root'=>sys_get_temp_dir()]]);
        $app->register(\Illuminate\View\ViewServiceProvider::class);
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $db->table('item_warehouse')->update(['stock'=>'1000']);
        $base['payment_condition_id']='02';$base['payments']=[];
        $printer=new class extends UnpaidController {
            public array $captured=[];
            public function uploadFile($filename,$file_content,$file_type) {
                $isolation=$this->document->getConnection()->selectOne('SHOW transaction_isolation')->transaction_isolation;
                $this->captured=[$filename,$file_content,$file_type,$isolation];
            }
        };
        foreach (['subcent','large','thirds','discount'] as $case) {
            $input=$base;$input['external_id']=(string)\Illuminate\Support\Str::uuid();
            $input['items'][0]['affectation_igv_type_id']='20';
            if ($case==='subcent') {
                $input['items'][0]['quantity']='1';
                $input['items'][0]['unit_price']='1.008';
                $input['discounts']=[['discount_type_id'=>'03','description'=>'Descuento subcéntimo','is_amount'=>true,'amount'=>'0.004']];
            } elseif ($case==='large') {
                $input['items'][0]['quantity']='1';
                $input['items'][0]['unit_price']='123456789012345678.123456789012';
            } else {
                $input['items'][0]['quantity']='166.666666666667';
                $input['items'][0]['unit_price']='3';
                $input['items'][0]['quantity_input']=['amount'=>'500','unit_price'=>'3'];
                if ($case==='thirds') {
                    $input['charges']=[['charge_type_id'=>'50','description'=>'Cargo dos','factor'=>'0.02','amount'=>'10'],
                        ['charge_type_id'=>'50','description'=>'Cargo tres','factor'=>'0.03','amount'=>'15']];
                } else {
                    $input['discounts']=[['discount_type_id'=>'03','description'=>'Descuento periódico','amount'=>'0.333333333333',
                        'amount_exact'=>['n'=>'1','d'=>'3']]];
                }
            }
            $document=(new Facturalo())->save($input)->getDocument()->fresh();
            $service=\App\Services\Fiscal\FiscalDocumentPersistence::class;
            $active=$service::payment($document,['operation_key'=>(string)\Illuminate\Support\Str::uuid(),
                'date_of_payment'=>'2026-09-10','payment_method_type_id'=>'01','payment'=>'0.01','reference'=>'COBRO-ACTIVO']);
            $reversed=$service::payment($document,['operation_key'=>(string)\Illuminate\Support\Str::uuid(),
                'date_of_payment'=>'2026-09-10','payment_method_type_id'=>'01','payment'=>'0.02','reference'=>'COBRO-REVERTIDO']);
            $service::reverse($reversed,'Reversión temporal de prueba');
            $historicalName=$active->payment_method_snapshot['description'];
            $newName='CATALOGO-MODIFICADO-'.$case;
            $db->table('payment_method_types')->where('id','01')->update(['description'=>$newName]);
            $document=$document->fresh();
            $before=(array)$db->table('documents')->where('id',$document->id)->first();
            $lineBefore=(array)$db->table('document_items')->where('document_id',$document->id)->first();
            $expected=['subcent'=>'1.004','large'=>'123456789012345678.123456789012','thirds'=>'525','discount'=>'499.666666666667'][$case];
            self::assertSame($expected,Decimal::represent($document->exactValue('total')));
            if (in_array($case,['thirds','discount'],true)) self::assertSame(['n'=>'500','d'=>'3'],Decimal::fraction($document->items->first()->exactValue('quantity')));
            if ($case==='discount') self::assertSame(['n'=>'1499','d'=>'3'],Decimal::fraction($document->exactValue('total')));
            foreach (['default','modern-2027','marca_de_agua','Plantilla_personalizable'] as $theme) foreach (['a4','ticket','ticket_58'] as $format) {
                $db->table('establishments')->where('id',$document->establishment_id)->update(['template_pdf'=>$theme]);
                self::assertSame(0,$db->transactionLevel());
                $print=clone $document;
                $customer=(array)$print->customer;$customer['address']=null;$print->customer=$customer;
                $establishment=(array)$print->establishment;$establishment['address']=null;$print->establishment=$establishment;
                set_error_handler(static function ($severity,$message,$file,$line) {
                    if ($severity===E_DEPRECATED || $severity===E_USER_DEPRECATED) throw new \ErrorException($message,0,$severity,$file,$line);
                    return false;
                });
                try {$printer->createPdf($print,$format,$print->filename);}
                finally {restore_error_handler();}
                [$name,$bytes,$kind,$isolation]=$printer->captured;
                self::assertSame('repeatable read',$isolation);
                self::assertSame(0,$db->transactionLevel());
                self::assertSame($document->filename,$name);
                self::assertSame('unpaid',$kind);
                self::assertStringStartsWith('%PDF-',$bytes);
                $artifacts=base_path('informes/evidencias/postgresql_17_implementacion/resultados/unpaid-'.getenv('PRO9_POSTGRES_RUN_ID'));
                if (!is_dir($artifacts)) mkdir($artifacts,0755,true);
                file_put_contents($artifacts.'/'.$case.'-'.$theme.'-'.$format.'.pdf',$bytes);
                $pdf=tempnam(sys_get_temp_dir(),'pro9-unpaid-pdf-');
                try {
                    file_put_contents($pdf,$bytes);
                    // Stream order keeps wrapped cells together instead of interleaving adjacent columns.
                    $process=new \Symfony\Component\Process\Process(['pdftotext','-raw',$pdf,'-']);
                    $process->setTimeout(30);$process->mustRun();
                    $text=preg_replace('/\s+/u',' ',$process->getOutput());
                    $compact=preg_replace('/\s+/u','',$text);
                    self::assertStringContainsString($document->series,$text);
                    self::assertStringContainsString('10/09/2026',$compact);
                    self::assertStringContainsString(['subcent'=>'1.00','large'=>'123,456,789,012,345,678.12','thirds'=>'525.00','discount'=>'499.67'][$case],$compact);
                    self::assertStringContainsString(['subcent'=>'0.99','large'=>'123,456,789,012,345,678.11','thirds'=>'524.99','discount'=>'499.66'][$case],$compact);
                    self::assertStringContainsString('COBRO-ACTIVO',$compact);
                    self::assertStringNotContainsString('COBRO-REVERTIDO',$compact);
                    self::assertStringContainsString(preg_replace('/\s+/u','',$historicalName),$compact);
                    self::assertStringNotContainsString(preg_replace('/\s+/u','',$newName),$compact);
                    if ($case==='thirds') {
                        self::assertMatchesRegularExpression('/CARGOS\s*\(\s*5(?:\.0*)?\s*%\s*\)/u',$text);
                    }
                    if (in_array($case,['thirds','discount'],true)) self::assertStringContainsString('166.666666666667',$compact);
                    if ($case==='subcent') {
                        self::assertMatchesRegularExpression('/SUMADEIMPORTES:[^\d]*1\.00(?!\d)/u',$compact,"$theme $format");
                        self::assertMatchesRegularExpression('/DESCUENTOTOTAL:[^\d]*0\.00(?!\d)/u',$compact,"$theme $format");
                        self::assertMatchesRegularExpression('/TOTALAPAGAR:[^\d]*1\.00(?!\d)/u',$compact,"$theme $format");
                    }
                    if ($format==='ticket_58') {
                        $fontProcess=new \Symfony\Component\Process\Process(['pdftohtml','-xml','-i','-hidden','-zoom','1','-stdout',$pdf]);
                        $fontProcess->setTimeout(30);$fontProcess->mustRun();
                        $fontXml=new \DOMDocument();
                        self::assertTrue($fontXml->loadXML($fontProcess->getOutput(),LIBXML_NONET));
                        $sizes=[];
                        foreach ($fontXml->getElementsByTagName('fontspec') as $font) $sizes[$font->getAttribute('id')]=(float)$font->getAttribute('size');
                        $observedSizes=[];
                        foreach ($fontXml->getElementsByTagName('text') as $textNode) {
                            if (trim($textNode->textContent)==='') continue;
                            self::assertArrayHasKey($textNode->getAttribute('font'),$sizes);
                            $observedSizes[]=$sizes[$textNode->getAttribute('font')];
                        }
                        // Record the existing design; the user did not authorize a new font minimum.
                        file_put_contents($artifacts.'/'.$case.'-'.$theme.'-'.$format.'-fonts.json',json_encode([
                            'reference_revision'=>'d13c5dfa238bd5274340d236d015915be169bf69',
                            'minimum_observed_points'=>$observedSizes ? min($observedSizes) : null,
                            'layout_changed'=>false],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
                    }
                    $parser=new \setasign\Fpdi\Fpdi();
                    $pages=$parser->setSourceFile($pdf);
                    self::assertGreaterThan(0,$pages);
                    $dimensions=$parser->getTemplateSize($parser->importPage(1));
                    self::assertEqualsWithDelta($format==='a4' ? 210 : ($format==='ticket_58' ? 56 : 78),$dimensions['width'],0.2);
                } finally {if (is_file($pdf)) unlink($pdf);}
                self::assertSame($before,(array)$db->table('documents')->where('id',$document->id)->first());
                self::assertSame($lineBefore,(array)$db->table('document_items')->where('document_id',$document->id)->first());
            }
        }
    }

    public function test_invoice_unpaid_rows_do_not_add_received_currency_change_to_document_currency_payment(): void
    {
        [$db,$app,$input]=(new \ReflectionMethod($this->fixture,'bootHkaFixture'))->invoke($this->fixture);
        $this->viewDirectory=sys_get_temp_dir().'/pro9-unpaid-currency-'.bin2hex(random_bytes(8));mkdir($this->viewDirectory,0700,true);
        config(['view.compiled'=>$this->viewDirectory,'view.paths'=>[base_path('resources/views')],
            'filesystems.disks.public'=>['driver'=>'local','root'=>sys_get_temp_dir()]]);
        $app->register(\Illuminate\View\ViewServiceProvider::class);
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);$db->table('configurations')->update(['auto_send_hka'=>false]);
        $input['payments']=[];$input['payment_condition_id']='02';$input['items'][0]['quantity']='1';
        $input['items'][0]['unit_price']='90';$input['items'][0]['affectation_igv_type_id']='20';
        $invoice=(new Facturalo())->save($input)->getDocument()->fresh();
        // A presentation-only projection. Persistence of actual VES/USD change is
        // covered independently by the native fiscal payment suites.
        $payment=new \App\Models\Tenant\DocumentPayment(['date_of_payment'=>'2026-09-10','payment'=>'90','change'=>'1',
            'currency_type_id'=>'USD','exchange_rate'=>'10','reference'=>'MONEDA-RECIBIDA-USD','payment_method_type_id'=>'10',
            'payment_method_snapshot'=>['description'=>'EFECTIVO-USD-HISTORICO']]);
        $invoice->setRelation('payments',new \Illuminate\Database\Eloquent\Collection([$payment]));
        foreach (['default','modern-2027','marca_de_agua','Plantilla_personalizable'] as $theme) foreach (['a4','ticket','ticket_58'] as $format) {
            $html=(new \App\CoreFacturalo\Template())->pdf($theme,'unpaid',\App\Models\Tenant\Company::active(),$invoice,$format);
            $dom=new \DOMDocument();$previous=libxml_use_internal_errors(true);
            try {self::assertTrue($dom->loadHTML('<?xml encoding="utf-8" ?>'.$html,LIBXML_NONET));}
            finally {libxml_clear_errors();libxml_use_internal_errors($previous);}
            $xpath=new \DOMXPath($dom);$rows=$xpath->query('//tr[td[contains(.,"MONEDA-RECIBIDA-USD")]]');self::assertSame(1,$rows->length,$theme.'/'.$format);
            $text=preg_replace('/\s+/u',' ',trim($rows->item(0)->textContent));
            self::assertStringContainsString('EFECTIVO-USD-HISTORICO',$text);
            self::assertStringEndsWith('Bs. 90',$text,$theme.'/'.$format.' conserva la moneda documental; el vuelto es USD.');
        }
        self::assertSame(0,$db->table('document_payments')->where('document_id',$invoice->id)->count(),'La proyección no guarda cobros ficticios.');
    }

}
