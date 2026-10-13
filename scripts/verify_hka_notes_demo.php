<?php
/** Explicit DEMO acceptance runner. Reads only the selected tenant's connection credentials.
 * All commercial writes use a newly created, disposable database. Never sends email.
 * Usage: PRO9_FISCAL_MYSQL_TESTS=1 php -d memory_limit=1G scripts/verify_hka_notes_demo.php bbc.localhost [paid-extended|credit-extended]
 */
require dirname(__DIR__).'/vendor/autoload.php';
use App\Models\Tenant\{Company, Document, Person, Item};
use App\Services\Fiscal\{HkaAuthentication, HkaPdf};
use App\CoreFacturalo\Facturalo;
use Illuminate\Support\Str;

$root=dirname(__DIR__); $host=$argv[1] ?? '';
if (!$host || getenv('PRO9_FISCAL_MYSQL_TESTS') !== '1') exit("Specify a source host and explicitly enable isolated MySQL tests.\n");
$app=require $root.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$hostname=Hyn\Tenancy\Models\Hostname::where('fqdn',$host)->firstOrFail();
app(Hyn\Tenancy\Environment::class)->tenant($hostname->website);
$source=Company::firstOrFail();
if ($source->fiscal_emission_mode !== 'digital' || $source->fiscal_environment !== 'demo') exit("Only an existing digital DEMO configuration may be used.\n");
$credentials=HkaAuthentication::credentials($source);
if (!$credentials) exit("DEMO credentials are unavailable.\n");
$issuer=$source->only(['number','name','trade_name']);

$fixture=new class('test_note_debit_is_a_separate_receivable_without_inventory') extends Tests\Unit\FiscalEmissionSchemaTest {
    public function start(): array { $this->setUp();$method=new ReflectionMethod(Tests\Unit\FiscalEmissionSchemaTest::class,'bootHkaFixture');$method->setAccessible(true);return $method->invoke($this); }
    public function stop(): void { $this->tearDown(); }
};
$report=['date'=>date('c'),'environment'=>'demo','run_id'=>date('His').'_'.bin2hex(random_bytes(3)),'contract'=>App\Services\Fiscal\HkaPayloadBuilder::contractVersion(),
    'synthetic_commercial_data'=>true,'email_sent'=>false,'contract_probe'=>true,'cases'=>[]];
