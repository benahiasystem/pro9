<?php
namespace Modules\Accounting\Tests;
use PHPUnit\Framework\TestCase;
use Illuminate\Foundation\Application;
use Illuminate\Config\Repository;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\DateFactory;
use Illuminate\Support\Str;
use Hyn\Tenancy\Database\Connection as TenancyConnection;
use Modules\Accounting\Domain\{CompanyContext,ApiProblem,Exact};
use Modules\Accounting\Models\{Company,Account,JournalEntry,JournalLine,Period,SourceDocument,SourceVersion};
use Modules\Accounting\Services\{CompanyService,EntryService,ReportService};
use Modules\Accounting\Support\TenantDB as DB;
use App\Models\Tenant\User;

final class CoreIntegrationTest extends TestCase {
 private static Application $application;
 private User $admin;private Company $a;private Company $b;
 public static function setUpBeforeClass():void {
  parent::setUpBeforeClass();
  if(getenv('ACCOUNTING_ISOLATED_TESTS')!=='1'||getenv('ACCOUNTING_TEST_DATABASE')!=='accounting_module_test'||getenv('ACCOUNTING_TEST_HOST')!=='accounting-db')
   throw new \RuntimeException('Esta suite exige la base y red aisladas del módulo.');
  $root=dirname(__DIR__,3);$app=new Application($root);
  $config=new Repository(['app'=>['env'=>'testing','timezone'=>'America/Caracas'],'accounting'=>['demo'=>true],'database'=>['default'=>'tenant']]);
  $app->instance('config',$config);$app->instance('date',new DateFactory());
  $events=new Dispatcher($app);$app->instance('events',$events);
  $manager=new Manager($app);$manager->addConnection(['driver'=>'mysql','host'=>'accounting-db','port'=>3306,'database'=>'accounting_module_test','username'=>getenv('ACCOUNTING_TEST_USERNAME'),'password'=>getenv('ACCOUNTING_TEST_PASSWORD'),'charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','prefix'=>'','strict'=>true],'tenant');
  $manager->getDatabaseManager()->setDefaultConnection('tenant');$manager->setEventDispatcher($events);$manager->setAsGlobal();$manager->bootEloquent();$app->instance('db',$manager->getDatabaseManager());
  $tenant=new class extends TenancyConnection {public function __construct(){}public function tenantName():string{return 'tenant';}};
  $app->instance(TenancyConnection::class,$tenant);$app->singleton(CompanyContext::class,fn()=>new CompanyContext());
  Facade::clearResolvedInstances();Facade::setFacadeApplication($app);self::$application=$app;
  $schema=DB::getSchemaBuilder();
  if(!$schema->hasTable('users'))$schema->create('users',function($t){$t->increments('id');$t->string('name');$t->string('email')->unique();$t->string('password');$t->string('type')->default('admin');$t->boolean('active')->default(true);$t->boolean('locked')->default(false);$t->string('api_token')->nullable();$t->timestamps();});
  $schema->disableForeignKeyConstraints();foreach(DB::select('SHOW TABLES') as $record){$table=array_values((array)$record)[0];if(str_starts_with($table,'accounting_'))$schema->drop($table);}$schema->enableForeignKeyConstraints();
  $migration=require dirname(__DIR__).'/Database/Migrations/2026_11_11_000100_create_accounting_module.php';$migration->up();
  $security=require dirname(__DIR__).'/Database/Migrations/2026_11_11_000200_create_accounting_step_up_auth.php';$security->up();
  if(!DB::table('users')->where('email','contabilidad-test@example.invalid')->exists())DB::table('users')->insert(['name'=>'Contador de prueba aislada','email'=>'contabilidad-test@example.invalid','password'=>password_hash('Fictitious-Accounting-Test-Only',PASSWORD_BCRYPT),'type'=>'admin','active'=>true,'locked'=>false,'api_token'=>str_repeat('t',60),'created_at'=>now(),'updated_at'=>now()]);
 }
 protected function setUp():void {
  parent::setUp();DB::beginTransaction();
  $this->admin=User::without('establishment')->where('email','contabilidad-test@example.invalid')->firstOrFail();
  $organization=(string)Str::uuid();DB::table('accounting_organizations')->insert(['id'=>$organization,'name'=>'Pruebas aisladas','created_at'=>now(),'updated_at'=>now()]);
  $service=app(CompanyService::class);
  $this->a=$service->create(['name'=>'Empresa A de prueba','tax_id'=>'J-00000000-0','approval_mode'=>'self_control'],$organization,$this->admin->id);
  $this->b=$service->create(['name'=>'Empresa B de prueba','tax_id'=>'J-00000000-1','approval_mode'=>'self_control'],$organization,$this->admin->id);
  foreach([$this->a,$this->b] as $co)app(CompanyContext::class)->run($co,fn()=>$service->defaultChart());
  app(CompanyContext::class)->set($this->a);
 }
 protected function tearDown():void {DB::rollBack();app(CompanyContext::class)->clear();parent::tearDown();}
 private function draft():JournalEntry {
  $accounts=Account::get()->keyBy('code');
  return app(EntryService::class)->create(['accounting_date'=>now('America/Caracas')->format('Y-m-d'),'concept'=>'Movimiento de prueba aislada','lines'=>[
   ['account_id'=>$accounts['1.1.02']->id,'debit'=>'100.00','credit'=>'0.00'],
   ['account_id'=>$accounts['3.1']->id,'debit'=>'0.00','credit'=>'100.00']
  ]],$this->admin->id);
 }
 private function approved():JournalEntry {$s=app(EntryService::class);$e=$this->draft();$e=$s->transition($e->id,$e->version,$this->admin->id,'submit');return $s->transition($e->id,$e->version,$this->admin->id,'approve','Autocontrol explícito del caso de prueba.');}
 public function test_decimal_inputs_preserve_precision_and_reject_floats():void {$this->assertSame('0.30',Exact::total(['0.10','0.20']));$this->expectException(ApiProblem::class);Exact::of(0.1);}
 public function test_company_scope_prevents_reading_another_book():void {$e=$this->draft();app(CompanyContext::class)->set($this->b);$this->assertNull(JournalEntry::find($e->id));}
 public function test_composite_foreign_key_prevents_cross_company_account():void {
  $foreign=app(CompanyContext::class)->run($this->b,fn()=>Account::where('posting',true)->firstOrFail());
  $e=$this->draft();$this->expectException(\Illuminate\Database\QueryException::class);DB::table('accounting_journal_lines')->where('journal_entry_id',$e->id)->update(['account_id'=>$foreign->id]);
 }
 public function test_posting_retries_have_a_single_effect():void {
  $e=$this->approved();$s=app(EntryService::class);$one=$s->post($e->id,$e->version,$this->admin->id,'retry-module-test-001');$two=$s->post($e->id,$e->version,$this->admin->id,'retry-module-test-001');$this->assertSame($one['reference'],$two['reference']);$this->assertSame(1,JournalEntry::whereNotNull('posted_at')->count());
 }
 public function test_database_rejects_changes_to_posted_lines():void {
  $e=$this->approved();app(EntryService::class)->post($e->id,$e->version,$this->admin->id,'posted-immutable-001');$this->expectException(\Illuminate\Database\QueryException::class);DB::table('accounting_journal_lines')->where('journal_entry_id',$e->id)->update(['debit'=>'200.00']);
 }
 public function test_database_rejects_moving_a_draft_line_into_a_posted_entry():void {
  $posted=$this->approved();app(EntryService::class)->post($posted->id,$posted->version,$this->admin->id,'posted-move-target-001');
  $draft=$this->draft();$line=JournalLine::where('journal_entry_id',$draft->id)->firstOrFail();
  $this->expectException(\Illuminate\Database\QueryException::class);
  DB::table('accounting_journal_lines')->where('id',$line->id)->update(['journal_entry_id'=>$posted->id,'position'=>99]);
 }
 public function test_ledger_page_balance_includes_previous_pages():void {
  $s=app(EntryService::class);
  for($i=0;$i<3;$i++){$e=$this->approved();$s->post($e->id,$e->version,$this->admin->id,'ledger-pagination-'.$i);}
  $report=app(ReportService::class)->build('ledger',['account_id'=>Account::where('code','1.1.02')->firstOrFail()->id,'per_page'=>1,'page'=>3]);
  $this->assertCount(1,$report['rows']);$this->assertSame('300.00',$report['rows'][0]['balance']);$this->assertSame(3,$report['total']);
 }
 public function test_closed_period_cannot_receive_an_entry():void {
  $e=$this->approved();$p=Period::findOrFail($e->period_id);$p->status='closed';$p->save();
  try{app(EntryService::class)->post($e->id,$e->version,$this->admin->id,'closed-period-test-001');$this->fail('Debe rechazar el período cerrado.');}catch(ApiProblem $p){$this->assertSame('PERIOD_CLOSED',$p->errorCode);}
 }
 public function test_reversal_retains_original_and_cancels_ledger_effect():void {
  $s=app(EntryService::class);$e=$this->approved();$s->post($e->id,$e->version,$this->admin->id,'module-original-001');
  $r=$s->prepareReversal($e->id,now('America/Caracas')->format('Y-m-d'),'Reverso de prueba',$this->admin->id);$r=$s->transition($r->id,$r->version,$this->admin->id,'submit');$r=$s->transition($r->id,$r->version,$this->admin->id,'approve','Autocontrol del reverso de prueba.');$s->post($r->id,$r->version,$this->admin->id,'module-reversal-001');
  $report=app(ReportService::class)->build('ledger',['account_id'=>Account::where('code','3.1')->firstOrFail()->id]);$this->assertSame('0.00',$report['totals']['balance']);$this->assertNotNull(JournalEntry::findOrFail($e->id)->posted_at);
 }
 public function test_stale_version_is_rejected_without_modification():void {$e=$this->draft();$this->expectException(ApiProblem::class);app(EntryService::class)->version($e,$e->version+1);}
 public function test_approval_is_invalidated_after_editing():void {
  $e=$this->approved();$s=app(EntryService::class);$d=$s->resource($e);
  $e=$s->edit($e->id,['accounting_date'=>$d['accounting_date'],'concept'=>'Concepto revisado','lines'=>$d['lines']],$e->version,$this->admin->id);
  $this->assertSame('draft',$e->status);$this->assertNull($e->approved_by);$this->assertNull($e->approved_version);
 }
 private function source():SourceDocument {
  $date=now('America/Caracas')->format('Y-m-d');
  return SourceDocument::create(['kind'=>'sale_invoice','number'=>'FV-PRUEBA','issue_date'=>$date,'fiscal_date'=>$date,'currency'=>'VES','exchange_rate'=>'1','exchange_source'=>'Prueba aislada','exchange_date'=>$date,'amounts'=>['total'=>'100.00'],'fiscal_amounts'=>[],'status'=>'validated']);
 }
 public function test_changed_source_blocks_posting_of_an_approved_version():void {
  $source=$this->source();$s=app(EntryService::class);$e=$this->draft();$e->source_document_id=$source->id;$e->source_revision=1;$e->save();
  $e=$s->transition($e->id,$e->version,$this->admin->id,'submit');$e=$s->transition($e->id,$e->version,$this->admin->id,'approve','Autocontrol del caso de prueba.');
  $source->source_revision=2;$source->status='changed';$source->save();
  try{$s->post($e->id,$e->version,$this->admin->id,'source-revalidation-test');$this->fail('Debe exigir validar la nueva versión de origen.');}
  catch(ApiProblem $problem){$this->assertSame('SOURCE_REVALIDATION_REQUIRED',$problem->errorCode);$this->assertNull(JournalEntry::findOrFail($e->id)->posted_at);}
 }
 public function test_source_evidence_cannot_be_rewritten():void {
  $source=$this->source();$version=SourceVersion::create(['source_document_id'=>$source->id,'revision'=>1,'snapshot'=>['total'=>'100.00'],'hash'=>hash('sha256','100.00')]);
  $this->expectException(\Illuminate\Database\QueryException::class);DB::table('accounting_source_versions')->where('id',$version->id)->update(['snapshot'=>json_encode(['total'=>'1.00'])]);
 }
 public function test_foreign_currency_conversion_must_match_the_book():void {
  $e=$this->draft();JournalLine::where('journal_entry_id',$e->id)->update(['original_currency'=>'USD','original_amount'=>'1.00','exchange_rate'=>'99.00']);
  try{app(EntryService::class)->transition($e->id,$e->version,$this->admin->id,'submit');$this->fail('Debe detectar la conversión inconsistente.');}
  catch(ApiProblem $problem){$this->assertSame('CONVERSION_MISMATCH',$problem->errorCode);}
 }
}
