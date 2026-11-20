<?php
namespace Tests\Unit;

use App\Support\Exact\Decimal as D;
use Illuminate\Http\Request;

/** @group postgres-integration */
final class PdfGeneratorControllersPostgresTest extends \PHPUnit\Framework\TestCase
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
            if($this->viewDirectory)(new \Illuminate\Filesystem\Filesystem())->deleteDirectory($this->viewDirectory);
            if($this->fixture)$this->fixture->stop();
        } finally {parent::tearDown();}
    }

    /** @dataProvider formats */
    public function test_real_generators_render_frozen_quantities_without_mutating_rows(string $kind,string $format,bool $nullable,bool $footer,string $theme='default'): void
    {
        [$db,$app,$input]=(new \ReflectionMethod($this->fixture,'bootHkaFixture'))->invoke($this->fixture,true);
        \Tests\Support\PostgresTestEnvironment::assertServer($db);
        $this->viewDirectory=sys_get_temp_dir().'/'.$db->getDatabaseName().'/controller-pdf-views';
        mkdir($this->viewDirectory,0700,true);
        config(['view.paths'=>[resource_path('views')],'view.compiled'=>$this->viewDirectory,
            'tenant.pdf_template'=>$theme,'tenant.pdf_template_footer'=>$footer,'tenant.pdf_name_regular'=>false,
            'tenant.enabled_template_ticket_80'=>false,'tenant.enabled_template_ticket_70'=>false,
            'filesystems.disks.public'=>['driver'=>'local','root'=>sys_get_temp_dir().'/'.$db->getDatabaseName()]]);
        $app->register(\Illuminate\View\ViewServiceProvider::class);
        $app->instance('request',Request::create('http://pdf-generators.example.test/'));
        $app->register(\Illuminate\Routing\RoutingServiceProvider::class);
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $db->table('configurations')->update(['change_decimal_quantity_unit_price_pdf'=>true,'decimal_quantity_unit_price_pdf'=>12]);
        $db->table('establishments')->update(['template_pdf'=>$theme,'template_ticket_pdf'=>$theme]);
        $input=array_replace($input,['id'=>null,'prefix'=>$kind==='order'?'PD':'NV','series'=>'NV01','number'=>1,
            'document_type_id'=>'80','type_period'=>null,'quantity_period'=>0,'payments'=>[],
            'external_id'=>(string)\Illuminate\Support\Str::uuid()]);
        if($kind==='order') {
            $controller=new class extends \Modules\Order\Http\Controllers\OrderNoteController {
                public string $captured='';
                public function uploadFile($filename,$file_content,$file_type){$this->captured=$file_content;}
            };
            $prepared=$controller->mergeData(Request::create('/','POST',$input));
            $document=\Modules\Order\Models\OrderNote::create($prepared);
        } else {
            $controller=$kind==='api' ? new class extends \App\Http\Controllers\Tenant\Api\SaleNoteController {
                public string $captured='';
                public function uploadFile($filename,$file_content,$file_type){$this->captured=$file_content;}
            } : new class extends \App\Http\Controllers\Tenant\SaleNoteController {
                public string $captured='';
                public function uploadFile($filename,$file_content,$file_type){$this->captured=$file_content;}
            };
            // The two printers consume the same frozen sale-note model.
            $prepared=(new \App\Http\Controllers\Tenant\SaleNoteController())->mergeData($input);
            $document=\App\Models\Tenant\SaleNote::create($prepared);
        }
        $line=$input['items'][0];$line['quantity']='0.333333333333';$line['quantity_input']=['amount'=>'1','unit_price'=>'3'];
        $line['unit_price']='3';$line['unit_value']='3';$line['affectation_igv_type_id']='20';
        $savedLine=$document->items()->create($line);
        if($theme!=='default') {
            $item=(array)$savedLine->item;$item['description']=str_repeat('Descripcion venezolana extensa de prueba ',8);
            if($theme==='marca_de_agua') {
                $item['IdLoteSelected']=[['code'=>'LOTE-TEMPORAL-LARGO-2026-00000000001','date_of_due'=>'2027-09-10']];
                $item['lots']=[['series'=>'SERIAL-TEMPORAL-LARGO-2026-00000000001','has_sale'=>true]];
            }
            $savedLine->item=$item;$savedLine->saveQuietly();
        }
        $document=$document->fresh();$customer=(array)$document->customer;$customer['address']=$nullable ? null : 'Direccion temporal de prueba';
        $document->customer=$customer;$document->saveQuietly();$document=$document->fresh();
        self::assertSame(['n'=>'1','d'=>'3'],D::fraction($document->items->first()->exactValue('quantity')));
        self::assertTrue($document->exactValue('total')->isEqualTo(1));
        $before=(array)$db->table($document->getTable())->where('id',$document->id)->first();
        $lineBefore=(array)$db->table($savedLine->getTable())->where('id',$savedLine->id)->first();
        $previousHandler=null;
        $previousHandler=set_error_handler(static function ($severity,$message,$file,$line) use (&$previousHandler) {
            if (in_array($severity,[E_DEPRECATED,E_USER_DEPRECATED],true)) throw new \ErrorException($message,0,$severity,$file,$line);
            return is_callable($previousHandler) ? $previousHandler($severity,$message,$file,$line) : false;
        });
        try {$controller->createPdf($document,$format,'TEMP-CONTROLLER-PDF');$bytes=$controller->captured;}
        finally {restore_error_handler();}
        self::assertStringStartsWith('%PDF',$bytes);
        $path=tempnam(sys_get_temp_dir(),'pro9-controller-pdf-');
        try {
            file_put_contents($path,$bytes);
            $process=new \Symfony\Component\Process\Process(['pdftotext','-raw',$path,'-']);$process->mustRun();$text=$process->getOutput();
            if ($format==='ticket_58') {
                if ($nullable) self::assertStringNotContainsString('Dirección:',$text);
                else self::assertStringNotContainsString('prueba , , -',preg_replace('/\s+/u',' ',$text));
            }
            self::assertStringContainsString('0.333333333333',preg_replace('/\s+/u','',$text));
            self::assertStringContainsString('1.00',$text);
            $pdf=new \setasign\Fpdi\Fpdi();$pages=$pdf->setSourceFile($path);self::assertGreaterThanOrEqual(1,$pages);
            $size=$pdf->getTemplateSize($pdf->importPage(1));
            $width=$format==='ticket_58' ? ($kind==='order'?58:56) : ($format==='ticket'?78:210);
            self::assertEqualsWithDelta($width,$size['width'],0.3);
        } finally {unlink($path);}
        self::assertSame($before,(array)$db->table($document->getTable())->where('id',$document->id)->first());
        self::assertSame($lineBefore,(array)$db->table($savedLine->getTable())->where('id',$savedLine->id)->first());
        self::assertSame(0,$db->transactionLevel());
    }

    public static function formats(): array
    {
        $cases=[];
        foreach(['web','api','order'] as $kind)foreach(['a4','ticket','ticket_58','a5'] as $format) {
            $cases[$kind.' '.$format]=[$kind,$format,false,false];
            if(in_array($format,['ticket_58','a5'],true))$cases[$kind.' '.$format.' nullable']=[$kind,$format,true,false];
        }
        $cases['order supplied document footer']=['order','a4',false,true];
        foreach(['web','api','order'] as $kind)foreach(['modern-2027','marca_de_agua','Plantilla_personalizable'] as $theme)
            $cases[$kind.' ticket_58 '.$theme]=[$kind,'ticket_58',false,false,$theme];
        foreach(['web','api','order'] as $kind)foreach(['modern-2027','marca_de_agua','Plantilla_personalizable'] as $theme)
            $cases[$kind.' ticket_58 '.$theme.' nullable']=[$kind,'ticket_58',true,false,$theme];
        return $cases;
    }
}
