<?php

namespace Tests\Unit;

use App\Support\Exact\Decimal as D;
use Carbon\Carbon;
use Modules\Dashboard\Helpers\DashboardKpi;
use Modules\Dashboard\Widgets\WidgetContext;

/** @group postgres-integration */
final class DashboardKpiPostgresTest extends \PHPUnit\Framework\TestCase
{
    private $fixture;
    private $db;
    private $app;
    private array $input;
    private $previousNow;

    protected function setUp(): void
    {
        $this->previousNow=Carbon::getTestNow();
        $this->fixture=new class extends FiscalEmissionSchemaTest {
            public function start(): void {parent::setUp();}
            public function stop(): void {parent::tearDown();}
        };
        $this->fixture->start();
        [$this->db,$this->app,$this->input]=(new \ReflectionMethod($this->fixture,'bootHkaFixture'))->invoke($this->fixture,false);
        $this->db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        Carbon::setTestNow('2026-11-11 12:00:00');
    }

    protected function tearDown(): void
    {
        try {if ($this->fixture) $this->fixture->stop();}
        finally {Carbon::setTestNow($this->previousNow);parent::tearDown();}
    }

    private function invoice(string $price, string $date, bool $periodicUsd=false)
    {
        $input=array_replace($this->input,['external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'date_of_issue'=>$date,'payments'=>[],'payment_condition_id'=>'02',
            'currency_type_id'=>$periodicUsd ? 'USD' : 'VES','exchange_rate_sale'=>$periodicUsd ? '0.01500000' : '1']);
        $input['invoice']['date_of_due']=$date;
        $input['items'][0]=array_replace($input['items'][0],['quantity'=>'1','unit_price'=>$price,'affectation_igv_type_id'=>'20']);
        if ($periodicUsd) $input['items'][0]['price_input']=['amount'=>'0.005','currency_type_id'=>'VES','includes_iva'=>true];
        return (new \App\CoreFacturalo\Facturalo())->save($input)->getDocument()->fresh();
    }

    private function purchase($total, $perception, string $date, string $currency='VES', string $rate='1'): int
    {
        return $this->db->table('purchases')->insertGetId(['user_id'=>$this->input['user_id'],
            'external_id'=>(string)\Illuminate\Support\Str::uuid(),'establishment_id'=>$this->input['establishment_id'],
            'fiscal_environment'=>'demo','state_type_id'=>'01','group_id'=>'01','document_type_id'=>'01',
            'series'=>'FP01','number'=>$this->db->table('purchases')->count()+1,'date_of_issue'=>$date,'time_of_issue'=>'12:00:00',
            'supplier_id'=>$this->input['customer_id'],'supplier'=>json_encode($this->input['customer'],JSON_THROW_ON_ERROR),
            'currency_type_id'=>$currency,'exchange_rate_sale'=>$rate,'total'=>D::represent($total),
            'calculation_snapshot'=>json_encode(D::snapshot(['total'=>$total,'exchange_rate_sale'=>$rate]),JSON_THROW_ON_ERROR)]);
    }

    public function test_variation_uses_original_values_even_when_both_display_as_zero(): void
    {
        $this->invoice('0.003','2026-11-09');$this->invoice('0.004','2026-11-10');
        $result=(new DashboardKpi())->data(['date_start'=>'2026-11-10','date_end'=>'2026-11-10','compare'=>'previous_period']);
        self::assertSame(33.3,$result['month_sales_variation']);
        self::assertSame('0.00',$result['month_sales']);
        self::assertTrue(D::readRequired($result,'month_sales')->isEqualTo('0.004'));
        self::assertTrue(D::readRequired($result,'month_sales_variation')->isEqualTo('100/3'));
    }

    public function test_daily_points_preserve_the_original_fraction_and_a_separate_raw_total(): void
    {
        $this->invoice('0.333333333333','2026-11-10',true);$this->invoice('0.333333333333','2026-11-11',true);
        $result=(new DashboardKpi())->monthlyComparison(['period'=>'week','date_start'=>'2026-11-10','date_end'=>'2026-11-11']);
        self::assertSame([0.01,0.01],$result['sales']);
        self::assertSame([['n'=>'1','d'=>'200'],['n'=>'1','d'=>'200']],$result['sales_fractions']);
        self::assertTrue(D::readRequired($result,'sales_total')->isEqualTo('0.01'));
    }

    public function test_weekly_buckets_do_not_expand_the_requested_range_to_the_whole_month(): void
    {
        $this->invoice('100','2026-11-01');$this->invoice('1','2026-11-10');
        $this->invoice('1','2026-11-11');$this->invoice('100','2026-11-30');
        $result=(new DashboardKpi())->monthlyComparison(['period'=>'month','date_start'=>'2026-11-10','date_end'=>'2026-11-11']);
        self::assertSame(['Sem 1'],$result['categories']);
        self::assertSame([2.0],$result['sales']);
        self::assertTrue(D::readRequired($result,'sales_total')->isEqualTo('2'));
    }

