<?php

namespace Tests\Unit;

use App\Models\Tenant\Item;
use App\Support\Exact\Decimal as D;
use Modules\Dashboard\Widgets\{WidgetDataset,WidgetContext,WidgetSourceRegistry,CallbackWidgetSource};
use Modules\Expense\Widgets\ExpenseTotalsSource;

/** @group postgres-integration */
final class WidgetSnapshotPostgresTest extends \PHPUnit\Framework\TestCase
{
    private $fixture;
    private $db;
    private $app;
    private array $input;
    private array $reported=[];

    protected function setUp(): void
    {
        $this->fixture=new class extends FiscalEmissionSchemaTest {
            public function start(): void {parent::setUp();}
            public function stop(): void {parent::tearDown();}
        };
        $this->fixture->start();
        [$this->db,$this->app,$this->input]=(new \ReflectionMethod($this->fixture,'bootHkaFixture'))->invoke($this->fixture,false);
        $item=Item::findOrFail($this->input['items'][0]['item_id']);
        $item->setExactValues(['purchase_unit_price'=>'1']);$item->saveQuietly();
        $handler=$this->createMock(\Illuminate\Contracts\Debug\ExceptionHandler::class);
        $handler->method('report')->willReturnCallback(function ($error) {$this->reported[]=get_class($error).': '.$error->getMessage();});
        $this->app->instance(\Illuminate\Contracts\Debug\ExceptionHandler::class,$handler);
    }

    protected function tearDown(): void
    {
        try {if ($this->fixture) $this->fixture->stop();}
        finally {parent::tearDown();}
    }

    private function expense(string $date, string $amount, string $currency='VES', string $rate='1'): int
    {
        return $this->db->table('expenses')->insertGetId(['user_id'=>$this->input['user_id'],'fiscal_environment'=>'demo',
            'expense_type_id'=>$this->db->table('expense_types')->value('id'),
            'expense_reason_id'=>$this->db->table('expense_reasons')->value('id'),
            'establishment_id'=>$this->input['establishment_id'],'supplier_id'=>$this->input['customer_id'],
            'currency_type_id'=>$currency,'external_id'=>(string)\Illuminate\Support\Str::uuid(),'state_type_id'=>'05',
            'date_of_issue'=>$date,'time_of_issue'=>'12:00:00','supplier'=>json_encode($this->input['customer'],JSON_THROW_ON_ERROR),
            'exchange_rate_sale'=>$rate,'total'=>D::represent($amount),
            'calculation_snapshot'=>json_encode(D::snapshot(['total'=>$amount,'exchange_rate_sale'=>$rate]),JSON_THROW_ON_ERROR)]);
    }

    private function source(string $key, callable $resolve, ?callable $visible=null): CallbackWidgetSource
    {
        return new CallbackWidgetSource(['key'=>$key,'label'=>$key,'module'=>'tests','module_label'=>'Tests'],$resolve,$visible);
    }

    private function itemValue(): \Brick\Math\BigRational
    {
        return D::readRequired(Item::findOrFail($this->input['items'][0]['item_id']),'purchase_unit_price');
    }

    public function test_expense_widget_uses_snapshot_totals_before_rounding_daily_points(): void
    {
        $this->expense('2026-11-09','0.004');
        $this->expense('2026-11-10','1/3','USD','0.01500000');$this->expense('2026-11-11','1/3','USD','0.01500000');
        $result=(new ExpenseTotalsSource())->resolve(new WidgetContext(['period'=>'between_dates',
            'date_start'=>'2026-11-10','date_end'=>'2026-11-11']))->toArray();
        self::assertSame(0.01,$result['totals']['current']);
        self::assertTrue(D::readRequired($result['totals'],'current')->isEqualTo('0.01'));
        self::assertTrue(D::readRequired($result['totals'],'previous')->isEqualTo('0.004'));
        self::assertSame(150.0,$result['totals']['delta']);
        self::assertSame([['n'=>'1','d'=>'200'],['n'=>'1','d'=>'200']],$result['series'][0]['data_fractions']);
    }

