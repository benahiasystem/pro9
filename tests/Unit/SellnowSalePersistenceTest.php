<?php

namespace Tests\Unit;

use App\Models\Tenant\{Cash, Document, SaleNote, User};
use App\Services\Payments\CashLedger;
use App\Services\Sellnow\{SaleEffects, SaleOperations};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

class SellnowSalePersistenceTest extends TestCase
{
    private $fixture;
    private $db;
    private $app;
    private $input;

    protected function setUp(): void
    {
        if (getenv('SAB_FISCAL_MYSQL_TESTS') !== '1') $this->markTestSkipped('Requiere una base MySQL descartable.');
        $this->fixture = new class extends FiscalEmissionSchemaTest {
            public bool $ready = false;
            public function start(): array { parent::setUp(); $this->ready=true; return (new \ReflectionMethod(parent::class,'bootHkaFixture'))->invoke($this); }
            public function stop(): void { parent::tearDown(); }
        };
        [$this->db,$this->app,$this->input] = $this->fixture->start();
        $migration = require base_path('database/migrations/tenant/2026_11_12_000001_create_sellnow_sales_table.php');
        $migration->up();
        $this->db->table('configurations')->update(['auto_send_hka'=>false,'auto_send_pdf_email'=>false]);
        \Carbon\Carbon::setTestNow('2026-09-10 12:00:00');
        Request::macro('validate', function ($rules) { return validator($this->all(), $rules)->validate(); });
        $factory=$this->createMock(\Illuminate\Contracts\Routing\ResponseFactory::class);
        $factory->method('json')->willReturnCallback(fn($data,$status=200)=>new JsonResponse($data,$status));
        $this->app->instance(\Illuminate\Contracts\Routing\ResponseFactory::class,$factory);
        $this->app->register(\Illuminate\Routing\RoutingServiceProvider::class);
        $this->app['router']->get('downloads/{model}/{type}/{external_id}/{format?}',fn()=>null)->name('tenant.download.external_id');
        $this->app['router']->getRoutes()->refreshNameLookups();
        // Rendering and actual delivery have their own tests. The business path,
        // fiscal normalization, numbering, payments, stock and ledger are real.
        $this->app->instance(SaleEffects::class, new class extends SaleEffects {
            protected function execute($sale, string $effect) { return null; }
        });
    }

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
        if ($this->fixture && $this->fixture->ready) $this->fixture->stop();
        parent::tearDown();
    }

    private function envelope(string $kind = 'document', ?int $cashId = null): array
    {
        $input = $this->input;
        $input['actions']=['send_email'=>false,'format_pdf'=>'ticket','auto_print'=>false];
        $input['operation_type_id']='0101';
        $input['date_of_due']=$input['invoice']['date_of_due'];
        $input['payments'][0] += ['payment_destination_id'=>'cash','currency_type_id'=>'VES','original_amount'=>'232','exchange_rate'=>'1',
            'operation_key'=>(string)Str::uuid()];
        if ($kind === 'document') {
            $payload=\App\CoreFacturalo\Requests\Inputs\Transform\DocumentWebTransform::transform($input);
            $payload['datos_del_cliente_o_receptor']['codigo_pais']='VE';
            $payload['datos_del_cliente_o_receptor']['ubigeo']='000619';
        } else {
            $payload=array_replace($input,['id'=>null,'number'=>null,
                'series_id'=>$this->db->table('series')->where('document_type_id','80')->value('id'),
                'document_type_id'=>'80','prefix'=>'NV','force_create_if_not_exist'=>false,'type_period'=>null,'quantity_period'=>0]);
        }
        $payload['source_module']='VENDEYA';
        return ['client_sale_id'=>(string)Str::uuid(),'device_id'=>(string)Str::uuid(),'recorded_at'=>'2026-09-10T12:00:00-04:00',
            'cash_id'=>$cashId ?? Cash::where('user_id',auth()->id())->where('state',true)->value('id'),
            'kind'=>$kind,'payload'=>$payload];
    }

    public function test_native_invoice_and_note_replays_do_not_duplicate_document_payments_stock_or_cash(): void
    {
        $operations = new SaleOperations();
        foreach (['document','sale_note'] as $kind) {
            $envelope=$this->envelope($kind);
            $before=$this->snapshot();
            $sale=$operations->submit($envelope);
            self::assertSame('confirmed',$sale->status,json_encode($sale->error));
            self::assertSame('not_requested',$sale->result['print']['reason']);
            $after=$this->snapshot();
            self::assertSame($before['documents']+($kind === 'document' ? 1 : 0),$after['documents']);
            self::assertSame($before['notes']+($kind === 'sale_note' ? 1 : 0),$after['notes']);
            self::assertSame($before['payments']+1,$after['payments']);
            self::assertSame($before['links']+1,$after['links']);
            self::assertSame($before['stock']-2,$after['stock']);
            $replay=$operations->submit($envelope);
            self::assertSame($sale->result['data']['id'],$replay->result['data']['id']);
            self::assertSame($after,$this->snapshot());
            $envelope['payload']['additional_information']='Contenido distinto';
            try { $operations->submit($envelope); self::fail('La clave con otro contenido debe rechazarse.'); }
            catch (\Symfony\Component\HttpKernel\Exception\ConflictHttpException $error) { self::assertSame(409,$error->getStatusCode()); }
            self::assertSame($after,$this->snapshot());
        }
        self::assertSame('464',CashLedger::report(Cash::findOrFail($sale->cash_id))['balances_by_currency']['VES']['final_balance']);
        self::assertNull(\App\Services\Sellnow\CashContext::current());
    }

    public function test_rejected_payload_rolls_back_all_business_writes_and_keeps_the_intent(): void
    {
        $envelope=$this->envelope();
        $envelope['payload']['codigo_tipo_moneda']='PEN';
        $before=$this->snapshot();
        $sale=(new SaleOperations())->submit($envelope);
        self::assertSame('needs_attention',$sale->status);
        self::assertSame('VALIDATION_FAILED',$sale->error['code']);
        self::assertSame($before,$this->snapshot());
        self::assertSame(1,$this->db->table('sellnow_sales')->count());
        $mismatch=$this->envelope();
        $mismatch['payload']['totales']['total_venta']='233';
        $rejected=(new SaleOperations())->submit($mismatch);
        self::assertSame('needs_attention',$rejected->status);
        self::assertArrayHasKey('total',$rejected->error['fields']);
        self::assertSame($before,$this->snapshot());
        self::assertSame(2,$this->db->table('sellnow_sales')->count());
        self::assertNull(\App\Services\Sellnow\CashContext::current());
    }

    public function test_closed_cash_requires_an_explicit_authorized_override_and_retains_original_hash(): void
    {
        $operations=new SaleOperations();
        $envelope=$this->envelope();
        $this->db->table('cash')->where('id',$envelope['cash_id'])->update(['state'=>false]);
        $sale=$operations->submit($envelope);
        self::assertSame('needs_attention',$sale->status);
        self::assertSame('CASH_CLOSED',$sale->error['code']);
        $hash=$sale->payload_hash;
        $cash=Cash::create(['user_id'=>auth()->id(),'state'=>true,'date_opening'=>'2026-09-11','time_opening'=>'12:00:00','beginning_balance'=>'0']);
        $this->db->transaction(fn()=>CashLedger::open($cash,[]));
        $operations->reassign($sale->client_sale_id,$cash->id);
        $confirmed=$operations->submit($envelope);
        self::assertSame('confirmed',$confirmed->status,json_encode($confirmed->error));
        self::assertEquals($envelope['cash_id'],$confirmed->original_cash_id);
        self::assertEquals($cash->id,$confirmed->cash_id);
        self::assertSame($hash,$confirmed->payload_hash);
        self::assertCount(1,$confirmed->cash_history);
        self::assertEquals($cash->id,$this->db->table('global_payments')->value('cash_session_id'));
        self::assertSame(0,$this->db->table('cash_documents')->where('cash_id',$envelope['cash_id'])->count());
        try { $operations->reassign($sale->client_sale_id,$envelope['cash_id']); self::fail('No cambiar caja de una venta confirmada.'); }
        catch (\Symfony\Component\HttpKernel\Exception\ConflictHttpException $error) { self::assertSame(409,$error->getStatusCode()); }
    }

    public function test_admin_selected_cash_is_used_for_cash_and_bank_receipts_without_actor_cash_leaks(): void
    {
        $userId=$this->db->table('users')->insertGetId(['name'=>'Other cashier','email'=>'cashier@example.test','password'=>'not-a-login-hash','type'=>'seller','establishment_id'=>$this->input['establishment_id']]);
        $cash=Cash::create(['user_id'=>$userId,'state'=>true,'date_opening'=>'2026-09-10','time_opening'=>'12:00:00','beginning_balance'=>'0']);
        $this->db->transaction(fn()=>CashLedger::open($cash,[]));
        $operations=new SaleOperations();
        $envelope=$this->envelope('sale_note',$cash->id);
        $sale=$operations->submit($envelope);
        self::assertSame('confirmed',$sale->status,json_encode($sale->error));
        self::assertEquals($cash->id,$this->db->table('global_payments')->value('cash_session_id'));
        self::assertEquals($cash->id,$this->db->table('global_payments')->value('destination_id'));
        self::assertEquals($cash->id,$this->db->table('cash_documents')->value('cash_id'));
        $bankId=$this->db->table('bank_accounts')->insertGetId(['bank_id'=>$this->db->table('banks')->value('id'),'description'=>'Offline bank','number'=>'TEST-OFFLINE-VES','currency_type_id'=>'VES','status'=>1]);
        $this->db->table('payment_method_types')->where('id','04')->update(['collection_kind'=>'account','currency_type_id'=>'VES','cash_currency_type_id'=>null,'is_cash'=>0,'is_active'=>1,'bank_account_id'=>$bankId]);
        $bank=$this->envelope('document',$cash->id);
        $bank['payload']['pagos'][0]['codigo_metodo_pago']='04';
        $bank['payload']['pagos'][0]['codigo_destino_pago']=$bankId;
        $bankSale=$operations->submit($bank);
        self::assertSame('confirmed',$bankSale->status,json_encode($bankSale->error));
        self::assertSame(2,$this->db->table('global_payments')->where('cash_session_id',$cash->id)->count());
        self::assertSame(2,$this->db->table('cash_documents')->where('cash_id',$cash->id)->count());
        $owner=auth()->user();
        auth()->setUser(User::findOrFail($userId));
        try { $operations->owned($sale->client_sale_id); self::fail('Otra identidad no debe leer una venta.'); }
        catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $error) { self::assertSame(404,$error->getStatusCode()); }
        finally { auth()->setUser($owner); }
        self::assertNull(\App\Services\Sellnow\CashContext::current());
    }

    public function test_two_processes_and_a_crash_after_commit_reuse_the_same_business_sale(): void
    {
        $envelope=$this->envelope();
        $start=function ($body,bool $crash=false) {
            $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/sellnow_concurrency_worker.php']);
            $process->setInput(json_encode(['connection'=>$this->db->getConfig(),'user_id'=>auth()->id(),'envelope'=>$body,'exit_after_commit'=>$crash],JSON_THROW_ON_ERROR));
            $process->setTimeout(40); $process->start();
            return $process;
        };
        $before=$this->snapshot();
        $processes=[];
        $this->db->beginTransaction();
        try {
            $this->db->table('cash')->where('id',$envelope['cash_id'])->lockForUpdate()->first();
            $processes=[$start($envelope),$start($envelope)];
            $deadline=microtime(true)+15;
            foreach ($processes as $process) {
                while (!str_contains($process->getOutput(),"READY\n")) {
                    if (!$process->isRunning() || microtime(true)>$deadline) self::fail($process->getErrorOutput());
                    usleep(10000);
                }
                self::assertTrue($process->isRunning());
            }
            $this->db->commit();
            $results=[];
            foreach ($processes as $process) {
                self::assertSame(0,$process->wait(),$process->getErrorOutput());
                $result=json_decode(substr($process->getOutput(),6),true,512,JSON_THROW_ON_ERROR);
                self::assertSame('confirmed',$result['status'],json_encode($result));
                $results[]=$result['result']['data']['id'];
            }
            self::assertSame($results[0],$results[1]);
            self::assertSame($before['documents']+1,$this->snapshot()['documents']);
            self::assertSame($before['payments']+1,$this->snapshot()['payments']);
            self::assertSame($before['stock']-2,$this->snapshot()['stock']);
        } finally {
            if ($this->db->transactionLevel()) $this->db->rollBack();
            foreach ($processes as $process) if ($process->isRunning()) $process->stop();
        }
        $crashBody=$this->envelope();
        $crash=$start($crashBody,true);
        self::assertSame(77,$crash->wait(),$crash->getErrorOutput());
        $saved=(new SaleOperations())->owned($crashBody['client_sale_id']);
        self::assertSame('confirmed',$saved->status);
        $snapshot=$this->snapshot();
        $recovery=$start($crashBody);
        self::assertSame(0,$recovery->wait(),$recovery->getErrorOutput());
        $result=json_decode(substr($recovery->getOutput(),6),true,512,JSON_THROW_ON_ERROR);
        self::assertSame($saved->result['data']['id'],$result['result']['data']['id']);
        self::assertSame($snapshot,$this->snapshot());
    }

    public function test_fractional_price_quantity_totals_keep_the_twelve_decimal_transfer_boundary(): void
    {
        $price='1.123456789012';$quantity='1.123456789012';
        $exact=\App\Support\Exact\Decimal::of($price)->multipliedBy($quantity);
        $total=\App\Support\Exact\Decimal::represent($exact);
        $received=\App\Support\Exact\Decimal::represent($exact,2);
        foreach (['document','sale_note'] as $kind) {
            $body=$this->envelope($kind);
            if ($kind === 'document') {
                $body['payload']['items'][0]['cantidad']=$quantity;
                $body['payload']['items'][0]['precio_unitario']=$price;
                $body['payload']['items'][0]['price_input']=['amount'=>$price,'currency_type_id'=>'VES','includes_iva'=>true];
                $body['payload']['totales']['total_venta']=$total;
                $body['payload']['pagos'][0]['monto']=$received;
                $body['payload']['pagos'][0]['original_amount']=$received;
            } else {
                $body['payload']['items'][0]['quantity']=$quantity;
                $body['payload']['items'][0]['unit_price']=$price;
                $body['payload']['items'][0]['price_input']=['amount'=>$price,'currency_type_id'=>'VES','includes_iva'=>true];
                $body['payload']['total']=$total;
                $body['payload']['payments'][0]['payment']=$received;
                $body['payload']['payments'][0]['original_amount']=$received;
            }
            $sale=(new SaleOperations())->submit($body);
            self::assertSame('confirmed',$sale->status,json_encode($sale->error));
            $model=$kind === 'document' ? Document::class : SaleNote::class;
            $document=$model::findOrFail($sale->result['data']['id']);
            self::assertSame($total,\App\Support\Exact\Decimal::represent($document->exactValue('total')));
            self::assertSame($body['payload'],$sale->envelope['payload']);
        }
    }

    public function test_recovery_limit_does_not_starve_newer_work_behind_a_permanent_failure(): void
    {
        $this->app->instance(SaleEffects::class,new class extends SaleEffects { public function run($sale): void {} });
        $old=(new SaleOperations())->submit($this->envelope('sale_note'));
        $journal=$old->effects;$journal['pdf']=['status'=>'failed'];$old->update(['effects'=>$journal]);
        \Carbon\Carbon::setTestNow('2026-09-10 12:01:00');
        $new=(new SaleOperations())->submit($this->envelope('sale_note'));
        $effects=new class($old->id) extends SaleEffects {
            public array $seen=[];
            public function __construct(private int $failedId) {}
            protected function execute($sale,string $effect)
            {
                $this->seen[]=$sale->id;
                if ($sale->id === $this->failedId) throw new \RuntimeException('Permanent test renderer failure');
                return null;
            }
        };
        $command=new \App\Console\Commands\SellnowRecoverCommand();
        $command->setInput(new \Symfony\Component\Console\Input\ArrayInput(['--limit'=>1],$command->getDefinition()));
        \Carbon\Carbon::setTestNow('2026-09-10 12:02:00');
        self::assertSame(0,$command->handle($effects));
        self::assertSame([$old->id],$effects->seen);
        \Carbon\Carbon::setTestNow('2026-09-10 12:03:00');
        self::assertSame(0,$command->handle($effects));
        self::assertSame([$old->id,$new->id],$effects->seen);
        self::assertSame('confirmed',$new->fresh()->effects['pdf']['status']);
        self::assertSame('failed',$old->fresh()->effects['pdf']['status']);
    }

    public function test_expired_print_claim_resuming_after_a_second_worker_cannot_duplicate_the_queue(): void
    {
        $this->app->instance(SaleEffects::class,new class extends SaleEffects { public function run($sale): void {} });
        $body=$this->envelope('sale_note');
        $body['payload']['acciones']=['formato_pdf'=>'ticket','auto_print'=>true,'name_printer'=>'LEASE TEST ONLY'];
        $sale=(new SaleOperations())->submit($body);
        self::assertSame('confirmed',$sale->status);
        $journal=$sale->effects;$journal['pdf']=['status'=>'confirmed'];$sale->update(['effects'=>$journal]);
        $gate=sys_get_temp_dir().'/sab-sellnow-print-'.bin2hex(random_bytes(10));
        $start=function (?string $gate=null) use ($body) {
            $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/sellnow_concurrency_worker.php']);
            $process->setInput(json_encode(['connection'=>$this->db->getConfig(),'user_id'=>auth()->id(),'envelope'=>$body,
                'print_effect'=>true,'print_gate'=>$gate],JSON_THROW_ON_ERROR));
            $process->setTimeout(35);$process->start();return $process;
        };
        $old=$start($gate);$recovery=null;
        try {
            $deadline=microtime(true)+15;
            while (!str_contains($old->getOutput(),"PRINT_CLAIMED\n")) {
                if (!$old->isRunning() || microtime(true)>$deadline) self::fail($old->getErrorOutput());
                usleep(10000);
            }
            $journal=$sale->fresh()->effects;
            self::assertSame('processing',$journal['print']['status']);
            $journal['print']['started_at']=time()-500;$sale->update(['effects'=>$journal]);
            $recovery=$start();
            self::assertSame(0,$recovery->wait(),$recovery->getErrorOutput());
            self::assertSame(1,$this->db->table('print_orders')->count());
            self::assertSame('confirmed',$sale->fresh()->effects['print']['status']);
            touch($gate);
            self::assertSame(0,$old->wait(),$old->getErrorOutput());
            self::assertSame(1,$this->db->table('print_orders')->count());
            self::assertSame(1,$this->db->table('sale_notes')->count());
            self::assertSame(1,$this->db->table('sale_note_payments')->count());
        } finally {
            touch($gate);
            foreach ([$old,$recovery] as $process) if ($process && $process->isRunning()) $process->stop();
            if (is_file($gate)) unlink($gate);
        }
    }

    public function test_effect_journal_recovers_pdf_without_repeating_sales_or_uncertain_deliveries(): void
    {
        $publisher=new class extends \Modules\Restaurant\Services\PrintPublisherService {
            public array $published=[];
            public function publish(\Modules\Restaurant\Models\PrintOrder $order): void { $this->published[]=$order->id; }
        };
        $this->app->instance(\Modules\Restaurant\Services\PrintPublisherService::class,$publisher);
        \Modules\Restaurant\Models\PrintOrder::observe(\Modules\Restaurant\Observers\PrintOrderObserver::class);
        $this->db->beginTransaction();
        \Modules\Restaurant\Models\PrintOrder::create(['name_printer'=>'ROLLED BACK','status'=>0,'pdf_b64'=>'']);
        self::assertCount(0,$publisher->published);
        $this->db->rollBack();
        self::assertCount(0,$publisher->published);
        self::assertSame(0,$this->db->table('print_orders')->count());
        $effects=new class extends SaleEffects {
            public array $calls=[];
            public bool $failPdf=true;
            protected function execute($sale,string $effect)
            {
                $this->calls[$effect]=($this->calls[$effect] ?? 0)+1;
                if ($effect === 'pdf' && $this->failPdf) { $this->failPdf=false;throw new \RuntimeException('Controlled renderer outage'); }
                if ($effect === 'print') {
                    $order=\Modules\Restaurant\Models\PrintOrder::create(['name_printer'=>'TEST ONLY','status'=>0,'pdf_b64'=>base64_encode('%PDF-Test fixture')]);
                    return ['auto_printed'=>true,'print_order_id'=>$order->id,'reason'=>null];
                }
                return null;
            }
        };
        $this->app->instance(SaleEffects::class,$effects);
        $operations=new SaleOperations();
        $body=$this->envelope('sale_note');
        $body['payload']['acciones']=['formato_pdf'=>'ticket','auto_print'=>true,'name_printer'=>'TEST ONLY'];
        $sale=$operations->submit($body);
        self::assertSame('confirmed',$sale->status);
        self::assertSame('failed',$sale->effects['pdf']['status']);
        $business=$this->snapshot();
        $replay=$operations->submit($body);
        self::assertSame('confirmed',$replay->effects['pdf']['status']);
        self::assertSame('confirmed',$replay->effects['print']['status']);
        self::assertTrue($replay->result['print']['auto_printed']);
        self::assertSame(1,$this->db->table('print_orders')->count());
        $operations->submit($body);
        self::assertSame(1,$this->db->table('print_orders')->count());
        self::assertSame(1,$effects->calls['print']);
        self::assertCount(1,$publisher->published);
        self::assertSame($business,$this->snapshot());
        $journal=$replay->effects;
        $journal['email']=['status'=>'processing','started_at'=>time()-500];
        $replay->update(['effects'=>$journal]);
        $effects->run($replay->fresh());
        self::assertSame('uncertain',$replay->fresh()->effects['email']['status']);
        self::assertArrayNotHasKey('email',$effects->calls);
        $effects->run($replay->fresh());
        self::assertArrayNotHasKey('email',$effects->calls);
        self::assertSame($business,$this->snapshot());
    }

    public function test_production_http_routes_authentication_replay_reassignment_and_pdf(): void
    {
        $schema=$this->db->getSchemaBuilder();
        $schema->create('websites',function ($t) { $t->bigIncrements('id');$t->string('uuid');$t->timestamps();$t->softDeletes(); });
        $schema->create('hostnames',function ($t) { $t->bigIncrements('id');$t->string('fqdn');$t->string('redirect_to')->nullable();$t->boolean('force_https')->default(false);$t->timestamp('under_maintenance_since')->nullable();$t->unsignedBigInteger('website_id');$t->timestamps();$t->softDeletes(); });
        $this->db->table('websites')->insert(['id'=>1,'uuid'=>$this->db->getDatabaseName()]);
        $hostname=$this->db->table('hostnames')->insertGetId(['fqdn'=>'exact-http.example.test','website_id'=>1]);
        $schema->create('plans',function ($t) { $t->increments('id');$t->string('name');$t->integer('limit_documents');$t->boolean('include_sale_notes_limit_documents')->default(false); });
        $schema->create('clients',function ($t) { $t->increments('id');$t->unsignedBigInteger('hostname_id');$t->string('name');$t->unsignedInteger('plan_id');$t->integer('whatsapp_messages_limit_override')->nullable(); });
        $this->db->table('plans')->insert(['id'=>1,'name'=>'VendeYa HTTP temporary','limit_documents'=>0]);
        $this->db->table('clients')->insert(['hostname_id'=>$hostname,'name'=>'VendeYa HTTP temporary','plan_id'=>1]);
        $token=bin2hex(random_bytes(30));
        $this->db->table('users')->where('id',$this->input['user_id'])->update(['api_token'=>$token,'email_verified_at'=>date('Y-m-d H:i:s')]);
        $this->db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $temp=sys_get_temp_dir().'/sab-sellnow-http-'.bin2hex(random_bytes(6));
        mkdir($temp,0700);
        foreach (['framework/views','framework/cache','framework/sessions','logs','tenant'] as $directory) mkdir($temp.'/'.$directory,0700,true);
        $private=$temp.'/fixture.json';
        file_put_contents($private,json_encode(['connection'=>$this->db->getConfig(),'storage'=>$temp],JSON_THROW_ON_ERROR));chmod($private,0600);
        $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
        if (!$socket) throw new \RuntimeException($error);
        $address=stream_socket_get_name($socket,false);fclose($socket);
        $environment=getenv();$environment['SAB_EXACT_HTTP_FIXTURE']=$private;$environment['APP_ENV']='testing';
        $server=proc_open([PHP_BINARY,'-S',$address,dirname(__DIR__).'/Support/exact_http_router.php'],
            [0=>['pipe','r'],1=>['file',$temp.'/server.log','a'],2=>['file',$temp.'/server.log','a']],$pipes,dirname(__DIR__,2),$environment);
        if (!is_resource($server)) throw new \RuntimeException('No se pudo iniciar HTTP temporal.');
        fclose($pipes[0]);
        try {
            for ($attempt=0;$attempt<100;$attempt++) {
                $probe=@stream_socket_client('tcp://'.$address,$errno,$error,0.1);
                if ($probe) { fclose($probe);break; } usleep(50000);
            }
            $call=static function (string $method,string $path,?array $payload=null,?string $authToken=null) use ($address,$token): array {
                $curl=curl_init('http://'.$address.$path);
                curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>60,CURLOPT_CUSTOMREQUEST=>$method,
                    CURLOPT_HTTPHEADER=>array_filter(['Host: exact-http.example.test','Accept: application/json','Content-Type: application/json',
                        $authToken === '' ? null : 'Authorization: Bearer '.($authToken ?? $token)])]);
                if ($payload !== null) curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($payload,JSON_THROW_ON_ERROR));
                $body=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_HTTP_CODE);$error=curl_error($curl);curl_close($curl);
                if ($body === false) throw new \RuntimeException($error);
                return [$status,json_decode($body,true),$body];
            };
            [$status]=$call('GET','/api/sellnow/offline-context',null,'');self::assertSame(401,$status);
            [$status,$context,$raw]=$call('GET','/api/sellnow/offline-context');
            self::assertSame(200,$status,substr($raw,0,1500));
            self::assertEquals($this->input['user_id'],$context['data']['user_id']);
            self::assertSame(1,$context['data']['capabilities']['offline_sales']);
            self::assertSame('exact-http.example.test',$context['data']['tenant']);
            self::assertCount(1,$context['data']['available_cash']);
            $envelope=$this->envelope();
            $today=date('Y-m-d');
            $envelope['recorded_at']=date('c');
            $envelope['payload']['fecha_de_emision']=$today;
            $envelope['payload']['fecha_de_vencimiento']=$today;
            foreach ($envelope['payload']['pagos'] as &$payment) $payment['fecha_de_emision']=$today;
            unset($payment);
            [$status,$body,$raw]=$call('POST','/api/sellnow/sales',$envelope);
            self::assertSame(200,$status,substr($raw,0,2500));
            self::assertSame('confirmed',$body['data']['status']);
            self::assertSame('not_requested',$body['data']['result']['print']['reason']);
            self::assertSame(0,$this->db->table('print_orders')->count());
            self::assertSame('confirmed',$body['data']['effects']['pdf']['status'],json_encode($body['data']['effects']));
            $id=$body['data']['result']['data']['id'];
            $snapshot=$this->snapshot();
            [$status,$again,$raw]=$call('POST','/api/sellnow/sales',$envelope);
            self::assertSame(200,$status,substr($raw,0,1500));
            self::assertSame($id,$again['data']['result']['data']['id']);
            self::assertSame($snapshot,$this->snapshot());
            [$status,$read]=$call('GET','/api/sellnow/sales/'.$envelope['client_sale_id']);
            self::assertSame(200,$status);self::assertSame('confirmed',$read['data']['status']);
            $changed=$envelope;$changed['payload']['informacion_adicional']='Changed';
            [$status]=$call('POST','/api/sellnow/sales',$changed);self::assertSame(409,$status);
            [$pdfStatus,,$pdfBody]=$call('GET','/api/documents/'.Document::findOrFail($id)->external_id.'/download-pdf/ticket');
            self::assertSame(200,$pdfStatus,substr($pdfBody,0,1500));
            self::assertStringStartsWith('%PDF-',$pdfBody);
            $note=$this->envelope('sale_note');
            $note['payload']['date_of_issue']=$today;
            foreach ($note['payload']['payments'] as &$payment) $payment['date_of_payment']=$today;
            unset($payment);
            $this->db->table('cash')->where('id',$note['cash_id'])->update(['state'=>false]);
            [$status,$blocked,$raw]=$call('POST','/api/sellnow/sales',$note);
            self::assertSame(422,$status,substr($raw,0,1500));self::assertSame('CASH_CLOSED',$blocked['code']);
            $cash=Cash::create(['user_id'=>auth()->id(),'state'=>true,'date_opening'=>$today,'time_opening'=>'12:00:00','beginning_balance'=>'0']);
            $this->db->transaction(fn()=>CashLedger::open($cash,[]));
            [$status,$ready,$raw]=$call('PATCH','/api/sellnow/sales/'.$note['client_sale_id'].'/cash',['cash_id'=>$cash->id]);
            self::assertSame(200,$status,substr($raw,0,1500));self::assertSame('pending',$ready['data']['status']);
            [$status,$confirmed,$raw]=$call('POST','/api/sellnow/sales',$note);
            self::assertSame(200,$status,substr($raw,0,2500));self::assertSame('confirmed',$confirmed['data']['status']);
            self::assertEquals($cash->id,$confirmed['data']['cash_id']);
            self::assertEquals($note['cash_id'],$confirmed['data']['original_cash_id']);
            self::assertSame('confirmed',$confirmed['data']['effects']['pdf']['status'],json_encode($confirmed['data']['effects']));
            $otherToken=bin2hex(random_bytes(30));
            $otherId=$this->db->table('users')->insertGetId(['name'=>'Other HTTP seller','email'=>'other-http@example.test','password'=>'not-a-login-hash','type'=>'seller','establishment_id'=>$this->input['establishment_id'],'api_token'=>$otherToken,'email_verified_at'=>date('Y-m-d H:i:s')]);
            $otherCash=Cash::create(['user_id'=>$otherId,'state'=>true,'date_opening'=>$today,'time_opening'=>'12:00:00','beginning_balance'=>'0']);
            $this->db->transaction(fn()=>CashLedger::open($otherCash,[]));
            [$status,$otherContext,$raw]=$call('GET','/api/sellnow/offline-context',null,$otherToken);
            self::assertSame(200,$status,substr($raw,0,1500));
            self::assertSame([$otherCash->id],array_column($otherContext['data']['available_cash'],'id'));
            [$status,$adminContext]=$call('GET','/api/sellnow/offline-context');
            self::assertSame(200,$status);self::assertCount(2,$adminContext['data']['available_cash']);
            [$status]=$call('GET','/api/sellnow/sales/'.$envelope['client_sale_id'],null,$otherToken);self::assertSame(404,$status);
            [$status]=$call('POST','/api/sellnow/sales',$envelope,$otherToken);self::assertSame(404,$status);
            self::assertSame(1,$this->db->table('documents')->count());
            self::assertSame(1,$this->db->table('sale_notes')->count());
        } finally {
            proc_terminate($server);proc_close($server);
            (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($temp);
        }
    }

    private function snapshot(): array
    {
        return ['documents'=>$this->db->table('documents')->count(),'notes'=>$this->db->table('sale_notes')->count(),
            'payments'=>$this->db->table('document_payments')->count()+$this->db->table('sale_note_payments')->count(),
            'links'=>$this->db->table('cash_documents')->count(),
            'stock'=>(float)$this->db->table('item_warehouse')->where('item_id',$this->input['items'][0]['item_id'])->value('stock')];
    }
}
