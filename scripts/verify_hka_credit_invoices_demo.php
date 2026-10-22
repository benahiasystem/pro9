<?php
/** Explicit isolated acceptance; creates four remote DEMO invoices, never source-tenant sales.
 * PRO9_FISCAL_MYSQL_TESTS=1 php -d memory_limit=1G scripts/verify_hka_credit_invoices_demo.php bbc.localhost
 */
require dirname(__DIR__).'/vendor/autoload.php';

use App\CoreFacturalo\Facturalo;
use App\Models\Tenant\{Company, Document};
use App\Services\Fiscal\{HkaAuthentication, HkaCreditInvoice, HkaEmission, HkaPayloadBuilder, HkaPdf, HkaResponse, HkaTransport};
use Illuminate\Support\Str;

$root=dirname(__DIR__);$host=$argv[1] ?? '';
if (!$host || getenv('PRO9_FISCAL_MYSQL_TESTS') !== '1') exit("Specify a host and explicitly enable isolated MySQL tests.\n");
$app=require $root.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$hostname=Hyn\Tenancy\Models\Hostname::where('fqdn',$host)->firstOrFail();
app(Hyn\Tenancy\Environment::class)->tenant($hostname->website);
$source=Company::firstOrFail();
if ($source->fiscal_emission_mode !== 'digital' || $source->fiscal_environment !== 'demo') exit("Only digital DEMO is permitted.\n");
$credentials=HkaAuthentication::credentials($source);
if (!$credentials) exit("DEMO credentials are unavailable.\n");
$issuer=$source->only(['number','name','trade_name']);
$sourcePdo=(new Document)->getConnection()->getPdo();
$fingerprint=static function () use ($sourcePdo): string {
    $state=[];
    foreach (['series_configurations','documents','document_emissions','document_payments','global_payments'] as $table) {
        $state[$table]=$sourcePdo->query('SELECT * FROM `'.$table.'` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    }
    return hash('sha256',json_encode($state,JSON_THROW_ON_ERROR));
};
$before=$fingerprint();
$fixture=new class('test_credit_invoice_confirmation_preserves_full_receivable_and_later_collection') extends Tests\Unit\FiscalEmissionSchemaTest {
    public function start(): array { $this->setUp();$m=new ReflectionMethod(Tests\Unit\FiscalEmissionSchemaTest::class,'bootHkaFixture');$m->setAccessible(true);return $m->invoke($this); }
    public function stop(): void { $this->tearDown(); }
};
$run=date('Y_m_d_His').'_'.bin2hex(random_bytes(3));
$report=['date'=>date('c'),'environment'=>'demo','contract'=>HkaPayloadBuilder::contractVersion(),
    'credit_contract'=>HkaCreditInvoice::VERSION,'synthetic_commercial_data'=>true,'email_sent'=>false,'cases'=>[]];
$started=false;$passed=false;
try {
    [$db,$testApp,$base]=$fixture->start();$started=true;
    $owned=$db->getDatabaseName();$connection=$db->getConfig();
    $cleanupPdo=new PDO('mysql:host='.$connection['host'].';port='.$connection['port'].';dbname=information_schema;charset=utf8mb4',$connection['username'],$connection['password']);
    register_shutdown_function(static function () use ($owned,$cleanupPdo) {
        if (preg_match('/^pro9_fiscal_test_[a-f0-9]{12}$/D',$owned)) $cleanupPdo->exec('DROP DATABASE IF EXISTS `'.$owned.'`');
    });
    $db->table('companies')->update(['fiscal_credentials'=>null]);
    $key=random_bytes(32);config(['app.key'=>'base64:'.base64_encode($key),'hka_credit_invoices.demo_no_advance_verified'=>true]);
    $testApp->instance('encrypter',new Illuminate\Encryption\Encrypter($key,'AES-256-CBC'));unset($key);
    Illuminate\Support\Facades\Facade::clearResolvedInstance('encrypter');
    $company=Company::firstOrFail();$company->forceFill($issuer)->save();
    $company->fiscal_credentials=json_encode($credentials);$company->save();unset($credentials,$source);
    $db->table('configurations')->update(['auto_send_hka'=>false,'auto_send_pdf_email'=>false]);
    $db->table('item_warehouse')->update(['stock'=>1000]);
    $seriesId=$db->table('series')->insertGetId(['establishment_id'=>$base['establishment_id'],'document_type_id'=>'01','number'=>'']);
    $first=random_int(1000000000,1500000000);
    $db->table('series_configurations')->insert(['series_id'=>$seriesId,'document_type_id'=>'01','series'=>'','number'=>$first]);
    $base['series']='';$base['date_of_issue']=\Carbon\Carbon::now('America/Caracas')->format('Y-m-d');
    $base['time_of_issue']=\Carbon\Carbon::now('America/Caracas')->format('H:i:s');
    $base['invoice']['date_of_due']=\Carbon\Carbon::parse($base['date_of_issue'])->addDays(30)->format('Y-m-d');
    $base['payment_condition_id']='02';$base['payments']=[];$base['source_module']='WEB';$base['exchange_rate_sale']='50.12345678';
    $http=$testApp->make(Illuminate\Http\Client\Factory::class);$http->fake([]);
    $transport=app(HkaTransport::class);$token=$transport->token($company);
    $pdfDir=sys_get_temp_dir().'/pro9-credit-invoices-'.$run;mkdir($pdfDir,0700,true);
    foreach (['VES','USD'] as $currency) foreach ([false,true] as $mixed) {
        $case=['currency'=>$currency,'mixed_iva'=>$mixed];
        try {
            // Never knowingly reuse an occupied remote number or send after an ambiguous lookup.
            $number=$first+count($report['cases']);
            $identity=['tipoDocumento'=>'01','serie'=>'','numeroDocumento'=>(string)$number];
            $check=$transport->status($token,$identity);
            $absence=HkaResponse::interpret($check->status(),$check->json(),$identity+['transaccionId'=>''],true);
            if (!$absence['retry_allowed']) throw new RuntimeException('Number availability was not established.');
            $input=$base;$input['currency_type_id']=$currency;$input['external_id']=(string)Str::uuid();
            if ($mixed) {
                $exempt=$input['items'][0];
                $exempt=array_replace($exempt,['quantity'=>1,'unit_value'=>50,'unit_price'=>50,'affectation_igv_type_id'=>'20',
                    'total_base_igv'=>50,'percentage_igv'=>0,'total_igv'=>0,'total_taxes'=>0,'total_value'=>50,'total'=>50]);
                $input['items'][]=$exempt;
            }
            $doc=(new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh();
            if ((string)$doc->number !== (string)$number) throw new RuntimeException('Unexpected synthetic numbering.');
            $beforeMoney=[$db->table('document_payments')->count(),$db->table('global_payments')->count()];
            $view=app(HkaEmission::class)->send($doc);$doc=$doc->fresh();
            $case+=['number'=>$doc->number,'status'=>$view['status'],'code'=>$view['code'],
                'validation_codes'=>$view['validation_codes'],'diagnostic'=>$view['diagnostic'],'control'=>$doc->control_number,
                'commercial_total'=>$doc->total,'commercial_balance'=>(string)$doc->exactBalance(),
                'payments_count'=>$doc->payments()->count(),'financial_counts_unchanged'=>$beforeMoney===[$db->table('document_payments')->count(),$db->table('global_payments')->count()]];
            if ($view['status']==='confirmed') {
                $payload=$doc->emission->payload;$header=$payload['documentoElectronico']['encabezado'];
                $query=app(HkaEmission::class)->query($doc);
                $a4=app(HkaPdf::class)->download($doc->fresh(),'a4');$a5=app(HkaPdf::class)->download($doc->fresh(),'a5');
                $label=$currency.($mixed?'-mixed':'-general');
                file_put_contents($pdfDir.'/'.$label.'-a4.pdf',$a4);file_put_contents($pdfDir.'/'.$label.'-a5.pdf',$a5);
                $case+=['queried_status'=>$query['status'],'tipo_de_pago'=>$header['identificacionDocumento']['tipoDePago'],
                    'fiscal_forms'=>$header['totales']['formasPago'],'fiscal_total'=>$header['totales']['totalAPagar'],
                    'due_date'=>$header['identificacionDocumento']['fechaVencimiento'],
                    'a4_bytes'=>strlen($a4),'a5_bytes'=>strlen($a5),'a4_sha256'=>hash('sha256',$a4),
                    'payload_unchanged_after_query'=>$payload===$doc->fresh()->emission->payload];
            }
        } catch (Throwable $e) {
            $case['error_type']=get_class($e);
            if ($e instanceof Illuminate\Validation\ValidationException) $case['validation_fields']=array_keys($e->errors());
        }
        $report['cases'][]=$case;
        echo json_encode($case,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
    }
    $report['source_tenant_unchanged']=hash_equals($before,$fingerprint());
    $passed=$report['source_tenant_unchanged'] && count($report['cases'])===4;
    foreach ($report['cases'] as $case) $passed=$passed && ($case['status']??'')==='confirmed'
        && ($case['queried_status']??'')==='confirmed' && empty($case['error_type']) && $case['payments_count']===0
        && $case['financial_counts_unchanged'] && $case['payload_unchanged_after_query'];
    $report['accepted']=$passed;
    $path=$root.'/informes/evidencias/facturas_credito_sin_abono_hka_demo_'.$run.'.json';
    file_put_contents($path,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    echo json_encode(['accepted'=>$passed,'evidence'=>$path,'pdf_directory'=>$pdfDir],JSON_UNESCAPED_SLASHES)."\n";
} catch (Throwable $e) { echo json_encode(['error_type'=>get_class($e)])."\n"; }
finally { if ($started) $fixture->stop(); }
exit($passed?0:1);