    public function test_expense_periods_share_one_snapshot_and_reject_foreign_transactions(): void
    {
        $previous=$this->expense('2026-11-09','1');$this->expense('2026-11-10','1');
        $this->app['config']->set('database.connections.widget_writer',$this->db->getConfig());$writer=$this->app['db']->connection('widget_writer');$changed=false;
        $this->db->listen(function ($event) use (&$changed,$writer,$previous) {
            if (!$changed && str_contains($event->sql,'from "expenses"')) {
                $changed=true;$writer->table('expenses')->where('id',$previous)->update(['total'=>'9',
                    'calculation_snapshot'=>json_encode(D::snapshot(['total'=>'9','exchange_rate_sale'=>'1']),JSON_THROW_ON_ERROR)]);
            }
        });
        try {
            $context=new WidgetContext(['period'=>'date','date_start'=>'2026-11-10']);$sut=new ExpenseTotalsSource();
            $during=$sut->resolve($context)->toArray();self::assertTrue($changed);
            self::assertSame(1.0,$during['totals']['previous']);
            self::assertSame(9.0,$sut->resolve($context)->toArray()['totals']['previous']);self::assertSame(0,$this->db->transactionLevel());
            $this->db->beginTransaction();
            try {$this->expectException(\LogicException::class);$sut->resolve($context);}
            finally {$this->db->rollBack();}
        } finally {$writer->disconnect();}
    }

