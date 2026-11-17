<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExactHttpAcceptanceTest extends TestCase
{
    public function test_production_kernel_routes_and_api_authentication_run_against_disposable_mysql(): void
    {
        if (getenv('SAB_FISCAL_MYSQL_TESTS') !== '1') $this->markTestSkipped('Requiere MySQL temporal aislado.');
        $fixture=new class extends FiscalEmissionSchemaTest {
            public bool $ready=false;
            public function start(): array { parent::setUp();$this->ready=true;return (new \ReflectionMethod(parent::class,'bootHkaFixture'))->invoke($this); }
            public function stop(): void { parent::tearDown(); }
        };
        $server=null;$referenceServer=null;$referenceAddress=null;$raceServer=null;$temp=null;
        try {
            [$db,$app,$input]=$fixture->start();
            $schema=$db->getSchemaBuilder();
            $schema->create('websites',function ($t) { $t->bigIncrements('id');$t->string('uuid');$t->timestamps();$t->softDeletes(); });
            $schema->create('hostnames',function ($t) { $t->bigIncrements('id');$t->string('fqdn');$t->string('redirect_to')->nullable();$t->boolean('force_https')->default(false);$t->timestamp('under_maintenance_since')->nullable();$t->unsignedBigInteger('website_id');$t->timestamps();$t->softDeletes(); });
            $db->table('websites')->insert(['id'=>1,'uuid'=>$db->getDatabaseName()]);
            $hostnameId=$db->table('hostnames')->insertGetId(['fqdn'=>'exact-http.example.test','website_id'=>1]);
            // Minimal central plan for the real document-limit check; both tables are disposable.
            $schema->create('plans',function ($t) { $t->increments('id');$t->string('name');$t->integer('limit_documents');$t->boolean('include_sale_notes_limit_documents')->default(false); });
            $schema->create('clients',function ($t) { $t->increments('id');$t->unsignedBigInteger('hostname_id');$t->string('name');$t->unsignedInteger('plan_id');$t->integer('whatsapp_messages_limit_override')->nullable(); });
            $db->table('plans')->insert(['id'=>1,'name'=>'Plan HTTP temporal','limit_documents'=>0]);
            $db->table('clients')->insert(['hostname_id'=>$hostnameId,'name'=>'Cliente HTTP temporal','plan_id'=>1]);
            $token=bin2hex(random_bytes(30));
            $db->table('users')->where('id',$input['user_id'])->update(['api_token'=>$token,'email_verified_at'=>'2026-10-19 12:00:00']);
            $db->table('configurations')->update(['auto_send_hka'=>false]);
            $temp=sys_get_temp_dir().'/sab-http-'.bin2hex(random_bytes(6));
            mkdir($temp,0700);
            foreach (['framework/views','framework/cache','framework/sessions','logs','tenant'] as $directory) mkdir($temp.'/'.$directory,0700,true);
            $sessionId=bin2hex(random_bytes(20));$csrf=bin2hex(random_bytes(20));
            file_put_contents($temp.'/framework/sessions/'.$sessionId,serialize([
                '_token'=>$csrf,'login_web_'.sha1(\Illuminate\Auth\SessionGuard::class)=>$input['user_id'],
            ]));
            $encrypter=new \Illuminate\Encryption\Encrypter(str_repeat('k',32),'AES-256-CBC');
            $cookie=$encrypter->encrypt(\Illuminate\Cookie\CookieValuePrefix::create('sab_exact_http_session',str_repeat('k',32)).$sessionId,false);
            $private=$temp.'/fixture.json';
            file_put_contents($private,json_encode(['connection'=>$db->getConfig(),'storage'=>$temp],JSON_THROW_ON_ERROR));chmod($private,0600);
            $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
            if (!$socket) throw new \RuntimeException($error);
            $address=stream_socket_get_name($socket,false);fclose($socket);
            $env=getenv();$env['SAB_EXACT_HTTP_FIXTURE']=$private;$env['APP_ENV']='testing';
            $server=proc_open([PHP_BINARY,'-S',$address,dirname(__DIR__).'/Support/exact_http_router.php'],
                [0=>['pipe','r'],1=>['file',$temp.'/server.log','a'],2=>['file',$temp.'/server.log','a']],$pipes,dirname(__DIR__,2),$env);
            if (!is_resource($server)) throw new \RuntimeException('No se pudo abrir HTTP temporal.');
            fclose($pipes[0]);
            for ($attempt=0;$attempt<100;$attempt++) {
                $probe=@stream_socket_client('tcp://'.$address,$errno,$error,0.1);
                if ($probe) { fclose($probe);break; }
                usleep(50000);
            }
            $reference=getenv('SAB_EXACT_HTTP_REFERENCE_CONTROLLER');
            if ($reference) {
                if (!is_file($reference)) throw new \RuntimeException('Falta la fuente congelada del controlador.');
                $privateReference=$temp.'/reference.json';
                file_put_contents($privateReference,json_encode(['connection'=>$db->getConfig(),'storage'=>$temp,'controller_reference'=>$reference],JSON_THROW_ON_ERROR));chmod($privateReference,0600);
                $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);$referenceAddress=stream_socket_get_name($socket,false);fclose($socket);
                $referenceEnv=$env;$referenceEnv['SAB_EXACT_HTTP_FIXTURE']=$privateReference;
                $referenceServer=proc_open([PHP_BINARY,'-S',$referenceAddress,dirname(__DIR__).'/Support/exact_http_router.php'],
                    [0=>['pipe','r'],1=>['file',$temp.'/reference.log','a'],2=>['file',$temp.'/reference.log','a']],$referencePipes,dirname(__DIR__,2),$referenceEnv);
                if (!is_resource($referenceServer)) throw new \RuntimeException('No se pudo abrir HTTP de referencia.');
                fclose($referencePipes[0]);
                for ($attempt=0;$attempt<100;$attempt++) {
                    $probe=@stream_socket_client('tcp://'.$referenceAddress,$errno,$error,0.1);
                    if ($probe) { fclose($probe);break; }usleep(50000);
                }
            }
            $get=static function (string $path, bool $authenticated=true, ?array $payload=null,bool $useReference=false,string $method='POST') use ($address,$referenceAddress,$token,$cookie,$csrf): array {
                $handle=curl_init('http://'.($useReference ? $referenceAddress : $address).$path);$headers=[];
                curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>60,
                    CURLOPT_HTTPHEADER=>array_filter(['Host: exact-http.example.test','Accept: application/json','Content-Type: application/json',
                        'X-Requested-With: XMLHttpRequest',$authenticated ? 'Authorization: Bearer '.$token : null,
                        $authenticated ? 'Cookie: sab_exact_http_session='.rawurlencode($cookie) : null,
                        $authenticated ? 'X-CSRF-TOKEN: '.$csrf : null]),
                    CURLOPT_HEADERFUNCTION=>static function ($handle,$line) use (&$headers) { $parts=explode(':',$line,2);if (count($parts)===2) $headers[strtolower(trim($parts[0]))]=trim($parts[1]);return strlen($line); }]);
                if ($payload!==null) {
                    curl_setopt($handle,CURLOPT_POSTFIELDS,json_encode($payload,JSON_THROW_ON_ERROR));
                    curl_setopt($handle,CURLOPT_CUSTOMREQUEST,$method);
                }
                $body=curl_exec($handle);$code=curl_getinfo($handle,CURLINFO_HTTP_CODE);$error=curl_error($handle);curl_close($handle);
                if ($body===false) throw new \RuntimeException($error);
                return [$code,$body,$headers];
            };
            [$code,$body,$headers]=$get('/api/company');
            self::assertSame(200,$code,substr($body,0,3500));
            self::assertStringContainsString('Test fiscal',$body);
            self::assertGreaterThan(0,(int)$headers['x-sab-test-queries']);
            [$code,$body]=$get('/api/company',false);
            self::assertSame(401,$code,$body);
            $posItemId = $input['items'][0]['item_id'];
            $posItem = $db->table('items')->find($posItemId);
            $warehouseId = $db->table('warehouses')->where('establishment_id', $input['establishment_id'])->value('id');
            $available = \Modules\Inventory\Models\ItemWarehouse::where('warehouse_id', $warehouseId)
                ->where('item_id', $posItemId)->firstOrFail()->exactValue('stock');
            foreach ([
                ['/pos/item/'.$posItemId, 'data'],
                ['/pos/search_items?input_item='.rawurlencode($posItem->internal_id), 'items'],
            ] as [$catalogPath, $records]) {
                [$status, $response] = $get($catalogPath);
                self::assertSame(200, $status, $response);
                $entry = collect(json_decode($response, true, 512, JSON_THROW_ON_ERROR)[$records])->firstWhere('id', $posItemId);
                self::assertSame(\App\Support\Exact\Decimal::fraction($available), $entry['pos_stock']['components'][0]['stock']);
            }
            $this->assertPosCatalogExcludesServices($db, $get, $posItem, $warehouseId);
            foreach (['/documents/tables','/sale-notes/tables','/sale-notes/option/tables',
                '/quotations/tables','/quotations/option/tables'] as $catalogPath) {
                [$status,$catalogBody]=$get($catalogPath);
                self::assertSame(200,$status,$catalogPath);
                $catalog=json_decode($catalogBody,true,512,JSON_THROW_ON_ERROR);
                self::assertNotEmpty($catalog['sellers'],$catalogPath);
                foreach ($catalog['sellers'] as $seller) {
                    self::assertArrayHasKey('id',$seller,$catalogPath);
                    self::assertArrayHasKey('name',$seller,$catalogPath);
                    self::assertArrayNotHasKey('api_token',$seller,$catalogPath);
                    self::assertArrayNotHasKey('password',$seller,$catalogPath);
                }
            }
            // The actual authenticated web route must reject a cash invoice with an unpaid balance.
            $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
            $cashRequest=array_replace($input,['payment_condition_id'=>'01','operation_type_id'=>'0101','date_of_due'=>'2026-09-10']);
            $cashRequest['payments'][0]['payment']='50';
            $cashRequest['payments'][0]['payment_destination_id']='cash';
            $before=['documents'=>$db->table('documents')->count(),'payments'=>$db->table('document_payments')->count(),
                'movements'=>$db->table('global_payments')->count(),
                'stock'=>$db->table('item_warehouse')->where('item_id',$input['items'][0]['item_id'])->value('stock')];
            [$status,$failure]=$get('/documents',true,$cashRequest);
            self::assertSame(422,$status,substr($failure,0,2500));
            $error=json_decode($failure,true,512,JSON_THROW_ON_ERROR);
            $errors=$error['errors'] ?? $error['message'] ?? [];
            self::assertArrayHasKey('payments',$errors,$failure);
            self::assertStringContainsString('contado',$errors['payments'][0]);
            self::assertSame($before['documents'],$db->table('documents')->count());
            self::assertSame($before['payments'],$db->table('document_payments')->count());
            self::assertSame($before['movements'],$db->table('global_payments')->count());
            self::assertSame($before['stock'],$db->table('item_warehouse')->where('item_id',$input['items'][0]['item_id'])->value('stock'));
            $db->table('companies')->update(['fiscal_emission_mode'=>'digital']);
            // Exercise the real tenant web route, authentication, FormRequest and transaction.
            $bankId=$db->table('banks')->value('id');
            $ves=\App\Models\Tenant\BankAccount::create(['bank_id'=>$bankId,'description'=>'HTTP VES','number'=>'HTTP-LOAN-VES','currency_type_id'=>'VES','initial_balance'=>'0']);
            $usd=\App\Models\Tenant\BankAccount::create(['bank_id'=>$bankId,'description'=>'HTTP USD','number'=>'HTTP-LOAN-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
            $loan=$db->table('bank_loans')->insertGetId(['user_id'=>$input['user_id'],'bank_loan_type_id'=>1,'establishment_id'=>$input['establishment_id'],
                'bank_id'=>$bankId,'bank_account_id'=>$ves->id,'currency_type_id'=>'VES','external_id'=>(string)\Illuminate\Support\Str::uuid(),
                'number'=>1,'date_of_issue'=>'2026-10-26','time_of_issue'=>'09:00:00','bank'=>'{}','total'=>'100','fiscal_environment'=>'demo',
                'created_at'=>'2026-10-26 09:00:00','updated_at'=>'2026-10-26 09:00:00']);
            [$code,$body]=$get('/bank_loan/records?column=number&value=1');
            self::assertSame(200,$code,substr($body,0,1500));
            self::assertSame($loan,json_decode($body,true)['data'][0]['id']);
            $db->table('payment_method_types')->where('id','01')->update(['collection_kind'=>'account','currency_type_id'=>'VES','bank_account_id'=>$ves->id,'cash_currency_type_id'=>null,'is_cash'=>0,'is_active'=>1]);
            $loanInput=['bank_loan_id'=>$loan,'date_of_payment'=>'2026-10-26','payment_method_type_id'=>'01','payment_destination_id'=>$ves->id,'payment'=>'10'];
            [$code,$body]=$get('/loan-payments',true,$loanInput);
            self::assertSame(200,$code,substr($body,0,1500));
            self::assertTrue(json_decode($body,true)['success']);
            foreach (['0', '-1'] as $invalidAmount) {
                [$code,$body]=$get('/loan-payments',true,array_replace($loanInput,['payment'=>$invalidAmount]));
                self::assertSame(422,$code,substr($body,0,1500));
            }
            [$code,$body]=$get('/loan-payments',true,array_replace($loanInput,['payment_destination_id'=>$usd->id]));
            self::assertSame(422,$code,substr($body,0,1500));
            [$code,$body]=$get('/loan-payments',true,array_replace($loanInput,['payment_destination_id'=>null,'expense_method_type_id'=>'1']));
            self::assertSame(422,$code,substr($body,0,1500));
            $db->table('payment_method_types')->where('id','04')->update(['collection_kind'=>'cash','cash_currency_type_id'=>'VES','is_active'=>1]);
            [$code,$body]=$get('/loan-payments',true,array_replace($loanInput,['payment_method_type_id'=>'04']));
            self::assertSame(422,$code,substr($body,0,1500));
            $db->table('payment_method_types')->where('id','01')->update(['is_active'=>0]);
            [$code,$body]=$get('/loan-payments',true,$loanInput);
            self::assertSame(422,$code,substr($body,0,1500));
            self::assertSame(1,$db->table('bank_loan_payments')->count());
            self::assertSame(1,$db->table('global_payments')->count());
            $db->table('payment_method_types')->where('id','01')->update(['is_active'=>1,'currency_type_id'=>'USD','bank_account_id'=>$usd->id]);
            $incomeInput=['id'=>null,'income_type_id'=>$db->table('income_types')->value('id'),
                'income_reason_id'=>$db->table('income_reasons')->value('id'),'establishment_id'=>$input['establishment_id'],
                'customer'=>'Prueba HTTP','currency_type_id'=>'VES','date_of_issue'=>'2026-10-26','time_of_issue'=>'09:00:00',
                'exchange_rate_sale'=>'100','total'=>'100','items'=>[['description'=>'Prueba','total'=>'100']],
                'payments'=>[['payment_method_type_id'=>'01','payment_destination_id'=>$usd->id,'date_of_payment'=>'2026-10-26',
                    'currency_type_id'=>'USD','original_amount'=>'1','exchange_rate'=>'100','payment'=>'100']]];
            [$code,$body]=$get('/finances/income',true,$incomeInput);
            self::assertSame(200,$code,substr($body,0,1500));
            self::assertTrue(json_decode($body,true)['success']);
            $wrong=$incomeInput; $wrong['payments'][0]['payment_destination_id']=$ves->id;
            [$code,$body]=$get('/finances/income',true,$wrong);
            self::assertSame(422,$code,substr($body,0,1500));
            $wrong=$incomeInput; $wrong['payments'][0]['currency_type_id']='VES';
            [$code,$body]=$get('/finances/income',true,$wrong);
            self::assertSame(422,$code,substr($body,0,1500));
            $db->table('bank_accounts')->where('id',$usd->id)->update(['status'=>0]);
            [$code,$body]=$get('/finances/income',true,$incomeInput);
            self::assertSame(422,$code,substr($body,0,1500));
            $db->table('bank_accounts')->where('id',$usd->id)->update(['status'=>1]);
            $db->table('payment_method_types')->where('id','01')->update(['currency_type_id'=>'VES']);
            [$code,$body]=$get('/finances/income',true,$incomeInput);
            self::assertSame(422,$code,substr($body,0,1500));
            $db->table('payment_method_types')->where('id','01')->update(['currency_type_id'=>'USD','is_active'=>0]);
            [$code,$body]=$get('/finances/income',true,$incomeInput);
            self::assertSame(422,$code,substr($body,0,1500));
            $db->table('payment_method_types')->where('id','01')->update(['is_active'=>1,'collection_kind'=>'cash','cash_currency_type_id'=>'USD']);
            [$code,$body]=$get('/finances/income',true,$incomeInput);
            self::assertSame(422,$code,substr($body,0,1500));
            self::assertSame(1,$db->table('income')->count());
            self::assertSame(1,$db->table('income_items')->count());
            self::assertSame(1,$db->table('income_payments')->count());
            self::assertSame(2,$db->table('global_payments')->count());
            $received=$db->table('income_payments')->first();
            self::assertSame('USD',$received->currency_type_id);
            self::assertTrue(\App\Support\Exact\Decimal::of($received->original_amount)->isEqualTo(\App\Support\Exact\Decimal::of('1')));
            self::assertTrue(\App\Support\Exact\Decimal::of($received->payment)->isEqualTo(\App\Support\Exact\Decimal::of('100')));
            $snapshot=json_decode($db->table('global_payments')->where('payment_type',\Modules\Finance\Models\IncomePayment::class)->value('report_context'),true);
            self::assertSame('account',$snapshot['method']['collection_kind']);
            self::assertSame('USD',$snapshot['currency_type_id']);
            $db->table('cash')->insert(['user_id'=>$input['user_id'],'date_opening'=>'2026-10-19','time_opening'=>'12:00:00','state'=>true]);
            $db->table('payment_method_types')->where('id','10')->update(['is_active'=>1]);
            $quotation=$db->table('quotations')->insertGetId(['user_id'=>$input['user_id'],'external_id'=>(string)\Illuminate\Support\Str::uuid(),
                'establishment_id'=>$input['establishment_id'],'establishment'=>json_encode($input['establishment']),
                'fiscal_environment'=>'demo','state_type_id'=>'05','prefix'=>'COT','date_of_issue'=>'2026-10-26','time_of_issue'=>'09:00:00',
                'customer_id'=>$input['customer_id'],'customer'=>json_encode($input['customer']),'currency_type_id'=>'VES','exchange_rate_sale'=>'100','total'=>'232']);
            $quotationInput=['quotation_id'=>$quotation,'date_of_payment'=>'2026-10-26','payment_method_type_id'=>'10',
                'payment_destination_id'=>'cash','payment'=>'100','currency_type_id'=>'USD','original_amount'=>'1','exchange_rate'=>'100'];
            [$code,$body]=$get('/quotation_payments',true,array_replace($quotationInput,['payment_destination_id'=>$usd->id]));
            self::assertSame(422,$code,substr($body,0,1500));
            [$code,$body]=$get('/quotation_payments',true,$quotationInput);
            self::assertSame(200,$code,substr($body,0,1500));
            self::assertTrue(json_decode($body,true)['success']);
            $record=$db->table('quotation_payments')->where('quotation_id',$quotation)->first();
            self::assertSame('10',$record->payment_method_type_id);
            self::assertSame('USD',$record->currency_type_id);
            self::assertSame(1,$db->table('quotation_payments')->where('quotation_id',$quotation)->count());
            self::assertSame(3,$db->table('global_payments')->count());
            [$code,$body]=$get('/quotation_payments/records/'.$quotation);
            self::assertSame(200,$code,substr($body,0,1500));
            self::assertSame('Efectivo Dólares',json_decode($body,true)['data'][0]['payment_method_type_description']);
            // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
            // A stale or manipulated conversion must not drop an existing receipt.
            $baseline=['notes'=>$db->table('sale_notes')->count(),'documents'=>$db->table('documents')->count(),
                'note_payments'=>$db->table('sale_note_payments')->count(),'document_payments'=>$db->table('document_payments')->count(),
                'movements'=>$db->table('global_payments')->count(),'quotation_snapshot'=>$record->payment_method_snapshot,
                'movement_context'=>$db->table('global_payments')->where('payment_type',\Modules\Sale\Models\QuotationPayment::class)
                    ->where('payment_id',$record->id)->value('report_context')];
            $noteSeries=$db->table('series')->where('document_type_id','80')->where('establishment_id',$input['establishment_id'])->value('id');
            $noteConversion=array_replace($input,['id'=>null,'quotation_id'=>$quotation,'series_id'=>$noteSeries,
                'document_type_id'=>'80','prefix'=>'NV','exchange_rate_sale'=>'100','payments'=>[]]);
            [$code,$body]=$get('/sale-notes',true,$noteConversion);
            self::assertSame(200,$code,substr($body,0,1500));
            self::assertFalse(json_decode($body,true)['success'],$body);
            self::assertStringContainsString('cobro',mb_strtolower(json_decode($body,true)['message']));
            $apiConversion=['quotation_id'=>$quotation,'customer_id'=>$input['customer_id'],'establishment_id'=>$input['establishment_id'],
                'currency_type_id'=>'VES','payments'=>[]];
            [$code,$body]=$get('/api/sale-note',true,$apiConversion);
            self::assertSame(422,$code,substr($body,0,1500));
            self::assertArrayHasKey('payments',json_decode($body,true)['errors'] ?? json_decode($body,true)['message'] ?? [],$body);
            $invoiceConversion=array_replace($input,['quotation_id'=>$quotation,'payments'=>[],
                'operation_type_id'=>'0101','date_of_due'=>'2026-10-26']);
            [$code,$body]=$get('/documents',true,$invoiceConversion);
            self::assertSame(422,$code,substr($body,0,1500));
            self::assertArrayHasKey('payments',json_decode($body,true)['errors'] ?? json_decode($body,true)['message'] ?? [],$body);
            self::assertSame($baseline['notes'],$db->table('sale_notes')->count());
            self::assertSame($baseline['documents'],$db->table('documents')->count());
            self::assertSame($baseline['note_payments'],$db->table('sale_note_payments')->count());
            self::assertSame($baseline['document_payments'],$db->table('document_payments')->count());
            self::assertSame($baseline['movements'],$db->table('global_payments')->count());
            self::assertSame($baseline['quotation_snapshot'],$db->table('quotation_payments')->where('id',$record->id)->value('payment_method_snapshot'));
            self::assertSame($baseline['movement_context'],$db->table('global_payments')->where('payment_type',\Modules\Sale\Models\QuotationPayment::class)
                ->where('payment_id',$record->id)->value('report_context'));
            [$code,$body]=$get('/quotations/record2/'.$quotation);
            self::assertSame(200,$code,substr($body,0,1500));
            $sourceRows=json_decode($body,true)['data']['quotation']['payments'];
            self::assertCount(1,$sourceRows);
            self::assertSame('10',$sourceRows[0]['payment_method_type_id']);
            self::assertSame('USD',$sourceRows[0]['currency_type_id']);
            $db->table('payment_method_types')->where('id','10')->update(['collection_kind'=>'account',
                'currency_type_id'=>'VES','cash_currency_type_id'=>null,'is_cash'=>0,'is_active'=>0,'description'=>'Reclasificado HTTP']);
            $noteConversion['payments']=$sourceRows;
            [$code,$body]=$get('/sale-notes',true,$noteConversion);
            self::assertSame(200,$code,substr($body,0,2500));
            $created=json_decode($body,true);
            self::assertTrue($created['success'],$body);
            $createdNote=$created['data']['id'];
            $reused=$db->table('sale_note_payments')->where('sale_note_id',$createdNote)->first();
            self::assertNotNull($reused);
            self::assertSame('10',$reused->payment_method_type_id);
            self::assertSame('USD',$reused->currency_type_id);
            self::assertSame('1',\App\Support\Exact\Decimal::represent($reused->original_amount));
            self::assertSame('100',\App\Support\Exact\Decimal::represent($reused->payment));
            $origin=json_decode($reused->payment_method_snapshot,true)['receipt_origin'];
            self::assertSame((string)$record->id,$origin['id']);
            self::assertSame(\Modules\Sale\Models\QuotationPayment::class,$origin['type']);
            self::assertSame($baseline['quotation_snapshot'],$db->table('quotation_payments')->where('id',$record->id)->value('payment_method_snapshot'));
            self::assertNotNull($baseline['movement_context']);
            self::assertSame($baseline['movement_context'],$db->table('global_payments')->where('payment_type',\Modules\Sale\Models\QuotationPayment::class)
                ->where('payment_id',$record->id)->value('report_context'));
            self::assertSame($baseline['movements'],$db->table('global_payments')->count());
            [$code,$body]=$get('/sale-notes',true,$noteConversion);
            self::assertSame(200,$code,substr($body,0,1500));
            self::assertFalse(json_decode($body,true)['success'],$body);
            self::assertSame(1,$db->table('sale_notes')->where('quotation_id',$quotation)->count());
            self::assertSame($baseline['movements'],$db->table('global_payments')->count());
            $db->table('payment_method_types')->where('id','10')->update(['collection_kind'=>'cash',
                'currency_type_id'=>'USD','cash_currency_type_id'=>'USD','is_cash'=>1,'is_active'=>1,'description'=>'Efectivo Dólares']);
            // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
            $db->table('payment_method_types')->where('id','01')->update(['collection_kind'=>'account','cash_currency_type_id'=>null,'currency_type_id'=>'USD','is_active'=>1]);
            $service=$db->table('technical_services')->insertGetId(['user_id'=>$input['user_id'],'customer_id'=>$input['customer_id'],
                'customer'=>json_encode($input['customer']),'establishment_id'=>$input['establishment_id'],'fiscal_environment'=>'demo',
                'currency_type_id'=>'VES','exchange_rate_sale'=>'100','date_of_issue'=>'2026-10-26','time_of_issue'=>'09:00:00',
                'total'=>'100','cellphone'=>'','description'=>'Prueba HTTP','state'=>'','reason'=>'','serial_number'=>'HTTP-TEST','cost'=>'0']);
            $serviceInput=['technical_service_id'=>$service,'date_of_payment'=>'2026-10-26','payment_method_type_id'=>'01',
                'payment_destination_id'=>$usd->id,'payment'=>'100','currency_type_id'=>'USD','original_amount'=>'1','exchange_rate'=>'100'];
            [$code,$body]=$get('/technical-service-payments',true,array_replace($serviceInput,['payment_destination_id'=>$ves->id]));
            self::assertSame(422,$code,substr($body,0,1500));
            [$code,$body]=$get('/technical-service-payments',true,$serviceInput);
            self::assertSame(200,$code,substr($body,0,1500));
            self::assertTrue(json_decode($body,true)['success']);
            $servicePayment=$db->table('technical_service_payments')->where('technical_service_id',$service)->first();
            self::assertSame('01',$servicePayment->payment_method_type_id);
            self::assertSame('USD',$servicePayment->currency_type_id);
            self::assertSame(1,$db->table('technical_service_payments')->where('technical_service_id',$service)->count());
            self::assertSame(4,$db->table('global_payments')->count());
            [$code,$body]=$get('/hotels/reception/tables');
            self::assertSame(200,$code,substr($body,0,1500));
            $hotelMethods=collect(json_decode($body,true)['payment_method_types'])->keyBy('id');
            self::assertSame('account',$hotelMethods['01']['collection_kind']);
            self::assertSame('USD',$hotelMethods['01']['currency_type_id']);
            self::assertSame('cash',$hotelMethods['04']['collection_kind']);
            self::assertSame('VES',$hotelMethods['04']['currency_type_id']);
            $category=$db->table('hotel_categories')->insertGetId(['description'=>'Categoría HTTP','establishment_id'=>$input['establishment_id']]);
            $floor=$db->table('hotel_floors')->insertGetId(['description'=>'Piso HTTP','establishment_id'=>$input['establishment_id']]);
            $room=$db->table('hotel_rooms')->insertGetId(['name'=>'HTTP 1','hotel_category_id'=>$category,'hotel_floor_id'=>$floor,
                'item_id'=>$input['items'][0]['item_id'],'status'=>'OCUPADO','establishment_id'=>$input['establishment_id']]);
            $rent=$db->table('hotel_rents')->insertGetId(['customer_id'=>$input['customer_id'],'customer'=>json_encode($input['customer']),
                'hotel_room_id'=>$room,'payment_status'=>'PAID','output_date'=>'2026-10-27','output_time'=>'12:00','establishment_id'=>$input['establishment_id']]);
            $hotelInput=['operation_key'=>(string)\Illuminate\Support\Str::uuid(),'products'=>[['id'=>null,'item_id'=>$input['items'][0]['item_id'],
                'total'=>'100','payment_status'=>'PAID','rent_payment'=>['payment_method_type_id'=>'01','payment_destination_id'=>$usd->id,
                    'payment'=>'100','currency_type_id'=>'USD','original_amount'=>'1','exchange_rate'=>'100']]]];
            $wrongHotel=$hotelInput; $wrongHotel['products'][0]['rent_payment']['payment_destination_id']=$ves->id;
            [$code,$body]=$get('/hotels/reception/'.$rent.'/rent/products/store',true,$wrongHotel);
            self::assertSame(422,$code,substr($body,0,1500));
            self::assertSame(0,$db->table('hotel_rent_item_payments')->count());
            [$code,$body]=$get('/hotels/reception/'.$rent.'/rent/products/store',true,$hotelInput);
            self::assertSame(200,$code,substr($body,0,1500));
            [$code,$body]=$get('/hotels/reception/'.$rent.'/rent/products/store',true,$hotelInput);
            self::assertSame(200,$code,substr($body,0,1500));
            self::assertSame(1,$db->table('hotel_rent_item_payments')->count());
            self::assertSame(5,$db->table('global_payments')->count());
            $hotelItem=$db->table('hotel_rent_items')->where('hotel_rent_id',$rent)->first();
            $tampered=$hotelInput;
            $tampered['operation_key']=(string)\Illuminate\Support\Str::uuid();
            $tampered['products'][0]['id']=$hotelItem->id;
            $tampered['products'][0]['payment_status']='DEBT';
            $tampered['products'][0]['total']='1';
            [$code,$body]=$get('/hotels/reception/'.$rent.'/rent/products/store',true,$tampered);
            self::assertSame(422,$code,substr($body,0,1500));
            self::assertSame('PAID',$db->table('hotel_rent_items')->where('id',$hotelItem->id)->value('payment_status'));
            self::assertSame('100',json_decode($db->table('hotel_rent_items')->where('id',$hotelItem->id)->value('item'),true)['total']);
            self::assertSame(1,$db->table('hotel_rent_item_payments')->count());
            self::assertSame(5,$db->table('global_payments')->count());
            $debtItem=$db->table('hotel_rent_items')->insertGetId(['type'=>'PRO','hotel_rent_id'=>$rent,
                'item_id'=>$input['items'][0]['item_id'],'item'=>json_encode(['total'=>'100']),'payment_status'=>'DEBT']);
            $tampered['products'][0]['id']=$debtItem;
            $tampered['products'][0]['payment_status']='PAID';
            $tampered['products'][0]['total']='100';
            [$code,$body]=$get('/hotels/reception/'.$rent.'/rent/products/store',true,$tampered);
            self::assertSame(422,$code,substr($body,0,1500));
            self::assertSame('DEBT',$db->table('hotel_rent_items')->where('id',$debtItem)->value('payment_status'));
            self::assertSame(1,$db->table('hotel_rent_item_payments')->count());
            self::assertSame(5,$db->table('global_payments')->count());
            $db->table('hotel_rent_items')->where('id',$debtItem)->delete();
            // Reproduce the old integer-only schema in this disposable database.
            $db->statement('ALTER TABLE hotel_rents DROP COLUMN calculation_snapshot, MODIFY arrears int(11) NOT NULL DEFAULT 0');
            [$code,$body]=$get('/hotels/reception/'.$rent.'/rent/finalized',true,['arrears'=>'0.25']);
            self::assertSame(422,$code,substr($body,0,1500));
            self::assertNotSame('FINALIZADO',$db->table('hotel_rents')->where('id',$rent)->value('status'));
            self::assertSame('OCUPADO',$db->table('hotel_rooms')->where('id',$room)->value('status'));
            [$code,$body]=$get('/hotels/reception/'.$rent.'/rent/finalized',true,['arrears'=>'1']);
            self::assertSame(422,$code,substr($body,0,1500));
            [$code,$body]=$get('/hotels/reception/'.$rent.'/rent/finalized',true,['arrears'=>'0']);
            self::assertSame(200,$code,substr($body,0,1500));
            self::assertSame(0,(int)$db->table('hotel_rents')->where('id',$rent)->value('arrears'));
            $db->table('hotel_rents')->where('id',$rent)->update(['status'=>'INICIADO','arrears'=>0]);
            $db->table('hotel_rooms')->where('id',$room)->update(['status'=>'OCUPADO']);
            $db->statement("ALTER TABLE hotel_rents MODIFY arrears decimal(30,12) NOT NULL DEFAULT '0.000000000000', ADD calculation_snapshot json DEFAULT NULL");
            [$code,$body]=$get('/hotels/reception/'.$rent.'/rent/finalized',true,['arrears'=>'-0.25']);
            self::assertSame(422,$code,substr($body,0,1500));
            self::assertNotSame('FINALIZADO',$db->table('hotel_rents')->where('id',$rent)->value('status'));
            [$code,$body]=$get('/hotels/reception/'.$rent.'/rent/finalized',true,['arrears'=>'0.25']);
            self::assertSame(422,$code,substr($body,0,1500));
            self::assertNotSame('FINALIZADO',$db->table('hotel_rents')->where('id',$rent)->value('status'));
            [$code,$body]=$get('/hotels/reception/'.$rent.'/rent/finalized',true,['arrears'=>'0']);
            self::assertSame(200,$code,substr($body,0,1500));
            self::assertTrue(json_decode($body,true)['success']);
            self::assertSame('FINALIZADO',$db->table('hotel_rents')->where('id',$rent)->value('status'));
            self::assertSame('0.000000000000',$db->table('hotel_rents')->where('id',$rent)->value('arrears'));
            self::assertEquals(['n'=>'0','d'=>'1'],json_decode($db->table('hotel_rents')->where('id',$rent)->value('calculation_snapshot'),true)['values']['arrears']);
            self::assertSame('LIMPIEZA',$db->table('hotel_rooms')->where('id',$room)->value('status'));
            self::assertSame(1,$db->table('hotel_rent_item_payments')->count());
            self::assertSame(5,$db->table('global_payments')->count());
            $afterCheckout=$hotelInput;
            $afterCheckout['operation_key']=(string)\Illuminate\Support\Str::uuid();
            [$code,$body]=$get('/hotels/reception/'.$rent.'/rent/products/store',true,$afterCheckout);
            self::assertSame(422,$code,substr($body,0,1500));
            self::assertSame(1,$db->table('hotel_rent_items')->where('hotel_rent_id',$rent)->count());
            self::assertSame(5,$db->table('global_payments')->count());
            $invoice=$db->table('documents')->insertGetId(['user_id'=>$input['user_id'],'external_id'=>(string)\Illuminate\Support\Str::uuid(),
                'establishment_id'=>$input['establishment_id'],'establishment'=>json_encode($input['establishment']),
                'issuer'=>json_encode(['name'=>'Test fiscal','number'=>'J123456789']),'fiscal_environment'=>'demo','fiscal_emission_mode'=>'digital',
                'group_id'=>'01','state_type_id'=>'01','document_type_id'=>'01','series'=>'FF01','number'=>901,
                'date_of_issue'=>'2026-10-26','time_of_issue'=>'09:00:00','customer_id'=>$input['customer_id'],
                'customer'=>json_encode($input['customer']),'currency_type_id'=>'VES','exchange_rate_sale'=>'100','total_value'=>'100','total'=>'100']);
            $attachment=$temp.'/receipt.pdf';
            file_put_contents($attachment,"%PDF-1.4\n% test receipt\n%%EOF\n");
            $invoiceInput=['document_id'=>$invoice,'date_of_payment'=>'2026-10-26','payment_method_type_id'=>'01',
                'payment_destination_id'=>$usd->id,'currency_type_id'=>'USD','original_amount'=>'1','exchange_rate'=>'100',
                'igtf_status'=>'not_applicable','operation_key'=>(string)\Illuminate\Support\Str::uuid(),
                'temp_path'=>$attachment,'filename'=>'receipt.pdf'];
            [$code,$body]=$get('/document_payments',true,array_replace($invoiceInput,['payment_destination_id'=>$ves->id]));
            self::assertSame(422,$code,substr($body,0,1500));
            [$code,$body]=$get('/document_payments',true,$invoiceInput);
            self::assertSame(200,$code,substr($body,0,1500));
            self::assertTrue(json_decode($body,true)['success']);
            [$code,$body]=$get('/document_payments',true,$invoiceInput);
            self::assertSame(200,$code,substr($body,0,1500));
            self::assertSame(1,$db->table('document_payments')->where('document_id',$invoice)->count());
            self::assertSame(6,$db->table('global_payments')->count());
            self::assertSame(1,$db->table('payment_files')->count());
            $attachmentName=$db->table('payment_files')->value('filename');
            self::assertFileExists($temp.'/tenant/'.$db->getDatabaseName().'/payment_files/documents/'.$attachmentName);
            // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
            $mobileDocumentPayment=$db->table('document_payments')->where('document_id',$invoice)->first();
            $mobileMovement=$db->table('global_payments')->where('payment_type',\App\Models\Tenant\DocumentPayment::class)
                ->where('payment_id',$mobileDocumentPayment->id)->first();
            $mobileCapture=$db->table('received_payments')->insertGetId(['provider'=>'Zelle','amount'=>'1','currency_type_id'=>'USD',
                'raw_text'=>'Prueba HTTP','package_name'=>'test.mobile','posted_at'=>'2026-10-26 12:00:00','device_id'=>'http',
                'dedup_hash'=>'http-recorded-receipt','status'=>'matched','matched_document_id'=>$invoice,
                'matched_document_payment_id'=>$mobileDocumentPayment->id,'claimed_by_user_id'=>$input['user_id']]);
            $mobileInput=['document_id'=>$invoice,'payment_method_type_id'=>'01','payment_destination_id'=>$usd->id,
                'currency_type_id'=>'USD','exchange_rate'=>'100'];
            [$code,$body]=$get('/api/received-payments/'.$mobileCapture,false,$mobileInput,false,'PUT');
            self::assertSame(401,$code,substr($body,0,1500));
            [$code,$body]=$get('/api/received-payments/'.$mobileCapture,true,$mobileInput,false,'PUT');
            self::assertSame(409,$code,substr($body,0,1500));
            self::assertStringContainsString('contabilizado',json_decode($body,true,512,JSON_THROW_ON_ERROR)['message']);
            self::assertSame($mobileMovement->report_context,$db->table('global_payments')->where('id',$mobileMovement->id)->value('report_context'));
            self::assertSame($mobileDocumentPayment->payment_method_snapshot,$db->table('document_payments')->where('id',$mobileDocumentPayment->id)->value('payment_method_snapshot'));
            self::assertSame($mobileDocumentPayment->id,$db->table('received_payments')->where('id',$mobileCapture)->value('matched_document_payment_id'));
            self::assertSame(1,$db->table('document_payments')->where('document_id',$invoice)->count());
            self::assertSame(6,$db->table('global_payments')->count());
            // ######## FIN MONEDA DEL MEDIO DE COBRO ########
            $note=new \App\Models\Tenant\SaleNote();
            $note->fill(array_intersect_key($input,array_flip($note->getFillable())));
            $note->establishment=array_replace($input['establishment'],['district_id'=>'-','province_id'=>'-','department_id'=>'-','email'=>'-']);
            $note->forceFill(['prefix'=>'NV','document_type_id'=>'80','series'=>'NV01','number'=>'#','total'=>'0',
                'external_id'=>(string)\Illuminate\Support\Str::uuid(),'user_id'=>$input['user_id'],'state_type_id'=>'01'])->save();
            $line=$note->items()->create(array_replace($input['items'][0],['quantity'=>'1','unit_price'=>'500']));
            $noteInput=['sale_note_id'=>$note->id,'date_of_payment'=>'2026-10-26','payment_method_type_id'=>'01',
                'payment_destination_id'=>$usd->id,'payment'=>'100','currency_type_id'=>'USD','original_amount'=>'1','exchange_rate'=>'100'];
            [$code,$body]=$get('/sale_note_payments',true,array_replace($noteInput,['payment_destination_id'=>$ves->id]));
            self::assertSame(422,$code,substr($body,0,1500));
            [$code,$body]=$get('/sale_note_payments',true,$noteInput);
            self::assertSame(200,$code,substr($body,0,1500));
            self::assertTrue(json_decode($body,true)['success']);
            $notePayment=$db->table('sale_note_payments')->where('sale_note_id',$note->id)->first();
            self::assertSame('01',$notePayment->payment_method_type_id);
            self::assertSame('USD',$notePayment->currency_type_id);
            self::assertSame(1,$db->table('sale_note_payments')->where('sale_note_id',$note->id)->count());
            $db->table('payment_method_types')->where('id','11')->update(['collection_kind'=>'account','currency_type_id'=>'VES','bank_account_id'=>$ves->id,'cash_currency_type_id'=>null,'is_active'=>1]);
            $serviceUsd=$db->table('technical_services')->insertGetId(['user_id'=>$input['user_id'],'customer_id'=>$input['customer_id'],
                'customer'=>json_encode($input['customer']),'establishment_id'=>$input['establishment_id'],'fiscal_environment'=>'demo',
                'currency_type_id'=>'USD','exchange_rate_sale'=>'100','date_of_issue'=>'2026-10-26','time_of_issue'=>'09:00:00',
                'total'=>'1','cellphone'=>'','description'=>'Prueba inversa','state'=>'','reason'=>'','serial_number'=>'HTTP-INVERSE','cost'=>'0']);
            $inverse=['technical_service_id'=>$serviceUsd,'date_of_payment'=>'2026-10-26','payment_method_type_id'=>'11',
                'payment_destination_id'=>$ves->id,'payment'=>'1','currency_type_id'=>'VES','original_amount'=>'100','exchange_rate'=>'100'];
            $serviceModel=\Modules\Sale\Models\TechnicalService::withoutEagerLoads()->findOrFail($serviceUsd);
            self::assertSame('USD',$serviceModel->currency_type_id);
            $prepared=\App\Services\Payments\ReceiptAmounts::prepare(\App\Models\Tenant\PaymentMethodType::findOrFail('11'),'USD','100',$inverse);
            self::assertSame('1',$prepared['payment']);
            self::assertSame('1',\App\Support\Exact\Decimal::represent(\App\Services\Payments\AppliedReceiptBalance::values($serviceModel,true)['total_difference']));
            [$code,$body]=$get('/technical-service-payments/document/'.$serviceUsd);
            self::assertSame(200,$code,substr($body,0,1500));
            self::assertSame('USD',json_decode($body,true)['currency_type_id'],$body);
            self::assertSame('1',json_decode($body,true)['total_difference'],$body);
            [$code,$body]=$get('/technical-service-payments',true,array_replace($inverse,['payment_destination_id'=>$usd->id]));
            self::assertSame(422,$code,substr($body,0,1500));
            self::assertArrayHasKey('payment_destination_id',json_decode($body,true)['message'],$body);
            self::assertSame(0,$db->table('technical_service_payments')->where('technical_service_id',$serviceUsd)->count());
            [$code,$body]=$get('/technical-service-payments',true,$inverse);
            self::assertSame(200,$code,substr($body,0,1500));
            $inversePayment=$db->table('technical_service_payments')->where('technical_service_id',$serviceUsd)->first();
            self::assertSame('VES',$inversePayment->currency_type_id);
            self::assertTrue(\App\Support\Exact\Decimal::of($inversePayment->original_amount)->isEqualTo(\App\Support\Exact\Decimal::of('100')));
            self::assertTrue(\App\Support\Exact\Decimal::of($inversePayment->payment)->isEqualTo(\App\Support\Exact\Decimal::of('1')));
            self::assertSame(1,$db->table('technical_service_payments')->where('technical_service_id',$serviceUsd)->count());
            $contractInput=['id'=>null,'prefix'=>'CON','customer_id'=>$input['customer_id'],'establishment_id'=>$input['establishment_id'],
                'date_of_issue'=>'2026-10-26','time_of_issue'=>'09:00:00','currency_type_id'=>'VES','exchange_rate_sale'=>'100',
                'total'=>'100','items'=>[],'payments'=>[['payment_method_type_id'=>'01','payment_destination_id'=>$usd->id,
                    'date_of_payment'=>'2026-10-26','currency_type_id'=>'USD','original_amount'=>'1','exchange_rate'=>'100','payment'=>'100']]];
            $wrongContract=$contractInput; $wrongContract['payments'][0]['payment_destination_id']=$ves->id;
            [$code,$body]=$get('/contracts',true,$wrongContract);
            self::assertSame(422,$code,substr($body,0,1500));
            self::assertSame(0,$db->table('contracts')->count());
            [$code,$body]=$get('/contracts',true,$contractInput);
            self::assertSame(200,$code,substr($body,0,1500));
            self::assertTrue(json_decode($body,true)['success']);
            self::assertSame(1,$db->table('contract_payments')->count());
            $contractId=json_decode($body,true)['data']['id'];
            [$code,$body]=$get('/contracts/record/'.$contractId);
            self::assertSame(200,$code,substr($body,0,1500));
            $contractPayments=json_decode($body,true)['data']['contract']['payments'];
            $beforeSnapshot=$db->table('contract_payments')->where('contract_id',$contractId)->value('payment_method_snapshot');
            $db->table('payment_method_types')->where('id','01')->update(['collection_kind'=>'cash','currency_type_id'=>'VES','cash_currency_type_id'=>'VES']);
            $contractEdit=array_replace($contractInput,['id'=>$contractId,'payments'=>$contractPayments]);
            [$code,$body]=$get('/contracts',true,$contractEdit);
            self::assertSame(200,$code,substr($body,0,1500));
            self::assertSame($beforeSnapshot,$db->table('contract_payments')->where('contract_id',$contractId)->value('payment_method_snapshot'));
            self::assertSame(1,$db->table('contract_payments')->where('contract_id',$contractId)->count());
            $contractEdit['payments'][0]['original_amount']='2';
            [$code,$body]=$get('/contracts',true,$contractEdit);
            self::assertSame(422,$code,substr($body,0,1500));
            self::assertSame($beforeSnapshot,$db->table('contract_payments')->where('contract_id',$contractId)->value('payment_method_snapshot'));
            [$code,$body]=$get('/sale-notes/items',true,['notes_id'=>[$note->id]]);
            self::assertSame(200,$code,substr($body,0,2500));
            self::assertSame('500',\App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::readRequired(json_decode($body,true)['data'][0],'unit_price')));
            if (getenv('SAB_EXACT_HTTP_PROFILE')==='1') {
                $profileRow=$line->getAttributes();unset($profileRow['id']);
                $db->table('sale_note_items')->where('sale_note_id',$note->id)->delete();
                foreach ([500,500] as $count) $db->table('sale_note_items')->insert(array_fill(0,$count,$profileRow));
                $start=hrtime(true);[$code,$body,$headers]=$get('/sale-notes/items',true,['notes_id'=>[$note->id]]);
                fwrite(STDERR,json_encode(['profile_headers'=>array_filter($headers,fn($key)=>str_starts_with($key,'x-sab'),ARRAY_FILTER_USE_KEY),'total_ms'=>(hrtime(true)-$start)/1000000,'body_bytes'=>strlen($body)]).PHP_EOL);
                self::assertSame(200,$code,substr($body,0,1500));
            }
            $output=getenv('SAB_EXACT_HTTP_BENCH_OUTPUT');
            if ($output) {
                $hashes=[];
                foreach (['app/Http/Controllers/Tenant/SaleNoteController.php','app/Http/Kernel.php','routes/web.php','composer.lock','tests/Support/exact_http_router.php'] as $file) $hashes[$file]=hash_file('sha256',dirname(__DIR__,2).'/'.$file);
                foreach (['app','modules','config','routes','bootstrap'] as $directory) {
                    foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__,2).'/'.$directory,\FilesystemIterator::SKIP_DOTS)) as $file) {
                        if ($file->getExtension()==='php' && !str_contains($file->getPathname(),'/bootstrap/cache/')) {
                            $relative=substr($file->getPathname(),strlen(dirname(__DIR__,2))+1);$hashes[$relative]=hash_file('sha256',$file->getPathname());
                        }
                    }
                }
                $result=['scope'=>'HTTP real: kernel, rutas, sesión cifrada, CSRF y MySQL 5.7 temporal. Sólo selección de partidas de notas; no acredita facturación/POS/caja ni central separado.',
                    'reference_head'=>trim(shell_exec('git rev-parse HEAD')),'warmups'=>3,'samples'=>30,
                    'php_version'=>PHP_VERSION,'mysql_version'=>$db->selectOne('SELECT VERSION() AS v')->v,'source_sha256'=>$hashes,
                    'reference_controller_sha256'=>$reference ? hash_file('sha256',$reference):null,
                    'queries_scope'=>'SQL del kernel tras bootstrap; la latencia incluye también bootstrap y transporte HTTP.',
                    'cases'=>[]];
                $row=$line->getAttributes();unset($row['id']);
                foreach ([1,100,1000] as $size) {
                    $db->table('sale_note_items')->where('sale_note_id',$note->id)->delete();
                    for ($offset=0;$offset<$size;$offset+=500) $db->table('sale_note_items')->insert(array_fill(0,min(500,$size-$offset),$row));
                    $times=$queries=$memory=[];$variants=$reference ? ['reference','current'] : ['current'];
                    for ($sample=-3;$sample<30;$sample++) foreach (($sample%2 ? array_reverse($variants) : $variants) as $variant) {
                        $start=hrtime(true);[$code,$body,$headers]=$get('/sale-notes/items',true,['notes_id'=>[$note->id]],$variant==='reference');$elapsed=(hrtime(true)-$start)/1000000;
                        self::assertSame(200,$code,substr($body,0,1500));
                        self::assertCount($size,json_decode($body,true)['data']);
                        if ($sample>=0) {$times[$variant][]=$elapsed;$queries[$variant][]=(int)$headers['x-sab-test-queries'];$memory[$variant][]=(int)$headers['x-sab-test-peak-memory'];}
                    }
                    foreach ($variants as $variant) {
                        $sorted=$times[$variant];sort($sorted);
                        $result['cases'][(string)$size][$variant]=['p50_ms'=>$sorted[14],'p95_ms'=>$sorted[28],
                            'queries_max'=>max($queries[$variant]),'memory_peak_bytes'=>max($memory[$variant]),'raw_ms'=>$times[$variant]];
                    }
                    if ($reference) {
                        $base=$result['cases'][(string)$size]['reference']['p95_ms'];$limit=$base+max($base*.2,100);
                        $result['cases'][(string)$size]['allowed_p95_ms']=$limit;
                        $result['cases'][(string)$size]['passed']=$result['cases'][(string)$size]['current']['p95_ms']<=$limit;
                    }
                    if ($initialPath=getenv('SAB_EXACT_HTTP_INITIAL_BASELINE')) {
                        $initial=json_decode(file_get_contents($initialPath),true,512,JSON_THROW_ON_ERROR);
                        $base=$initial['cases'][(string)$size]['p95_ms'];$limit=$base+max($base*.2,100);
                        $result['cases'][(string)$size]['initial_allowed_p95_ms']=$limit;
                        $result['cases'][(string)$size]['initial_passed']=$result['cases'][(string)$size]['current']['p95_ms']<=$limit;
                    }
                }
                foreach ($hashes as $file=>$hash) self::assertSame($hash,hash_file('sha256',dirname(__DIR__,2).'/'.$file),'Cambió una fuente durante la medición.');
                file_put_contents($output,json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL);
                if ($reference) foreach ($result['cases'] as $case) self::assertTrue($case['passed'],'El p95 excede el presupuesto HTTP.');
                if (getenv('SAB_EXACT_HTTP_INITIAL_BASELINE')) foreach ($result['cases'] as $case) self::assertTrue($case['initial_passed'],'El p95 excede la referencia inicial de la continuación.');
            }
            // Re-resolve the sources on each request; a stale selection is not authority.
            [$code,$body]=$get('/sale-notes/items',true,['notes_id'=>[$note->id]]);
            $prepared=json_decode($body,true);
            self::assertSame(200,$code,substr($body,0,1500));
            self::assertSame('VES',$prepared['conversion_context']['currency_type_id']);
            self::assertCount(1,$prepared['grouped_items']);
            // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
            self::assertCount(1,$prepared['payments']);
            self::assertSame($notePayment->id,$prepared['payments'][0]['id']);
            self::assertSame('USD',$prepared['payments'][0]['currency_type_id']);
            self::assertSame((string)$usd->id,(string)$prepared['payments'][0]['payment_destination_id']);
            // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
            $other=\App\Support\Exact\LineCalculation::calculate(array_replace($input['items'][0],['unit_price'=>'499','quantity'=>'1']),'16');
            $otherRow=$line->getAttributes();unset($otherRow['id']);
            $otherItem=$input['items'][0]['item'];$otherItem['presentation']=(object)[];
            $otherRow['item']=json_encode($otherItem,JSON_THROW_ON_ERROR);
            foreach (\App\Support\Exact\Persistence::columns($line) as $field) if (isset($other[$field])) $otherRow[$field]=$other[$field];
            $otherRow['calculation_snapshot']=json_encode($other['calculation_snapshot'],JSON_THROW_ON_ERROR);
            $db->table('sale_note_items')->insert($otherRow);
            [$code,$body]=$get('/sale-notes/items',true,['notes_id'=>[$note->id]]);
            self::assertSame(200,$code,substr($body,0,1500));
            self::assertSame(['499','500'],collect(json_decode($body,true)['grouped_items'])->map(fn($row)=>\App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::readRequired($row,'unit_price')))->sort()->values()->all());
            [$code,$body]=$get('/sale-notes/items',true,['notes_id'=>[$note->id],'select_all'=>true]);
            self::assertSame(200,$code,substr($body,0,1500));
            $raw=json_decode($body)->data;
            self::assertInstanceOf(\stdClass::class,$raw[array_key_last($raw)]->item->presentation);
            $db->table('sale_notes')->where('id',$note->id)->update(['state_type_id'=>'11']);
            [$code]=$get('/sale-notes/items',true,['notes_id'=>[$note->id]]);self::assertSame(422,$code);
            $db->table('sale_notes')->where('id',$note->id)->update(['state_type_id'=>'01']);
            [$code]=$get('/sale-notes/items',true,['notes_id'=>[$note->id,$note->id]]);self::assertSame(422,$code);
            [$code]=$get('/sale-notes/items',true,['notes_id'=>[99999999]]);self::assertSame(404,$code);
            $foreignContext=$note->replicate();
            $foreignContext->forceFill(['number'=>'#','external_id'=>(string)\Illuminate\Support\Str::uuid(),
                'currency_type_id'=>'USD','exchange_rate_sale'=>'2.00000000'])->save();
            [$code]=$get('/sale-notes/items',true,['notes_id'=>[$note->id,$foreignContext->id]]);
            self::assertSame(422,$code,'Una selección mixta no puede heredar silenciosamente la primera tasa o moneda.');
            $legacy=$note->replicate();
            $legacy->forceFill(['number'=>'#','external_id'=>(string)\Illuminate\Support\Str::uuid()])->save();
            foreach ([1,2] as $position) $legacy->items()->create(array_replace($input['items'][0],[
                'quantity'=>'1','unit_price'=>'100000000000000',
                'discounts'=>[['discount_type_id'=>'00','is_amount'=>true,'amount'=>'1234567890123.12']],
            ]));
            // A stored JSON number is not an invitation to convert it through PHP float.
            $db->table('sale_note_items')->where('sale_note_id',$legacy->id)->update([
                'discounts'=>'[{"discount_type_id":"00","is_amount":true,"amount":1234567890123.12}]',
            ]);
            [$code,$body]=$get('/sale-notes/items',true,['notes_id'=>[$legacy->id]]);
            self::assertSame(200,$code,substr($body,0,1500));
            $grouped=json_decode($body,true)['grouped_items'][0];
            self::assertSame('2469135780246.24',\App\Support\Exact\Decimal::represent(
                $grouped['discounts'][0]['amount_exact']));
            // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
            // Convert a second quotation directly to an invoice using a historical bank receipt.
            $db->table('payment_method_types')->where('id','13')->update(['collection_kind'=>'account',
                'currency_type_id'=>'USD','bank_account_id'=>$usd->id,'cash_currency_type_id'=>null,'is_cash'=>0,'is_active'=>1]);
            $invoiceQuotation=$db->table('quotations')->insertGetId(['user_id'=>$input['user_id'],'external_id'=>(string)\Illuminate\Support\Str::uuid(),
                'establishment_id'=>$input['establishment_id'],'establishment'=>json_encode($input['establishment']),
                'fiscal_environment'=>'demo','state_type_id'=>'05','prefix'=>'COT','date_of_issue'=>'2026-10-26','time_of_issue'=>'09:00:00',
                'customer_id'=>$input['customer_id'],'customer'=>json_encode($input['customer']),'currency_type_id'=>'VES','exchange_rate_sale'=>'100','total'=>'232']);
            [$code,$body]=$get('/quotation_payments',true,['quotation_id'=>$invoiceQuotation,'date_of_payment'=>'2026-10-26',
                'payment_method_type_id'=>'13','payment_destination_id'=>$usd->id,'payment'=>'232',
                'currency_type_id'=>'USD','original_amount'=>'2.32','exchange_rate'=>'100']);
            self::assertSame(200,$code,substr($body,0,1500));
            self::assertTrue(json_decode($body,true)['success'],$body);
            $invoiceSource=$db->table('quotation_payments')->where('quotation_id',$invoiceQuotation)->first();
            $invoiceMovement=$db->table('global_payments')->where('payment_type',\Modules\Sale\Models\QuotationPayment::class)
                ->where('payment_id',$invoiceSource->id)->value('report_context');
            self::assertNotNull($invoiceMovement);
            [$code,$body]=$get('/quotations/record2/'.$invoiceQuotation);
            self::assertSame(200,$code,substr($body,0,1500));
            $invoiceRows=json_decode($body,true)['data']['quotation']['payments'];
            self::assertCount(1,$invoiceRows);
            $db->table('payment_method_types')->where('id','13')->update(['collection_kind'=>'cash',
                'currency_type_id'=>'VES','cash_currency_type_id'=>'VES','is_cash'=>1,'is_active'=>0,'description'=>'Reclasificado factura HTTP']);
            $movementCount=$db->table('global_payments')->count();
            $invoiceRequest=array_replace($input,['external_id'=>(string)\Illuminate\Support\Str::uuid(),
                'quotation_id'=>$invoiceQuotation,'exchange_rate_sale'=>'100','payments'=>$invoiceRows,
                'operation_type_id'=>'0101','date_of_due'=>'2026-10-26']);
            [$code,$body]=$get('/documents',true,$invoiceRequest);
            self::assertSame(200,$code,substr($body,0,2500));
            $invoiceResult=json_decode($body,true);
            self::assertTrue($invoiceResult['success'],$body);
            $newInvoice=$invoiceResult['data']['id'];
            [$reportCode,$reportBody]=$get('/api/reports/general-items?type=sale&document_type_id=01&date_start=2026-01-01&date_end=2027-12-31');
            self::assertSame(200,$reportCode,substr($reportBody,0,2500));
            $reportRows=json_decode($reportBody,true,512,JSON_THROW_ON_ERROR);
            self::assertNotEmpty($reportRows);
            self::assertArrayHasKey('calculation_snapshot',$reportRows[0]);
            self::assertArrayHasKey('purchase_total_fraction',$reportRows[0]);
            [$reportCode]=$get('/api/reports/general-items?type=sale&document_type_id=01&date_start=2026-01-01&date_end=2027-12-31',false);
            self::assertSame(401,$reportCode);
            [$reportCode,$reportBody]=$get('/reports/general-items/records?type=sale&document_type_id=01&period=between_dates&date_start=2026-01-01&date_end=2027-12-31&apply_conversion_to_pen=false');
            self::assertSame(200,$reportCode,substr($reportBody,0,2500));
            $webRows=json_decode($reportBody,true,512,JSON_THROW_ON_ERROR)['data'];
            self::assertNotEmpty($webRows);
            self::assertArrayHasKey('calculation_snapshot',$webRows[0]);
            $invoiceReceipt=$db->table('document_payments')->where('document_id',$newInvoice)->first();
            self::assertNotNull($invoiceReceipt);
            self::assertSame('13',$invoiceReceipt->payment_method_type_id);
            self::assertSame('USD',$invoiceReceipt->currency_type_id);
            self::assertSame('2.32',\App\Support\Exact\Decimal::represent($invoiceReceipt->original_amount));
            self::assertSame('232',\App\Support\Exact\Decimal::represent($invoiceReceipt->payment));
            $invoiceMethodSnapshot=json_decode($invoiceReceipt->payment_method_snapshot,true);
            self::assertSame('account',$invoiceMethodSnapshot['collection_kind']);
            self::assertSame('100',\App\Support\Exact\Decimal::represent($invoiceReceipt->exchange_rate));
            $invoiceOrigin=$invoiceMethodSnapshot['receipt_origin'];
            self::assertSame(\Modules\Sale\Models\QuotationPayment::class,$invoiceOrigin['type']);
            self::assertSame((string)$invoiceSource->id,$invoiceOrigin['id']);
            self::assertSame($invoiceSource->payment_method_snapshot,$db->table('quotation_payments')->where('id',$invoiceSource->id)->value('payment_method_snapshot'));
            self::assertSame($invoiceMovement,$db->table('global_payments')->where('payment_type',\Modules\Sale\Models\QuotationPayment::class)
                ->where('payment_id',$invoiceSource->id)->value('report_context'));
            self::assertSame($movementCount,$db->table('global_payments')->count());
            [$code,$body]=$get('/documents',true,$invoiceRequest);
            self::assertTrue($code===422 || ($code===200 && !json_decode($body,true)['success']),$body);
            self::assertSame(1,$db->table('documents')->where('quotation_id',$invoiceQuotation)->count());
            self::assertSame($movementCount,$db->table('global_payments')->count());
            $db->table('payment_method_types')->where('id','13')->update(['collection_kind'=>'account','currency_type_id'=>'VES',
                'bank_account_id'=>$ves->id,'cash_currency_type_id'=>null,'is_cash'=>0,'is_active'=>1,'commission_percentage'=>'10']);
            $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
            $settlementInput=array_replace($input,['series'=>'LC01','number'=>'#','supplier_id'=>$input['customer_id'],
                'operation_data'=>[],'subtotal'=>'232','total'=>'232','exchange_rate_sale'=>'100',
                'payments'=>[['date_of_payment'=>'2026-10-26','payment_method_type_id'=>'13',
                    'payment_destination_id'=>$ves->id,'reference'=>'HTTP liquidación','payment'=>'232']]]);
            $settlementBefore=$db->table('purchase_settlements')->count();
            self::assertFalse($db->table('cat_document_types')->where('id','04')->exists());
            [$blockedCode,$blockedBody]=$get('/purchase-settlements',true,$settlementInput);
            self::assertSame(422,$blockedCode,substr($blockedBody,0,1000));
            self::assertSame($settlementBefore,$db->table('purchase_settlements')->count());
            $unavailableSettlement=$settlementInput; $unavailableSettlement['document_type_id']='04';
            [$blockedCode,$blockedBody]=$get('/purchase-settlements',true,$unavailableSettlement);
            self::assertSame(422,$blockedCode,substr($blockedBody,0,1000));
            self::assertSame($settlementBefore,$db->table('purchase_settlements')->count());
            // The retired type exists only in this disposable fixture to exercise historical calculation paths.
            $db->table('cat_document_types')->insert(['id'=>'04','active'=>1,'short'=>'LC','description'=>'LIQUIDACIÓN DE PRUEBA','is_sunat'=>0]);
            $legacySettlementSeries=$db->table('series')->insertGetId(['establishment_id'=>$input['establishment_id'],'document_type_id'=>'04','number'=>'LC01']);
            $db->table('series_configurations')->insert(['series_id'=>$legacySettlementSeries,'document_type_id'=>'04','series'=>'LC01','number'=>100]);
            $settlementInput['document_type_id']='04';
            $paymentBefore=$db->table('purchase_settlement_payments')->count();
            $movementBefore=$db->table('global_payments')->count();
            $feeBefore=$db->table('payment_commissions')->count();
            $invalidSettlement=$settlementInput;$invalidSettlement['payments'][0]['payment_destination_id']='cash';
            [$code,$body]=$get('/purchase-settlements',true,$invalidSettlement);
            self::assertFalse(json_decode($body,true)['success'] ?? true,$body);
            self::assertStringContainsString('cuenta',strtolower(json_decode($body,true)['message'] ?? ''),$body);
            self::assertSame($settlementBefore,$db->table('purchase_settlements')->count());
            self::assertSame($paymentBefore,$db->table('purchase_settlement_payments')->count());
            self::assertSame($movementBefore,$db->table('global_payments')->count());
            [$code,$body]=$get('/purchase-settlements',true,$settlementInput);
            self::assertSame(200,$code,substr($body,0,2500));
            self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            self::assertSame($settlementBefore+1,$db->table('purchase_settlements')->count());
            self::assertSame('0101',$db->table('purchase_settlements')->orderByDesc('id')->value('operation_type_id'));
            $operation=json_decode($db->table('purchase_settlements')->orderByDesc('id')->value('operation_data'),true);
            self::assertSame('14',$operation['department_id']);
            self::assertSame('0229',$operation['province_id']);
            self::assertSame('000619',$operation['district_id']);
            self::assertSame($paymentBefore+1,$db->table('purchase_settlement_payments')->count());
            self::assertSame($movementBefore+1,$db->table('global_payments')->count());
            self::assertSame($feeBefore,$db->table('payment_commissions')->count());
            $fractionSettlement=$settlementInput; $fractionSettlement['payments']=[];
            $fractionSettlement['total']='9999'; $fractionSettlement['subtotal']='9999';
            $fractionSettlement['items'][0]=array_replace($fractionSettlement['items'][0],[
                'quantity'=>'0.333333333333','quantity_input'=>['amount'=>'1','unit_price'=>'3'],
                'unit_price'=>'3','price_input'=>null,'affectation_igv_type_id'=>'20',
                'total'=>'9999','total_value'=>'9999','total_igv'=>'9999','total_taxes'=>'9999']);
            $excessSettlement=$fractionSettlement;
            $excessSettlement['payments']=$settlementInput['payments']; $excessSettlement['payments'][0]['payment']='2';
            $beforeExcess=$db->table('purchase_settlements')->count();
            [$code,$body]=$get('/purchase-settlements',true,$excessSettlement);
            self::assertSame(422,$code,substr($body,0,2500));
            self::assertSame($beforeExcess,$db->table('purchase_settlements')->count());
            self::assertSame($paymentBefore+1,$db->table('purchase_settlement_payments')->count());
            self::assertSame($movementBefore+1,$db->table('global_payments')->count());
            [$code,$body]=$get('/purchase-settlements',true,$fractionSettlement);
            self::assertSame(200,$code,substr($body,0,2500)); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $fractionSettlementId=json_decode($body,true)['data']['id'];
            $savedSettlement=\App\Models\Tenant\PurchaseSettlement::findOrFail($fractionSettlementId);
            $savedSettlementItem=$savedSettlement->items()->firstOrFail();
            self::assertTrue($savedSettlementItem->exactValue('quantity')->isEqualTo(\App\Support\Exact\Decimal::of(1)->dividedBy(3)));
            self::assertTrue($savedSettlementItem->exactValue('total')->isEqualTo('1'));
            self::assertTrue($savedSettlement->exactValue('total')->isEqualTo('1'));
            self::assertTrue($savedSettlement->exactValue('total_igv')->isZero());
            self::assertStringNotContainsString('nueve mil',strtolower(json_encode($savedSettlement->legends)));
            [$listStatus,$listBody]=$get('/purchase-settlements/records?column=number&value='.$savedSettlement->number);
            self::assertSame(200,$listStatus,substr($listBody,0,1000));
            $listRows=json_decode($listBody,true)['data'];
            $listedSettlement=collect($listRows)->firstWhere('id',$fractionSettlementId);
            self::assertNotNull($listedSettlement); self::assertSame('VES',$listedSettlement['currency_type_id']);
            self::assertTrue(\App\Support\Exact\Decimal::of($listedSettlement['total'])->isEqualTo('1'));
            [$pdfStatus,$settlementPdf]=$get('/downloads/purchaseSettlement/pdf/'.$savedSettlement->external_id);
            self::assertSame(200,$pdfStatus,substr($settlementPdf,0,1000));
            self::assertStringStartsWith('%PDF-',$settlementPdf);
            $settlementPdfPath=$temp.'/settlement-fraction.pdf'; $settlementTextPath=$temp.'/settlement-fraction.txt';
            file_put_contents($settlementPdfPath,$settlementPdf);
            $process=proc_open(['pdftotext','-layout',$settlementPdfPath,$settlementTextPath],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            self::assertIsResource($process); foreach ($pipes as $pipe) fclose($pipe);
            self::assertSame(0,proc_close($process));
            $settlementText=file_get_contents($settlementTextPath);
            self::assertStringContainsString('1.00',$settlementText);
            self::assertStringNotContainsString('9,999.00',$settlementText);
            $apiSettlement=[
                'serie_documento'=>'LC01','numero_documento'=>'#','fecha_de_emision'=>'2026-10-26',
                'hora_de_emision'=>'09:00:00','codigo_tipo_operacion'=>'0101','codigo_tipo_documento'=>'04',
                'codigo_tipo_moneda'=>'VES','factor_tipo_de_cambio'=>'100',
                'datos_del_proveedor_o_receptor'=>['codigo_tipo_documento_identidad'=>'1','numero_documento'=>'12345678',
                    'apellidos_y_nombres_o_razon_social'=>'Proveedor API','codigo_pais'=>'VE','direccion'=>'Prueba',
                    'codigo_tipo_direccion'=>'01'],
                'datos_de_la_operacion'=>['codigo_pais'=>'VE','ubigeo'=>'000619','direccion'=>'Prueba','codigo_tipo_direccion'=>'01'],
                'totales'=>['total_anticipos'=>'0','total_operaciones_gravadas'=>'9999','total_operaciones_inafectas'=>'0',
                    'total_operaciones_exoneradas'=>'0','total_igv'=>'9999','total_impuestos'=>'9999',
                    'total_valor'=>'9999','subtotal_compra'=>'9999','total_compra'=>'9999'],
                'items'=>[['codigo_interno'=>'MOCK-ITEM-VES-001','descripcion'=>'Producto prueba','unidad_de_medida'=>'UND',
                    'cantidad'=>'0.333333333333','quantity_input'=>['amount'=>'1','unit_price'=>'3'],
                    'valor_unitario'=>'3','precio_unitario'=>'3','codigo_tipo_precio'=>'01','codigo_tipo_afectacion_igv'=>'20',
                    'total_base_igv'=>'9999','porcentaje_igv'=>'0','total_igv'=>'9999','total_impuestos'=>'9999',
                    'total_valor_item'=>'9999','total_item'=>'9999']],
                'acciones'=>['enviar_email'=>false,'formato_pdf'=>'a4'],
            ];
            $wrongApiSettlement=$apiSettlement; $wrongApiSettlement['codigo_tipo_documento']='01';
            [$blockedCode,$blockedBody]=$get('/api/purchase-settlements',true,$wrongApiSettlement);
            self::assertSame(422,$blockedCode,substr($blockedBody,0,1000));
            $db->table('cat_document_types')->where('id','04')->update(['active'=>false]);
            [$blockedCode,$blockedBody]=$get('/api/purchase-settlements',true,$apiSettlement);
            self::assertSame(422,$blockedCode,substr($blockedBody,0,1000));
            $db->table('cat_document_types')->where('id','04')->update(['active'=>true]);
            [$apiStatus,$apiBody]=$get('/api/purchase-settlements',true,$apiSettlement);
            self::assertSame(200,$apiStatus,substr($apiBody,0,2500)); self::assertTrue(json_decode($apiBody,true)['success'] ?? false,$apiBody);
            $apiExternal=json_decode($apiBody,true)['data']['external_id'];
            $apiSaved=\App\Models\Tenant\PurchaseSettlement::where('external_id',$apiExternal)->firstOrFail();
            self::assertTrue($apiSaved->items()->firstOrFail()->exactValue('quantity')->isEqualTo(\App\Support\Exact\Decimal::of(1)->dividedBy(3)));
            self::assertTrue($apiSaved->exactValue('total')->isEqualTo('1'));
            $apiAdvance=$apiSettlement;
            unset($apiAdvance['items'][0]['quantity_input']);
            $apiAdvance['items'][0]['cantidad']='1'; $apiAdvance['items'][0]['precio_unitario']='0.1';
            $secondAdvanceLine=$apiAdvance['items'][0]; $secondAdvanceLine['precio_unitario']='0.9';
            $apiAdvance['items'][]=$secondAdvanceLine;
            $apiAdvance['totales']['total_anticipos']='0.4';
            $apiAdvance['anticipos']=[['numero'=>'A-1','codigo_tipo_documento'=>'01','monto'=>'0.1'],
                ['numero'=>'A-2','codigo_tipo_documento'=>'01','monto'=>'0.3']];
            foreach (['mismatch','duplicate','excess'] as $invalidKind) {
                $invalidAdvance=$apiAdvance;
                if ($invalidKind==='mismatch') $invalidAdvance['totales']['total_anticipos']='0.3';
                if ($invalidKind==='duplicate') $invalidAdvance['anticipos'][1]['numero']='A-1';
                if ($invalidKind==='excess') { $invalidAdvance['anticipos'][1]['monto']='1'; $invalidAdvance['totales']['total_anticipos']='1.1'; }
                $beforeInvalidAdvance=$db->table('purchase_settlements')->count();
                $advanceItemId=$input['items'][0]['item_id']; $advanceWarehouse=$input['items'][0]['warehouse_id'];
                $beforeAdvanceStock=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$advanceItemId)->where('warehouse_id',$advanceWarehouse)->firstOrFail()->exactValue('stock');
                $beforeAdvanceGlobal=\App\Models\Tenant\Item::findOrFail($advanceItemId)->exactValue('stock');
                $beforeAdvanceKardex=$db->table('inventory_kardex')->where('item_id',$advanceItemId)->count();
                [$apiStatus,$apiBody]=$get('/api/purchase-settlements',true,$invalidAdvance);
                self::assertSame(422,$apiStatus,$invalidKind.': '.substr($apiBody,0,1000));
                self::assertSame($beforeInvalidAdvance,$db->table('purchase_settlements')->count());
                self::assertTrue(\Modules\Inventory\Models\ItemWarehouse::where('item_id',$advanceItemId)->where('warehouse_id',$advanceWarehouse)->firstOrFail()->exactValue('stock')->isEqualTo($beforeAdvanceStock));
                self::assertTrue(\App\Models\Tenant\Item::findOrFail($advanceItemId)->exactValue('stock')->isEqualTo($beforeAdvanceGlobal));
                self::assertSame($beforeAdvanceKardex,$db->table('inventory_kardex')->where('item_id',$advanceItemId)->count());
            }
            [$apiStatus,$apiBody]=$get('/api/purchase-settlements',true,$apiAdvance);
            self::assertSame(200,$apiStatus,substr($apiBody,0,2500)); self::assertTrue(json_decode($apiBody,true)['success'] ?? false,$apiBody);
            $advanceExternal=json_decode($apiBody,true)['data']['external_id'];
            $advanceSaved=\App\Models\Tenant\PurchaseSettlement::where('external_id',$advanceExternal)->firstOrFail();
            self::assertTrue($advanceSaved->exactValue('total_prepayment')->isEqualTo('0.4'));
            self::assertTrue($advanceSaved->exactValue('total')->isEqualTo('0.6'));
            self::assertCount(2,(array)$advanceSaved->prepayments);
            [$pdfStatus,$advancePdf]=$get('/downloads/purchaseSettlement/pdf/'.$advanceExternal);
            self::assertSame(200,$pdfStatus,substr($advancePdf,0,1000)); self::assertStringStartsWith('%PDF-',$advancePdf);
            $advancePdfPath=$temp.'/settlement-advance.pdf'; $advanceTextPath=$temp.'/settlement-advance.txt';
            file_put_contents($advancePdfPath,$advancePdf);
            $process=proc_open(['pdftotext','-layout',$advancePdfPath,$advanceTextPath],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            self::assertIsResource($process); foreach ($pipes as $pipe) fclose($pipe);
            self::assertSame(0,proc_close($process)); $advanceText=file_get_contents($advanceTextPath);
            self::assertStringContainsString('ANTICIPOS',$advanceText);
            self::assertStringContainsString('0.40',$advanceText); self::assertStringContainsString('0.60',$advanceText);
            $apiDiscount=$apiSettlement;
            unset($apiDiscount['items'][0]['quantity_input']);
            $apiDiscount['items'][0]['cantidad']='1';
            $apiDiscount['items'][0]['price_input']=['amount'=>'1','currency_type_id'=>'USD','includes_iva'=>true];
            $apiDiscount['items'][0]['descuentos']=[['codigo'=>'00','descripcion'=>'Descuento','factor'=>'0.1','monto'=>'9999','base'=>'9999']];
            [$apiStatus,$apiBody]=$get('/api/purchase-settlements',true,$apiDiscount);
            self::assertSame(200,$apiStatus,substr($apiBody,0,2500)); self::assertTrue(json_decode($apiBody,true)['success'] ?? false,$apiBody);
            $discountSaved=\App\Models\Tenant\PurchaseSettlement::where('external_id',json_decode($apiBody,true)['data']['external_id'])->firstOrFail();
            self::assertTrue($discountSaved->exactValue('total')->isEqualTo('90'));
            self::assertCount(1,$discountSaved->items()->firstOrFail()->discounts);
            $originalSettlementTemplate=$db->table('establishments')->where('id',$input['establishment_id'])->value('template_pdf');
            foreach (['default','Plantilla_personalizable','modern-2027','marca_de_agua'] as $settlementTemplate) {
                $db->table('establishments')->where('id',$input['establishment_id'])->update(['template_pdf'=>$settlementTemplate]);
                [$pdfStatus,$templatePdf]=$get('/downloads/purchaseSettlement/pdf/'.$advanceExternal.'/a4');
                self::assertSame(200,$pdfStatus,$settlementTemplate.': '.substr($templatePdf,0,1000));
                self::assertStringStartsWith('%PDF-',$templatePdf);
                if ($evidenceDir=getenv('SAB_SETTLEMENT_PDF_EVIDENCE_DIR')) file_put_contents($evidenceDir.'/'.$settlementTemplate.'.pdf',$templatePdf);
                $templatePdfPath=$temp.'/settlement-'.$settlementTemplate.'.pdf';
                $templateTextPath=$temp.'/settlement-'.$settlementTemplate.'.txt';
                file_put_contents($templatePdfPath,$templatePdf);
                $process=proc_open(['pdftotext','-layout',$templatePdfPath,$templateTextPath],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
                self::assertIsResource($process); foreach ($pipes as $pipe) fclose($pipe);
                self::assertSame(0,proc_close($process)); $templateText=file_get_contents($templateTextPath);
                self::assertStringContainsString('ANTICIPOS',$templateText);
                self::assertStringContainsString('0.40',$templateText); self::assertStringContainsString('0.60',$templateText);
                self::assertStringContainsString('IVA:',$templateText);
            }
            $db->table('establishments')->where('id',$input['establishment_id'])->update(['template_pdf'=>$originalSettlementTemplate]);
            $apiLocationInput=array_replace($input,['payment_condition_id'=>'02','payments'=>[],'fee'=>[],
                'operation_type_id'=>'0101','date_of_issue'=>now()->format('Y-m-d'),
                'date_of_due'=>now()->addDays(30)->format('Y-m-d'),'actions'=>['send_email'=>false]]);
            $apiLocation=\App\CoreFacturalo\Requests\Inputs\Transform\DocumentWebTransform::transform($apiLocationInput);
            $apiLocation['datos_del_cliente_o_receptor']['numero_documento']='98765432';
            $apiLocation['datos_del_cliente_o_receptor']['codigo_pais']='VE';
            $apiLocation['datos_del_cliente_o_receptor']['ubigeo']='000619';
            $apiLocation['datos_del_cliente_o_receptor']['correo_electronico']=null;
            $invalidLocation=$apiLocation;
            $invalidLocation['datos_del_cliente_o_receptor']['numero_documento']='98765433';
            $invalidLocation['datos_del_cliente_o_receptor']['ubigeo']='999999';
            $beforeLocationDocuments=$db->table('documents')->count();
            [$locationStatus,$locationBody]=$get('/api/documents',true,$invalidLocation);
            self::assertSame(422,$locationStatus,substr($locationBody,0,1500));
            self::assertFalse($db->table('persons')->where('number','98765433')->exists());
            self::assertSame($beforeLocationDocuments,$db->table('documents')->count());
            [$locationStatus,$locationBody]=$get('/api/documents',true,$apiLocation);
            self::assertSame(200,$locationStatus,substr($locationBody,0,1500));
            self::assertTrue(json_decode($locationBody,true)['success'] ?? false,$locationBody);
            $locationInvoice=\App\Models\Tenant\Document::findOrFail(json_decode($locationBody,true)['data']['id']);
            self::assertSame('free_form',$locationInvoice->fiscal_emission_mode);
            self::assertSame('14',$locationInvoice->customer->department_id);
            self::assertSame('0229',$locationInvoice->customer->province_id);
            self::assertSame('000619',$locationInvoice->customer->district_id);
            self::assertTrue($locationInvoice->exactValue('total')->isEqualTo('232'));
            [$locationPdfStatus,$locationPdf]=$get('/documents/'.$locationInvoice->external_id.'/download-pdf/a4');
            self::assertSame(200,$locationPdfStatus); self::assertStringStartsWith('%PDF-',$locationPdf);
            $apiDispatch=[
                'serie_documento'=>'TT01','numero_documento'=>'876','codigo_tipo_documento'=>'09',
                'fecha_de_emision'=>now()->format('Y-m-d'),'hora_de_emision'=>'12:00:00','fecha_de_traslado'=>now()->format('Y-m-d'),
                'datos_del_emisor'=>['codigo_del_domicilio_fiscal'=>'0000'],
                'datos_del_cliente_o_receptor'=>['codigo_tipo_documento_identidad'=>'1','numero_documento'=>'98765432',
                    'apellidos_y_nombres_o_razon_social'=>'Cliente territorial','codigo_pais'=>'VE','ubigeo'=>'000619','direccion'=>'Prueba'],
                'codigo_modo_transporte'=>'02','codigo_motivo_traslado'=>'24','descripcion_motivo_traslado'=>'Prueba API',
                'indicador_de_transbordo'=>false,'unidad_peso_total'=>'KG','peso_total'=>'1',
                'direccion_partida'=>['ubigeo'=>'000619','direccion'=>'Origen API','codigo_del_domicilio_fiscal'=>'0000'],
                'direccion_llegada'=>['ubigeo'=>'000619','direccion'=>'Destino API','codigo_del_domicilio_fiscal'=>'0000'],
                'chofer'=>['codigo_tipo_documento_identidad'=>'1','numero_documento'=>'98760001','nombres'=>'Conductor API','numero_licencia'=>'123'],
                'vehiculo'=>['numero_de_placa'=>'QA98765','marca'=>'Prueba','modelo'=>'Prueba'],
                'items'=>[['codigo_interno'=>'API-NEW-DISPATCH-UND','descripcion'=>'Producto nuevo API',
                    'unidad_de_medida'=>'UND','cantidad'=>'2.5','precio_unitario'=>'1','codigo_tipo_afectacion_igv'=>'20']],
                'acciones'=>['enviar_email'=>false,'formato_pdf'=>'a4'],
            ];
            $invalidDispatch=$apiDispatch; $invalidDispatch['numero_documento']='875';
            $invalidDispatch['items'][0]['codigo_interno']='API-INVALID-DISPATCH';
            $invalidDispatch['items'][0]['codigo_tipo_afectacion_igv']='30';
            $beforeInvalidDispatch=$db->table('dispatches')->count();
            [$dispatchStatus,$dispatchBody]=$get('/api/dispatches',true,$invalidDispatch);
            self::assertSame(422,$dispatchStatus,substr($dispatchBody,0,1800));
            self::assertFalse($db->table('items')->where('internal_id','API-INVALID-DISPATCH')->exists());
            self::assertSame($beforeInvalidDispatch,$db->table('dispatches')->count());
            [$dispatchStatus,$dispatchBody]=$get('/api/dispatches',true,$apiDispatch);
            self::assertSame(200,$dispatchStatus,substr($dispatchBody,0,1800));
            self::assertTrue(json_decode($dispatchBody,true)['success'] ?? false,$dispatchBody);
            $newDispatchItem=\App\Models\Tenant\Item::where('internal_id','API-NEW-DISPATCH-UND')->firstOrFail();
            self::assertTrue($newDispatchItem->exactValue('stock')->isZero());
            $newDispatchWarehouse=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$newDispatchItem->id)
                ->where('warehouse_id',$input['items'][0]['warehouse_id'])->first();
            self::assertTrue(!$newDispatchWarehouse || $newDispatchWarehouse->exactValue('stock')->isZero());
            $initialStockControl=$db->table('inventory_configurations')->value('stock_control');
            $db->table('inventory_configurations')->update(['stock_control'=>false]);
            $db->table('cat_transfer_reason_types')->where('id','24')->update(['discount_stock'=>true]);
            $unavailableDispatch=$apiDispatch; $unavailableDispatch['numero_documento']='877';
            $unavailableDispatch['items'][0]['codigo_interno']='API-NO-STOCK-DISPATCH';
            $unavailableDispatch['datos_del_cliente_o_receptor']['numero_documento']='98769999';
            $unavailableDispatch['chofer']['numero_documento']='98769998';
            $unavailableDispatch['vehiculo']['numero_de_placa']='QA98766';
            $beforeUnavailableDispatch=$db->table('dispatches')->count();
            $beforeUnavailableKardex=$db->table('inventory_kardex')->count();
            [$dispatchStatus,$dispatchBody]=$get('/api/dispatches',true,$unavailableDispatch);
            self::assertSame(422,$dispatchStatus,substr($dispatchBody,0,1800));
            self::assertFalse($db->table('items')->where('internal_id','API-NO-STOCK-DISPATCH')->exists());
            self::assertFalse($db->table('persons')->where('number','98769999')->exists());
            self::assertFalse($db->table('drivers')->where('number','98769998')->exists());
            self::assertFalse($db->table('transports')->where('plate_number','QA98766')->exists());
            self::assertSame($beforeUnavailableKardex,$db->table('inventory_kardex')->count());
            self::assertSame($beforeUnavailableDispatch,$db->table('dispatches')->count());
            $existingDispatchItemId=$input['items'][0]['item_id']; $existingDispatchWarehouse=$input['items'][0]['warehouse_id'];
            $beforeExistingStock=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$existingDispatchItemId)
                ->where('warehouse_id',$existingDispatchWarehouse)->firstOrFail()->exactValue('stock');
            $beforeExistingGlobal=\App\Models\Tenant\Item::findOrFail($existingDispatchItemId)->exactValue('stock');
            $existingDispatch=$apiDispatch; $existingDispatch['numero_documento']='878';
            $existingDispatch['items'][0]['codigo_interno']='MOCK-ITEM-VES-001';
            $existingDispatch['items'][0]['cantidad']='0.333333333333';
            [$dispatchStatus,$dispatchBody]=$get('/api/dispatches',true,$existingDispatch);
            self::assertSame(200,$dispatchStatus,substr($dispatchBody,0,1800));
            self::assertTrue(json_decode($dispatchBody,true)['success'] ?? false,$dispatchBody);
            $deductedStock=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$existingDispatchItemId)
                ->where('warehouse_id',$existingDispatchWarehouse)->firstOrFail()->exactValue('stock');
            self::assertTrue($deductedStock->isEqualTo($beforeExistingStock->minus('0.333333333333')));
            self::assertTrue(\App\Models\Tenant\Item::findOrFail($existingDispatchItemId)->exactValue('stock')->isEqualTo($beforeExistingGlobal->minus('0.333333333333')));
            $savedApiDispatch=\App\Models\Tenant\Dispatch::where('external_id',json_decode($dispatchBody,true)['data']['external_id'])->firstOrFail();
            [$voidStatus,$voidBody]=$get('/dispatches/anulate/'.$savedApiDispatch->id,true,[]);
            self::assertSame(200,$voidStatus,substr($voidBody,0,1800)); self::assertTrue(json_decode($voidBody,true)['success'] ?? false,$voidBody);
            self::assertTrue(\Modules\Inventory\Models\ItemWarehouse::where('item_id',$existingDispatchItemId)
                ->where('warehouse_id',$existingDispatchWarehouse)->firstOrFail()->exactValue('stock')->isEqualTo($beforeExistingStock));
            self::assertTrue(\App\Models\Tenant\Item::findOrFail($existingDispatchItemId)->exactValue('stock')->isEqualTo($beforeExistingGlobal));
            $priorFlags=$db->table('items')->where('id',$existingDispatchItemId)->first(['lots_enabled','series_enabled']);
            foreach (['lots_enabled','series_enabled'] as $trackingFlag) {
                $db->table('items')->where('id',$existingDispatchItemId)->update([$trackingFlag=>true]);
                $trackedApiDispatch=$existingDispatch; $trackedApiDispatch['numero_documento']=$trackingFlag==='lots_enabled' ? '879' : '880';
                $beforeTracked=$db->table('dispatches')->count();
                [$dispatchStatus,$dispatchBody]=$get('/api/dispatches',true,$trackedApiDispatch);
                self::assertSame(422,$dispatchStatus,substr($dispatchBody,0,1800));
                self::assertSame($beforeTracked,$db->table('dispatches')->count());
                self::assertTrue(\Modules\Inventory\Models\ItemWarehouse::where('item_id',$existingDispatchItemId)
                    ->where('warehouse_id',$existingDispatchWarehouse)->firstOrFail()->exactValue('stock')->isEqualTo($beforeExistingStock));
                $db->table('items')->where('id',$existingDispatchItemId)->update([$trackingFlag=>$priorFlags->$trackingFlag]);
            }
            $db->table('cat_transfer_reason_types')->where('id','24')->update(['discount_stock'=>false]);
            $db->table('inventory_configurations')->update(['stock_control'=>$initialStockControl]);
            $newServiceInvoice=$apiLocation;
            $newServiceInvoice['items'][0]['codigo_interno']='API-NEW-INVOICE-SERVICE';
            $newServiceInvoice['items'][0]['descripcion']='Servicio nuevo API';
            $newServiceInvoice['items'][0]['codigo_tipo_item']='02';
            $newServiceInvoice['items'][0]['unidad_de_medida']='SERV';
            $newServiceInvoice['items'][0]['cantidad']='0.333333333333';
            $newServiceInvoice['items'][0]['quantity_input']=['amount'=>'1','unit_price'=>'3'];
            $newServiceInvoice['items'][0]['precio_unitario']='3';
            $newServiceInvoice['items'][0]['codigo_tipo_afectacion_igv']='20';
            [$serviceStatus,$serviceBody]=$get('/api/documents',true,$newServiceInvoice);
            self::assertSame(200,$serviceStatus,substr($serviceBody,0,1800));
            self::assertTrue(json_decode($serviceBody,true)['success'] ?? false,$serviceBody);
            $newService=\App\Models\Tenant\Item::where('internal_id','API-NEW-INVOICE-SERVICE')->firstOrFail();
            $serviceInvoice=\App\Models\Tenant\Document::findOrFail(json_decode($serviceBody,true)['data']['id']);
            self::assertSame('SERV',$newService->unit_type_id); self::assertSame('20',$newService->sale_affectation_igv_type_id);
            self::assertTrue($newService->exactValue('sale_unit_price')->isEqualTo('3'));
            self::assertTrue($newService->exactValue('stock')->isZero());
            self::assertTrue($serviceInvoice->items()->firstOrFail()->exactValue('quantity')->isEqualTo(\App\Support\Exact\Decimal::of(1)->dividedBy(3)));
            self::assertTrue($serviceInvoice->exactValue('total')->isEqualTo('1'));
            self::assertSame(0,$db->table('inventory_kardex')->where('item_id',$newService->id)->count());
            $crossPriceInvoice=$newServiceInvoice;
            $crossPriceInvoice['items'][0]['codigo_interno']='API-NEW-INVOICE-CROSS-PRICE';
            $crossPriceInvoice['items'][0]['cantidad']='3'; unset($crossPriceInvoice['items'][0]['quantity_input']);
            $crossPriceInvoice['items'][0]['precio_unitario']='0.01';
            $crossPriceInvoice['items'][0]['price_input']=['amount'=>['n'=>'1','d'=>'3'],'currency_type_id'=>'USD','includes_iva'=>true];
            $crossPriceInvoice['factor_tipo_de_cambio']='100';
            [$serviceStatus,$serviceBody]=$get('/api/documents',true,$crossPriceInvoice);
            self::assertSame(200,$serviceStatus,substr($serviceBody,0,1800));
            self::assertTrue(json_decode($serviceBody,true)['success'] ?? false,$serviceBody);
            $crossPriceService=\App\Models\Tenant\Item::where('internal_id','API-NEW-INVOICE-CROSS-PRICE')->firstOrFail();
            $crossPriceDocument=\App\Models\Tenant\Document::findOrFail(json_decode($serviceBody,true)['data']['id']);
            self::assertTrue($crossPriceService->exactValue('sale_unit_price')->isEqualTo(\App\Support\Exact\Decimal::of(100)->dividedBy(3)));
            self::assertTrue($crossPriceDocument->exactValue('total')->isEqualTo('100'));
            // Reopening an invoice must use its own stored fraction, not a snapshot supplied by the client.
            $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
            $fractionInvoice=array_replace($input,['external_id'=>(string)\Illuminate\Support\Str::uuid(),
                'payment_condition_id'=>'02','payments'=>[],'fee'=>[],'operation_type_id'=>'0101','date_of_due'=>'2026-11-26']);
            $fractionInvoice['items'][0]['quantity']='0.333333333333';
            $fractionInvoice['items'][0]['quantity_input']=['amount'=>'1','unit_price'=>'3'];
            $fractionInvoice['items'][0]['unit_price']='3';
            $fractionInvoice['items'][0]['affectation_igv_type_id']='20';
            // A new invoice must derive its quantity from the submitted decimal,
            // never from a client-supplied historical snapshot.
            $untrustedInvoice=$fractionInvoice;
            unset($untrustedInvoice['items'][0]['quantity_input']);
            $untrustedInvoice['items'][0]['calculation_snapshot']=\App\Support\Exact\Decimal::snapshot(['quantity'=>'3333333333334/10000000000000']);
            [$status,$body]=$get('/documents',true,$untrustedInvoice);
            self::assertSame(200,$status,substr($body,0,2500));
            self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $untrustedId=json_decode($body,true)['data']['id'];
            $untrustedLine=\App\Models\Tenant\Document::findOrFail($untrustedId)->items()->firstOrFail();
            self::assertSame(['n'=>'333333333333','d'=>'1000000000000'],\App\Support\Exact\Decimal::fraction($untrustedLine->exactValue('quantity')));
            $fractionInvoice['external_id']=(string)\Illuminate\Support\Str::uuid();
            [$status,$body]=$get('/documents',true,$fractionInvoice);
            self::assertSame(200,$status,substr($body,0,2500));
            $result=json_decode($body,true,512,JSON_THROW_ON_ERROR);
            self::assertTrue($result['success'] ?? false,$body);
            $fractionId=$result['data']['id'];
            $fractionDocument=\App\Models\Tenant\Document::findOrFail($fractionId);
            $storedLine=$fractionDocument->items()->firstOrFail();
            self::assertSame(['n'=>'1','d'=>'3'],\App\Support\Exact\Decimal::fraction($storedLine->exactValue('quantity')));
            // The legacy void communication does not cancel invoices; neither web nor API may claim success.
            $db->table('documents')->where('id',$fractionId)->update(['state_type_id'=>'05']);
            [$status,$body]=$get('/documents/record/'.$fractionId);
            self::assertSame(200,$status,substr($body,0,2000));
            self::assertFalse(json_decode($body,true)['data']['btn_voided'] ?? true);
            [$status,$body]=$get('/documents/records?series='.rawurlencode($fractionDocument->series).'&number='.rawurlencode((string)$fractionDocument->number));
            self::assertSame(200,$status,substr($body,0,2000));
            $listed=collect(json_decode($body,true)['data'] ?? [])->firstWhere('id',$fractionId);
            self::assertNotNull($listed,$body); self::assertFalse($listed['btn_voided']);
            $voidCount=$db->table('voided')->count();
            $voidRequest=['date_of_reference'=>$fractionDocument->date_of_issue->format('Y-m-d'),
                'documents'=>[['document_id'=>$fractionId,'description'=>'Prueba']]];
            [$status,$body]=$get('/voided',true,$voidRequest);
            self::assertSame(422,$status,substr($body,0,2000));
            self::assertArrayHasKey('documents',json_decode($body,true)['errors'] ?? json_decode($body,true)['message'] ?? []);
            $apiVoid=['fecha_de_emision_de_documentos'=>$fractionDocument->date_of_issue->format('d-m-Y'),
                'documentos'=>[['external_id'=>$fractionDocument->external_id,'motivo_anulacion'=>'Prueba']]];
            [$status,$body]=$get('/api/voided',true,$apiVoid);
            self::assertSame(422,$status,substr($body,0,2000));
            self::assertSame($voidCount,$db->table('voided')->count());
            self::assertSame('05',(string)$db->table('documents')->where('id',$fractionId)->value('state_type_id'));
            $legacyData=\App\CoreFacturalo\Requests\Inputs\VoidedInput::set($voidRequest);
            $legacy=\App\Models\Tenant\Voided::create(collect($legacyData)->except(['type','documents'])->all());
            $legacy->documents()->create($legacyData['documents'][0]);
            [$status,$body]=$get('/voided/'.$legacy->id,true,[],false,'DELETE');
            self::assertSame(422,$status,substr($body,0,2000));
            self::assertTrue($db->table('voided')->where('id',$legacy->id)->exists());
            self::assertSame('05',(string)$db->table('documents')->where('id',$fractionId)->value('state_type_id'));
            $db->table('voided_documents')->where('voided_id',$legacy->id)->delete();
            $db->table('voided')->where('id',$legacy->id)->delete();
            $db->table('documents')->where('id',$fractionId)->update(['state_type_id'=>$fractionDocument->state_type_id]);
            $edit=array_replace($fractionInvoice,['id'=>$fractionId,'series'=>$fractionDocument->series,'number'=>$fractionDocument->number,
                'received_retentions'=>[],'guarantee_fund'=>null,'purchase_order'=>'','total_exonerated'=>'1']);
            unset($edit['items'][0]['quantity_input']);
            $edit['items'][0]['record_id']=$storedLine->id;
            $edit['items'][0]['calculation_snapshot']=\App\Support\Exact\Decimal::snapshot(['quantity'=>'3333333333334/10000000000000']);
            [$status,$body]=$get('/documents/'.$fractionId.'/update',true,$edit);
            self::assertSame(200,$status,substr($body,0,2500));
            self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $freshLine=$fractionDocument->fresh()->items()->firstOrFail();
            self::assertSame(['n'=>'1','d'=>'3'],\App\Support\Exact\Decimal::fraction($freshLine->exactValue('quantity')));
            self::assertSame('1',\App\Support\Exact\Decimal::represent($fractionDocument->fresh()->exactValue('total')));
            $edit['items'][0]['record_id']=$freshLine->id+99999;
            [$status,$body]=$get('/documents/'.$fractionId.'/update',true,$edit);
            self::assertSame(422,$status,substr($body,0,2500));
            self::assertSame(['n'=>'1','d'=>'3'],\App\Support\Exact\Decimal::fraction($fractionDocument->fresh()->items()->firstOrFail()->exactValue('quantity')));
            $edit['items'][0]['record_id']=$freshLine->id;
            $edit['items'][0]['quantity']='0.5';
            [$status,$body]=$get('/documents/'.$fractionId.'/update',true,$edit);
            self::assertSame(200,$status,substr($body,0,2500));
            self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            self::assertSame(['n'=>'1','d'=>'2'],\App\Support\Exact\Decimal::fraction($fractionDocument->fresh()->items()->firstOrFail()->exactValue('quantity')));
            self::assertSame('1.5',\App\Support\Exact\Decimal::represent($fractionDocument->fresh()->exactValue('total')));
            // Real web middleware must not let alternate price or quantity inputs alter a debit service charge.
            $serviceItem=\App\Models\Tenant\Item::where('unit_type_id','SERV')->firstOrFail();
            $debit=array_replace($fractionInvoice,['document_type_id'=>'08','series'=>'FD01','number'=>'#',
                'note_creation_key'=>(string)\Illuminate\Support\Str::uuid(),'note_credit_or_debit_type_id'=>'01',
                'note_description'=>'Cargo de prueba','affected_document_id'=>$fractionId,'payments'=>[],'fee'=>[],
                'fiscal_data'=>[],'taxes'=>[],'received_retentions'=>[],'guarantee_fund'=>null]);
            $debit['items']=[['item_id'=>$serviceItem->id,'adjustment_amount'=>'11.60','affectation_igv_type_id'=>'10',
                'price_type_id'=>'01','additional_information'=>'','currency_type_id'=>'VES','exchange_rate_sale'=>'1',
                'quantity_input'=>['amount'=>'2','unit_price'=>'1'],'price_input'=>['amount'=>'20','currency_type_id'=>'VES'],
                'calculation_snapshot'=>\App\Support\Exact\Decimal::snapshot(['quantity'=>'2','unit_price'=>'20'])]];
            [$status,$body]=$get('/documents',true,$debit);
            self::assertSame(200,$status,substr($body,0,2500));
            self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $debitDocument=\App\Models\Tenant\Document::findOrFail(json_decode($body,true)['data']['id']);
            self::assertSame('1',\App\Support\Exact\Decimal::represent($debitDocument->items()->firstOrFail()->exactValue('quantity')));
            self::assertSame('11.6',\App\Support\Exact\Decimal::represent($debitDocument->exactValue('total')));
            $tinyInvoice=$fractionInvoice;
            $tinyInvoice['items'][0]['quantity']='0.000000000001';
            $tinyInvoice['items'][0]['quantity_input']=['amount'=>'0.000000000001','unit_price'=>'3'];
            $tinyInvoice['items'][0]['unit_price']='0.1';
            [$status,$body]=$get('/documents',true,$tinyInvoice);
            self::assertSame(200,$status,substr($body,0,2500));
            self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $tinyId=json_decode($body,true)['data']['id'];
            $tinyDocument=\App\Models\Tenant\Document::findOrFail($tinyId);
            self::assertSame(['n'=>'1','d'=>'30000000000000'],\App\Support\Exact\Decimal::fraction($tinyDocument->exactValue('total')));
            [$status,$body]=$get('/documents/note/record/'.$tinyId);
            self::assertSame(200,$status,substr($body,0,1800));
            $tinyContext=json_decode($body,true)['note_context'];
            self::assertSame('0',$tinyContext['credit_available']);
            self::assertSame(['n'=>'1','d'=>'30000000000000'],$tinyContext['calculation_snapshot']['values']['credit_available']);
            self::assertSame(['n'=>'1','d'=>'3000000000000'],$tinyContext['items'][0]['calculation_snapshot']['values']['remaining_quantity']);
            $cancellation=array_replace($tinyInvoice,['document_type_id'=>'07','series'=>'FC01','number'=>'#',
                'note_creation_key'=>(string)\Illuminate\Support\Str::uuid(),'note_credit_or_debit_type_id'=>'01',
                'note_description'=>'Anulación de prueba','affected_document_id'=>$tinyId,'payments'=>[],'fee'=>[],
                'items'=>[],'fiscal_data'=>[],'taxes'=>[],'received_retentions'=>[],'guarantee_fund'=>null]);
            [$status,$body]=$get('/documents',true,$cancellation);
            self::assertSame(200,$status,substr($body,0,2500));
            self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $creditDocument=\App\Models\Tenant\Document::findOrFail(json_decode($body,true)['data']['id']);
            self::assertSame(\App\Support\Exact\Decimal::fraction($tinyDocument->exactValue('total')),\App\Support\Exact\Decimal::fraction($creditDocument->exactValue('total')));
            $lotItemId=$fractionInvoice['items'][0]['item_id']; $lotWarehouse=$fractionInvoice['items'][0]['warehouse_id'];
            $lotStockBefore=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$lotItemId)->where('warehouse_id',$lotWarehouse)->firstOrFail()->exactValue('stock');
            $fractionLot=$db->table('item_lots_group')->insertGetId(['item_id'=>$lotItemId,'code'=>'HTTP-NOTE-THIRD','quantity'=>'1','date_of_due'=>'2027-11-30']);
            $lotInvoice=$fractionInvoice; $lotInvoice['external_id']=(string)\Illuminate\Support\Str::uuid();
            $selection=[['id'=>$fractionLot,'code'=>'HTTP-NOTE-THIRD','date_of_due'=>'2027-11-30','compromise_quantity'=>'0.333333333333']];
            $lotInvoice['items'][0]['IdLoteSelected']=$selection; $lotInvoice['items'][0]['item']['IdLoteSelected']=$selection;
            [$status,$body]=$get('/documents',true,$lotInvoice);
            self::assertSame(200,$status,substr($body,0,1800)); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $lotInvoiceId=json_decode($body,true)['data']['id'];
            self::assertSame('2/3',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($fractionLot)->exactValue('quantity'));
            [$status,$body]=$get('/documents/note/record/'.$lotInvoiceId);
            self::assertSame(200,$status,substr($body,0,1800));
            self::assertSame('0.333333333333',json_decode($body,true)['note_context']['items'][0]['lots'][0]['quantity'] ?? null);
            $lotCredit=array_replace($cancellation,['affected_document_id'=>$lotInvoiceId,'note_creation_key'=>(string)\Illuminate\Support\Str::uuid()]);
            [$status,$body]=$get('/documents',true,$lotCredit);
            self::assertSame(200,$status,substr($body,0,1800)); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $lotCreditDocument=\App\Models\Tenant\Document::findOrFail(json_decode($body,true)['data']['id']);
            self::assertSame('1/3',(string)$lotCreditDocument->items()->firstOrFail()->exactValue('inventory_quantity'));
            self::assertSame('1',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($fractionLot)->exactValue('quantity'));
            self::assertTrue(\Modules\Inventory\Models\ItemWarehouse::where('item_id',$lotItemId)->where('warehouse_id',$lotWarehouse)->firstOrFail()->exactValue('stock')->isEqualTo($lotStockBefore));
            [$status,$body]=$get('/documents/'.$lotCreditDocument->id.'/annul',true,
                ['operation_key'=>(string)\Illuminate\Support\Str::uuid(),'reason'=>'Prueba aislada']);
            self::assertSame(422,$status,substr($body,0,1800));
            $annulmentResponse=json_decode($body,true);
            self::assertArrayHasKey('note',$annulmentResponse['errors'] ?? $annulmentResponse['message'] ?? [],$body);
            self::assertNotContains((string)$lotCreditDocument->fresh()->state_type_id,['09','11']);
            self::assertSame('1',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($fractionLot)->exactValue('quantity'));
            self::assertTrue(\Modules\Inventory\Models\ItemWarehouse::where('item_id',$lotItemId)->where('warehouse_id',$lotWarehouse)->firstOrFail()->exactValue('stock')->isEqualTo($lotStockBefore));
            foreach ([$lotInvoiceId,$lotCreditDocument->id] as $pdfDocumentId) {
                $pdfDocument=\App\Models\Tenant\Document::findOrFail($pdfDocumentId);
                [$pdfStatus,$pdfBody]=$get('/documents/'.$pdfDocument->external_id.'/download-pdf/a4');
                self::assertSame(200,$pdfStatus); self::assertStringStartsWith('%PDF-',$pdfBody);
                $pdfPath=$temp.'/lot-note-'.$pdfDocumentId.'.pdf'; $textPath=$temp.'/lot-note-'.$pdfDocumentId.'.txt';
                file_put_contents($pdfPath,$pdfBody);
                $process=proc_open(['pdftotext','-layout',$pdfPath,$textPath],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
                self::assertIsResource($process); foreach ($pipes as $pipe) fclose($pipe);
                self::assertSame(0,proc_close($process));
                $pdfText=file_get_contents($textPath);
                self::assertStringContainsString('HTTP-NOTE-THIRD',$pdfText);
                self::assertStringContainsString('0.333333333333',$pdfText);
                self::assertStringContainsString('1.00',$pdfText);
            }
            $lotA=$db->table('item_lots_group')->insertGetId(['item_id'=>$lotItemId,'code'=>'HTTP-PART-A','quantity'=>'1','date_of_due'=>'2027-11-30']);
            $lotB=$db->table('item_lots_group')->insertGetId(['item_id'=>$lotItemId,'code'=>'HTTP-PART-B','quantity'=>'1','date_of_due'=>'2027-11-30']);
            $multiInvoice=$fractionInvoice;
            $multiSelection=[['id'=>$lotA,'code'=>'HTTP-PART-A','date_of_due'=>'2027-11-30','compromise_quantity'=>'0.1'],
                ['id'=>$lotB,'code'=>'HTTP-PART-B','date_of_due'=>'2027-11-30','compromise_quantity'=>'0.233333333333']];
            $multiInvoice['items'][0]['IdLoteSelected']=$multiSelection;
            $multiInvoice['items'][0]['item']['IdLoteSelected']=$multiSelection;
            [$status,$body]=$get('/documents',true,$multiInvoice);
            self::assertSame(200,$status,substr($body,0,1800)); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $multiInvoiceId=json_decode($body,true)['data']['id'];
            $multiSource=\App\Models\Tenant\Document::findOrFail($multiInvoiceId)->items()->firstOrFail();
            $partialCredit=array_replace($lotCredit,['affected_document_id'=>$multiInvoiceId,'note_credit_or_debit_type_id'=>'07',
                'note_creation_key'=>(string)\Illuminate\Support\Str::uuid(),'items'=>[['affected_document_item_id'=>$multiSource->id,'quantity'=>'0.2',
                    'lot_returns'=>[['id'=>$lotA,'compromise_quantity'=>'0.1'],['id'=>$lotB,'compromise_quantity'=>'0.1']]]]]);
            [$status,$body]=$get('/documents',true,$partialCredit);
            self::assertSame(200,$status,substr($body,0,1800)); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $partialDoc=\App\Models\Tenant\Document::findOrFail(json_decode($body,true)['data']['id']);
            self::assertSame('1/5',(string)$partialDoc->items()->firstOrFail()->exactValue('inventory_quantity'));
            self::assertSame('1',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($lotA)->exactValue('quantity'));
            self::assertSame('13/15',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($lotB)->exactValue('quantity'));
            $remainingCredit=array_replace($lotCredit,['affected_document_id'=>$multiInvoiceId,'note_creation_key'=>(string)\Illuminate\Support\Str::uuid()]);
            [$status,$body]=$get('/documents',true,$remainingCredit);
            self::assertSame(200,$status,substr($body,0,1800)); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $remainingDoc=\App\Models\Tenant\Document::findOrFail(json_decode($body,true)['data']['id']);
            self::assertSame('2/15',(string)$remainingDoc->items()->firstOrFail()->exactValue('inventory_quantity'));
            self::assertSame('1',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($lotB)->exactValue('quantity'));
            self::assertTrue(\Modules\Inventory\Models\ItemWarehouse::where('item_id',$lotItemId)->where('warehouse_id',$lotWarehouse)->firstOrFail()->exactValue('stock')->isEqualTo($lotStockBefore));
            // Full web chain: the lot and its exact quantity belong to the order, not the client payload.
            $chainLot=$db->table('item_lots_group')->insertGetId(['item_id'=>$lotItemId,'code'=>'HTTP-ORDER-CHAIN','quantity'=>'1','date_of_due'=>'2027-11-30']);
            $chainSelection=[['id'=>$chainLot,'code'=>'CODIGO-MANIPULADO','date_of_due'=>'2030-01-01','compromise_quantity'=>'0.333333333333']];
            $warehouseStock=fn()=>\Modules\Inventory\Models\ItemWarehouse::where('item_id',$lotItemId)->where('warehouse_id',$lotWarehouse)->firstOrFail()->exactValue('stock');
            $globalStock=fn()=>\App\Models\Tenant\Item::findOrFail($lotItemId)->exactValue('stock');
            $chainStockBefore=$warehouseStock(); $chainGlobalBefore=$globalStock();
            $orderInput=array_replace($fractionInvoice,['prefix'=>'PED','prepayments'=>[]]);
            $orderInput['items'][0]['IdLoteSelected']=$chainSelection; $orderInput['items'][0]['item']['IdLoteSelected']=$chainSelection;
            [$status,$body]=$get('/order-notes',true,$orderInput);
            self::assertSame(200,$status,substr($body,0,2200)); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $orderId=json_decode($body,true)['data']['id'];
            $orderExternalId=json_decode($body,true)['data']['external_id'];
            self::assertSame('2/3',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($chainLot)->exactValue('quantity'));
            self::assertTrue($warehouseStock()->isEqualTo($chainStockBefore->minus(\App\Support\Exact\Decimal::of('1/3'))));
            [$pdfStatus,$pdfBody]=$get('/order-notes/print/'.$orderExternalId.'/a4');
            self::assertSame(200,$pdfStatus); self::assertStringStartsWith('%PDF-',$pdfBody);
            $chainPdf=$temp.'/order-chain.pdf'; $chainText=$temp.'/order-chain.txt'; file_put_contents($chainPdf,$pdfBody);
            $process=proc_open(['pdftotext','-layout',$chainPdf,$chainText],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            self::assertIsResource($process); foreach ($pipes as $pipe) fclose($pipe); self::assertSame(0,proc_close($process));
            $orderPdfText=file_get_contents($chainText);
            self::assertStringContainsString('HTTP-ORDER-CHAIN',$orderPdfText); self::assertStringNotContainsString('CODIGO-MANIPULADO',$orderPdfText);
            self::assertStringContainsString('2027-11-30',$orderPdfText); self::assertStringNotContainsString('2030-01-01',$orderPdfText);
            self::assertStringContainsString('0.333333333333',$orderPdfText); self::assertStringContainsString('1.00',$orderPdfText);
            $db->table('item_lots_group')->where('id',$chainLot)->update(['code'=>'CATALOGO-CAMBIADO','date_of_due'=>'2029-01-01']);
            $saleInput=array_replace($orderInput,['id'=>null,'order_note_id'=>$orderId,'series_id'=>$noteSeries,'document_type_id'=>'80','prefix'=>'NV','payments'=>[]]);
            unset($saleInput['items'][0]['quantity_input']);
            $saleInput['items'][0]['IdLoteSelected']=[['id'=>999999,'compromise_quantity'=>'0.333333333333']];
            [$status,$body]=$get('/sale-notes',true,$saleInput);
            self::assertSame(200,$status,substr($body,0,2200)); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $chainNote=\App\Models\Tenant\SaleNote::findOrFail(json_decode($body,true)['data']['id']);
            self::assertSame('1/3',(string)$chainNote->items()->firstOrFail()->exactValue('quantity'));
            self::assertSame('HTTP-ORDER-CHAIN',$chainNote->items()->firstOrFail()->item->IdLoteSelected[0]->code);
            self::assertTrue($globalStock()->isEqualTo($chainGlobalBefore->minus(\App\Support\Exact\Decimal::of('1/3'))));
            $chainInvoice=$fractionInvoice; $chainInvoice['sale_note_id']=$chainNote->id;
            $chainInvoice['items'][0]['IdLoteSelected']=[['id'=>999999,'compromise_quantity'=>'0.333333333333']];
            unset($chainInvoice['items'][0]['quantity_input']);
            [$status,$body]=$get('/documents',true,$chainInvoice);
            self::assertSame(200,$status,substr($body,0,2200)); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $chainDocument=\App\Models\Tenant\Document::findOrFail(json_decode($body,true)['data']['id']);
            $chainLine=$chainDocument->items()->firstOrFail();
            self::assertSame('1/3',(string)$chainLine->exactValue('quantity'));
            self::assertSame('1/3',(string)\App\Support\Exact\Decimal::of($chainLine->item->IdLoteSelected[0]->compromise_quantity_exact));
            self::assertSame('HTTP-ORDER-CHAIN',$chainLine->item->IdLoteSelected[0]->code);
            self::assertTrue($chainDocument->exactValue('total')->isEqualTo($chainNote->exactValue('total')));
            self::assertTrue($globalStock()->isEqualTo($chainGlobalBefore->minus(\App\Support\Exact\Decimal::of('1/3'))));
            $chainCredit=array_replace($cancellation,['affected_document_id'=>$chainDocument->id,'note_creation_key'=>(string)\Illuminate\Support\Str::uuid()]);
            [$status,$body]=$get('/documents',true,$chainCredit);
            self::assertSame(200,$status,substr($body,0,2200)); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            self::assertSame('1',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($chainLot)->exactValue('quantity'));
            self::assertTrue($warehouseStock()->isEqualTo($chainStockBefore)); self::assertTrue($globalStock()->isEqualTo($chainGlobalBefore));
            // A direct sale-note pack conversion must use the frozen component, even after catalog changes.
            $pack=\App\Models\Tenant\Item::findOrFail($lotItemId)->replicate();
            $pack->internal_id='HTTP-NV-PACK'; $pack->is_set=true; $pack->stock='5'; $pack->save();
            $packSet=$db->table('item_sets')->insertGetId(['item_id'=>$pack->id,'individual_item_id'=>$lotItemId,
                'quantity'=>'0.333333333333','calculation_snapshot'=>json_encode(\App\Support\Exact\Decimal::snapshot(['quantity'=>'1/3']))]);
            $packStockBefore=$warehouseStock();
            $packSale=array_replace($fractionInvoice,['id'=>null,'series_id'=>$noteSeries,'document_type_id'=>'80',
                'prefix'=>'NV','payments'=>[],'fee'=>[],'external_id'=>(string)\Illuminate\Support\Str::uuid()]);
            $packSale['items'][0]['item_id']=$pack->id; $packSale['items'][0]['item']=$pack->fresh()->toArray();
            $packSale['items'][0]['quantity']='2'; unset($packSale['items'][0]['quantity_input']);
            [$status,$body]=$get('/sale-notes',true,$packSale);
            self::assertSame(200,$status,substr($body,0,2200)); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $packNote=\App\Models\Tenant\SaleNote::findOrFail(json_decode($body,true)['data']['id']);
            self::assertTrue($warehouseStock()->isEqualTo($packStockBefore->minus(\App\Support\Exact\Decimal::of('2/3'))));
            self::assertSame('3',(string)$pack->fresh()->exactValue('stock'));
            $db->table('item_sets')->where('id',$packSet)->update(['quantity'=>'4','calculation_snapshot'=>null]);
            $packInvoice=$fractionInvoice; $packInvoice['external_id']=(string)\Illuminate\Support\Str::uuid();
            $packInvoice['sale_note_id']=$packNote->id; $packInvoice['items']=$packSale['items'];
            [$status,$body]=$get('/documents',true,$packInvoice);
            self::assertSame(200,$status,substr($body,0,2200)); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $packDocument=\App\Models\Tenant\Document::findOrFail(json_decode($body,true)['data']['id']);
            self::assertTrue($warehouseStock()->isEqualTo($packStockBefore->minus(\App\Support\Exact\Decimal::of('2/3'))));
            self::assertSame('3',(string)$pack->fresh()->exactValue('stock'));
            $packCredit=array_replace($cancellation,['affected_document_id'=>$packDocument->id,
                'note_creation_key'=>(string)\Illuminate\Support\Str::uuid()]);
            [$status,$body]=$get('/documents',true,$packCredit);
            self::assertSame(200,$status,substr($body,0,2200)); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            self::assertTrue($warehouseStock()->isEqualTo($packStockBefore));
            self::assertSame('5',(string)$pack->fresh()->exactValue('stock'));
            $db->table('item_sets')->where('id',$packSet)->update(['quantity'=>'0.333333333333',
                'calculation_snapshot'=>json_encode(\App\Support\Exact\Decimal::snapshot(['quantity'=>'1/3']))]);
            $packOrder=$fractionInvoice; $packOrder['prefix']='PED'; $packOrder['prepayments']=[]; $packOrder['items']=$packSale['items'];
            $packOrder['external_id']=(string)\Illuminate\Support\Str::uuid();
            [$status,$body]=$get('/order-notes',true,$packOrder);
            self::assertSame(200,$status,substr($body,0,2200)); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $packOrderId=json_decode($body,true)['data']['id'];
            self::assertTrue($warehouseStock()->isEqualTo($packStockBefore->minus(\App\Support\Exact\Decimal::of('2/3'))));
            $db->table('item_sets')->where('id',$packSet)->update(['quantity'=>'4','calculation_snapshot'=>null]);
            $packOrderInvoice=$fractionInvoice; $packOrderInvoice['order_note_id']=$packOrderId;
            $packOrderInvoice['items']=$packSale['items']; $packOrderInvoice['external_id']=(string)\Illuminate\Support\Str::uuid();
            [$status,$body]=$get('/documents',true,$packOrderInvoice);
            self::assertSame(200,$status,substr($body,0,2200)); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $packOrderDocument=\App\Models\Tenant\Document::findOrFail(json_decode($body,true)['data']['id']);
            self::assertTrue($warehouseStock()->isEqualTo($packStockBefore->minus(\App\Support\Exact\Decimal::of('2/3'))));
            self::assertSame('3',(string)$pack->fresh()->exactValue('stock'));
            self::assertSame(0,$packOrderDocument->inventory_kardex()->count());
            $packOrderCredit=array_replace($cancellation,['affected_document_id'=>$packOrderDocument->id,
                'note_creation_key'=>(string)\Illuminate\Support\Str::uuid()]);
            [$status,$body]=$get('/documents',true,$packOrderCredit);
            self::assertSame(200,$status,substr($body,0,2200)); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            self::assertTrue($warehouseStock()->isEqualTo($packStockBefore));
            self::assertSame('5',(string)$pack->fresh()->exactValue('stock'));
            $db->table('item_sets')->where('id',$packSet)->update(['quantity'=>'0.333333333333',
                'calculation_snapshot'=>json_encode(\App\Support\Exact\Decimal::snapshot(['quantity'=>'1/3']))]);
            $directPack=$fractionInvoice; $directPack['items']=$packSale['items']; $directPack['external_id']=(string)\Illuminate\Support\Str::uuid();
            [$status,$body]=$get('/documents',true,$directPack);
            self::assertSame(200,$status,substr($body,0,2200)); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $directPackDocument=\App\Models\Tenant\Document::findOrFail(json_decode($body,true)['data']['id']);
            self::assertTrue($warehouseStock()->isEqualTo($packStockBefore->minus(\App\Support\Exact\Decimal::of('2/3'))));
            self::assertSame('3',(string)$pack->fresh()->exactValue('stock'));
            $db->table('item_sets')->where('id',$packSet)->update(['quantity'=>'4','calculation_snapshot'=>null]);
            $directPackCredit=array_replace($cancellation,['affected_document_id'=>$directPackDocument->id,
                'note_creation_key'=>(string)\Illuminate\Support\Str::uuid()]);
            [$status,$body]=$get('/documents',true,$directPackCredit);
            self::assertSame(200,$status,substr($body,0,2200)); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            self::assertTrue($warehouseStock()->isEqualTo($packStockBefore));
            self::assertSame('5',(string)$pack->fresh()->exactValue('stock'));
            $serialId=$db->table('item_lots')->insertGetId(['series'=>'HTTP-ORDER-SERIAL','date'=>'2026-10-26','item_id'=>$lotItemId,
                'warehouse_id'=>$lotWarehouse,'item_loteable_type'=>'test','item_loteable_id'=>$lotItemId,'has_sale'=>0]);
            $serialLot=$db->table('item_lots_group')->insertGetId(['item_id'=>$lotItemId,'code'=>'HTTP-ORDER-COMBINED','quantity'=>'1','date_of_due'=>'2027-11-30']);
            $serialOrigin=FiscalEmissionSchemaTest::makeOrderSerialLotOrigin($db,$fractionInvoice,$serialLot);
            $db->table('item_lots')->where('id',$serialId)->update(['item_loteable_type'=>\App\Models\Tenant\PurchaseItem::class,'item_loteable_id'=>$serialOrigin]);
            $seriesWasEnabled=$db->table('items')->where('id',$lotItemId)->value('series_enabled');
            $lotsWereEnabled=$db->table('items')->where('id',$lotItemId)->value('lots_enabled');
            $db->table('items')->where('id',$lotItemId)->update(['series_enabled'=>1,'lots_enabled'=>1]);
            $serialOrder=$fractionInvoice; $serialOrder['prefix']='PED'; $serialOrder['prepayments']=[];
            $serialOrder['items'][0]['quantity']='1'; unset($serialOrder['items'][0]['quantity_input']);
            $serialOrder['items'][0]['item']['lots']=[['id'=>$serialId,'series'=>'MANIPULADO','has_sale'=>true]];
            $serialOrder['items'][0]['item']['IdLoteSelected']=[['id'=>$serialLot,'compromise_quantity'=>'1']];
            $serialOrder['items'][0]['IdLoteSelected']=$serialOrder['items'][0]['item']['IdLoteSelected'];
            $noSerial=$serialOrder; $noSerial['items'][0]['item']['lots']=[];
            [$status,$body]=$get('/order-notes',true,$noSerial); self::assertSame(422,$status,substr($body,0,2200));
            self::assertFalse((bool)$db->table('item_lots')->where('id',$serialId)->value('has_sale'));
            [$status,$body]=$get('/order-notes',true,$serialOrder);
            self::assertSame(200,$status,substr($body,0,2200)); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $serialOrderId=json_decode($body,true)['data']['id'];
            self::assertTrue((bool)$db->table('item_lots')->where('id',$serialId)->value('has_sale'));
            self::assertSame('0',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($serialLot)->exactValue('quantity'));
            self::assertTrue($warehouseStock()->isEqualTo($chainStockBefore->minus(1)));
            $serialNote=array_replace($serialOrder,['id'=>null,'order_note_id'=>$serialOrderId,'series_id'=>$noteSeries,'document_type_id'=>'80','prefix'=>'NV','payments'=>[]]);
            $serialNote['items'][0]['lots']=[['id'=>999999,'has_sale'=>true]];
            [$status,$body]=$get('/sale-notes',true,$serialNote);
            self::assertSame(200,$status,substr($body,0,2200)); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $serialNoteModel=\App\Models\Tenant\SaleNote::findOrFail(json_decode($body,true)['data']['id']);
            self::assertSame('HTTP-ORDER-SERIAL',$serialNoteModel->items()->firstOrFail()->item->lots[0]->series);
            $serialInvoice=$serialOrder; $serialInvoice['sale_note_id']=$serialNoteModel->id;
            $serialInvoice['items'][0]['item']['lots']=[['id'=>999999,'has_sale'=>true]];
            [$status,$body]=$get('/documents',true,$serialInvoice);
            self::assertSame(200,$status,substr($body,0,2200)); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $serialDocument=\App\Models\Tenant\Document::findOrFail(json_decode($body,true)['data']['id']);
            self::assertSame('HTTP-ORDER-SERIAL',$serialDocument->items()->firstOrFail()->item->lots[0]->series);
            $serialCredit=array_replace($cancellation,['affected_document_id'=>$serialDocument->id,'note_creation_key'=>(string)\Illuminate\Support\Str::uuid()]);
            [$status,$body]=$get('/documents',true,$serialCredit);
            self::assertSame(200,$status,substr($body,0,2200)); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            self::assertFalse((bool)$db->table('item_lots')->where('id',$serialId)->value('has_sale'));
            self::assertSame('1',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($serialLot)->exactValue('quantity'));
            self::assertTrue($warehouseStock()->isEqualTo($chainStockBefore)); self::assertTrue($globalStock()->isEqualTo($chainGlobalBefore));
            $db->table('items')->where('id',$lotItemId)->update(['series_enabled'=>$seriesWasEnabled,'lots_enabled'=>$lotsWereEnabled]);
            // The product PDF route accepts a cash ID; a seller may only access their own cash.
            $ownCash=$db->table('cash')->insertGetId(['user_id'=>$input['user_id'],'date_opening'=>'2026-10-19','time_opening'=>'12:00:00','state'=>true]);
            // Associate the actual fractional invoice with this synthetic shift so the authorized report is not empty.
            $db->table('cash_documents')->insert(['cash_id'=>$ownCash,'document_id'=>$fractionId]);
            $otherSeller=$db->table('users')->insertGetId(['name'=>'Otro cajero','email'=>'other-cash-http@example.test',
                'password'=>'unused','type'=>'seller','establishment_id'=>$input['establishment_id']]);
            $otherCash=$db->table('cash')->insertGetId(['user_id'=>$otherSeller,'date_opening'=>'2026-10-19','time_opening'=>'12:00:00','state'=>true]);
            $db->table('users')->where('id',$input['user_id'])->update(['type'=>'seller']);
            [$status,$body]=$get('/cash/report/products/'.$otherCash);
            self::assertSame(403,$status,substr($body,0,500));
            [$status,$body]=$get('/cash/report/products/'.$ownCash);
            self::assertSame(200,$status,substr($body,0,500));
            self::assertStringStartsWith('%PDF',$body);
            [$status,$body]=$get('/cash/report/products-excel/'.$otherCash);
            self::assertSame(403,$status,substr($body,0,500));
            [$status,$body]=$get('/cash/report/products-excel/'.$ownCash);
            self::assertSame(200,$status,substr($body,0,500));
            self::assertStringStartsWith('PK',$body);
            $cashWorkbook=$temp.'/cash-products.xlsx'; file_put_contents($cashWorkbook,$body);
            $book=\PhpOffice\PhpSpreadsheet\IOFactory::load($cashWorkbook);
            $cells=[]; foreach ($book->getActiveSheet()->toArray() as $row) foreach ($row as $value) if ($value !== null) $cells[]=(string)$value;
            self::assertContains('VES',$cells);
            self::assertContains('0.5',$cells);
            self::assertContains('1.50',$cells);
            $book->disconnectWorksheets();
            $db->table('users')->where('id',$input['user_id'])->update(['type'=>'admin']);
            [$status,$body]=$get('/cash/report/products/'.$otherCash);
            self::assertSame(200,$status,substr($body,0,500));
            self::assertStringStartsWith('%PDF',$body);
            // The real payment routes reconcile the checkout after commit and after reversal.
            $db->table('hotel_rooms')->where('id',$room)->update(['status'=>'OCUPADO']);
            $creditRent=$db->table('hotel_rents')->insertGetId(['customer_id'=>$input['customer_id'],'customer'=>json_encode($input['customer']),
                'hotel_room_id'=>$room,'payment_status'=>'DEBT','output_date'=>'2026-10-27','output_time'=>'12:00','establishment_id'=>$input['establishment_id']]);
            $creditItem=$db->table('hotel_rent_items')->insertGetId(['type'=>'HAB','hotel_rent_id'=>$creditRent,
                'item_id'=>$input['items'][0]['item_id'],'item'=>json_encode(['total'=>'232']),'payment_status'=>'DEBT']);
            $saleController=new \App\Http\Controllers\Tenant\SaleNoteController();
            $creditInput=array_replace($input,['id'=>null,'prefix'=>'NV','series'=>'NV01','document_type_id'=>'80','payments'=>[],
                'source_module'=>'HOTEL','hotel_rent_id'=>$creditRent,'external_id'=>(string)\Illuminate\Support\Str::uuid()]);
            $hotelNote=$db->transaction(fn () => \App\Models\Tenant\SaleNote::create($saleController->mergeData($creditInput)));
            $hotelNote->items()->create($input['items'][0]);
            [$status,$body]=$get('/hotels/reception/'.$creditRent.'/rent/finalized',true,
                ['arrears'=>'0','checkout_document_type'=>'80','checkout_document_id'=>$hotelNote->id]);
            self::assertSame(200,$status,substr($body,0,1500));
            self::assertSame('DEBT',$db->table('hotel_rents')->where('id',$creditRent)->value('payment_status'));
            $db->table('payment_method_types')->where('id','01')->update(['is_active'=>1,'collection_kind'=>'account',
                'currency_type_id'=>'USD','bank_account_id'=>$usd->id,'cash_currency_type_id'=>null]);
            $receipt=['sale_note_id'=>$hotelNote->id,'date_of_payment'=>'2026-10-26','payment_method_type_id'=>'01',
                'payment_destination_id'=>$usd->id,'payment'=>'232','currency_type_id'=>'USD','original_amount'=>'2.32','exchange_rate'=>'100'];
            [$status,$body]=$get('/sale_note_payments',true,$receipt);
            self::assertSame(200,$status,substr($body,0,1500));
            self::assertSame('PAID',$db->table('hotel_rents')->where('id',$creditRent)->value('payment_status'));
            self::assertSame('PAID',$db->table('hotel_rent_items')->where('id',$creditItem)->value('payment_status'));
            $paymentId=$db->table('sale_note_payments')->where('sale_note_id',$hotelNote->id)->value('id');
            [$status,$body]=$get('/sale_note_payments/'.$paymentId,true,[],false,'DELETE');
            self::assertSame(200,$status,substr($body,0,1500));
            self::assertSame('DEBT',$db->table('hotel_rents')->where('id',$creditRent)->value('payment_status'));
            self::assertSame('DEBT',$db->table('hotel_rent_items')->where('id',$creditItem)->value('payment_status'));
            $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
            $db->table('hotel_rooms')->where('id',$room)->update(['status'=>'OCUPADO']);
            $invoiceRent=$db->table('hotel_rents')->insertGetId(['customer_id'=>$input['customer_id'],'customer'=>json_encode($input['customer']),
                'hotel_room_id'=>$room,'payment_status'=>'DEBT','output_date'=>'2026-10-27','output_time'=>'12:00','establishment_id'=>$input['establishment_id']]);
            $invoiceItem=$db->table('hotel_rent_items')->insertGetId(['type'=>'HAB','hotel_rent_id'=>$invoiceRent,
                'item_id'=>$input['items'][0]['item_id'],'item'=>json_encode(['total'=>'232']),'payment_status'=>'DEBT']);
            $hotelInvoice=array_replace($input,['source_module'=>'HOTEL','hotel_rent_id'=>$invoiceRent,'payments'=>[],
                'payment_condition_id'=>'02','operation_type_id'=>'0101','date_of_due'=>'2026-11-26',
                'fee'=>[['date'=>'2026-11-26','currency_type_id'=>'VES','amount'=>'232']]]);
            $hotelInvoice['invoice']['date_of_due']='2026-11-26';
            [$status,$body]=$get('/documents',true,$hotelInvoice);
            self::assertSame(200,$status,substr($body,0,2000));
            self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $hotelInvoiceId=json_decode($body,true)['data']['id'];
            [$status,$body]=$get('/hotels/reception/'.$invoiceRent.'/rent/finalized',true,
                ['arrears'=>'0','checkout_document_type'=>'01','checkout_document_id'=>$hotelInvoiceId]);
            self::assertSame(200,$status,substr($body,0,1500));
            self::assertSame('DEBT',$db->table('hotel_rents')->where('id',$invoiceRent)->value('payment_status'));
            $invoiceReceipt=array_replace($receipt,['document_id'=>$hotelInvoiceId,'operation_key'=>(string)\Illuminate\Support\Str::uuid()]);
            unset($invoiceReceipt['sale_note_id']);
            [$status,$body]=$get('/document_payments',true,$invoiceReceipt);
            self::assertSame(200,$status,substr($body,0,1500));
            self::assertSame('PAID',$db->table('hotel_rents')->where('id',$invoiceRent)->value('payment_status'));
            self::assertSame('PAID',$db->table('hotel_rent_items')->where('id',$invoiceItem)->value('payment_status'));
            $invoicePaymentId=$db->table('document_payments')->where('document_id',$hotelInvoiceId)->whereNull('reversed_at')->value('id');
            [$status,$body]=$get('/document_payments/'.$invoicePaymentId,true,['reason'=>'Prueba temporal'],false,'DELETE');
            self::assertSame(200,$status,substr($body,0,1500));
            self::assertSame('DEBT',$db->table('hotel_rents')->where('id',$invoiceRent)->value('payment_status'));
            self::assertSame('DEBT',$db->table('hotel_rent_items')->where('id',$invoiceItem)->value('payment_status'));
            $makeAtomicRent=static function () use ($db,$input,$room) {
                $db->table('hotel_rooms')->where('id',$room)->update(['status'=>'OCUPADO']);
                $rentId=$db->table('hotel_rents')->insertGetId(['customer_id'=>$input['customer_id'],'customer'=>json_encode($input['customer']),
                    'hotel_room_id'=>$room,'payment_status'=>'DEBT','output_date'=>'2026-10-27','output_time'=>'12:00','establishment_id'=>$input['establishment_id']]);
                $db->table('hotel_rent_items')->insert(['type'=>'HAB','hotel_rent_id'=>$rentId,'item_id'=>$input['items'][0]['item_id'],
                    'item'=>json_encode(['total'=>'232']),'payment_status'=>'DEBT']);
                return $rentId;
            };
            $atomicRent=$makeAtomicRent();
            $atomicNote=array_replace($input,['id'=>null,'prefix'=>'NV','series'=>'NV01','series_id'=>$noteSeries,
                'document_type_id'=>'80','payments'=>[],'payment_condition_id'=>'02']);
            $atomic=['operation_key'=>(string)\Illuminate\Support\Str::uuid(),'arrears'=>'0','document'=>$atomicNote];
            $pdfFiles=static function () use ($temp): array {
                $files=[];
                $iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($temp.'/tenant', \FilesystemIterator::SKIP_DOTS));
                foreach ($iterator as $file) if ($file->isFile() && strtolower($file->getExtension())==='pdf') $files[$file->getPathname()]=hash_file('sha256',$file->getPathname());
                ksort($files);
                return $files;
            };
            // A failure after note creation must roll back the note and its PDF in both hotel operations.
            $productRent=$makeAtomicRent();
            $pendingProduct=['item_id'=>$input['items'][0]['item_id'],'total'=>'232','payment_status'=>'PAID',
                'rent_payment'=>['payment_method_type_id'=>'01','payment_destination_id'=>$usd->id,
                    'payment'=>'232','currency_type_id'=>'USD','original_amount'=>'2.32','exchange_rate'=>'100']];
            $productOperation=['operation_key'=>(string)\Illuminate\Support\Str::uuid(),'products'=>[$pendingProduct], 'sale_note'=>$atomicNote];
            $productPdfBefore=$pdfFiles(); $productNotesBefore=$db->table('sale_notes')->count();
            [$status,$body]=$get('/hotels/reception/'.$productRent.'/rent/products/store',true,$productOperation);
            self::assertSame(422,$status,substr($body,0,1500));
            self::assertStringContainsString('No se encontró un cobro disponible',json_encode(json_decode($body,true),JSON_UNESCAPED_UNICODE));
            self::assertSame($productNotesBefore,$db->table('sale_notes')->count());
            self::assertSame($productPdfBefore,$pdfFiles());
            $thirdLine=array_replace($input['items'][0],['quantity'=>'0.333333333333','unit_price'=>'1',
                'price_input'=>['amount'=>'1','currency_type_id'=>'VES','includes_iva'=>true], 'total'=>'0.333333333333']);
            $thirdPayment=['date_of_payment'=>'2026-10-26','payment_method_type_id'=>'04','payment_destination_id'=>'cash',
                'payment'=>'0.33','currency_type_id'=>'VES','original_amount'=>'0.33','exchange_rate'=>'1'];
            $thirdProduct=['item_id'=>$input['items'][0]['item_id'],'total'=>'0.333333333333','payment_status'=>'PAID',
                'rent_payment'=>$thirdPayment];
            $thirdNote=array_replace($atomicNote,['items'=>[$thirdLine,$thirdLine],'payments'=>[$thirdPayment,$thirdPayment]]);
            $roundingOperation=['operation_key'=>(string)\Illuminate\Support\Str::uuid(),
                'products'=>[$thirdProduct,$thirdProduct],'sale_note'=>$thirdNote];
            [$status,$body]=$get('/hotels/reception/'.$productRent.'/rent/products/store',true,$roundingOperation);
            self::assertSame(422,$status,substr($body,0,2000));
            self::assertStringContainsString('Los cobros no cubren el total',json_encode(json_decode($body,true),JSON_UNESCAPED_UNICODE));
            self::assertSame($productNotesBefore,$db->table('sale_notes')->count());
            self::assertSame($productPdfBefore,$pdfFiles());
            $productOperation['operation_key']=(string)\Illuminate\Support\Str::uuid();
            $paidNote=array_replace($atomicNote,['payments'=>[array_replace($pendingProduct['rent_payment'],['date_of_payment'=>'2026-10-26'])]]);
            $productOperation['sale_note']=$paidNote;
            $otherCustomer=\App\Models\Tenant\Person::create(['type'=>'customers','identity_document_type_id'=>'1',
                'number'=>'98765432','name'=>'Cliente Hotel de prueba','country_id'=>'VE','address'=>'Prueba','enabled'=>true]);
            $wrongCustomer=$productOperation; $wrongCustomer['operation_key']=(string)\Illuminate\Support\Str::uuid();
            $wrongCustomer['sale_note']['customer_id']=$otherCustomer->id;
            [$status,$body]=$get('/hotels/reception/'.$productRent.'/rent/products/store',true,$wrongCustomer);
            self::assertSame(422,$status,substr($body,0,1500));
            self::assertStringContainsString('La nota no corresponde al cliente',json_encode(json_decode($body,true),JSON_UNESCAPED_UNICODE));
            self::assertSame($productNotesBefore,$db->table('sale_notes')->count());
            self::assertSame($productPdfBefore,$pdfFiles());
            $wrongProduct=$productOperation; $wrongProduct['operation_key']=(string)\Illuminate\Support\Str::uuid();
            $wrongProduct['products'][0]['item_id']=$db->table('items')->where('id','<>',$input['items'][0]['item_id'])->value('id');
            self::assertNotNull($wrongProduct['products'][0]['item_id']);
            [$status,$body]=$get('/hotels/reception/'.$productRent.'/rent/products/store',true,$wrongProduct);
            self::assertSame(422,$status,substr($body,0,1500));
            self::assertSame($productNotesBefore,$db->table('sale_notes')->count());
            self::assertSame($productPdfBefore,$pdfFiles());
            $mismatch=$productOperation; $mismatch['operation_key']=(string)\Illuminate\Support\Str::uuid();
            $mismatch['products'][0]['total']='231';
            [$status,$body]=$get('/hotels/reception/'.$productRent.'/rent/products/store',true,$mismatch);
            self::assertSame(422,$status,substr($body,0,1500));
            self::assertSame($productNotesBefore,$db->table('sale_notes')->count());
            self::assertSame($productPdfBefore,$pdfFiles());
            $unpaidNote=$productOperation; $unpaidNote['operation_key']=(string)\Illuminate\Support\Str::uuid();
            $unpaidNote['products'][0]['payment_status']='DEBT';
            [$status,$body]=$get('/hotels/reception/'.$productRent.'/rent/products/store',true,$unpaidNote);
            self::assertSame(422,$status,substr($body,0,1500));
            self::assertSame($productNotesBefore,$db->table('sale_notes')->count());
            self::assertSame($productPdfBefore,$pdfFiles());
            [$status,$body]=$get('/hotels/reception/'.$productRent.'/rent/products/store',true,$productOperation);
            self::assertSame(200,$status,substr($body,0,1500));
            self::assertSame($productNotesBefore+1,$db->table('sale_notes')->count());
            self::assertGreaterThan(count($productPdfBefore),count($pdfFiles()));
            $usedNote=json_decode($body,true)['sale_note_id'];
            $reuse=$productOperation; unset($reuse['sale_note']);
            $reuse['operation_key']=(string)\Illuminate\Support\Str::uuid(); $reuse['sale_note_id']=$usedNote;
            [$status,$body]=$get('/hotels/reception/'.$productRent.'/rent/products/store',true,$reuse);
            self::assertSame(422,$status,substr($body,0,1500));
            self::assertSame(1,$db->table('hotel_rent_orders')->where('sale_note_id',$usedNote)->count());
            $roundingOperation['operation_key']=(string)\Illuminate\Support\Str::uuid();
            $roundingOperation['products'][1]['rent_payment']['payment']='0.34';
            $roundingOperation['products'][1]['rent_payment']['original_amount']='0.34';
            $roundingOperation['sale_note']['payments'][1]['payment']='0.34';
            $roundingOperation['sale_note']['payments'][1]['original_amount']='0.34';
            [$status,$body]=$get('/hotels/reception/'.$productRent.'/rent/products/store',true,$roundingOperation);
            self::assertSame(200,$status,substr($body,0,2000));
            $roundingNoteId=json_decode($body,true)['sale_note_id'];
            self::assertSame('0.666666666666',$db->table('sale_notes')->where('id',$roundingNoteId)->value('total'));
            self::assertTrue(\App\Support\Exact\Decimal::of($db->table('sale_note_payments')->where('sale_note_id',$roundingNoteId)->sum('payment'))->isEqualTo('0.67'));
            self::assertTrue(\App\Support\Exact\Decimal::of($db->table('hotel_rent_item_payments')->join('hotel_rent_items','hotel_rent_items.id','=','hotel_rent_item_payments.hotel_rent_item_id')
                ->join('hotel_rent_orders','hotel_rent_orders.id','=','hotel_rent_items.hotel_rent_order_id')
                ->where('hotel_rent_orders.sale_note_id',$roundingNoteId)->sum('hotel_rent_item_payments.payment'))->isEqualTo('0.67'));
            $mixedRounding=$roundingOperation;
            $mixedRounding['operation_key']=(string)\Illuminate\Support\Str::uuid();
            $usdCent=['date_of_payment'=>'2026-10-26','payment_method_type_id'=>'01','payment_destination_id'=>$usd->id,
                'payment'=>'0.33','currency_type_id'=>'USD','original_amount'=>'0.01','exchange_rate'=>'33'];
            $mixedRounding['products'][0]['rent_payment']=$usdCent;
            $mixedRounding['sale_note']['payments'][0]=$usdCent;
            [$status,$body]=$get('/hotels/reception/'.$productRent.'/rent/products/store',true,$mixedRounding);
            self::assertSame(200,$status,substr($body,0,2000));
            $mixedRoundingNote=json_decode($body,true)['sale_note_id'];
            $usdReceipt=$db->table('sale_note_payments')->where('sale_note_id',$mixedRoundingNote)->where('currency_type_id','USD')->first();
            self::assertTrue(\App\Support\Exact\Decimal::of($usdReceipt->original_amount)->isEqualTo('0.01'));
            self::assertTrue(\App\Support\Exact\Decimal::of($usdReceipt->payment)->isEqualTo('0.33'));
            self::assertTrue(\App\Support\Exact\Decimal::of($db->table('sale_note_payments')->where('sale_note_id',$mixedRoundingNote)->sum('payment'))->isEqualTo('0.67'));
            $mixedSourceIds=$db->table('sale_note_payments')->where('sale_note_id',$mixedRoundingNote)->pluck('id')->all();
            self::assertCount(2,$mixedSourceIds);
            self::assertSame(2,$db->table('hotel_rent_item_payments')->whereIn('source_sale_note_payment_id',$mixedSourceIds)->count());

            $rate=$db->table('hotel_rates')->insertGetId(['description'=>'Tarifa HTTP','establishment_id'=>$input['establishment_id']]);
            $db->table('hotel_rooms')->where('id',$room)->update(['status'=>'DISPONIBLE']);
            $rentOperation=['operation_key'=>(string)\Illuminate\Support\Str::uuid(),'customer_id'=>$input['customer_id'],
                'customer'=>$input['customer'],'towels'=>1,'duration'=>1,'quantity_persons'=>1,'payment_status'=>'PAID',
                'input_time'=>'10:00','output_date'=>'2026-10-27','output_time'=>'12:00','product'=>$pendingProduct,
                'hotel_rate_id'=>$rate,'affectation_igv_type_id'=>'10','data_persons'=>[['name'=>'Huésped de prueba']],
                'rent_payment'=>$pendingProduct['rent_payment'],'sale_note'=>$atomicNote];
            $rentPdfBefore=$pdfFiles(); $rentNotesBefore=$db->table('sale_notes')->count();
            [$status,$body]=$get('/hotels/reception/'.$room.'/rent/store',true,$rentOperation);
            self::assertSame(422,$status,substr($body,0,1500));
            self::assertStringContainsString('No se encontró un cobro disponible',json_encode(json_decode($body,true),JSON_UNESCAPED_UNICODE));
            self::assertSame($rentNotesBefore,$db->table('sale_notes')->count());
            self::assertSame($rentPdfBefore,$pdfFiles());
            self::assertSame('DISPONIBLE',$db->table('hotel_rooms')->where('id',$room)->value('status'));
            $rentOperation['operation_key']=(string)\Illuminate\Support\Str::uuid();
            $rentOperation['sale_note']=$paidNote;
            [$status,$body]=$get('/hotels/reception/'.$room.'/rent/store',true,$rentOperation);
            self::assertSame(200,$status,substr($body,0,1500));
            self::assertSame($rentNotesBefore+1,$db->table('sale_notes')->count());
            self::assertGreaterThan(count($rentPdfBefore),count($pdfFiles()));
            $pdfsBeforeNote=$pdfFiles();
            $notesBefore=$db->table('sale_notes')->count(); $movementsBefore=$db->table('global_payments')->count();
            [$status,$body]=$get('/hotels/reception/'.$atomicRent.'/rent/checkout/operation',true,$atomic);
            self::assertSame(200,$status,substr($body,0,2500));
            self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $atomicId=json_decode($body,true)['data']['id'];
            self::assertGreaterThan(count($pdfsBeforeNote),count($pdfFiles()),'La nota confirmada debe generar su PDF.');
            self::assertSame($notesBefore+1,$db->table('sale_notes')->count());
            self::assertSame('DEBT',$db->table('hotel_rents')->where('id',$atomicRent)->value('payment_status'));
            [$status,$body]=$get('/hotels/reception/'.$atomicRent.'/rent/checkout/operation',true,$atomic);
            self::assertSame(200,$status,substr($body,0,1500));
            self::assertSame($atomicId,json_decode($body,true)['data']['id']);
            [$status,$body]=$get('/hotels/reception/'.$atomicRent.'/rent/checkout/operation/'.$atomic['operation_key']);
            self::assertSame(200,$status,substr($body,0,1500));
            self::assertSame($atomicId,json_decode($body,true)['data']['id']);
            [$status,$body]=$get('/hotels/reception/'.$atomicRent.'/rent/checkout/operation',true,array_replace($atomic,['arrears'=>'0.01']));
            self::assertSame(422,$status,substr($body,0,1500));
            self::assertSame($notesBefore+1,$db->table('sale_notes')->count());
            self::assertSame($movementsBefore,$db->table('global_payments')->count());
            $failedRent=$makeAtomicRent();
            $beforeStock=$db->table('item_warehouse')->where('item_id',$input['items'][0]['item_id'])->value('stock');
            $badAtomic=array_replace($atomic,['operation_key'=>(string)\Illuminate\Support\Str::uuid(),'arrears'=>'1']);
            $pdfsBeforeFailedNote=$pdfFiles();
            [$status,$body]=$get('/hotels/reception/'.$failedRent.'/rent/checkout/operation',true,$badAtomic);
            self::assertSame(422,$status,substr($body,0,1500));
            self::assertSame($pdfsBeforeFailedNote,$pdfFiles(),'El rollback no debe crear ni sobrescribir PDF.');
            self::assertSame($notesBefore+1,$db->table('sale_notes')->count());
            self::assertSame($movementsBefore,$db->table('global_payments')->count());
            self::assertSame($beforeStock,$db->table('item_warehouse')->where('item_id',$input['items'][0]['item_id'])->value('stock'));
            self::assertNotSame('FINALIZADO',$db->table('hotel_rents')->where('id',$failedRent)->value('status'));
            [$status,$body]=$get('/hotels/reception/'.$failedRent.'/rent/checkout/operation/'.$badAtomic['operation_key']);
            self::assertSame(200,$status,substr($body,0,1500));
            self::assertFalse(json_decode($body,true)['found']);
            $adoptRent=$makeAtomicRent();
            $orphanInput=array_replace($atomicNote,['source_module'=>'HOTEL','hotel_rent_id'=>$adoptRent,
                'external_id'=>(string)\Illuminate\Support\Str::uuid()]);
            $orphan=$db->transaction(fn () => \App\Models\Tenant\SaleNote::create($saleController->mergeData($orphanInput)));
            $orphan->items()->create($input['items'][0]);
            $beforeAdoption=$db->table('sale_notes')->count();
            $adoption=array_replace($atomic,['operation_key'=>(string)\Illuminate\Support\Str::uuid()]);
            [$status,$body]=$get('/hotels/reception/'.$adoptRent.'/rent/checkout/operation',true,$adoption);
            self::assertSame(422,$status,substr($body,0,1500));
            self::assertNotSame('FINALIZADO',$db->table('hotel_rents')->where('id',$adoptRent)->value('status'));
            self::assertSame($beforeAdoption,$db->table('sale_notes')->count());
            $atomicInvoiceRent=$makeAtomicRent();
            $invoiceOperation=['operation_key'=>(string)\Illuminate\Support\Str::uuid(),'arrears'=>'0','document'=>$hotelInvoice];
            unset($invoiceOperation['document']['type']);
            $invoiceOperation['document']['series_id']=$db->table('series')->where('document_type_id','01')->where('establishment_id',$input['establishment_id'])->where('number',$hotelInvoice['series'])->value('id');
            unset($invoiceOperation['document']['series'], $invoiceOperation['document']['user_id'],
                $invoiceOperation['document']['external_id'], $invoiceOperation['document']['invoice'],
                $invoiceOperation['document']['transport']);
            $beforeInvoices=$db->table('documents')->count();
            $pdfsBeforeInvoice=$pdfFiles();
            [$status,$body]=$get('/hotels/reception/'.$atomicInvoiceRent.'/rent/checkout/operation',true,$invoiceOperation);
            self::assertSame(200,$status,substr($body,0,2500));
            self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $atomicInvoiceId=json_decode($body,true)['data']['id'];
            self::assertGreaterThan(count($pdfsBeforeInvoice),count($pdfFiles()),'La factura confirmada debe generar su PDF.');
            [$status,$body]=$get('/hotels/reception/'.$atomicInvoiceRent.'/rent/checkout/operation',true,$invoiceOperation);
            self::assertSame(200,$status,substr($body,0,1500));
            self::assertSame($atomicInvoiceId,json_decode($body,true)['data']['id']);
            self::assertSame($beforeInvoices+1,$db->table('documents')->count());
            self::assertSame('DEBT',$db->table('hotel_rents')->where('id',$atomicInvoiceRent)->value('payment_status'));
            foreach ([[\App\Models\Tenant\SaleNote::class,$atomicId,'sale_notes'],
                [\App\Models\Tenant\Document::class,$atomicInvoiceId,'documents']] as [$class,$id,$table]) {
                $original=$class::withoutEagerLoads()->findOrFail($id);
                foreach (['hotel_rent_id'=>$productRent,'source_module'=>'WEB'] as $field=>$value) {
                    $edited=$class::withoutEagerLoads()->findOrFail($id); $edited->{$field}=$value;
                    try { $edited->save(); self::fail('No se debe cambiar el vínculo de Hotel en '.$table); }
                    catch (\Illuminate\Validation\ValidationException $e) { self::assertArrayHasKey('checkout',$e->errors()); }
                    self::assertEquals($original->getRawOriginal($field),$db->table($table)->where('id',$id)->value($field));
                }
            }
            $ordinaryEdit=array_replace($atomicNote,['id'=>$atomicId,'number'=>$db->table('sale_notes')->where('id',$atomicId)->value('number')]);
            unset($ordinaryEdit['source_module'],$ordinaryEdit['hotel_rent_id']);
            [$status,$body]=$get('/sale-notes',true,$ordinaryEdit);
            self::assertSame(200,$status,substr($body,0,1500));
            self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            self::assertSame('HOTEL',$db->table('sale_notes')->where('id',$atomicId)->value('source_module'));
            self::assertEquals($atomicRent,$db->table('sale_notes')->where('id',$atomicId)->value('hotel_rent_id'));
            $converted=array_replace($hotelInvoice,['sale_note_id'=>$atomicId,'source_module'=>'WEB','hotel_rent_id'=>null,
                'external_id'=>(string)\Illuminate\Support\Str::uuid()]);
            [$status,$body]=$get('/documents',true,$converted);
            self::assertSame(200,$status,substr($body,0,2000));
            self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $convertedId=json_decode($body,true)['data']['id'];
            self::assertSame($convertedId,$db->table('sale_notes')->where('id',$atomicId)->value('document_id'));
            $convertedLine=$db->table('document_items')->where('document_id',$convertedId)->first();
            $otherConvertedItem=$db->table('items')->where('id','<>',$convertedLine->item_id)->value('id');
            self::assertNotNull($otherConvertedItem);
            $db->table('document_items')->where('id',$convertedLine->id)->update(['item_id'=>$otherConvertedItem]);
            $convertedPayment=array_replace($invoiceReceipt,['document_id'=>$convertedId,'operation_key'=>(string)\Illuminate\Support\Str::uuid()]);
            [$status,$body]=$get('/document_payments',true,$convertedPayment);
            self::assertSame(200,$status,substr($body,0,1500));
            self::assertSame('DEBT',$db->table('hotel_rents')->where('id',$atomicRent)->value('payment_status'));
            $db->table('document_items')->where('id',$convertedLine->id)->update(['item_id'=>$convertedLine->item_id]);
            [$status,$body]=$get('/hotels/reception/'.$atomicRent.'/rent/finalized',true,
                ['arrears'=>'0','checkout_document_type'=>'80','checkout_document_id'=>$atomicId]);
            self::assertSame(200,$status,substr($body,0,1500));
            self::assertSame('PAID',$db->table('hotel_rents')->where('id',$atomicRent)->value('payment_status'));
            $convertedPaymentId=$db->table('document_payments')->where('document_id',$convertedId)->whereNull('reversed_at')->value('id');
            [$status,$body]=$get('/document_payments/'.$convertedPaymentId,true,['reason'=>'Prueba temporal'],false,'DELETE');
            self::assertSame(200,$status,substr($body,0,1500));
            self::assertSame('DEBT',$db->table('hotel_rents')->where('id',$atomicRent)->value('payment_status'));
            // A second commercial channel cannot attach another checkout to a finalized rent.
            $duplicateNote=array_replace($atomicNote,['source_module'=>'HOTEL','hotel_rent_id'=>$atomicInvoiceRent]);
            $beforeDuplicateNotes=$db->table('sale_notes')->count();
            [$status,$body]=$get('/sale-notes',true,$duplicateNote);
            self::assertContains($status,[200,422],substr($body,0,1500));
            self::assertFalse(json_decode($body,true)['success'] ?? false,substr($body,0,1500));
            self::assertSame($beforeDuplicateNotes,$db->table('sale_notes')->count());
            $duplicateInvoice=array_replace($hotelInvoice,['source_module'=>'HOTEL','hotel_rent_id'=>$atomicInvoiceRent]);
            [$status,$body]=$get('/documents',true,$duplicateInvoice);
            self::assertSame(422,$status,substr($body,0,1500));
            self::assertSame($beforeInvoices+2,$db->table('documents')->count());
            $failedInvoiceRent=$makeAtomicRent();
            $failedInvoice=array_replace($invoiceOperation,['operation_key'=>(string)\Illuminate\Support\Str::uuid(),'arrears'=>'1']);
            $pdfsBeforeFailedInvoice=$pdfFiles();
            [$status,$body]=$get('/hotels/reception/'.$failedInvoiceRent.'/rent/checkout/operation',true,$failedInvoice);
            self::assertSame(422,$status,substr($body,0,1500));
            self::assertSame($pdfsBeforeFailedInvoice,$pdfFiles(),'El rollback de factura no debe crear ni sobrescribir PDF.');
            self::assertSame($beforeInvoices+2,$db->table('documents')->count());
            // Two checkout notes converted to one invoice: partial receipts are not
            // attributed to either stay without an explicit allocation.
            $groupRents=[]; $groupNotes=[];
            for ($i=0;$i<2;$i++) {
                $groupRent=$makeAtomicRent();
                $groupOperation=array_replace($atomic,['operation_key'=>(string)\Illuminate\Support\Str::uuid()]);
                [$status,$body]=$get('/hotels/reception/'.$groupRent.'/rent/checkout/operation',true,$groupOperation);
                self::assertSame(200,$status,substr($body,0,1500));
                $groupRents[]=$groupRent; $groupNotes[]=json_decode($body,true)['data']['id'];
            }
            $groupInvoice=array_replace($hotelInvoice,['source_module'=>'WEB','hotel_rent_id'=>null,'sale_note_id'=>null,
                'sale_notes_relateds'=>array_map(fn ($id)=>['id'=>$id],$groupNotes),
                'external_id'=>(string)\Illuminate\Support\Str::uuid(), 'items'=>[$input['items'][0],$input['items'][0]],
                'total_taxed'=>'400','total_igv'=>'64','total_taxes'=>'64','total_value'=>'400','subtotal'=>'464','total'=>'464',
                'fee'=>[['date'=>'2026-11-26','currency_type_id'=>'VES','amount'=>'464']]]);
            [$status,$body]=$get('/documents',true,$groupInvoice);
            self::assertSame(200,$status,substr($body,0,2500));
            self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $groupInvoiceId=json_decode($body,true)['data']['id'];
            self::assertSame(2,$db->table('sale_notes')->whereIn('id',$groupNotes)->where('document_id',$groupInvoiceId)->count());
            for ($i=0;$i<2;$i++) {
                $groupPayment=array_replace($invoiceReceipt,['document_id'=>$groupInvoiceId,'operation_key'=>(string)\Illuminate\Support\Str::uuid()]);
                [$status,$body]=$get('/document_payments',true,$groupPayment);
                self::assertSame(200,$status,substr($body,0,1500));
                foreach ($groupRents as $groupRent) self::assertSame($i===0 ? 'DEBT' : 'PAID',
                    $db->table('hotel_rents')->where('id',$groupRent)->value('payment_status'));
            }
            $lastGroupPayment=$db->table('document_payments')->where('document_id',$groupInvoiceId)->whereNull('reversed_at')->orderByDesc('id')->value('id');
            [$status,$body]=$get('/document_payments/'.$lastGroupPayment,true,['reason'=>'Prueba temporal'],false,'DELETE');
            self::assertSame(200,$status,substr($body,0,1500));
            foreach ($groupRents as $groupRent) self::assertSame('DEBT',$db->table('hotel_rents')->where('id',$groupRent)->value('payment_status'));
            $mixedRents=[]; $mixedNotes=[];
            for ($i=0;$i<2;$i++) {
                $mixedRent=$makeAtomicRent();
                $mixedOperation=array_replace($atomic,['operation_key'=>(string)\Illuminate\Support\Str::uuid()]);
                [$status,$body]=$get('/hotels/reception/'.$mixedRent.'/rent/checkout/operation',true,$mixedOperation);
                self::assertSame(200,$status,substr($body,0,1500));
                $mixedRents[]=$mixedRent; $mixedNotes[]=json_decode($body,true)['data']['id'];
            }
            $paidSource=array_replace($receipt,['sale_note_id'=>$mixedNotes[0],'operation_key'=>(string)\Illuminate\Support\Str::uuid()]);
            [$status,$body]=$get('/sale_note_payments',true,$paidSource);
            self::assertSame(200,$status,substr($body,0,1500));
            self::assertSame('PAID',$db->table('hotel_rents')->where('id',$mixedRents[0])->value('payment_status'));
            $sourcePreview=\App\Support\Exact\SaleNoteConversionLines::preview($mixedNotes);
            $mixedInvoice=array_replace($groupInvoice,['sale_notes_relateds'=>array_map(fn ($id)=>['id'=>$id],$mixedNotes),
                'external_id'=>(string)\Illuminate\Support\Str::uuid(),'payments'=>$sourcePreview['payments'],
                'fee'=>[['date'=>'2026-11-26','currency_type_id'=>'VES','amount'=>'232']]]);
            [$status,$body]=$get('/documents',true,$mixedInvoice);
            self::assertSame(200,$status,substr($body,0,2500));
            self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $mixedInvoiceId=json_decode($body,true)['data']['id'];
            self::assertSame('PAID',$db->table('hotel_rents')->where('id',$mixedRents[0])->value('payment_status'));
            self::assertSame('DEBT',$db->table('hotel_rents')->where('id',$mixedRents[1])->value('payment_status'));
            $mixedPayment=array_replace($invoiceReceipt,['document_id'=>$mixedInvoiceId,'operation_key'=>(string)\Illuminate\Support\Str::uuid()]);
            [$status,$body]=$get('/document_payments',true,$mixedPayment);
            self::assertSame(200,$status,substr($body,0,1500));
            foreach ($mixedRents as $mixedRent) self::assertSame('PAID',$db->table('hotel_rents')->where('id',$mixedRent)->value('payment_status'));
            $mixedPaymentId=$db->table('document_payments')->where('document_id',$mixedInvoiceId)->whereNull('reversed_at')->orderByDesc('id')->value('id');
            [$status,$body]=$get('/document_payments/'.$mixedPaymentId,true,['reason'=>'Prueba temporal'],false,'DELETE');
            self::assertSame(200,$status,substr($body,0,1500));
            self::assertSame('PAID',$db->table('hotel_rents')->where('id',$mixedRents[0])->value('payment_status'));
            self::assertSame('DEBT',$db->table('hotel_rents')->where('id',$mixedRents[1])->value('payment_status'));
            // Two independent HTTP workers wait on the same rent, then submit the same paid operation.
            $raceRent=$makeAtomicRent();
            $raceDocument=array_replace($atomicNote,['payments'=>[['date_of_payment'=>'2026-10-26','payment_method_type_id'=>'01',
                'payment_destination_id'=>$usd->id,'payment'=>'232','currency_type_id'=>'USD','original_amount'=>'2.32','exchange_rate'=>'100']]]);
            $raceOperation=['operation_key'=>(string)\Illuminate\Support\Str::uuid(),'arrears'=>'0','document'=>$raceDocument];
            $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
            if (!$socket) throw new \RuntimeException($error);
            $raceAddress=stream_socket_get_name($socket,false); fclose($socket);
            $raceServer=proc_open([PHP_BINARY,'-S',$raceAddress,dirname(__DIR__).'/Support/exact_http_router.php'],
                [0=>['pipe','r'],1=>['file',$temp.'/race.log','a'],2=>['file',$temp.'/race.log','a']],$racePipes,dirname(__DIR__,2),$env);
            if (!is_resource($raceServer)) throw new \RuntimeException('No se pudo iniciar el segundo servidor temporal.');
            fclose($racePipes[0]);
            for ($attempt=0;$attempt<100;$attempt++) {
                $probe=@stream_socket_client('tcp://'.$raceAddress,$errno,$error,0.1);
                if ($probe) { fclose($probe); break; } usleep(50000);
            }
            if (!$probe) throw new \RuntimeException('El segundo servidor temporal no respondió.');
            $notesBeforeRace=$db->table('sale_notes')->count(); $movementsBeforeRace=$db->table('global_payments')->count();
            $multi=curl_multi_init(); $handles=[]; $db->beginTransaction();
            try {
                $db->table('hotel_rents')->where('id',$raceRent)->lockForUpdate()->first();
                foreach ([$address,$raceAddress] as $workerAddress) {
                    $handle=curl_init('http://'.$workerAddress.'/hotels/reception/'.$raceRent.'/rent/checkout/operation');
                    curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>60,CURLOPT_POST=>true,
                        CURLOPT_POSTFIELDS=>json_encode($raceOperation,JSON_THROW_ON_ERROR),CURLOPT_HTTPHEADER=>[
                            'Host: exact-http.example.test','Accept: application/json','Content-Type: application/json','X-Requested-With: XMLHttpRequest',
                            'Authorization: Bearer '.$token,'Cookie: sab_exact_http_session='.rawurlencode($cookie),'X-CSRF-TOKEN: '.$csrf]]);
                    curl_multi_add_handle($multi,$handle); $handles[]=$handle;
                }
                $deadline=microtime(true)+20; $connected=0;
                do {
                    curl_multi_exec($multi,$running);
                    $connected=count(array_filter($handles,fn ($handle)=>curl_getinfo($handle,CURLINFO_CONNECT_TIME)>0));
                    if ($connected===2) break;
                    usleep(50000);
                } while (microtime(true)<$deadline);
                self::assertSame(2,$connected,'Ambas solicitudes deben estar conectadas mientras la estancia está bloqueada.');
                $deadline=microtime(true)+1;
                do { curl_multi_exec($multi,$running); usleep(50000); } while (microtime(true)<$deadline);
                foreach ($handles as $handle) self::assertSame(0,curl_getinfo($handle,CURLINFO_HTTP_CODE),'Ninguna respuesta debe completar antes de liberar la estancia.');
                $db->commit();
                do { curl_multi_exec($multi,$running); if ($running) curl_multi_select($multi,0.1); } while ($running);
                $raceIds=[];
                foreach ($handles as $handle) {
                    $body=curl_multi_getcontent($handle); self::assertSame(200,curl_getinfo($handle,CURLINFO_HTTP_CODE),substr($body,0,2000));
                    $raceIds[]=json_decode($body,true,512,JSON_THROW_ON_ERROR)['data']['id'];
                }
                self::assertSame($raceIds[0],$raceIds[1]);
            } finally {
                if ($db->transactionLevel()) $db->rollBack();
                foreach ($handles as $handle) { curl_multi_remove_handle($multi,$handle); curl_close($handle); }
                curl_multi_close($multi);
            }
            self::assertSame($notesBeforeRace+1,$db->table('sale_notes')->count());
            self::assertSame($movementsBeforeRace+1,$db->table('global_payments')->count());
            self::assertSame('PAID',$db->table('hotel_rents')->where('id',$raceRent)->value('payment_status'));
            // Different sessions race the atomic checkout and the general sale-note endpoint.
            $crossRent=$makeAtomicRent();
            $crossAtomic=['operation_key'=>(string)\Illuminate\Support\Str::uuid(),'arrears'=>'0','document'=>$atomicNote];
            $crossGeneral=array_replace($atomicNote,['source_module'=>'HOTEL','hotel_rent_id'=>$crossRent]);
            $secondSession=bin2hex(random_bytes(20)); $secondCsrf=bin2hex(random_bytes(20));
            file_put_contents($temp.'/framework/sessions/'.$secondSession,serialize([
                '_token'=>$secondCsrf,'login_web_'.sha1(\Illuminate\Auth\SessionGuard::class)=>$input['user_id'],
            ]));
            $secondCookie=$encrypter->encrypt(\Illuminate\Cookie\CookieValuePrefix::create('sab_exact_http_session',str_repeat('k',32)).$secondSession,false);
            // Two API delivery orders contend for one physical unit with stock control disabled.
            $raceDispatchItem=\App\Models\Tenant\Item::findOrFail($input['items'][0]['item_id'])->replicate();
            $raceDispatchItem->internal_id='API-DISPATCH-RACE'; $raceDispatchItem->stock='1';
            $raceDispatchItem->lots_enabled=false; $raceDispatchItem->series_enabled=false; $raceDispatchItem->save();
            \Modules\Inventory\Models\ItemWarehouse::create(['item_id'=>$raceDispatchItem->id,
                'warehouse_id'=>$input['items'][0]['warehouse_id'],'stock'=>'1']);
            $raceDispatchReason=$db->table('cat_transfer_reason_types')->where('id','24')->value('discount_stock');
            $raceDispatchControl=$db->table('inventory_configurations')->value('stock_control');
            $db->table('cat_transfer_reason_types')->where('id','24')->update(['discount_stock'=>true]);
            $db->table('inventory_configurations')->update(['stock_control'=>false]);
            $raceDispatchA=$apiDispatch; $raceDispatchA['numero_documento']='881';
            $raceDispatchA['items'][0]['codigo_interno']='API-DISPATCH-RACE'; $raceDispatchA['items'][0]['cantidad']='0.6';
            $raceDispatchB=$raceDispatchA; $raceDispatchB['numero_documento']='882';
            $beforeRaceDispatch=$db->table('dispatches')->count(); $raceDispatchHandles=[]; $multi=curl_multi_init(); $db->beginTransaction();
            try {
                $db->table('items')->where('id',$raceDispatchItem->id)->lockForUpdate()->first();
                foreach ([[$address,$cookie,$csrf,$raceDispatchA],[$raceAddress,$secondCookie,$secondCsrf,$raceDispatchB]] as [$worker,$workerCookie,$workerCsrf,$payload]) {
                    $handle=curl_init('http://'.$worker.'/api/dispatches');
                    curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>60,CURLOPT_POST=>true,
                        CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR),CURLOPT_HTTPHEADER=>[
                            'Host: exact-http.example.test','Accept: application/json','Content-Type: application/json','X-Requested-With: XMLHttpRequest',
                            'Authorization: Bearer '.$token,'Cookie: sab_exact_http_session='.rawurlencode($workerCookie),'X-CSRF-TOKEN: '.$workerCsrf]]);
                    curl_multi_add_handle($multi,$handle); $raceDispatchHandles[]=$handle;
                }
                $deadline=microtime(true)+20;
                do { curl_multi_exec($multi,$running); $connected=count(array_filter($raceDispatchHandles,fn($h)=>curl_getinfo($h,CURLINFO_CONNECT_TIME)>0));
                    if ($connected===2) break; usleep(50000); } while (microtime(true)<$deadline);
                self::assertSame(2,$connected);
                $deadline=microtime(true)+1;
                do { curl_multi_exec($multi,$running); usleep(50000); } while (microtime(true)<$deadline);
                foreach ($raceDispatchHandles as $handle) self::assertSame(0,curl_getinfo($handle,CURLINFO_HTTP_CODE));
                $db->commit();
                do { curl_multi_exec($multi,$running); if ($running) curl_multi_select($multi,0.1); } while ($running);
                $raceDispatchAccepted=[]; $raceDispatchRejected=0;
                foreach ($raceDispatchHandles as $handle) {
                    $body=curl_multi_getcontent($handle); $status=curl_getinfo($handle,CURLINFO_HTTP_CODE); $data=json_decode($body,true);
                    if ($status===200 && ($data['success'] ?? false)) $raceDispatchAccepted[]=$data['data']['external_id'];
                    elseif ($status===422) $raceDispatchRejected++;
                    else self::fail('Respuesta inesperada de orden API concurrente: '.$status.' '.substr($body,0,1000));
                }
                self::assertCount(1,$raceDispatchAccepted); self::assertSame(1,$raceDispatchRejected);
            } finally {
                if ($db->transactionLevel()) $db->rollBack();
                foreach ($raceDispatchHandles as $handle) { curl_multi_remove_handle($multi,$handle); curl_close($handle); }
                curl_multi_close($multi);
            }
            self::assertSame($beforeRaceDispatch+1,$db->table('dispatches')->count());
            $raceDispatchStock=fn()=>\Modules\Inventory\Models\ItemWarehouse::where('item_id',$raceDispatchItem->id)
                ->where('warehouse_id',$input['items'][0]['warehouse_id'])->firstOrFail()->exactValue('stock');
            self::assertTrue($raceDispatchStock()->isEqualTo('0.4'));
            self::assertTrue($raceDispatchItem->fresh()->exactValue('stock')->isEqualTo('0.4'));
            $raceDispatchRecord=\App\Models\Tenant\Dispatch::where('external_id',$raceDispatchAccepted[0])->firstOrFail();
            [$raceVoidStatus,$raceVoidBody]=$get('/dispatches/anulate/'.$raceDispatchRecord->id,true,[]);
            self::assertSame(200,$raceVoidStatus,substr($raceVoidBody,0,1000)); self::assertTrue(json_decode($raceVoidBody,true)['success'] ?? false,$raceVoidBody);
            self::assertTrue($raceDispatchStock()->isEqualTo('1'));
            self::assertTrue($raceDispatchItem->fresh()->exactValue('stock')->isEqualTo('1'));
            $db->table('cat_transfer_reason_types')->where('id','24')->update(['discount_stock'=>$raceDispatchReason]);
            $db->table('inventory_configurations')->update(['stock_control'=>$raceDispatchControl]);
            // Two different sessions contend for the same lot and serial in a free-form order.
            $raceFlags=$db->table('items')->where('id',$lotItemId)->first(['lots_enabled','series_enabled']);
            $db->table('items')->where('id',$lotItemId)->update(['lots_enabled'=>1,'series_enabled'=>1]);
            $raceStock=$warehouseStock(); $raceGlobal=$globalStock(); $raceOrderCount=$db->table('order_notes')->count();
            $raceKardexCount=$db->table('inventory_kardex')->where('item_id',$lotItemId)->count();
            self::assertSame('1',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($serialLot)->exactValue('quantity'));
            self::assertFalse((bool)$db->table('item_lots')->where('id',$serialId)->value('has_sale'));
            $multi=curl_multi_init(); $handles=[]; $db->beginTransaction();
            try {
                $db->table('items')->where('id',$lotItemId)->lockForUpdate()->first();
                foreach ([[$address,$cookie,$csrf],[$raceAddress,$secondCookie,$secondCsrf]] as [$worker,$workerCookie,$workerCsrf]) {
                    $handle=curl_init('http://'.$worker.'/order-notes');
                    curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>60,CURLOPT_POST=>true,
                        CURLOPT_POSTFIELDS=>json_encode($serialOrder,JSON_THROW_ON_ERROR),CURLOPT_HTTPHEADER=>[
                            'Host: exact-http.example.test','Accept: application/json','Content-Type: application/json','X-Requested-With: XMLHttpRequest',
                            'Authorization: Bearer '.$token,'Cookie: sab_exact_http_session='.rawurlencode($workerCookie),'X-CSRF-TOKEN: '.$workerCsrf]]);
                    curl_multi_add_handle($multi,$handle); $handles[]=$handle;
                }
                $deadline=microtime(true)+20;
                do { curl_multi_exec($multi,$running); $connected=count(array_filter($handles,fn($h)=>curl_getinfo($h,CURLINFO_CONNECT_TIME)>0));
                    if ($connected===2) break; usleep(50000); } while (microtime(true)<$deadline);
                self::assertSame(2,$connected);
                $deadline=microtime(true)+1;
                do { curl_multi_exec($multi,$running); usleep(50000); } while (microtime(true)<$deadline);
                foreach ($handles as $handle) self::assertSame(0,curl_getinfo($handle,CURLINFO_HTTP_CODE));
                $db->commit();
                do { curl_multi_exec($multi,$running); if ($running) curl_multi_select($multi,0.1); } while ($running);
                $accepted=[]; $rejected=0;
                foreach ($handles as $handle) {
                    $response=curl_multi_getcontent($handle); $status=curl_getinfo($handle,CURLINFO_HTTP_CODE);
                    if ($status===200 && (json_decode($response,true)['success'] ?? false)) $accepted[]=json_decode($response,true)['data']['id'];
                    elseif ($status===422) {
                        $failure=json_decode($response,true);
                        self::assertArrayHasKey('items',$failure['errors'] ?? $failure['message'] ?? [],$response);
                        $rejected++;
                    }
                    else self::fail('Respuesta inesperada de pedido concurrente: '.$status.' '.substr($response,0,1200));
                }
                self::assertCount(1,$accepted); self::assertSame(1,$rejected);
            } finally {
                if ($db->transactionLevel()) $db->rollBack();
                foreach ($handles as $handle) { curl_multi_remove_handle($multi,$handle); curl_close($handle); }
                curl_multi_close($multi);
            }
            self::assertSame($raceOrderCount+1,$db->table('order_notes')->count());
            self::assertSame($raceKardexCount+1,$db->table('inventory_kardex')->where('item_id',$lotItemId)->count());
            self::assertSame('0',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($serialLot)->exactValue('quantity'));
            self::assertTrue((bool)$db->table('item_lots')->where('id',$serialId)->value('has_sale'));
            self::assertTrue($warehouseStock()->isEqualTo($raceStock->minus(1))); self::assertTrue($globalStock()->isEqualTo($raceGlobal));
            [$status,$body]=$get('/order-notes/voided/'.$accepted[0]);
            self::assertSame(200,$status,substr($body,0,1200)); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            self::assertSame('1',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($serialLot)->exactValue('quantity'));
            self::assertFalse((bool)$db->table('item_lots')->where('id',$serialId)->value('has_sale'));
            self::assertTrue($warehouseStock()->isEqualTo($raceStock));
            self::assertSame($raceKardexCount+2,$db->table('inventory_kardex')->where('item_id',$lotItemId)->count());
            $db->table('items')->where('id',$lotItemId)->update(['lots_enabled'=>$raceFlags->lots_enabled,'series_enabled'=>$raceFlags->series_enabled]);
            $beforeCross=$db->table('sale_notes')->count(); $multi=curl_multi_init(); $handles=[]; $db->beginTransaction();
            try {
                $db->table('hotel_rents')->where('id',$crossRent)->lockForUpdate()->first();
                foreach ([[$address,'/hotels/reception/'.$crossRent.'/rent/checkout/operation',$crossAtomic,$cookie,$csrf],
                    [$raceAddress,'/sale-notes',$crossGeneral,$secondCookie,$secondCsrf]] as [$worker,$path,$data,$workerCookie,$workerCsrf]) {
                    $handle=curl_init('http://'.$worker.$path);
                    curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>60,CURLOPT_POST=>true,
                        CURLOPT_POSTFIELDS=>json_encode($data,JSON_THROW_ON_ERROR),CURLOPT_HTTPHEADER=>[
                            'Host: exact-http.example.test','Accept: application/json','Content-Type: application/json','X-Requested-With: XMLHttpRequest',
                            'Authorization: Bearer '.$token,'Cookie: sab_exact_http_session='.rawurlencode($workerCookie),'X-CSRF-TOKEN: '.$workerCsrf]]);
                    curl_multi_add_handle($multi,$handle); $handles[]=$handle;
                }
                $deadline=microtime(true)+20; $connected=0;
                do { curl_multi_exec($multi,$running); $connected=count(array_filter($handles,fn ($h)=>curl_getinfo($h,CURLINFO_CONNECT_TIME)>0));
                    if ($connected===2) break; usleep(50000); } while (microtime(true)<$deadline);
                self::assertSame(2,$connected);
                $deadline=microtime(true)+1;
                do { curl_multi_exec($multi,$running); usleep(50000); } while (microtime(true)<$deadline);
                foreach ($handles as $handle) self::assertSame(0,curl_getinfo($handle,CURLINFO_HTTP_CODE));
                $db->commit();
                do { curl_multi_exec($multi,$running); if ($running) curl_multi_select($multi,0.1); } while ($running);
                $outcomes=[];
                foreach ($handles as $handle) {
                    $body=curl_multi_getcontent($handle); $decoded=json_decode($body,true);
                    $outcomes[]=(curl_getinfo($handle,CURLINFO_HTTP_CODE)===200 && ($decoded['success'] ?? false)===true);
                }
                self::assertSame(1,count(array_filter($outcomes)),'Una sola solicitud debe confirmar la nota.');
            } finally {
                if ($db->transactionLevel()) $db->rollBack();
                foreach ($handles as $handle) { curl_multi_remove_handle($multi,$handle); curl_close($handle); }
                curl_multi_close($multi);
            }
            self::assertSame($beforeCross+1,$db->table('sale_notes')->count());
            // The production kernel boots both inventory providers, including legacy kardex.
            $service=\App\Models\Tenant\Item::where('internal_id','MOCK-SERV-VES-001')->firstOrFail();
            $serviceLine=array_replace($input['items'][0],['item_id'=>$service->id,'item'=>$service->toArray()]);
            $legacyServiceRows=$db->table('kardex')->where('item_id',$service->id)->count();
            $serviceStock=$db->table('items')->where('id',$service->id)->value('stock');
            $serviceNote=array_replace($atomicNote,['external_id'=>(string)\Illuminate\Support\Str::uuid(),'items'=>[$serviceLine]]);
            [$status,$body]=$get('/sale-notes',true,$serviceNote);
            self::assertSame(200,$status,substr($body,0,1500));
            self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            self::assertSame($legacyServiceRows,$db->table('kardex')->where('item_id',$service->id)->count());
            self::assertEquals($serviceStock,$db->table('items')->where('id',$service->id)->value('stock'));
            $serviceInvoice=array_replace($hotelInvoice,['source_module'=>'WEB','hotel_rent_id'=>null,
                'external_id'=>(string)\Illuminate\Support\Str::uuid(),'items'=>[$serviceLine]]);
            [$status,$body]=$get('/documents',true,$serviceInvoice);
            self::assertSame(200,$status,substr($body,0,1500));
            self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            self::assertSame($legacyServiceRows,$db->table('kardex')->where('item_id',$service->id)->count());
            self::assertEquals($serviceStock,$db->table('items')->where('id',$service->id)->value('stock'));
            $stockControl = $db->table('inventory_configurations')->value('stock_control');
            $db->table('inventory_configurations')->update(['stock_control' => true]);
            try {
                $unavailable = $input['items'][0];
                $unavailable['quantity'] = '1000000';
                $stockRow = \Modules\Inventory\Models\ItemWarehouse::where('warehouse_id', $warehouseId)
                    ->where('item_id', $posItemId)->firstOrFail();
                foreach ([
                    ['/documents', 'documents', $serviceInvoice],
                    ['/sale-notes', 'sale_notes', $serviceNote],
                ] as [$path, $table, $payload]) {
                    $draft = array_replace($payload, [
                        'external_id' => (string) \Illuminate\Support\Str::uuid(),
                        'created_from_pos' => true,
                        'items' => [$unavailable],
                    ]);
                    $documentsBefore = $db->table($table)->count();
                    $paymentsBefore = $db->table('global_payments')->count();
                    $stockBefore = $stockRow->fresh()->exactValue('stock');
                    [$status, $response] = $get($path, true, $draft);
                    self::assertSame(200, $status, $response);
                    self::assertFalse(json_decode($response, true, 512, JSON_THROW_ON_ERROR)['success'], $response);
                    self::assertStringContainsString('no tiene suficiente stock', $response);
                    self::assertSame($documentsBefore, $db->table($table)->count());
                    self::assertSame($paymentsBefore, $db->table('global_payments')->count());
                    self::assertTrue($stockRow->fresh()->exactValue('stock')->isEqualTo($stockBefore));
                }
            } finally {
                $db->table('inventory_configurations')->update(['stock_control' => $stockControl]);
            }
            $beforeServiceInventory=$db->table('inventory_kardex')->where('item_id',$service->id)->count();
            $service->sale_unit_price='1'; $service->purchase_unit_price='2'; $service->purchase_has_igv=true; $service->save();
            $purchaseLine=$serviceLine;
            $purchaseLine['unit_price']='0.333333333333';
            $purchaseLine['price_input']=['amount'=>'0.333333333333','currency_type_id'=>'VES','includes_iva'=>true];
            $purchaseLine['sale_unit_price']='9007199254740993.01';
            $purchaseLine['update_price']=true; $purchaseLine['update_purchase_price']=true;
            $purchaseLine['item']['item_warehouse_prices']=[['id'=>0,'warehouse_id'=>$input['items'][0]['warehouse_id'],'price'=>'9007199254740993.02']];
            $purchaseService=array_replace($input,['supplier_id'=>$input['customer_id'],'series'=>'CP01','number'=>901,
                'items'=>[$purchaseLine],'payments'=>[],'fee'=>[],'payment_condition_id'=>'02']);
            unset($purchaseService['additional_information']);
            [$status,$body]=$get('/purchases',true,$purchaseService);
            $purchaseLog=@file_get_contents($temp.'/logs/laravel.log') ?: '';
            preg_match_all('/^\[[^\n]+\] testing\.ERROR:[^\n]+/m',$purchaseLog,$purchaseErrors);
            self::assertSame(200,$status,substr($body,0,1500)."\n".substr((string)end($purchaseErrors[0]),0,1800));
            self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            self::assertSame($legacyServiceRows,$db->table('kardex')->where('item_id',$service->id)->count());
            self::assertSame($beforeServiceInventory,$db->table('inventory_kardex')->where('item_id',$service->id)->count());
            self::assertEquals($serviceStock,$db->table('items')->where('id',$service->id)->value('stock'));
            $updatedService=\App\Models\Tenant\Item::withoutEagerLoads()->findOrFail($service->id);
            self::assertSame('9007199254740993.01',\App\Support\Exact\Decimal::represent($updatedService->exactValue('sale_unit_price')));
            self::assertSame('0.333333333333',\App\Support\Exact\Decimal::represent($updatedService->exactValue('purchase_unit_price')));
            $warehousePrice=\App\Models\Tenant\ItemWarehousePrice::where('item_id',$service->id)->where('warehouse_id',$input['items'][0]['warehouse_id'])->firstOrFail();
            self::assertSame('9007199254740993.02',\App\Support\Exact\Decimal::represent($warehousePrice->exactValue('price')));
            // The catalog stores its price in its own currency and tax convention.
            $updatedService->currency_type_id='USD'; $updatedService->purchase_has_igv=false; $updatedService->save();
            $foreignLine=$purchaseLine;
            $foreignLine['price_input']=['amount'=>['n'=>'1','d'=>'3'],'currency_type_id'=>'USD','includes_iva'=>false];
            $foreignLine['unit_price']='38.666666666667'; $foreignLine['update_price']=false;
            unset($foreignLine['item']['item_warehouse_prices']);
            $foreignPurchase=array_replace($purchaseService,['number'=>902,'exchange_rate_sale'=>'100','items'=>[$foreignLine]]);
            [$status,$body]=$get('/purchases',true,$foreignPurchase);
            self::assertSame(200,$status,substr($body,0,1500));
            self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $updatedService=\App\Models\Tenant\Item::withoutEagerLoads()->findOrFail($service->id);
            self::assertSame('1/3',(string)$updatedService->exactValue('purchase_unit_price'));
            // Two independent sessions contend for the same purchase under real HTTP.
            $productId=$input['items'][0]['item_id']; $warehouseId=$input['items'][0]['warehouse_id'];
            $stockBeforeRace=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$productId)->where('warehouse_id',$warehouseId)->firstOrFail()->exactValue('stock');
            $globalBeforeRace=\App\Models\Tenant\Item::findOrFail($productId)->exactValue('stock');
            $racePurchase=array_replace($purchaseService,['number'=>903,'items'=>[$input['items'][0]]]);
            [$status,$body]=$get('/purchases',true,$racePurchase);
            self::assertSame(200,$status,substr($body,0,1500));
            $raceId=json_decode($body,true)['data']['id'] ?? null; self::assertNotNull($raceId,$body);
            $multi=curl_multi_init(); $handles=[]; $db->beginTransaction();
            try {
                $db->table('purchases')->where('id',$raceId)->lockForUpdate()->first();
                foreach ([[$address,'/purchases/anular/'.$raceId,$cookie,$csrf],[$raceAddress,'/purchases/delete/'.$raceId,$secondCookie,$secondCsrf]] as [$worker,$path,$workerCookie,$workerCsrf]) {
                    $handle=curl_init('http://'.$worker.$path);
                    curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>60,CURLOPT_HTTPHEADER=>[
                        'Host: exact-http.example.test','Accept: application/json','X-Requested-With: XMLHttpRequest',
                        'Authorization: Bearer '.$token,'Cookie: sab_exact_http_session='.rawurlencode($workerCookie),'X-CSRF-TOKEN: '.$workerCsrf]]);
                    curl_multi_add_handle($multi,$handle); $handles[]=$handle;
                }
                $deadline=microtime(true)+20; $connected=0;
                do { curl_multi_exec($multi,$running); $connected=count(array_filter($handles,fn($h)=>curl_getinfo($h,CURLINFO_CONNECT_TIME)>0));
                    if ($connected===2) break; usleep(50000); } while (microtime(true)<$deadline);
                self::assertSame(2,$connected);
                $deadline=microtime(true)+1;
                do { curl_multi_exec($multi,$running); usleep(50000); } while (microtime(true)<$deadline);
                foreach ($handles as $handle) self::assertSame(0,curl_getinfo($handle,CURLINFO_HTTP_CODE));
                $db->commit();
                do { curl_multi_exec($multi,$running); if ($running) curl_multi_select($multi,0.1); } while ($running);
                $deleteBody=curl_multi_getcontent($handles[1]);
                self::assertSame(200,curl_getinfo($handles[1],CURLINFO_HTTP_CODE),$deleteBody);
                self::assertTrue(json_decode($deleteBody,true)['success'] ?? false,$deleteBody);
                self::assertContains(curl_getinfo($handles[0],CURLINFO_HTTP_CODE),[200,404]);
            } finally {
                if ($db->transactionLevel()) $db->rollBack();
                foreach ($handles as $handle) { curl_multi_remove_handle($multi,$handle); curl_close($handle); }
                curl_multi_close($multi);
            }
            self::assertFalse($db->table('purchases')->where('id',$raceId)->exists());
            self::assertTrue(\Modules\Inventory\Models\ItemWarehouse::where('item_id',$productId)->where('warehouse_id',$warehouseId)->firstOrFail()->exactValue('stock')->isEqualTo($stockBeforeRace));
            self::assertTrue(\App\Models\Tenant\Item::findOrFail($productId)->exactValue('stock')->isEqualTo($globalBeforeRace));
            $itemRacePurchase=array_replace($racePurchase,['number'=>905]);
            [$status,$body]=$get('/purchases',true,$itemRacePurchase);
            self::assertSame(200,$status,substr($body,0,1500));
            $itemRaceId=json_decode($body,true)['data']['id'] ?? null; self::assertNotNull($itemRaceId,$body);
            $itemRaceLine=$db->table('purchase_items')->where('purchase_id',$itemRaceId)->value('id'); self::assertNotNull($itemRaceLine);
            $multi=curl_multi_init(); $handles=[]; $db->beginTransaction();
            try {
                $db->table('purchases')->where('id',$itemRaceId)->lockForUpdate()->first();
                foreach ([[$address,'/purchases/anular/'.$itemRaceId,$cookie,$csrf,'GET'],
                    [$raceAddress,'/purchases/destroy_purchase_item/'.$itemRaceLine,$secondCookie,$secondCsrf,'DELETE']] as [$worker,$path,$workerCookie,$workerCsrf,$method]) {
                    $handle=curl_init('http://'.$worker.$path);
                    curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>60,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>[
                        'Host: exact-http.example.test','Accept: application/json','X-Requested-With: XMLHttpRequest',
                        'Authorization: Bearer '.$token,'Cookie: sab_exact_http_session='.rawurlencode($workerCookie),'X-CSRF-TOKEN: '.$workerCsrf]]);
                    curl_multi_add_handle($multi,$handle); $handles[]=$handle;
                }
                $deadline=microtime(true)+20; $connected=0;
                do { curl_multi_exec($multi,$running); $connected=count(array_filter($handles,fn($h)=>curl_getinfo($h,CURLINFO_CONNECT_TIME)>0));
                    if ($connected===2) break; usleep(50000); } while (microtime(true)<$deadline);
                self::assertSame(2,$connected);
                $deadline=microtime(true)+1;
                do { curl_multi_exec($multi,$running); usleep(50000); } while (microtime(true)<$deadline);
                foreach ($handles as $handle) self::assertSame(0,curl_getinfo($handle,CURLINFO_HTTP_CODE));
                $db->commit();
                do { curl_multi_exec($multi,$running); if ($running) curl_multi_select($multi,0.1); } while ($running);
                $voidBody=curl_multi_getcontent($handles[0]);
                self::assertSame(200,curl_getinfo($handles[0],CURLINFO_HTTP_CODE),$voidBody);
                self::assertTrue(json_decode($voidBody,true)['success'] ?? false,$voidBody);
                self::assertContains(curl_getinfo($handles[1],CURLINFO_HTTP_CODE),[200,422]);
            } finally {
                if ($db->transactionLevel()) $db->rollBack();
                foreach ($handles as $handle) { curl_multi_remove_handle($multi,$handle); curl_close($handle); }
                curl_multi_close($multi);
            }
            self::assertSame('11',$db->table('purchases')->where('id',$itemRaceId)->value('state_type_id'));
            self::assertTrue(\Modules\Inventory\Models\ItemWarehouse::where('item_id',$productId)->where('warehouse_id',$warehouseId)->firstOrFail()->exactValue('stock')->isEqualTo($stockBeforeRace));
            self::assertTrue(\App\Models\Tenant\Item::findOrFail($productId)->exactValue('stock')->isEqualTo($globalBeforeRace));
            $individualPurchase=array_replace($racePurchase,['number'=>904]);
            [$status,$body]=$get('/purchases',true,$individualPurchase);
            self::assertSame(200,$status,substr($body,0,1500));
            $individualId=json_decode($body,true)['data']['id'] ?? null; self::assertNotNull($individualId,$body);
            $lineId=$db->table('purchase_items')->where('purchase_id',$individualId)->value('id'); self::assertNotNull($lineId);
            [$status,$body]=$get('/purchases/destroy_purchase_item/'.$lineId,true,[],false,'DELETE');
            self::assertSame(200,$status,substr($body,0,1500));
            self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            self::assertTrue(\Modules\Inventory\Models\ItemWarehouse::where('item_id',$productId)->where('warehouse_id',$warehouseId)->firstOrFail()->exactValue('stock')->isEqualTo($stockBeforeRace));
            self::assertTrue(\App\Models\Tenant\Item::findOrFail($productId)->exactValue('stock')->isEqualTo($globalBeforeRace));
            // A sale and cancellation contend for the same purchased lot.
            $lotLine=$input['items'][0]; $lotLine['item']['lots_enabled']=true;
            $lotLine['lot_code']='HTTP-RACE-LOT'; $lotLine['date_of_due']='2027-11-30';
            $lotPurchase=array_replace($racePurchase,['number'=>906,'items'=>[$lotLine]]);
            [$status,$body]=$get('/purchases',true,$lotPurchase);
            self::assertSame(200,$status,substr($body,0,1500));
            $lotPurchaseId=json_decode($body,true)['data']['id'] ?? null; self::assertNotNull($lotPurchaseId,$body);
            $lotId=$db->table('purchase_items')->where('purchase_id',$lotPurchaseId)->value('item_lot_group_id'); self::assertNotNull($lotId);
            $saleLine=$input['items'][0]; $saleLine['quantity']='1'; $saleLine['IdLoteSelected']=$lotId; $saleLine['item']['IdLoteSelected']=$lotId;
            $saleId=(string)\Illuminate\Support\Str::uuid();
            $lotInvoice=array_replace($hotelInvoice,['source_module'=>'WEB','hotel_rent_id'=>null,'external_id'=>$saleId,'items'=>[$saleLine],
                'fee'=>[['date'=>'2026-11-26','currency_type_id'=>'VES','amount'=>'116']]]);
            $multi=curl_multi_init(); $handles=[]; $db->beginTransaction();
            try {
                $db->table('item_lots_group')->where('id',$lotId)->lockForUpdate()->first();
                foreach ([[$address,'/purchases/anular/'.$lotPurchaseId,$cookie,$csrf,null],[$raceAddress,'/documents',$secondCookie,$secondCsrf,$lotInvoice]] as [$worker,$path,$workerCookie,$workerCsrf,$payload]) {
                    $handle=curl_init('http://'.$worker.$path);
                    curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>60,CURLOPT_HTTPHEADER=>[
                        'Host: exact-http.example.test','Accept: application/json','Content-Type: application/json','X-Requested-With: XMLHttpRequest',
                        'Authorization: Bearer '.$token,'Cookie: sab_exact_http_session='.rawurlencode($workerCookie),'X-CSRF-TOKEN: '.$workerCsrf]]);
                    if ($payload!==null) { curl_setopt($handle,CURLOPT_POST,true); curl_setopt($handle,CURLOPT_POSTFIELDS,json_encode($payload,JSON_THROW_ON_ERROR)); }
                    curl_multi_add_handle($multi,$handle); $handles[]=$handle;
                }
                $deadline=microtime(true)+20; $connected=0;
                do { curl_multi_exec($multi,$running); $connected=count(array_filter($handles,fn($h)=>curl_getinfo($h,CURLINFO_CONNECT_TIME)>0));
                    if ($connected===2) break; usleep(50000); } while (microtime(true)<$deadline);
                self::assertSame(2,$connected);
                $deadline=microtime(true)+1;
                do { curl_multi_exec($multi,$running); usleep(50000); } while (microtime(true)<$deadline);
                foreach ($handles as $handle) self::assertSame(0,curl_getinfo($handle,CURLINFO_HTTP_CODE));
                $db->commit();
                do { curl_multi_exec($multi,$running); if ($running) curl_multi_select($multi,0.1); } while ($running);
                $lotRaceOutcomes=array_map(fn($h)=>[curl_getinfo($h,CURLINFO_HTTP_CODE),curl_multi_getcontent($h)],$handles);
            } finally {
                if ($db->transactionLevel()) $db->rollBack();
                foreach ($handles as $handle) { curl_multi_remove_handle($multi,$handle); curl_close($handle); }
                curl_multi_close($multi);
            }
            $wasVoided=(string)$db->table('purchases')->where('id',$lotPurchaseId)->value('state_type_id')==='11';
            $saleResponse=json_decode($lotRaceOutcomes[1][1] ?? '',true);
            $confirmedSaleId=$saleResponse['data']['id'] ?? null;
            $wasSold=$confirmedSaleId && $db->table('documents')->where('id',$confirmedSaleId)->exists();
            self::assertFalse($wasVoided && $wasSold,'Una venta no puede confirmar contra un lote anulado.');
            self::assertTrue($wasVoided || $wasSold,'Una operación debe avanzar.');
            $remaining=\App\Support\Exact\Decimal::of('2')->minus($wasVoided ? 2 : 0)->minus($wasSold ? 1 : 0);
            self::assertTrue(\Modules\Item\Models\ItemLotsGroup::findOrFail($lotId)->exactValue('quantity')->isEqualTo($remaining));
            $raceWarehouse=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$productId)->where('warehouse_id',$warehouseId)->firstOrFail()->exactValue('stock');
            self::assertTrue($raceWarehouse->isEqualTo($stockBeforeRace->plus($remaining)),json_encode(['before'=>(string)$stockBeforeRace,'after'=>(string)$raceWarehouse,'remaining'=>(string)$remaining,'voided'=>$wasVoided,'sold'=>$wasSold,'responses'=>$lotRaceOutcomes ?? []]));
            self::assertTrue(\App\Models\Tenant\Item::findOrFail($productId)->exactValue('stock')->isEqualTo($globalBeforeRace->plus($remaining)));
            // Cancellation of a serialized purchase and a new order contend for its exact lot and serial.
            $purchaseRaceFlags=$db->table('items')->where('id',$productId)->first(['lots_enabled','series_enabled']);
            $db->table('items')->where('id',$productId)->update(['lots_enabled'=>1,'series_enabled'=>1]);
            $beforePurchaseStock=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$productId)->where('warehouse_id',$warehouseId)->firstOrFail()->exactValue('stock');
            $beforePurchaseGlobal=\App\Models\Tenant\Item::findOrFail($productId)->exactValue('stock');
            $trackedPurchaseLine=$input['items'][0]; $trackedPurchaseLine['quantity']='1';
            $trackedPurchaseLine['item']['lots_enabled']=true; $trackedPurchaseLine['item']['series_enabled']=true;
            $trackedPurchaseLine['lot_code']='HTTP-PURCHASE-ORDER-RACE'; $trackedPurchaseLine['date_of_due']='2027-11-30';
            $trackedPurchaseLine['lots']=[['date'=>'2026-10-26','series'=>'HTTP-PURCHASE-ORDER-SERIAL','state'=>'A']];
            $trackedPurchase=array_replace($racePurchase,['number'=>907,'items'=>[$trackedPurchaseLine]]);
            [$status,$body]=$get('/purchases',true,$trackedPurchase);
            self::assertSame(200,$status,substr($body,0,1800)); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            $trackedPurchaseId=json_decode($body,true)['data']['id'];
            $trackedPurchaseItem=$db->table('purchase_items')->where('purchase_id',$trackedPurchaseId)->first();
            $trackedLot=$trackedPurchaseItem->item_lot_group_id;
            $trackedSerial=$db->table('item_lots')->where('item_loteable_type',\App\Models\Tenant\PurchaseItem::class)
                ->where('item_loteable_id',$trackedPurchaseItem->id)->value('id');
            self::assertNotNull($trackedLot); self::assertNotNull($trackedSerial);
            $trackedOrder=$serialOrder;
            $trackedOrder['items'][0]['IdLoteSelected']=[['id'=>$trackedLot,'compromise_quantity'=>'1']];
            $trackedOrder['items'][0]['item']['IdLoteSelected']=$trackedOrder['items'][0]['IdLoteSelected'];
            $trackedOrder['items'][0]['item']['lots']=[['id'=>$trackedSerial,'has_sale'=>true]];
            $beforeTrackedOrders=$db->table('order_notes')->count(); $multi=curl_multi_init(); $handles=[]; $db->beginTransaction();
            try {
                $db->table('items')->where('id',$productId)->lockForUpdate()->first();
                foreach ([[$address,'/purchases/anular/'.$trackedPurchaseId,$cookie,$csrf,null],[$raceAddress,'/order-notes',$secondCookie,$secondCsrf,$trackedOrder]] as [$worker,$path,$workerCookie,$workerCsrf,$payload]) {
                    $handle=curl_init('http://'.$worker.$path);
                    curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>60,CURLOPT_HTTPHEADER=>[
                        'Host: exact-http.example.test','Accept: application/json','Content-Type: application/json','X-Requested-With: XMLHttpRequest',
                        'Authorization: Bearer '.$token,'Cookie: sab_exact_http_session='.rawurlencode($workerCookie),'X-CSRF-TOKEN: '.$workerCsrf]]);
                    if ($payload!==null) { curl_setopt($handle,CURLOPT_POST,true); curl_setopt($handle,CURLOPT_POSTFIELDS,json_encode($payload,JSON_THROW_ON_ERROR)); }
                    curl_multi_add_handle($multi,$handle); $handles[]=$handle;
                }
                $deadline=microtime(true)+20;
                do { curl_multi_exec($multi,$running); $connected=count(array_filter($handles,fn($h)=>curl_getinfo($h,CURLINFO_CONNECT_TIME)>0));
                    if ($connected===2) break; usleep(50000); } while (microtime(true)<$deadline);
                self::assertSame(2,$connected);
                $deadline=microtime(true)+1;
                do { curl_multi_exec($multi,$running); usleep(50000); } while (microtime(true)<$deadline);
                foreach ($handles as $handle) self::assertSame(0,curl_getinfo($handle,CURLINFO_HTTP_CODE));
                $db->commit();
                do { curl_multi_exec($multi,$running); if ($running) curl_multi_select($multi,0.1); } while ($running);
                $trackedOutcomes=array_map(fn($h)=>[curl_getinfo($h,CURLINFO_HTTP_CODE),curl_multi_getcontent($h)],$handles);
            } finally {
                if ($db->transactionLevel()) $db->rollBack();
                foreach ($handles as $handle) { curl_multi_remove_handle($multi,$handle); curl_close($handle); }
                curl_multi_close($multi);
            }
            self::assertSame(200,$trackedOutcomes[0][0],$trackedOutcomes[0][1]);
            $purchaseVoided=(bool)(json_decode($trackedOutcomes[0][1],true)['success'] ?? false);
            $orderResponse=json_decode($trackedOutcomes[1][1],true); $orderAccepted=$trackedOutcomes[1][0]===200 && ($orderResponse['success'] ?? false);
            self::assertContains($trackedOutcomes[1][0],[200,422],$trackedOutcomes[1][1]);
            self::assertNotSame($purchaseVoided,$orderAccepted,'Sólo una operación puede consumir la existencia.');
            self::assertSame($beforeTrackedOrders+($orderAccepted ? 1 : 0),$db->table('order_notes')->count());
            self::assertSame('0',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($trackedLot)->exactValue('quantity'));
            self::assertTrue(\Modules\Inventory\Models\ItemWarehouse::where('item_id',$productId)->where('warehouse_id',$warehouseId)->firstOrFail()->exactValue('stock')->isEqualTo($beforePurchaseStock));
            if ($orderAccepted) {
                self::assertTrue((bool)$db->table('item_lots')->where('id',$trackedSerial)->value('has_sale'));
                [$status,$body]=$get('/order-notes/voided/'.$orderResponse['data']['id']); self::assertSame(200,$status,substr($body,0,1000));
                [$status,$body]=$get('/purchases/anular/'.$trackedPurchaseId); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            } else self::assertFalse($db->table('item_lots')->where('id',$trackedSerial)->exists());
            self::assertTrue(\Modules\Inventory\Models\ItemWarehouse::where('item_id',$productId)->where('warehouse_id',$warehouseId)->firstOrFail()->exactValue('stock')->isEqualTo($beforePurchaseStock));
            self::assertTrue(\App\Models\Tenant\Item::findOrFail($productId)->exactValue('stock')->isEqualTo($beforePurchaseGlobal));
            foreach (['purchase','item','edit'] as $caseIndex=>$deleteKind) {
                $deleteLine=$trackedPurchaseLine; $deleteLine['lot_code']='HTTP-DELETE-ORDER-'.$deleteKind;
                $deleteLine['lots'][0]['series']='HTTP-DELETE-SERIAL-'.$deleteKind;
                $deletePurchase=array_replace($trackedPurchase,['number'=>908+$caseIndex,'items'=>[$deleteLine]]);
                [$status,$body]=$get('/purchases',true,$deletePurchase);
                self::assertSame(200,$status,substr($body,0,1800)); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
                $deletePurchaseId=json_decode($body,true)['data']['id'];
                $deletePurchaseItem=$db->table('purchase_items')->where('purchase_id',$deletePurchaseId)->first();
                $deleteLot=$deletePurchaseItem->item_lot_group_id;
                $deleteSerial=$db->table('item_lots')->where('item_loteable_type',\App\Models\Tenant\PurchaseItem::class)
                    ->where('item_loteable_id',$deletePurchaseItem->id)->value('id');
                self::assertNotNull($deleteSerial);
                $deleteOrder=$serialOrder;
                $deleteOrder['items'][0]['IdLoteSelected']=[['id'=>$deleteLot,'compromise_quantity'=>'1']];
                $deleteOrder['items'][0]['item']['IdLoteSelected']=$deleteOrder['items'][0]['IdLoteSelected'];
                $deleteOrder['items'][0]['item']['lots']=[['id'=>$deleteSerial,'has_sale'=>true]];
                $deletePath=$deleteKind==='purchase' ? '/purchases/delete/'.$deletePurchaseId : ($deleteKind==='item' ? '/purchases/destroy_purchase_item/'.$deletePurchaseItem->id : '/purchases/update');
                $deleteMethod=$deleteKind==='purchase' ? 'GET' : ($deleteKind==='item' ? 'DELETE' : 'POST');
                $editPayload=$deleteKind==='edit' ? array_replace($deletePurchase,['id'=>$deletePurchaseId]) : null;
                $deleteOrderCount=$db->table('order_notes')->count(); $multi=curl_multi_init(); $handles=[]; $db->beginTransaction();
                try {
                    $db->table('items')->where('id',$productId)->lockForUpdate()->first();
                    foreach ([[$address,$deletePath,$deleteMethod,$cookie,$csrf,$editPayload],[$raceAddress,'/order-notes','POST',$secondCookie,$secondCsrf,$deleteOrder]] as [$worker,$path,$method,$workerCookie,$workerCsrf,$payload]) {
                        $handle=curl_init('http://'.$worker.$path);
                        curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>60,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>[
                            'Host: exact-http.example.test','Accept: application/json','Content-Type: application/json','X-Requested-With: XMLHttpRequest',
                            'Authorization: Bearer '.$token,'Cookie: sab_exact_http_session='.rawurlencode($workerCookie),'X-CSRF-TOKEN: '.$workerCsrf]]);
                        if ($payload!==null) curl_setopt($handle,CURLOPT_POSTFIELDS,json_encode($payload,JSON_THROW_ON_ERROR));
                        curl_multi_add_handle($multi,$handle); $handles[]=$handle;
                    }
                    $deadline=microtime(true)+20;
                    do { curl_multi_exec($multi,$running); $connected=count(array_filter($handles,fn($h)=>curl_getinfo($h,CURLINFO_CONNECT_TIME)>0));
                        if ($connected===2) break; usleep(50000); } while (microtime(true)<$deadline);
                    self::assertSame(2,$connected);
                    $deadline=microtime(true)+1;
                    do { curl_multi_exec($multi,$running); usleep(50000); } while (microtime(true)<$deadline);
                    foreach ($handles as $handle) self::assertSame(0,curl_getinfo($handle,CURLINFO_HTTP_CODE));
                    $db->commit();
                    do { curl_multi_exec($multi,$running); if ($running) curl_multi_select($multi,0.1); } while ($running);
                    $deleteOutcomes=array_map(fn($h)=>[curl_getinfo($h,CURLINFO_HTTP_CODE),curl_multi_getcontent($h)],$handles);
                } finally {
                    if ($db->transactionLevel()) $db->rollBack();
                    foreach ($handles as $handle) { curl_multi_remove_handle($multi,$handle); curl_close($handle); }
                    curl_multi_close($multi);
                }
                self::assertContains($deleteOutcomes[0][0],[200,422],$deleteOutcomes[0][1]);
                self::assertContains($deleteOutcomes[1][0],[200,422],$deleteOutcomes[1][1]);
                $deleted=$deleteOutcomes[0][0]===200 && (json_decode($deleteOutcomes[0][1],true)['success'] ?? false);
                $orderBody=json_decode($deleteOutcomes[1][1],true); $accepted=$deleteOutcomes[1][0]===200 && ($orderBody['success'] ?? false);
                self::assertNotSame($deleted,$accepted,'Eliminar y reservar no pueden completarse ambos.');
                self::assertSame($deleteOrderCount+($accepted ? 1 : 0),$db->table('order_notes')->count());
                $expectedAfterRace=$deleteKind==='edit' && $deleted ? $beforePurchaseStock->plus(1) : $beforePurchaseStock;
                self::assertTrue(\Modules\Inventory\Models\ItemWarehouse::where('item_id',$productId)->where('warehouse_id',$warehouseId)->firstOrFail()->exactValue('stock')->isEqualTo($expectedAfterRace));
                if ($accepted) {
                    self::assertTrue((bool)$db->table('item_lots')->where('id',$deleteSerial)->value('has_sale'));
                    [$status,$body]=$get('/order-notes/voided/'.$orderBody['data']['id']); self::assertSame(200,$status,substr($body,0,1000));
                    $cleanupPath=$deleteKind==='edit' ? '/purchases/delete/'.$deletePurchaseId : $deletePath;
                    $cleanupMethod=$deleteKind==='edit' ? 'GET' : $deleteMethod;
                    [$status,$body]=$get($cleanupPath,true,$cleanupMethod==='DELETE' ? [] : null,false,$cleanupMethod);
                    self::assertSame(200,$status,substr($body,0,1200)); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
                } elseif ($deleteKind==='edit') {
                    [$status,$body]=$get('/purchases/delete/'.$deletePurchaseId);
                    self::assertSame(200,$status,substr($body,0,1200)); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
                }
                self::assertFalse($db->table('item_lots')->where('id',$deleteSerial)->exists());
                self::assertFalse($db->table('item_lots_group')->where('id',$deleteLot)->exists());
                self::assertTrue(\Modules\Inventory\Models\ItemWarehouse::where('item_id',$productId)->where('warehouse_id',$warehouseId)->firstOrFail()->exactValue('stock')->isEqualTo($beforePurchaseStock));
                self::assertTrue(\App\Models\Tenant\Item::findOrFail($productId)->exactValue('stock')->isEqualTo($beforePurchaseGlobal));
            }
            $headerLine=$trackedPurchaseLine; $headerLine['lot_code']='HTTP-PURCHASE-HEADER';
            $headerLine['lots'][0]['series']='HTTP-PURCHASE-HEADER-SERIAL';
            $headerPurchase=array_replace($trackedPurchase,['number'=>911,'items'=>[$headerLine]]);
            [$status,$body]=$get('/purchases',true,$headerPurchase);
            self::assertSame(200,$status,substr($body,0,1200)); $headerResult=json_decode($body,true);
            self::assertTrue($headerResult['success'] ?? false,$body); $headerPurchaseId=$headerResult['data']['id'];
            $headerSource=$db->table('purchase_items')->where('purchase_id',$headerPurchaseId)->first();
            $headerSerial=$db->table('item_lots')->where('item_loteable_type',\App\Models\Tenant\PurchaseItem::class)->where('item_loteable_id',$headerSource->id)->value('id');
            $headerOrder=$serialOrder;
            $headerOrder['items'][0]['IdLoteSelected']=[['id'=>$headerSource->item_lot_group_id,'compromise_quantity'=>'1']];
            $headerOrder['items'][0]['item']['IdLoteSelected']=$headerOrder['items'][0]['IdLoteSelected'];
            $headerOrder['items'][0]['item']['lots']=[['id'=>$headerSerial,'has_sale'=>true]];
            [$status,$body]=$get('/order-notes',true,$headerOrder);
            self::assertSame(200,$status,substr($body,0,1200)); $headerOrderResult=json_decode($body,true);
            self::assertTrue($headerOrderResult['success'] ?? false,$body);
            [$status,$body]=$get('/purchases/record/'.$headerPurchaseId);
            self::assertSame(200,$status,substr($body,0,1200)); $purchaseRecord=json_decode($body,true)['data']['purchase'];
            $expectedPurchaseTotal=\App\Models\Tenant\Purchase::findOrFail($headerPurchaseId)->exactValue('total');
            $headerEdit=array_replace($headerPurchase,['id'=>$headerPurchaseId,'items'=>$purchaseRecord['items'],'observation'=>'Cabecera HTTP','total'=>'9999']);
            [$status,$body]=$get('/purchases/update',true,$headerEdit);
            self::assertSame(200,$status,substr($body,0,1200)); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            self::assertSame('Cabecera HTTP',$db->table('purchases')->where('id',$headerPurchaseId)->value('observation'));
            self::assertTrue(\App\Models\Tenant\Purchase::findOrFail($headerPurchaseId)->exactValue('total')->isEqualTo($expectedPurchaseTotal));
            self::assertTrue($db->table('purchase_items')->where('id',$headerSource->id)->exists());
            self::assertTrue((bool)$db->table('item_lots')->where('id',$headerSerial)->value('has_sale'));
            $headerEdit['items'][0]['quantity']='2';
            [$status,$body]=$get('/purchases/update',true,$headerEdit);
            self::assertSame(422,$status,substr($body,0,1200)); self::assertTrue($db->table('purchase_items')->where('id',$headerSource->id)->exists());
            [$status,$body]=$get('/order-notes/voided/'.$headerOrderResult['data']['id']); self::assertSame(200,$status,substr($body,0,1200));
            [$status,$body]=$get('/purchases/delete/'.$headerPurchaseId);
            self::assertSame(200,$status,substr($body,0,1200)); self::assertTrue(json_decode($body,true)['success'] ?? false,$body);
            self::assertTrue(\Modules\Inventory\Models\ItemWarehouse::where('item_id',$productId)->where('warehouse_id',$warehouseId)->firstOrFail()->exactValue('stock')->isEqualTo($beforePurchaseStock));
            self::assertTrue(\App\Models\Tenant\Item::findOrFail($productId)->exactValue('stock')->isEqualTo($beforePurchaseGlobal));
            $db->table('items')->where('id',$productId)->update(['lots_enabled'=>$purchaseRaceFlags->lots_enabled,'series_enabled'=>$purchaseRaceFlags->series_enabled]);
            $otherEstablishment=$db->table('establishments')->insertGetId(['description'=>'Otra sucursal','country_id'=>'VE',
                'department_id'=>'14','province_id'=>'0229','district_id'=>'000619','address'=>'Prueba','telephone'=>'04121234567','code'=>'9999']);
            $db->table('users')->where('id',$input['user_id'])->update(['type'=>'seller','establishment_id'=>$otherEstablishment]);
            $beforeDeniedNotes=$db->table('sale_notes')->count(); $beforeDeniedOrders=$db->table('hotel_rent_orders')->count();
            [$status,$body]=$get('/hotels/reception/'.$room.'/rent');
            self::assertSame(403,$status,substr($body,0,1000));
            [$status,$body]=$get('/hotels/reception/'.$productRent.'/rent/products');
            self::assertSame(403,$status,substr($body,0,1000));
            [$status,$body]=$get('/hotels/reception/'.$room.'/rent/store',true,$rentOperation);
            self::assertSame(403,$status,substr($body,0,1000));
            [$status,$body]=$get('/hotels/reception/'.$productRent.'/rent/products/store',true,$productOperation);
            self::assertSame(403,$status,substr($body,0,1000));
            [$status,$body]=$get('/hotels/reception/'.$productRent.'/rent/extend-time',true,[]);
            self::assertSame(403,$status,substr($body,0,1000));
            self::assertSame($beforeDeniedNotes,$db->table('sale_notes')->count());
            self::assertSame($beforeDeniedOrders,$db->table('hotel_rent_orders')->count());
            [$status,$body]=$get('/hotels/reception/'.$atomicRent.'/rent/checkout/operation/'.$atomic['operation_key']);
            self::assertSame(403,$status,substr($body,0,1000));
            [$status,$body]=$get('/hotels/reception/'.$atomicRent.'/rent/checkout/operation',true,$atomic);
            self::assertSame(403,$status,substr($body,0,1000));
            [$status,$body]=$get('/hotels/reception/'.$atomicRent.'/rent/finalized',true,['arrears'=>'0']);
            self::assertSame(403,$status,substr($body,0,1000));
            $db->table('users')->where('id',$input['user_id'])->update(['type'=>'admin','establishment_id'=>$input['establishment_id']]);
            // The document-list KPI must not round large VES amounts through a binary float.
            [$status,$body]=$get('/documents/kpis'); self::assertSame(200,$status,substr($body,0,1500));
            $baseline=json_decode($body,true)['sales']['total'];
            $D=\App\Support\Exact\Decimal::class;
            $baselineAmount=$D::of(str_replace([',','Bs. '],'',$baseline));
            $kpiRow=(array)$db->table('documents')->where('id',$fractionId)->first(); unset($kpiRow['id']);
            $kpiSnapshot=json_decode($kpiRow['calculation_snapshot'],true,512,JSON_THROW_ON_ERROR);
            $kpiSnapshot['values']['total']=['n'=>'900719925474099301','d'=>'100'];
            $kpiRow=array_replace($kpiRow,['external_id'=>(string)\Illuminate\Support\Str::uuid(),'number'=>99887766,
                'filename'=>'qa-http-document-kpi','unique_filename'=>'qa-http-document-kpi','date_of_issue'=>\Carbon\Carbon::now()->toDateString(),
                'currency_type_id'=>'VES','state_type_id'=>'01','control_number'=>null,'total'=>'9007199254740993.01',
                'total_payable'=>null,'calculation_snapshot'=>json_encode($kpiSnapshot,JSON_THROW_ON_ERROR)]);
            $kpiId=$db->table('documents')->insertGetId($kpiRow);
            [$status,$body]=$get('/documents/kpis'); self::assertSame(200,$status,substr($body,0,1500));
            $sales=json_decode($body,true)['sales'];
            self::assertSame('Bs. '.$D::format($baselineAmount->plus('9007199254740993.01')),$sales['total']);
            self::assertSame($sales['total'],$sales['facturas']);
            $db->table('documents')->where('id',$kpiId)->update(['currency_type_id'=>'USD']);
            $totalUrl='/documents/recordsTotal?series='.rawurlencode($kpiRow['series']).'&number='.rawurlencode((string)$kpiRow['number']);
            [$status,$body]=$get($totalUrl); self::assertSame(200,$status,substr($body,0,1500));
            $totals=collect(json_decode($body,true))->keyBy(fn($row)=>$row['document_type_id'].':'.$row['currency_type_id']);
            self::assertCount(6,$totals);
            self::assertSame('$ 9,007,199,254,740,993.01',$totals['01:USD']['total']);
            self::assertSame('Bs. 0.00',$totals['01:VES']['total']);
            self::assertSame(['n'=>'900719925474099301','d'=>'100'],$totals['01:USD']['calculation_snapshot']['values']['total']);
            $db->table('documents')->where('id',$kpiId)->update(['state_type_id'=>'11']);
            [$status,$body]=$get($totalUrl); self::assertSame(200,$status,substr($body,0,1500));
            $totals=collect(json_decode($body,true))->keyBy(fn($row)=>$row['document_type_id'].':'.$row['currency_type_id']);
            self::assertSame('$ 0.00',$totals['01:USD']['total']);
            $db->table('documents')->where('id',$kpiId)->delete();
            // The list and its totals share one uncached database snapshot.
            $db->table('configurations')->update(['show_totals_on_cpe_list'=>true]);
            $listed=(array) $db->table('documents')->where('id',$fractionId)->first();
            $listUrl='/documents/records?series='.rawurlencode($listed['series']).'&number='.rawurlencode((string)$listed['number']).'&include_totals=1';
            [$status,$body]=$get($listUrl); self::assertSame(200,$status,substr($body,0,1500));
            $listing=json_decode($body,true,512,JSON_THROW_ON_ERROR);
            self::assertCount(1,$listing['data']);
            self::assertSame($fractionId,$listing['data'][0]['id']);
            $listTotals=collect($listing['totals'])->keyBy(fn($row)=>$row['document_type_id'].':'.$row['currency_type_id']);
            $listedDocument=\App\Models\Tenant\Document::findOrFail($fractionId);
            $expected=\App\Support\Venezuela\Localization::currencySymbol($listed['currency_type_id']).' '.$D::format($listedDocument->exactValue('total'));
            self::assertSame($expected,$listTotals[$listed['document_type_id'].':'.$listed['currency_type_id']]['total']);
            // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
        } finally {
            if (is_resource($server)) { proc_terminate($server);proc_close($server); }
            if (is_resource($referenceServer)) { proc_terminate($referenceServer);proc_close($referenceServer); }
            if (is_resource($raceServer)) { proc_terminate($raceServer);proc_close($raceServer); }
            if ($temp) {
                if (is_file($temp.'/server.log')) file_put_contents(sys_get_temp_dir().'/sab-exact-http-last.log',file_get_contents($temp.'/server.log'));
                (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($temp);
            }
            if ($fixture->ready) $fixture->stop();
        }
    }

    private function assertPosCatalogExcludesServices($db, callable $get, object $product, int $warehouseId): void
    {
        $serviceId = $db->table('items')->where('internal_id', 'MOCK-SERV-VES-001')->value('id');
        $db->table('items')->where('id', $serviceId)->update(['category_id' => $product->category_id]);
        $variation = [
            'parent_item_id' => $product->id, 'currency_type_id' => 'VES', 'sale_unit_price' => 10,
            'sale_affectation_igv_type_id' => '10', 'purchase_affectation_igv_type_id' => '10',
            'category_id' => $product->category_id, 'brand_id' => $product->brand_id,
        ];
        $physicalVariationId = $db->table('items')->insertGetId($variation + [
            'description' => 'POS variation product', 'item_type_id' => '01', 'unit_type_id' => 'UND', 'stock' => 3,
        ]);
        $serviceVariationId = $db->table('items')->insertGetId($variation + [
            'description' => 'POS variation service', 'item_type_id' => '02', 'unit_type_id' => 'SERV', 'stock' => 7,
        ]);
        $db->table('item_warehouse')->insert(['warehouse_id' => $warehouseId, 'item_id' => $physicalVariationId, 'stock' => 3]);

        $articles = [$product->id => 'POS-PRODUCT', $serviceId => 'POS-SERVICE'];
        foreach ($articles as $id => $barcode) {
            $db->table('items')->where('id', $id)->update(['barcode' => $barcode, 'calculate_quantity' => true]);
            $db->table('item_unit_types')->insert([
                'item_id' => $id, 'unit_type_id' => 'CAJ', 'description' => 'Caja POS',
                'quantity_unit' => 2, 'barcode' => $barcode.'-BOX',
            ]);
        }

        $catalog = static function (string $path, ?string $key = null) use ($get) {
            [$status, $body] = $get($path);
            self::assertSame(200, $status, $path.' '.substr($body, 0, 1500));
            $response = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            return collect($key === null ? $response : $response[$key]);
        };
        $originalSetting = $db->table('configurations')->value('show_service_on_pos');
        try {
            foreach ([false, true] as $showServices) {
                $db->table('configurations')->update(['show_service_on_pos' => $showServices]);
                foreach ([
                    ['/pos/tables', 'items'], ['/pos/table/items', null], ['/pos/items?garage=1', 'data'],
                    ['/pos/items?group_variations=1', 'data'],
                    ['/pos/search_items_cat?input_item=MOCK&cat='.$product->category_id, 'data'],
                    ['/pos/search_items?input_item=MOCK', 'items'],
                ] as [$path, $key]) {
                    $rows = $catalog($path, $key);
                    self::assertContains($product->id, $rows->pluck('id'), $path);
                    self::assertNotContains('SERV', $rows->pluck('unit_type_id'), $path);
                }

                foreach (['', '-BOX&search_item_by_barcode_presentation=true'] as $suffix) {
                    self::assertSame([$product->id], $catalog('/pos/search_items?input_item=POS-PRODUCT'.$suffix, 'items')->pluck('id')->all());
                    self::assertCount(0, $catalog('/pos/search_items?input_item=POS-SERVICE'.$suffix, 'items'));
                }

                $parent = $catalog('/pos/items?group_variations=1', 'data')->firstWhere('id', $product->id);
                self::assertSame([$physicalVariationId], array_column($parent['variations'], 'id'));
                self::assertSame(1, $parent['variations_count']);
                self::assertEquals(3, $parent['variations_stock']);
                self::assertSame([$physicalVariationId], $catalog('/pos/item/'.$physicalVariationId, 'data')->pluck('id')->all());
                self::assertCount(0, $catalog('/pos/item/'.$serviceVariationId, 'data'));
                self::assertCount(0, $catalog('/pos/item/'.$serviceId, 'data'));
                self::assertContains($serviceId, $catalog('/documents/search-items?input=MOCK-SERV-VES-001', 'items')->pluck('id'));
            }
        } finally {
            $db->table('configurations')->update(['show_service_on_pos' => $originalSetting]);
            $db->table('item_unit_types')->whereIn('barcode', ['POS-PRODUCT-BOX', 'POS-SERVICE-BOX'])->delete();
            $db->table('items')->where('id', $product->id)->update(['barcode' => $product->barcode, 'calculate_quantity' => $product->calculate_quantity]);
            $db->table('items')->where('id', $serviceId)->update(['barcode' => null, 'calculate_quantity' => false, 'category_id' => null]);
            $db->table('item_warehouse')->where('item_id', $physicalVariationId)->delete();
            $db->table('items')->whereIn('id', [$physicalVariationId, $serviceVariationId])->delete();
        }
    }
}