$started=false;
try {
    [$db,$testApp,$base]=$fixture->start();$started=true;
    config(['hka_notes.contract_probe'=>true]); // Explicit isolated acceptance probe; does not enable the runtime capability.
    $owned=$db->getDatabaseName();$connection=$db->getConfig();
    $cleanupReserve=str_repeat(' ',8*1024*1024);
    $cleanupPdo=new PDO('mysql:host='.$connection['host'].';port='.$connection['port'].';dbname=information_schema;charset=utf8mb4',$connection['username'],$connection['password']);
    register_shutdown_function(static function () use ($owned,$cleanupPdo,&$cleanupReserve) {
        if (!preg_match('/^pro9_fiscal_test_[a-f0-9]{12}$/D',$owned)) return;
        $cleanupReserve=null;
        $cleanupPdo->exec('DROP DATABASE IF EXISTS `'.$owned.'`');
    });
    // Real credentials in the disposable fixture use a random, process-only encryption key.
    $db->table('companies')->update(['fiscal_credentials'=>null]);
    $ephemeralKey=random_bytes(32);config(['app.key'=>'base64:'.base64_encode($ephemeralKey)]);
    $testApp->instance('encrypter',new Illuminate\Encryption\Encrypter($ephemeralKey,'AES-256-CBC'));unset($ephemeralKey);
    Illuminate\Support\Facades\Facade::clearResolvedInstance('encrypter');
    $company=Company::firstOrFail();$company->forceFill($issuer)->save();$company->fiscal_credentials=json_encode($credentials);$company->save();
    unset($credentials,$source);
    $db->table('configurations')->update(['auto_send_pdf_email'=>false]);
    $db->table('item_warehouse')->update(['stock'=>1000]);
    foreach (['01','07','08'] as $type) {
        $id=$db->table('series')->insertGetId(['establishment_id'=>$base['establishment_id'],'document_type_id'=>$type,'number'=>'']);
        $db->table('series_configurations')->insert(['series_id'=>$id,'document_type_id'=>$type,'series'=>'','number'=>random_int(1000000000,1500000000)]);
    }
    $http=$testApp->make(Illuminate\Http\Client\Factory::class);$http->fake([]); // Record real HTTP without stubbing any response.
    $base['series']='';$base['date_of_issue']=date('Y-m-d');$base['time_of_issue']=date('H:i:s');$base['invoice']['date_of_due']=$base['date_of_issue'];
    $base['payments'][0]['date_of_payment']=$base['date_of_issue'];$base['source_module']='WEB';$base['exchange_rate_sale']='50.12345678';
    $service=Item::where('unit_type_id','SERV')->firstOrFail();
    $third=Person::findOrFail($base['customer_id']);
    $third->forceFill(['is_third_party'=>true,'third_party_rif'=>'J000000004','third_party_activity'=>'Comercio de prueba','address'=>'Caracas'])->save();
    $noteBanks=[];foreach (['VES','USD'] as $currency) $noteBanks[$currency]=$db->table('bank_accounts')->insertGetId(['description'=>'Cobros sintéticos de ND','number'=>'DEMO','bank_id'=>1,'currency_type_id'=>$currency,'establishment_id'=>$base['establishment_id']]);
    $cases=[];
    foreach (['VES','USD'] as $currency) {
        foreach (['07'=>['01','07','04','09'],'08'=>['02','01','03']] as $type=>$reasons) foreach ($reasons as $reason) {
            $cases[]=['currency'=>$currency,'type'=>$type,'reason'=>$reason,'paid'=>false,'regime'=>null,'third'=>false];
            if ($type === '08') $cases[]=['currency'=>$currency,'type'=>$type,'reason'=>$reason,'paid'=>true,'regime'=>null,'third'=>false];
            if ($type === '08' && in_array($argv[2] ?? '',['partial','acceptance-final'],true)) $cases[]=['currency'=>$currency,'type'=>$type,'reason'=>$reason,'paid'=>true,'partial'=>true,'regime'=>null,'third'=>false];
        }
        if (in_array($argv[2] ?? '', ['extended','paid-extended','credit-extended','annul-extended','annul-ves','annul-ves-credit'],true)) foreach (range(1,7) as $regime) foreach ([false,true] as $party) foreach (['07','08'] as $type)
            $cases[]=['currency'=>$currency,'type'=>$type,'reason'=>$type === '07' ? '09':'02','paid'=>in_array($argv[2] ?? '',['annul-extended','annul-ves','annul-ves-credit'],true) ? false : $type === '08','regime'=>$regime,'third'=>$party];
    }
    if (($argv[2] ?? '') === 'annul-extended') foreach (['VES','USD'] as $fullCurrency) foreach (range(1,7) as $regime) foreach ([false,true] as $party) $cases[]=['currency'=>$fullCurrency,'type'=>'07','reason'=>'01','paid'=>false,'regime'=>$regime,'third'=>$party];
    if (($argv[2] ?? '') === 'annul-ves') $cases=array_values(array_filter($cases,fn($c)=>$c['currency']==='VES' && $c['regime']));
    if (($argv[2] ?? '') === 'annul-ves-credit') $cases=array_values(array_filter($cases,fn($c)=>$c['currency']==='VES' && $c['regime'] && $c['type']==='08'));
    if (in_array($argv[2] ?? '', ['paid','paid-extended'],true)) $cases=array_values(array_filter($cases,fn ($c)=>$c['paid']));
    if (($argv[2] ?? '') === 'credit-extended') $cases=array_values(array_filter($cases,fn ($c)=>$c['type'] === '07'));
    if (($argv[2] ?? '') === 'partial') $cases=array_values(array_filter($cases,fn($c)=>!empty($c['partial'])));
    if (($argv[2] ?? '') === 'acceptance-final') {
        $cases=array_values(array_filter($cases,fn($c)=>!empty($c['partial'])));
        foreach ([6,7] as $regime) foreach ([false,true] as $party) $cases[]=['currency'=>'VES','type'=>'07','reason'=>'09','paid'=>false,'regime'=>$regime,'third'=>$party];
    }
    $offset=max(0,(int)($argv[3] ?? 0));
    $limit=max(1,min(20,(int)($argv[4] ?? 20)));
    $report['total_cases']=count($cases);$cases=array_slice($cases,$offset,$limit);$report['case_offset']=$offset;$report['case_limit']=$limit;
    foreach ($cases as $index=>$case) {
        $result=$case;
        try {
            $recordedBefore=$http->recorded()->count();
            $input=$base;$input['external_id']=(string)Str::uuid();$input['currency_type_id']=$case['currency'];
            $input['fiscal_data']=$case['regime'] ? ['special_tax_regime_id'=>$case['regime']] : [];
            if ($case['third']) $input['third_party_id']=$third->id;
            $invoice=(new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh();
            $result=$case+['invoice_status'=>$invoice->emission->status,'invoice_code'=>$invoice->emission->response['code'] ?? null];
            if ($invoice->emission->status === 'confirmed') {
                $line=$invoice->items->first();unset($input['invoice'],$input['third_party_id'],$input['fiscal_data']);
                $input['type']=$case['type'] === '07' ? 'credit':'debit';$input['document_type_id']=$case['type'];
                $input['external_id']=(string)Str::uuid();$input['note_creation_key']=(string)Str::uuid();
                $input['note']=['note_type'=>$input['type'],'note_credit_type_id'=>$case['type']==='07' ? $case['reason']:null,
                    'note_debit_type_id'=>$case['type']==='08' ? $case['reason']:null,'note_description'=>'Validación sintética DEMO de nota Pro9',
                    'affected_document_id'=>$invoice->id,'data_affected_document'=>null];
                $input['items']=in_array($case['reason'],['01','03'],true) && $case['type']==='08'
                    ? [['item_id'=>$service->id,'adjustment_amount'=>11.60,'affectation_igv_type_id'=>'10','price_type_id'=>'01','additional_information'=>'']]
                    : [['affected_document_item_id'=>$line->id,'quantity'=>1,'adjustment_amount'=>11.60]];
                $input['payment_condition_id']=$case['paid'] && empty($case['partial']) ? '01':'02';
                $input['payments']=$case['paid'] ? [['operation_key'=>(string)Str::uuid(),'payment_destination_id'=>$noteBanks[$case['currency']],'payment'=>11.60,'payment_method_type_id'=>'01','date_of_payment'=>$base['date_of_issue'],'reference'=>'Cobro sintético explícito de ND DEMO']] : [];
                if (!empty($case['partial'])) {
                    $received=$case['currency']==='VES'?'USD':'VES';
                    $amount=App\Services\Fiscal\FiscalAmounts::convert('5.80',$case['currency'],$received,$base['exchange_rate_sale']);
                    $input['payments'][0]=array_replace($input['payments'][0],['payment'=>App\Services\Fiscal\FiscalAmounts::convert($amount,$received,$case['currency'],$base['exchange_rate_sale']),'original_amount'=>$amount,'currency_type_id'=>$received,
                        'exchange_rate'=>$base['exchange_rate_sale'],'exchange_rate_source'=>'manual','exchange_rate_date'=>$base['date_of_issue'],
                        'payment_destination_id'=>$noteBanks[$received],'igtf_status'=>'not_applicable','financial_intermediation'=>'financial_institution']);
                }
                $note=(new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh();
                $result+=['note_status'=>$note->emission->status,'note_code'=>$note->emission->response['code'] ?? null,
                    'diagnostic'=>$note->emission->response['diagnostic'] ?? null,'number'=>$note->number,'control'=>$note->control_number,
                    'commercial_total'=>$note->total,'reference_number'=>$invoice->number,'transaction_id'=>$note->emission->payload['documentoElectronico']['encabezado']['identificacionDocumento']['transaccionId'] ?? null];
                if ($note->emission->status === 'confirmed') {
                    $view=app(App\Services\Fiscal\HkaEmission::class)->query($note);
                    $bytes=app(HkaPdf::class)->download($note,'a4');
                    $a5=app(HkaPdf::class)->download($note,'a5');
                    $result+=['queried_status'=>$view['status'],'a4_bytes'=>strlen($bytes),'a5_bytes'=>strlen($a5),'a4_sha256'=>hash('sha256',$bytes)];
                    $result['fiscal_forms']=$note->emission->payload['documentoElectronico']['encabezado']['totales']['formasPago'];
                    $result['real_note_payments']=$note->payments()->count();
                    if (in_array($argv[2] ?? '',['annul','annul-extended','annul-ves','annul-ves-credit','partial','acceptance-final'],true)) {
                        foreach ($note->payments()->whereNull('reversed_at')->whereNull('receipt_parent_id')->get() as $payment) App\Services\Fiscal\FiscalDocumentPersistence::reverse($payment,'Regularización explícita del cobro sintético antes de anular ND DEMO');
                        $annul=app(App\Services\Fiscal\NoteAnnulment::class)->request($note->fresh(),['operation_key'=>(string)Str::uuid(),'reason'=>'Anulación sintética DEMO de nota Pro9']);
                        $result['annulment']=$annul['annulment'];
                        $result['commercial_state_after_annulment']=$note->fresh()->state_type_id;
                        $result['invoice_balance_after_annulment']=$invoice->fresh()->balance;
                        $token=app(App\Services\Fiscal\HkaTransport::class)->token($company);
                        $request=App\Services\Fiscal\NoteAnnulment::journal($note->fresh())['request'];
                        $listed=app(App\Services\Fiscal\HkaTransport::class)->annulledDocuments($token,['tipoDocumento'=>$request['tipoDocumento'],'serie'=>$request['serie'],'fechaInicio'=>$request['fechaAnulacion'],'fechaFin'=>$request['fechaAnulacion'],'numPagina'=>1]);
                        $result['list_code']=$listed->json('codigo');
                        $match=collect($listed->json('documentos') ?? [])->first(fn($row)=> (string)($row['numeroDocumento'] ?? '') === (string)$note->number && ($row['numeroControl'] ?? '') === $note->control_number);
                        $result['remote_annulled_state']=$match['estadoDocumento'] ?? null;
                        $state=app(App\Services\Fiscal\HkaTransport::class)->status($token,$note->emission->payload['documentoElectronico']['encabezado']['identificacionDocumento']);
                        $result['remote_status_after_annulment']=$state->json('estado.estadoDocumento');
                        $result['remote_status_code']=$state->json('codigo');
                    }
                    if (!isset($preview)) {
                        $preview=sys_get_temp_dir().'/pro9-note-demo-preview';@mkdir($preview,0700,true);
                        file_put_contents($preview.'/a4.pdf',$bytes);file_put_contents($preview.'/a5.pdf',$a5);
                    }
                }
            }
        } catch (Throwable $e) { $result=array_replace($result,['error_type'=>get_class($e),'validation_fields'=>$e instanceof Illuminate\Validation\ValidationException ? array_keys($e->errors()) : []]); }
        $result['validations']=[];
        foreach ($http->recorded()->slice($recordedBefore) as [$request,$response]) if (str_ends_with($request->url(),'/Emision') && $response) {
            foreach ($response->json('validaciones') ?? [] as $message) {
                $message=preg_replace('/[VEJPGCR][-0-9]{8,15}/i','[RIF]',(string)$message);
                $message=preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\\.[A-Z]{2,}/i','[correo]',$message);
                $result['validations'][]=mb_substr($message,0,500);
            }
        }
        $result['memory_mb']=round(memory_get_usage(true)/1024/1024);
        $report['cases'][]=$result;
        echo json_encode(['case'=>$index+1,'status'=>$result['note_status'] ?? $result['invoice_status'] ?? 'error','code'=>$result['note_code'] ?? $result['invoice_code'] ?? null],JSON_UNESCAPED_UNICODE),PHP_EOL;
        $result['memory_mb']=round(memory_get_usage(true)/1024/1024);
        $http->fake([]);gc_collect_cycles(); // Release PDF/Base64 captures between synthetic cases.
        file_put_contents($root.'/informes/evidencias/notas_hka_demo_'.date('Y_m_d').'_'.$report['run_id'].'.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    }
} finally { if ($started) $fixture->stop(); }