    public function test_all_widgets_in_a_batch_share_the_same_snapshot(): void
    {
        $this->app['config']->set('database.connections.widget_writer',$this->db->getConfig());$writer=$this->app['db']->connection('widget_writer');$changed=false;
        $registry=new WidgetSourceRegistry();
        $registry->register($this->source('first',function () use ($writer,&$changed) {
            $value=$this->itemValue();
            if (!$changed) {
                $changed=true;$item=Item::findOrFail($this->input['items'][0]['item_id']);$snapshot=$item->calculation_snapshot;
                $snapshot['values']['purchase_unit_price']=D::fraction('9');
                $writer->table('items')->where('id',$item->id)->update(['purchase_unit_price'=>'9','calculation_snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR)]);
            }
            return WidgetDataset::make()->totals(D::represent($value));
        }));
        $registry->register($this->source('second',fn()=>WidgetDataset::make()->totals(D::represent($this->itemValue()))));
        try {
            $during=$registry->resolveBatch([['source'=>'first'],['source'=>'second']],[]);
            self::assertSame([],$this->reported,implode("\n",$this->reported));
            self::assertSame(1.0,$during['first']['totals']['current']);self::assertSame(1.0,$during['second']['totals']['current']);
            self::assertSame(9.0,$registry->resolveBatch([['source'=>'second']],[])['second']['totals']['current']);
            self::assertSame(0,$this->db->transactionLevel());
        } finally {$writer->disconnect();}
    }

    public function test_a_failed_sql_widget_rolls_back_its_savepoint_and_keeps_the_batch_usable(): void
    {
        $handler=$this->createMock(\Illuminate\Contracts\Debug\ExceptionHandler::class);$handler->expects(self::once())->method('report');
        $this->app->instance(\Illuminate\Contracts\Debug\ExceptionHandler::class,$handler);
        $registry=new WidgetSourceRegistry();$registry->register($this->source('good',fn()=>WidgetDataset::make()->totals(D::represent($this->itemValue()))));
        $registry->register($this->source('bad',function () {$this->db->statement('SELECT 1/0');return WidgetDataset::make();}));
        $result=$registry->resolveBatch([['source'=>'good','key'=>'before'],['source'=>'bad'],['source'=>'good','key'=>'after']],[]);
        self::assertSame(['error'=>'resolve_failed'],$result['bad']);
        self::assertSame(1.0,$result['before']['totals']['current']);self::assertSame(1.0,$result['after']['totals']['current']);
        self::assertSame(0,$this->db->transactionLevel());
    }

    public function test_a_visibility_query_error_is_isolated_from_the_following_widget(): void
    {
        $handler=$this->createMock(\Illuminate\Contracts\Debug\ExceptionHandler::class);$handler->expects(self::once())->method('report');
        $this->app->instance(\Illuminate\Contracts\Debug\ExceptionHandler::class,$handler);
        $registry=new WidgetSourceRegistry();
        $registry->register($this->source('hidden',fn()=>WidgetDataset::make(),function () {$this->db->statement('SELECT 1/0');return true;}));
        $registry->register($this->source('good',fn()=>WidgetDataset::make()->totals(D::represent($this->itemValue()))));
        $result=$registry->resolveBatch([['source'=>'hidden'],['source'=>'good']],[]);
        self::assertSame(['error'=>'resolve_failed'],$result['hidden']);self::assertSame(1.0,$result['good']['totals']['current']);
        self::assertSame(0,$this->db->transactionLevel());
    }

    public function test_a_foreign_transaction_is_rejected_before_resolving_the_batch(): void
    {
        $registry=new WidgetSourceRegistry();$registry->register($this->source('good',fn()=>WidgetDataset::make()->totals(D::represent($this->itemValue()))));
        $this->db->beginTransaction();
        try {$this->expectException(\LogicException::class);$registry->resolveBatch([['source'=>'good']],[]);}
        finally {$this->db->rollBack();}
    }

    public function test_all_default_and_expense_sources_resolve_the_real_temporary_tenant_producers(): void
    {
        $this->db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $input=array_replace($this->input,['payments'=>[],'payment_condition_id'=>'02',
            'currency_type_id'=>'VES','exchange_rate_sale'=>'0.01500000']);
        $input['items'][0]=array_replace($input['items'][0],['quantity'=>'1','unit_price'=>'0.005',
            'affectation_igv_type_id'=>'20','price_input'=>['amount'=>'0.005','currency_type_id'=>'VES','includes_iva'=>true]]);
        (new \App\CoreFacturalo\Facturalo())->save($input);
        $this->expense($input['date_of_issue'],'1/3','USD','0.01500000');
        $registry=new WidgetSourceRegistry();$registry->register(new ExpenseTotalsSource());$requests=[];
        foreach (\Modules\Dashboard\Widgets\DefaultWidgetSources::all() as $source) $requests[]=['source'=>$source->key()];
        $requests[]=['source'=>'gastos.totales'];
        $result=$registry->resolveBatch($requests,['period'=>'date','date_start'=>$input['date_of_issue']]);
        self::assertSame([],$this->reported,implode("\n",$this->reported));self::assertCount(21,$result);
        foreach ($result as $key=>$dataset) {
            self::assertArrayNotHasKey('error',$dataset,$key);
            self::assertSame(['unit','labels','series','breakdown','totals','meta'],array_keys($dataset));
        }
        foreach (['ventas.ventas_totales','ventas.periodo','gastos.totales'] as $key) {
            self::assertTrue(D::readRequired($result[$key]['totals'],'current')->isEqualTo('0.005'),$key);
        }
        self::assertSame(['n'=>'1','d'=>'200'],$result['ventas.top_productos']['breakdown']['values_fractions'][0]);
        self::assertSame(['missing'=>['error'=>'source_not_found']],$registry->resolveBatch([['source'=>'missing']],[]));
        self::assertSame(0,$this->db->transactionLevel());

        // Persist only public report DTOs from this marked temporary cluster for the compiled UI test.
        \Tests\Support\PostgresTestEnvironment::assertServer($this->db);
        $source=\Tests\Support\PostgresTestEnvironment::source();$run=getenv('SAB_POSTGRES_RUN_ID');
        $filters=['period'=>'date','date_start'=>$input['date_of_issue']];$context=new WidgetContext($filters);
        $export=['version'=>1,'run_id'=>$run,'source_sha256'=>$source['sha256'],'date'=>$input['date_of_issue'],
            'catalog'=>$registry->catalog(),'datasets'=>$result,'establishment_id'=>$input['establishment_id'],
            'api'=>['/dashboard/global-data'=>$context->globalData(),'/dashboard/sales-week'=>$context->salesWeek(),
                '/dashboard/cash-flow'=>$context->cashFlow(),'/dashboard/payment-methods'=>$context->paymentMethods(),
                '/dashboard/debtors'=>$context->debtors(),'/dashboard/low-stock'=>$context->lowStock(),
                '/dashboard/month-goal'=>$context->monthGoal(),'/dashboard/data'=>['data'=>$context->data()],
                '/dashboard/data_aditional'=>['data'=>$context->dataAditional()],'/dashboard/utilities'=>['data'=>$context->utilities()]]];
        $kpi=new \Modules\Dashboard\Helpers\DashboardKpi();
        $range=['date_start'=>$input['date_of_issue'],'date_end'=>$input['date_of_issue'],'compare'=>'previous_period'];
        $export['api']['/dashboard/kpi']=['data'=>$kpi->data($range)];
        $export['api']['/dashboard/monthly-comparison']=['data'=>$kpi->monthlyComparison($range+['period'=>'today'])];
        $export['api']['/dashboard/sales-growth']=['data'=>$kpi->salesGrowth($range)];
        $json=json_encode($export,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $path=base_path('informes/evidencias/postgresql_17_implementacion/resultados/'.$run.'-dashboard-fixture.json');
        self::assertSame(strlen($json),file_put_contents($path,$json));
    }
}