    public function test_monthly_buckets_clip_partial_months_and_distinguish_the_year(): void
    {
        $this->invoice('100','2025-12-01');$this->invoice('1','2025-12-20');
        $this->invoice('2','2026-01-05');$this->invoice('100','2026-01-31');
        $result=(new DashboardKpi())->monthlyComparison(['period'=>'year','date_start'=>'2025-12-15','date_end'=>'2026-01-10']);
        self::assertSame(['DIC 2025','ENE 2026'],$result['categories']);
        self::assertSame([1.0,2.0],$result['sales']);
    }

    public function test_each_purchase_converts_its_original_fraction(): void
    {
        $this->purchase('1/3','1/7','2026-11-10','USD','3');
        $this->purchase('0.004','0','2026-11-11');
        $result=(new DashboardKpi())->monthlyComparison(['period'=>'week','date_start'=>'2026-11-10','date_end'=>'2026-11-11']);
        self::assertSame([1.0,0.0],$result['purchases']);
        self::assertSame([['n'=>'1','d'=>'1'],['n'=>'1','d'=>'250']],$result['purchases_fractions']);
        self::assertTrue(D::readRequired($result,'purchases_total')->isEqualTo(D::of('1')->plus('0.004')));
    }

    public function test_widget_context_preserves_the_daily_period_instead_of_defaulting_to_month_buckets(): void
    {
        $this->invoice('1','2026-11-10');$this->invoice('2','2026-11-11');
        $result=(new WidgetContext(['period'=>'between_dates','date_start'=>'2026-11-10','date_end'=>'2026-11-11']))->monthlyComparison();
        self::assertSame([1.0,2.0],$result['sales']);
        self::assertCount(2,$result['categories']);
    }

    public function test_growth_totals_sum_fractions_before_the_monthly_projection(): void
    {
        $this->invoice('0.333333333333','2026-10-10',true);$this->invoice('0.333333333333','2026-11-10',true);
        $result=(new DashboardKpi())->salesGrowth(['date_start'=>'2026-01-01','date_end'=>'2026-11-30']);
        self::assertSame(['n'=>'1','d'=>'200'],$result['current_fractions'][9]);
        self::assertSame(['n'=>'1','d'=>'200'],$result['current_fractions'][10]);
        self::assertTrue(D::readRequired($result,'current_total')->isEqualTo('0.01'));
        self::assertTrue(D::readRequired($result,'previous_total')->isZero());
    }

    public function test_all_widget_period_keeps_older_documents_and_uses_month_buckets(): void
    {
        $this->invoice('1','2025-01-10');$this->invoice('2','2026-11-10');
        $result=(new WidgetContext(['period'=>'all']))->monthlyComparison();
        self::assertCount(23,$result['categories']);
        self::assertSame('ENE 2025',$result['categories'][0]);
        self::assertSame('NOV 2026',$result['categories'][22]);
        self::assertSame(1.0,$result['sales'][0]);self::assertSame(2.0,$result['sales'][22]);
        self::assertTrue(D::readRequired($result,'sales_total')->isEqualTo('3'));
    }

    public function test_a_large_kpi_total_keeps_digits_beyond_binary_integer_precision(): void
    {
        $this->invoice('9007199254740993.005','2026-11-10');
        $result=(new DashboardKpi())->data(['date_start'=>'2026-11-10','date_end'=>'2026-11-10']);
        self::assertSame('9007199254740993.01',$result['month_sales']);
        self::assertTrue(D::readRequired($result,'month_sales')->isEqualTo('9007199254740993.005'));
    }

    public function test_current_previous_and_purchase_reads_share_one_snapshot_and_release_it(): void
    {
        $this->invoice('1','2026-11-10');$id=$this->purchase('1','0','2026-11-10');
        $this->app['config']->set('database.connections.dashboard_kpi_writer',$this->db->getConfig());
        $writer=$this->app['db']->connection('dashboard_kpi_writer');$changed=false;
        $this->db->listen(function ($event) use ($writer,$id,&$changed) {
            if (!$changed && str_contains($event->sql,'from "documents"')) {
                $changed=true;$writer->table('purchases')->where('id',$id)->update(['total'=>'9',
                    'calculation_snapshot'=>json_encode(D::snapshot(['total'=>'9','total_perception'=>'0','exchange_rate_sale'=>'1']),JSON_THROW_ON_ERROR)]);
            }
        });
        try {
            $filters=['date_start'=>'2026-11-10','date_end'=>'2026-11-10'];$sut=new DashboardKpi();
            self::assertSame('1.00',$sut->data($filters)['month_purchases']);self::assertTrue($changed);
            self::assertSame('9.00',$sut->data($filters)['month_purchases']);self::assertSame(0,$this->db->transactionLevel());
            $this->db->beginTransaction();
            try {$this->expectException(\LogicException::class);$sut->data($filters);}
            finally {$this->db->rollBack();}
        } finally {$writer->disconnect();}
    }
}
