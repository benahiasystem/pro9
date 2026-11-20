<?php

namespace Tests\Unit;

use App\Support\Exact\Decimal as D;
use Carbon\Carbon;
use Modules\Dashboard\Helpers\DashboardData;

/** @group postgres-integration */
final class DashboardSeriesPostgresTest extends \PHPUnit\Framework\TestCase
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
        $item=\App\Models\Tenant\Item::findOrFail($this->input['items'][0]['item_id']);
        $item->setExactValues(['purchase_unit_price'=>'0']); $item->saveQuietly();
        $this->input['items'][0]['item']=$item->fresh()->toArray();
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

    public function test_weekday_chart_converts_the_original_usd_fraction_without_rounding_it_to_zero(): void
    {
        $invoice=$this->invoice('0.333333333333','2026-11-10',true);
        self::assertTrue(D::readRequired($invoice,'total')->isEqualTo('1/3'));
        $result=(new DashboardData())->salesWeek(['period'=>'all']);
        self::assertSame(0.01,$result['current'][1]);
        self::assertSame(['n'=>'1','d'=>'200'],$result['current_fractions'][1]);
        self::assertTrue(D::readRequired($result,'current_total')->isEqualTo('0.005'));
    }

    public function test_a_credit_day_keeps_its_negative_net_sales(): void
    {
        $credit=$this->invoice('1','2026-11-10');
        // Controlled credit header: its issuance/lifecycle has separate fiscal integration coverage.
        $this->db->table('documents')->where('id',$credit->id)->update(['document_type_id'=>'07']);
        $result=(new DashboardData())->salesWeek(['period'=>'all']);
        self::assertSame(-1.0,$result['current'][1]);
        self::assertTrue(D::readRequired($result,'current_total')->isEqualTo('-1'));
    }

    public function test_comparing_february_with_january_keeps_the_previous_months_last_days(): void
    {
        Carbon::setTestNow('2026-02-14 12:00:00');
        $this->invoice('1','2026-01-31');
        $result=(new DashboardData())->salesWeek(['period'=>'month','month_start'=>'2026-02']);
        self::assertCount(31,$result['previous']);
        self::assertCount(31,$result['labels']);
        self::assertSame('31/01 (anterior)',$result['labels'][30]);
        self::assertSame(1.0,$result['previous'][30]);
        self::assertSame('31/01',$result['previous_labels'][30]);
        self::assertNull($result['current'][30]);
        self::assertNull($result['current_fractions'][30]);
        self::assertTrue(D::readRequired($result,'previous_total')->isEqualTo('1'));
        self::assertTrue(D::readRequired($result,'current_total')->isZero());
    }

    /** @dataProvider consistentSeries */
    public function test_all_period_buckets_share_one_database_snapshot(string $method, string $field): void
    {
        $this->invoice('1','2026-11-10'); $moved=$this->invoice('1','2026-11-30');
        $this->app['config']->set('database.connections.dashboard_series_writer',$this->db->getConfig());
        $writer=$this->app['db']->connection('dashboard_series_writer'); $changed=false;
        $this->db->listen(function ($event) use ($writer,$moved,&$changed) {
            if (!$changed && str_contains($event->sql,'from "documents"')) {
                $changed=true;
                $writer->table('documents')->where('id',$moved->id)->update(['date_of_issue'=>'2026-11-11']);
            }
        });
        try {
            $filters=['period'=>'between_dates','date_start'=>'2026-11-10','date_end'=>'2026-11-11'];
            $sut=new DashboardData(); $during=$sut->$method($filters);
            self::assertTrue($changed); self::assertSame([1.0,0.0],$during[$field]);
            self::assertSame([1.0,1.0],$sut->$method($filters)[$field]);
            self::assertSame(0,$this->db->transactionLevel());
        } finally {$writer->disconnect();}
    }

    public static function consistentSeries(): array
    {
        return ['ventas'=>['salesWeek','current'],'caja'=>['cashFlow','income']];
    }

    public function test_cash_flow_keeps_raw_period_totals_separate_from_rounded_daily_points(): void
    {
        $this->invoice('0.333333333333','2026-11-10',true);
        $this->invoice('0.333333333333','2026-11-11',true);
        $result=(new DashboardData())->cashFlow(['period'=>'between_dates','date_start'=>'2026-11-10','date_end'=>'2026-11-11']);
        self::assertSame([0.01,0.01],$result['income']);
        self::assertSame([['n'=>'1','d'=>'200'],['n'=>'1','d'=>'200']],$result['income_fractions']);
        self::assertTrue(D::readRequired($result,'income_total')->isEqualTo('0.01'));
        self::assertTrue(D::readRequired($result,'egress_total')->isZero());
        self::assertTrue($result['cost_is_estimated']);
    }
}
