<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExactHttpAcceptanceTest extends TestCase
{
    public function test_production_kernel_routes_and_api_authentication_run_against_disposable_mysql(): void
    {
        if (getenv('PRO9_FISCAL_MYSQL_TESTS') !== '1') $this->markTestSkipped('Requiere MySQL temporal aislado.');
        $fixture=new class extends FiscalEmissionSchemaTest {
            public bool $ready=false;
            public function start(): array { parent::setUp();$this->ready=true;return (new \ReflectionMethod(parent::class,'bootHkaFixture'))->invoke($this); }
            public function stop(): void { parent::tearDown(); }
        };
        $server=null;$referenceServer=null;$referenceAddress=null;$temp=null;
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
            $temp=sys_get_temp_dir().'/pro9-http-'.bin2hex(random_bytes(6));
            mkdir($temp,0700);
            foreach (['framework/views','framework/cache','framework/sessions','logs','tenant'] as $directory) mkdir($temp.'/'.$directory,0700,true);
            $sessionId=bin2hex(random_bytes(20));$csrf=bin2hex(random_bytes(20));
            file_put_contents($temp.'/framework/sessions/'.$sessionId,serialize([
                '_token'=>$csrf,'login_web_'.sha1(\Illuminate\Auth\SessionGuard::class)=>$input['user_id'],
            ]));
            $encrypter=new \Illuminate\Encryption\Encrypter(str_repeat('k',32),'AES-256-CBC');
            $cookie=$encrypter->encrypt(\Illuminate\Cookie\CookieValuePrefix::create('pro9_exact_http_session',str_repeat('k',32)).$sessionId,false);
            $private=$temp.'/fixture.json';
            file_put_contents($private,json_encode(['connection'=>$db->getConfig(),'storage'=>$temp],JSON_THROW_ON_ERROR));chmod($private,0600);
            $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
            if (!$socket) throw new \RuntimeException($error);
            $address=stream_socket_get_name($socket,false);fclose($socket);
            $env=getenv();$env['PRO9_EXACT_HTTP_FIXTURE']=$private;$env['APP_ENV']='testing';
            $server=proc_open([PHP_BINARY,'-S',$address,dirname(__DIR__).'/Support/exact_http_router.php'],
                [0=>['pipe','r'],1=>['file',$temp.'/server.log','a'],2=>['file',$temp.'/server.log','a']],$pipes,dirname(__DIR__,2),$env);
            if (!is_resource($server)) throw new \RuntimeException('No se pudo abrir HTTP temporal.');
            fclose($pipes[0]);
            for ($attempt=0;$attempt<100;$attempt++) {
                $probe=@stream_socket_client('tcp://'.$address,$errno,$error,0.1);
                if ($probe) { fclose($probe);break; }
                usleep(50000);
            }
            $reference=getenv('PRO9_EXACT_HTTP_REFERENCE_CONTROLLER');
            if ($reference) {
                if (!is_file($reference)) throw new \RuntimeException('Falta la fuente congelada del controlador.');
                $privateReference=$temp.'/reference.json';
                file_put_contents($privateReference,json_encode(['connection'=>$db->getConfig(),'storage'=>$temp,'controller_reference'=>$reference],JSON_THROW_ON_ERROR));chmod($privateReference,0600);
                $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);$referenceAddress=stream_socket_get_name($socket,false);fclose($socket);
                $referenceEnv=$env;$referenceEnv['PRO9_EXACT_HTTP_FIXTURE']=$privateReference;
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
                        $authenticated ? 'Cookie: pro9_exact_http_session='.rawurlencode($cookie) : null,
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
            self::assertGreaterThan(0,(int)$headers['x-pro9-test-queries']);
            [$code,$body]=$get('/api/company',false);
            self::assertSame(401,$code,$body);
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
            $db->table('payment_method_types')->where('id','01')->update(['collection_kind'=>'account','currency_type_id'=>'USD','cash_currency_type_id'=>null,'is_cash'=>0,'is_active'=>1]);
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
            $db->table('payment_method_types')->where('id','01')->update(['is_active'=>1]);
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
            [$code,$body]=$get('/hotels/reception/'.$rent.'/rent/finalized',true,['arrears'=>0]);
            self::assertSame(200,$code,substr($body,0,1500));
            self::assertTrue(json_decode($body,true)['success']);
            self::assertSame('FINALIZADO',$db->table('hotel_rents')->where('id',$rent)->value('status'));
            self::assertSame('LIMPIEZA',$db->table('hotel_rooms')->where('id',$room)->value('status'));
            self::assertSame(1,$db->table('hotel_rent_item_payments')->count());
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
            $db->table('payment_method_types')->where('id','11')->update(['collection_kind'=>'account','currency_type_id'=>'VES','cash_currency_type_id'=>null,'is_active'=>1]);
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
            if (getenv('PRO9_EXACT_HTTP_PROFILE')==='1') {
                $profileRow=$line->getAttributes();unset($profileRow['id']);
                $db->table('sale_note_items')->where('sale_note_id',$note->id)->delete();
                foreach ([500,500] as $count) $db->table('sale_note_items')->insert(array_fill(0,$count,$profileRow));
                $start=hrtime(true);[$code,$body,$headers]=$get('/sale-notes/items',true,['notes_id'=>[$note->id]]);
                fwrite(STDERR,json_encode(['profile_headers'=>array_filter($headers,fn($key)=>str_starts_with($key,'x-pro9'),ARRAY_FILTER_USE_KEY),'total_ms'=>(hrtime(true)-$start)/1000000,'body_bytes'=>strlen($body)]).PHP_EOL);
                self::assertSame(200,$code,substr($body,0,1500));
            }
            $output=getenv('PRO9_EXACT_HTTP_BENCH_OUTPUT');
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
                        if ($sample>=0) {$times[$variant][]=$elapsed;$queries[$variant][]=(int)$headers['x-pro9-test-queries'];$memory[$variant][]=(int)$headers['x-pro9-test-peak-memory'];}
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
                    if ($initialPath=getenv('PRO9_EXACT_HTTP_INITIAL_BASELINE')) {
                        $initial=json_decode(file_get_contents($initialPath),true,512,JSON_THROW_ON_ERROR);
                        $base=$initial['cases'][(string)$size]['p95_ms'];$limit=$base+max($base*.2,100);
                        $result['cases'][(string)$size]['initial_allowed_p95_ms']=$limit;
                        $result['cases'][(string)$size]['initial_passed']=$result['cases'][(string)$size]['current']['p95_ms']<=$limit;
                    }
                }
                foreach ($hashes as $file=>$hash) self::assertSame($hash,hash_file('sha256',dirname(__DIR__,2).'/'.$file),'Cambió una fuente durante la medición.');
                file_put_contents($output,json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL);
                if ($reference) foreach ($result['cases'] as $case) self::assertTrue($case['passed'],'El p95 excede el presupuesto HTTP.');
                if (getenv('PRO9_EXACT_HTTP_INITIAL_BASELINE')) foreach ($result['cases'] as $case) self::assertTrue($case['initial_passed'],'El p95 excede la referencia inicial de la continuación.');
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
                'currency_type_id'=>'USD','cash_currency_type_id'=>null,'is_cash'=>0,'is_active'=>1]);
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
            // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
        } finally {
            if (is_resource($server)) { proc_terminate($server);proc_close($server); }
            if (is_resource($referenceServer)) { proc_terminate($referenceServer);proc_close($referenceServer); }
            if ($temp) {
                if (is_file($temp.'/server.log')) file_put_contents(sys_get_temp_dir().'/pro9-exact-http-last.log',file_get_contents($temp.'/server.log'));
                (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($temp);
            }
            if ($fixture->ready) $fixture->stop();
        }
    }
}
