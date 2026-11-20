<?php
namespace Tests\Unit;

use App\Support\Exact\Decimal as D;

/** @group postgres-integration */
final class SalesLimitPostgresTest extends \PHPUnit\Framework\TestCase
{
    private $fixture;

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
        try {if ($this->fixture) $this->fixture->stop();}
        finally {parent::tearDown();}
    }

    private function setupLimit(bool $includeNotes=true, string $cap="0.007"): array
    {
        [$db,$app,$input]=(new \ReflectionMethod($this->fixture,'bootHkaFixture'))->invoke($this->fixture,true);
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $db->table('configurations')->update(['restrict_sales_limit'=>true]);
        $db->getSchemaBuilder()->create('clients',static function ($table) {
            $table->bigIncrements('id'); $table->bigInteger('hostname_id'); $table->date('start_billing_cycle');
        });
        foreach (['hostnames','plans'] as $table) {
            if (!$db->getSchemaBuilder()->hasTable($table)) $db->getSchemaBuilder()->create($table,static function ($schema) {
                $schema->bigIncrements('id'); $schema->timestamp('deleted_at')->nullable();
            });
        }
        $db->table('clients')->insert(['hostname_id'=>1,'start_billing_cycle'=>date('Y-m').'-01']);
        $hostname=new \Hyn\Tenancy\Models\Hostname(); $hostname->setRawAttributes(['id'=>1]);
        $environment=$this->getMockBuilder(\Hyn\Tenancy\Environment::class)->disableOriginalConstructor()->onlyMethods(['hostname'])->getMock();
        $environment->method('hostname')->willReturn($hostname); $app->instance(\Hyn\Tenancy\Environment::class,$environment);
        $plan=new \App\Models\System\Plan();
        $plan->setRawAttributes(['id'=>1,'name'=>'Temporal','sales_limit'=>$cap,'sales_unlimited'=>false,
            'include_sale_notes_sales_limit'=>$includeNotes,'calculation_snapshot'=>json_encode(D::snapshot(['sales_limit'=>$cap]),JSON_THROW_ON_ERROR)]);
        $provider=new class($app,$plan) extends \App\Providers\LockedEmissionProvider {
            public array $selected=[]; private $plan;
            public function __construct($app,$plan) {parent::__construct($app);$this->plan=$plan;}
            public function getClientPlan($columns) {$this->selected=$columns;return $this->plan;}
        };
        $provider->boot();
        $input['payments']=[]; $input['payment_condition_id']='02';
        $input['date_of_issue']=date('Y-m-d'); $input['invoice']['date_of_due']=date('Y-m-d');
        $input['items'][0]['quantity']='1'; $input['items'][0]['unit_price']='0.004';
        $input['items'][0]['affectation_igv_type_id']='20';
        return [$db,$input,$provider,$plan];
    }

    public function test_real_created_observer_validates_raw_totals_inside_the_invoice_transaction(): void
    {
        [$db,$input,$provider]=$this->setupLimit();
        $first=(new \App\CoreFacturalo\Facturalo())->save($input)->getDocument()->fresh();
        self::assertTrue(D::readRequired($first,'total')->isEqualTo('0.004'));
        self::assertContains('calculation_snapshot',$provider->selected);
        $input['external_id']=(string)\Illuminate\Support\Str::uuid();
        try {(new \App\CoreFacturalo\Facturalo())->save($input);self::fail('El límite debe usar 0.008, no subtotales redondeados.');}
        catch (\Exception $error) {self::assertStringContainsString('límite de ventas',$error->getMessage());}
        self::assertSame(1,$db->table('documents')->count());
        self::assertSame(1,$db->table('configurations')->value('quantity_documents'));
        self::assertSame(0,$db->transactionLevel());
    }

    public function test_sale_note_limit_includes_the_new_header_and_rolls_back_the_rejected_note(): void
    {
        [$db,$input]=$this->setupLimit();
        (new \App\CoreFacturalo\Facturalo())->save($input);
        $controller=new \App\Http\Controllers\Tenant\SaleNoteController();
        $data=$controller->mergeData(array_replace($input,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]]));
        try {\App\Models\Tenant\SaleNote::create($data);self::fail('La cabecera de la nota debe consumir el límite en su propia transacción.');}
        catch (\Exception $error) {self::assertStringContainsString('límite de ventas',$error->getMessage());}
        self::assertSame(0,$db->table('sale_notes')->count());
        self::assertSame(1,$db->table('documents')->count()); self::assertSame(0,$db->transactionLevel());
    }
    public function test_successful_inner_guard_survives_a_later_sibling_savepoint_rollback(): void
    {
        [$db,$input]=$this->setupLimit(); (new \App\CoreFacturalo\Facturalo())->save($input);
        $input['external_id']=(string)\Illuminate\Support\Str::uuid();
        try {
            $db->transaction(function () use ($db,$input) {
                $db->transaction(fn()=>(new \App\CoreFacturalo\Facturalo())->save($input));
                try {$db->transaction(fn()=>throw new \RuntimeException('Descartar sólo el hermano.'));}
                catch (\RuntimeException $error) {self::assertSame('Descartar sólo el hermano.',$error->getMessage());}
            });
            self::fail('El guard del primer savepoint debe impedir el commit que excede el límite.');
        } catch (\RuntimeException $error) {self::assertStringContainsString('límite de ventas',$error->getMessage());}
        self::assertSame(1,$db->table('documents')->count()); self::assertSame(0,$db->transactionLevel());
    }

    public function test_rolled_back_inner_write_discards_its_guard_before_an_unrelated_commit(): void
    {
        [$db,$input,,$plan]=$this->setupLimit(); (new \App\CoreFacturalo\Facturalo())->save($input);
        $plan->setExactValues(['sales_limit'=>'0.001']); $input['external_id']=(string)\Illuminate\Support\Str::uuid();
        $db->transaction(function () use ($db,$input) {
            try {
                $db->transaction(function () use ($input) {
                    (new \App\CoreFacturalo\Facturalo())->save($input);
                    throw new \RuntimeException('Descartar la factura interna.');
                });
            } catch (\RuntimeException $error) {self::assertSame('Descartar la factura interna.',$error->getMessage());}
            $db->table('configurations')->where('id',1)->update(['dashboard_goal_enabled'=>true]);
        });
        self::assertSame(1,$db->table('documents')->count());
        self::assertEquals(1,$db->table('configurations')->value('dashboard_goal_enabled')); self::assertSame(0,$db->transactionLevel());
    }

    public function test_manual_commit_validates_final_headers_and_releases_failed_guards(): void
    {
        [$db,$input,,$plan]=$this->setupLimit(); (new \App\CoreFacturalo\Facturalo())->save($input);
        $plan->setExactValues(['sales_limit'=>'0.003']); $input['external_id']=(string)\Illuminate\Support\Str::uuid();
        $db->beginTransaction();
        try {
            (new \App\CoreFacturalo\Facturalo())->save($input); self::assertSame(1,$db->transactionLevel());
            $db->commit(); self::fail('El commit manual debe validar antes de confirmar.');
        } catch (\RuntimeException $error) {self::assertStringContainsString('límite de ventas',$error->getMessage());}
        self::assertSame(0,$db->transactionLevel()); self::assertSame(1,$db->table('documents')->count());
        $db->transaction(fn()=>$db->table('configurations')->where('id',1)->update(['dashboard_goal_enabled'=>true]));
        self::assertEquals(1,$db->table('configurations')->value('dashboard_goal_enabled'));
    }

    public function test_reporting_and_billing_keep_their_separate_transaction_contracts(): void
    {
        [$db]=$this->setupLimit();
        foreach ([fn()=>\App\Services\Plans\SalesLimit::forEmission(null,null,true),
            fn()=>\App\Services\Plans\SalesLimit::defer(null,null,true,'1')] as $operation) {
            try {$operation();self::fail('Una validación de escritura requiere transacción.');}
            catch (\LogicException $error) {self::assertNotEmpty($error->getMessage());}
            self::assertSame(0,$db->transactionLevel());
        }
        try {
            \App\Support\Exact\ReadSnapshot::run($db,fn()=>\App\Services\Plans\SalesLimit::defer(null,null,true,'1'));
            self::fail('La validación de escritura requiere READ COMMITTED.');
        } catch (\LogicException $error) {self::assertStringContainsString('READ COMMITTED',$error->getMessage());}
        self::assertSame(0,$db->transactionLevel());
        $db->beginTransaction();
        try {
            (new \Modules\Dashboard\Helpers\DashboardData())->document_totals_globals();
            self::fail('Un reporte no debe tomar una transacción ajena.');
        } catch (\LogicException $error) {self::assertStringContainsString('lectura consistente',$error->getMessage());}
        finally {$db->rollBack();}
        self::assertSame(0,$db->transactionLevel());
    }

    public function test_actual_note_controller_uses_the_final_header_at_its_manual_commit(): void
    {
        [$db,$input,,$plan]=$this->setupLimit(true,'0.1');
        (new \App\CoreFacturalo\Facturalo())->save($input);
        $app=\Illuminate\Container\Container::getInstance();
        $url=$this->createMock(\Illuminate\Contracts\Routing\UrlGenerator::class);
        $url->method('to')->willReturn('http://limit.example.test'); $app->instance(\Illuminate\Contracts\Routing\UrlGenerator::class,$url);
        $controller=$this->getMockBuilder(\App\Http\Controllers\Tenant\SaleNoteController::class)
            ->onlyMethods(['createPdf','generalWriteErrorLog'])->getMock();
        $renders=[];
        $controller->method('createPdf')->willReturnCallback(function ($note) use (&$renders,$db) {$renders[]=[$note->id,$db->transactionLevel()];});
        $data=array_replace($input,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]]);
        $first=$controller->storeWithData($data,true);
        self::assertTrue($first['success'],$first['message'] ?? 'Nota de venta rechazada.');
        $note=\App\Models\Tenant\SaleNote::findOrFail($first['data']['id']);
        self::assertTrue(D::readRequired($note,'total')->isEqualTo('0.004'));
        $totals=(new \App\CoreFacturalo\ClientHelper())->getTotalsDocumentSaleNote(date('Y-m-d'),date('Y-m-d'),true);
        self::assertTrue(D::readRequired($totals,'total')->isEqualTo('0.008'));
        self::assertSame('0.01',(new \App\CoreFacturalo\ClientHelper())->getSalesTotal(date('Y-m-d'),date('Y-m-d'),$plan));
        $plan->setExactValues(['sales_limit'=>'0.01']);
        $second=$controller->storeWithData($data,true);
        self::assertFalse($second['success']); self::assertStringContainsString('límite de ventas',$second['message']);
        self::assertSame(1,$db->table('sale_notes')->count()); self::assertSame(0,$db->transactionLevel());
        $db->transaction(fn()=>null);
        self::assertSame([[$note->id,0]],$renders);
    }

    public function test_twenty_independent_writers_share_the_limit_at_final_commit(): void
    {
        [$db,$input]=$this->setupLimit(); $invoice=(new \App\CoreFacturalo\Facturalo())->save($input)->getDocument();
        $copy=(array)$db->table('documents')->where('id',$invoice->id)->first(); unset($copy['id']);
        $processes=[]; $db->beginTransaction();
        try {
            $db->table('configurations')->where('id',1)->lockForUpdate()->first();
            foreach (range(1,20) as $index) {
                $row=$copy; $row['external_id']=(string)\Illuminate\Support\Str::uuid(); $row['number']=2000+$index;
                $row['filename']='sales-limit-'.$index; $row['unique_filename']='sales-limit-'.$index;
                $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/sales_limit_concurrency_worker.php']);
                $process->setInput(json_encode(['connection'=>$db->getConfig(),'row'=>$row,'start'=>date('Y-m-d'),
                    'end'=>date('Y-m-d'),'limit'=>'0.011'],JSON_THROW_ON_ERROR));
                $process->setTimeout(90); $process->start(); $processes[]=$process;
            }
            $deadline=microtime(true)+60;
            foreach ($processes as $process) {
                while (!str_contains($process->getOutput(),"READY\n")) {
                    if (!$process->isRunning() || microtime(true)>$deadline) self::fail('No llegó a la barrera: '.$process->getErrorOutput());
                    usleep(10000);
                }
                self::assertTrue($process->isRunning());
            }
            $db->commit(); $allowed=0; $rejected=0;
            foreach ($processes as $process) {
                self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());
                $result=json_decode(substr($process->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR);
                self::assertSame(0,$result['transaction_level']);
                if ($result['allowed']) $allowed++; else $rejected++;
            }
            self::assertSame(1,$allowed); self::assertSame(19,$rejected); self::assertSame(2,$db->table('documents')->count());
            $totals=\App\Services\Plans\SalesLimit::read(date('Y-m-d'),date('Y-m-d'),true);
            self::assertTrue(D::readRequired($totals,'total')->isEqualTo('0.008'));
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            foreach ($processes as $process) if ($process->isRunning()) $process->stop();
        }
    }

    private function noteController(): \App\Http\Controllers\Tenant\SaleNoteController
    {
        $app=\Illuminate\Container\Container::getInstance();
        $url=$this->createMock(\Illuminate\Contracts\Routing\UrlGenerator::class);
        $url->method('to')->willReturn('http://limit.example.test'); $app->instance(\Illuminate\Contracts\Routing\UrlGenerator::class,$url);
        return $this->getMockBuilder(\App\Http\Controllers\Tenant\SaleNoteController::class)
            ->onlyMethods(['createPdf','generalWriteErrorLog'])->getMock();
    }

    public function test_rejected_default_note_does_not_render_a_pdf_before_its_limit_is_checked(): void
    {
        [$db,$input]=$this->setupLimit(); (new \App\CoreFacturalo\Facturalo())->save($input);
        $controller=$this->noteController(); $renders=0;
        $controller->method('createPdf')->willReturnCallback(function () use (&$renders) {$renders++;});
        $result=$controller->storeWithData(array_replace($input,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]]));
        self::assertFalse($result['success']); self::assertStringContainsString('límite de ventas',$result['message']);
        self::assertSame(0,$renders); self::assertSame(0,$db->table('sale_notes')->count()); self::assertSame(0,$db->transactionLevel());
    }

    public function test_nested_note_rendering_waits_for_the_actual_outer_commit(): void
    {
        [$db,$input]=$this->setupLimit(true,'0.1'); $controller=$this->noteController(); $levels=[];
        $controller->method('createPdf')->willReturnCallback(function () use (&$levels,$db) {$levels[]=$db->transactionLevel();});
        $db->transaction(function () use ($controller,$input,&$levels) {
            $result=$controller->storeWithData(array_replace($input,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]]));
            self::assertTrue($result['success'],$result['message'] ?? 'Nota rechazada.');
            self::assertSame([],$levels);
        });
        self::assertSame([0],$levels); self::assertSame(1,$db->table('sale_notes')->count()); self::assertSame(0,$db->transactionLevel());
    }

    public function test_an_early_write_check_retains_the_guard_for_later_finalization(): void
    {
        [$db,$input]=$this->setupLimit();
        try {
            $db->transaction(function () use ($db,$input) {
                $document=(new \App\CoreFacturalo\Facturalo())->save($input)->getDocument();
                $db->assertBeforeCommitValidations();
                $snapshot=$document->calculation_snapshot; $snapshot['values']['total']=D::fraction('0.008');
                $db->table('documents')->where('id',$document->id)->update(['total'=>'0.008',
                    'calculation_snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR)]);
            });
            self::fail('La comprobación previa no puede consumir la validación definitiva.');
        } catch (\RuntimeException $error) {self::assertStringContainsString('límite de ventas',$error->getMessage());}
        self::assertSame(0,$db->table('documents')->count()); self::assertSame(0,$db->transactionLevel());
    }

    public function test_serialization_retry_discards_the_failed_attempts_guard_and_writes(): void
    {
        [$db,$input,,$plan]=$this->setupLimit(); $attempts=0;
        $db->transaction(function () use ($db,$input,$plan,&$attempts) {
            $attempts++;
            $plan->setExactValues(['sales_limit'=>$attempts===1 ? '0.003' : '0.01']);
            (new \App\CoreFacturalo\Facturalo())->save($input);
            if ($attempts===1) $db->unprepared("DO \$\$ BEGIN RAISE EXCEPTION 'temporal retry' USING ERRCODE='40001'; END \$\$");
        },2);
        self::assertSame(2,$attempts); self::assertSame(1,$db->table('documents')->count());
        self::assertSame(1,$db->table('configurations')->value('quantity_documents')); self::assertSame(0,$db->transactionLevel());
    }

    public function test_same_guard_registered_across_nested_scopes_runs_once_at_outer_commit(): void
    {
        [$db]=$this->setupLimit(); $validated=[];
        $db->transaction(function () use ($db,&$validated) {
            $db->beforeCommitValidation('one-rule',function () use (&$validated) {$validated[]='original';});
            $db->transaction(function () use ($db,&$validated) {
                $db->beforeCommitValidation('one-rule',function () use (&$validated) {$validated[]='duplicate';});
            });
            self::assertSame([],$validated);
        });
        self::assertSame(['original'],$validated); self::assertSame(0,$db->transactionLevel());
    }

    public function test_subscription_batch_restores_its_pdf_when_a_later_note_exceeds_the_quota(): void
    {
        [$db,$input]=$this->setupLimit();
        (new \App\CoreFacturalo\Facturalo())->save($input);
        $app=\Illuminate\Container\Container::getInstance();
        $url=$this->createMock(\Illuminate\Contracts\Routing\UrlGenerator::class);
        $url->method('to')->willReturn('http://limit.example.test');
        $app->instance(\Illuminate\Contracts\Routing\UrlGenerator::class,$url);
        $controller=$this->getMockBuilder(\Modules\Suscription\Services\SubscriptionSaleNoteController::class)
            ->onlyMethods(['createPdf','generalWriteErrorLog'])->getMock();
        $rendered=[]; $disk=\Illuminate\Support\Facades\Storage::disk('tenant');
        $controller->method('createPdf')->willReturnCallback(function ($note,$format,$filename) use ($controller,&$rendered,$db,$disk) {
            self::assertTrue(D::readRequired($note,'total')->isEqualTo('0.004'));
            $path='sale_note/'.$filename.'.pdf';
            $controller->uploadFile($filename,'%PDF-compensated-subscription','sale_note');
            self::assertTrue($disk->exists($path));
            $rendered[]=['path'=>$path,'level'=>$db->transactionLevel()];
        });
        $app->instance(\Modules\Suscription\Services\SubscriptionSaleNoteController::class,$controller);
        $subscription=$db->table('suscription_plans')->insertGetId(['name'=>'Cupo temporal','description'=>'Prueba','total'=>'0.004']);
        $request=new \Modules\Suscription\Http\Requests\PaymentsSuscriptionRequest();
        $request->merge(array_replace($input,[
            'suscription_plan_id'=>$subscription,'cat_period_id'=>1,'quantity_period'=>2,
            'start_date'=>\Carbon\Carbon::today()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d'),
            'parent_customer_id'=>$input['customer_id'],'parent_customer'=>$input['customer'],'children_customer'=>[],
        ]));
        try {
            (new \Modules\Suscription\Http\Controllers\PaymentsSuscriptionController())->store($request);
            self::fail('La segunda nota debe exceder el cupo junto a la factura existente.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertStringContainsString('límite de ventas',$error->errors()['sale_notes'][0]);
        }
        self::assertCount(1,$rendered);
        self::assertSame(2,$rendered[0]['level']);
        self::assertFalse($disk->exists($rendered[0]['path']));
        self::assertSame(0,$db->table('user_rel_suscription_plans')->count());
        self::assertSame(0,$db->table('sale_notes')->count());
        self::assertSame(1,$db->table('documents')->count());
        self::assertTrue(D::readRequired($db->table('documents')->first(),'total')->isEqualTo('0.004'));
        self::assertSame(0,$db->transactionLevel());
        $db->transaction(static function () {});
        self::assertCount(1,$rendered);
    }

}
