<?php
namespace Tests\Unit;

use App\Models\Tenant\{Document, SaleNote};
use App\Support\Exact\Decimal as D;
use Modules\Dashboard\Helpers\DashboardData;

/** @group postgres-integration */
final class DashboardSalesPostgresTest extends \PHPUnit\Framework\TestCase
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

    private function invoice(): array
    {
        [$db,$app,$input]=(new \ReflectionMethod($this->fixture,'bootHkaFixture'))->invoke($this->fixture,true);
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $input['payments']=[]; $input['payment_condition_id']='02';
        $invoice=(new \App\CoreFacturalo\Facturalo())->save($input)->getDocument()->fresh();
        return [$db,$app,$input,$invoice];
    }

    private function amount($db, $record, $total, string $currency='VES', string $rate='1'): void
    {
        $snapshot=$record->calculation_snapshot ?? D::snapshot([]);
        $snapshot['values']['total']=D::fraction($total);
        $snapshot['values']['exchange_rate_sale']=D::fraction($rate);
        $db->table($record->getTable())->where('id',$record->id)->update([
            'total'=>D::represent($total),'currency_type_id'=>$currency,'exchange_rate_sale'=>$rate,
            'calculation_snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR)]);
    }

    private function note($input): SaleNote
    {
        $controller=new \App\Http\Controllers\Tenant\SaleNoteController();
        return SaleNote::create($controller->mergeData(array_replace($input,[
            'id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]])));
    }

    public function test_range_converts_the_original_fraction_before_the_final_rounding(): void
    {
        [$db,,$input,$invoice]=$this->invoice();
        $this->amount($db,$invoice,D::of(1)->dividedBy(3),'USD','0.01500000');
        $result=(new DashboardData())->salesTotalByRange($input['establishment_id'],'2026-09-10','2026-09-10');
        self::assertEquals(0.01,$result['documents_total']);
        self::assertEquals(0.01,$result['total']);
        self::assertSame('0.01',$result['total_exact']);
        self::assertTrue(D::readRequired($result,'total')->isEqualTo('1/200'));
        self::assertSame('0.01',(new DashboardData())->document_totals_globals('2026-09-10','2026-09-10'));
        self::assertSame(0,$db->transactionLevel());
    }

    public function test_range_sums_documents_and_notes_before_rounding_each_presentation(): void
    {
        [$db,,$input,$invoice]=$this->invoice();
        $note=$this->note($input);
        $this->amount($db,$invoice,'0.004'); $this->amount($db,$note,'0.004');
        $result=(new DashboardData())->salesTotalByRange($input['establishment_id'],'2026-09-10','2026-09-10');
        self::assertEquals(0.01,$result['total']);
        self::assertSame('0.01',$result['total_exact']);
        self::assertTrue(D::readRequired($result,'documents_total')->isEqualTo('0.004'));
        self::assertTrue(D::readRequired($result,'sale_notes_total')->isEqualTo('0.004'));
        self::assertTrue(D::readRequired($result,'total')->isEqualTo('0.008'));
        self::assertSame('0.00',$result['documents_total_exact']);
        self::assertSame('0.00',$result['sale_notes_total_exact']);
        self::assertSame('0.00',(new DashboardData())->sale_note_totals_global('2026-09-10','2026-09-10'));
    }

    public function test_range_keeps_large_totals_and_respects_status_date_and_establishment_filters(): void
    {
        [$db,,$input,$invoice]=$this->invoice();
        $this->amount($db,$invoice,'9007199254740993.005');
        $helper=new DashboardData();
        $result=$helper->salesTotalByRange(null,null,null);
        self::assertSame('9007199254740993.01',$result['total_exact']);
        self::assertTrue(D::readRequired($result,'total')->isEqualTo('9007199254740993.005'));
        self::assertSame('0.00',$helper->salesTotalByRange($input['establishment_id'],'2026-09-11','2026-09-11')['total_exact']);
        self::assertSame('0.00',$helper->salesTotalByRange($input['establishment_id']+1,null,null)['total_exact']);
        $db->table('documents')->where('id',$invoice->id)->update(['state_type_id'=>'11']);
        self::assertSame('0.00',$helper->salesTotalByRange(null,null,null)['total_exact']);
        $db->table('documents')->where('id',$invoice->id)->update(['state_type_id'=>'01']);
        $note=$this->note($input); $this->amount($db,$note,'1.005');
        $db->table('sale_notes')->where('id',$note->id)->update(['changed'=>true]);
        self::assertSame('9007199254740993.01',$helper->salesTotalByRange(null,null,null)['total_exact']);
        $db->table('sale_notes')->where('id',$note->id)->update(['changed'=>false,'state_type_id'=>'11']);
        self::assertSame('9007199254740993.01',$helper->salesTotalByRange(null,null,null)['total_exact']);
    }

    public function test_range_owns_one_snapshot_across_documents_and_notes_and_releases_it(): void
    {
        [$db,$app,$input,$invoice]=$this->invoice();
        $note=$this->note($input); $this->amount($db,$invoice,'1'); $this->amount($db,$note,'1');
        $other=(new \ReflectionProperty(FiscalEmissionSchemaTest::class,'capsule'))->getValue($this->fixture)->getConnection('system');
        $changed=false;
        $db->setEventDispatcher(new \Illuminate\Events\Dispatcher($app));
        $db->listen(function ($event) use (&$changed,$other,$note) {
            if (!$changed && str_contains(strtolower($event->sql),'from "documents"')) {
                $changed=true;
                $other->table('sale_notes')->where('id',$note->id)->update(['total'=>'9',
                    'calculation_snapshot'=>json_encode(D::snapshot(['total'=>'9','exchange_rate_sale'=>'1']),JSON_THROW_ON_ERROR)]);
            }
        });
        $helper=new DashboardData();
        self::assertEquals(2,$helper->salesTotalByRange(null,null,null)['total']);
        self::assertTrue($changed);
        self::assertEquals(10,$helper->salesTotalByRange(null,null,null)['total']);
        self::assertSame(0,$db->transactionLevel());
        $db->beginTransaction();
        try {
            $this->expectException(\LogicException::class);
            $helper->salesTotalByRange(null,null,null);
        } finally {$db->rollBack();}
    }
    private function purchase($db, array $input, $total, $perception, string $currency, string $rate, int $number): int
    {
        return $db->table('purchases')->insertGetId(['user_id'=>$input['user_id'],
            'external_id'=>(string)\Illuminate\Support\Str::uuid(),'establishment_id'=>$input['establishment_id'],
            'fiscal_environment'=>'demo','state_type_id'=>'01','group_id'=>'01','document_type_id'=>'01',
            'series'=>'FP01','number'=>$number,'date_of_issue'=>'2026-09-10','time_of_issue'=>'12:00:00',
            'supplier_id'=>$input['customer_id'],'supplier'=>json_encode($input['customer'],JSON_THROW_ON_ERROR),
            'currency_type_id'=>$currency,'exchange_rate_sale'=>$rate,'total'=>D::represent($total),
            'total_perception'=>$perception===null ? null : D::represent($perception),
            'calculation_snapshot'=>json_encode(D::snapshot(array_filter(['total'=>$total,
                'exchange_rate_sale'=>$rate,'total_perception'=>$perception],fn($v)=>$v!==null)),JSON_THROW_ON_ERROR)]);
    }

    public function test_purchases_convert_each_own_perception_and_payment_fraction_once(): void
    {
        [$db,,$input]=$this->invoice();
        $a=$this->purchase($db,$input,'10','0.1','VES','1',1);
        $b=$this->purchase($db,$input,'1/3','1/7','USD','3',2);
        $c=$this->purchase($db,$input,'2','0.5','USD','2',3);
        $this->purchase($db,$input,'0.004',null,'VES','1',4);
        foreach ([$a=>'1/3',$b=>'1/3',$c=>'2'] as $id=>$amount) {
            $db->table('purchase_payments')->insert(['purchase_id'=>$id,'date_of_payment'=>'2026-09-10',
                'payment_method_type_id'=>'01','payment'=>D::represent($amount),
                'calculation_snapshot'=>json_encode(D::snapshot(['payment'=>$amount]),JSON_THROW_ON_ERROR)]);
        }
        $totals=(new DashboardData())->get_purchase_totals(null,'2026-09-10','2026-09-10')['totals'];
        $expected=D::of('10.104')->plus(D::of('1/3')->plus('1/7')->multipliedBy(3))->plus(5);
        self::assertEquals(D::format($expected,2,'.',''),$totals['total']);
        self::assertTrue(D::readRequired($totals,'total')->isEqualTo($expected));
        self::assertTrue(D::readRequired($totals,'total_payment')->isEqualTo('16/3'));
        self::assertSame('5.33',$totals['total_payment_exact']);
        self::assertEquals(0,(new DashboardData())->get_purchase_totals($input['establishment_id']+1,null,null)['totals']['total']);
        $db->table('purchases')->update(['state_type_id'=>'11']);
        self::assertEquals(0,(new DashboardData())->get_purchase_totals(null,null,null)['totals']['total']);
    }

    public function test_expenses_convert_paid_amounts_and_total_from_original_fractions(): void
    {
        [$db,,$input]=$this->invoice();
        $method=$db->table('expense_method_types')->value('id');
        foreach ([['USD','0.01500000','1/3'],['VES','1','0.004']] as [$currency,$rate,$amount]) {
            $id=$db->table('expenses')->insertGetId(['user_id'=>$input['user_id'],'fiscal_environment'=>'demo',
                'expense_type_id'=>$db->table('expense_types')->value('id'),
                'expense_reason_id'=>$db->table('expense_reasons')->value('id'),
                'establishment_id'=>$input['establishment_id'],'supplier_id'=>$input['customer_id'],
                'currency_type_id'=>$currency,'external_id'=>(string)\Illuminate\Support\Str::uuid(),
                'state_type_id'=>'05','date_of_issue'=>'2026-09-10','time_of_issue'=>'12:00:00',
                'supplier'=>json_encode($input['customer'],JSON_THROW_ON_ERROR),'exchange_rate_sale'=>$rate,
                'total'=>D::represent($amount),'calculation_snapshot'=>json_encode(D::snapshot([
                    'total'=>$amount,'exchange_rate_sale'=>$rate]),JSON_THROW_ON_ERROR)]);
            $db->table('expense_payments')->insert(['expense_id'=>$id,'date_of_payment'=>'2026-09-10',
                'expense_method_type_id'=>$method,'payment'=>D::represent($amount),
                'calculation_snapshot'=>json_encode(D::snapshot(['payment'=>$amount]),JSON_THROW_ON_ERROR)]);
        }
        $totals=(new DashboardData())->get_expense_totals(null,'2026-09-10','2026-09-10')['totals'];
        self::assertEquals(0.01,$totals['total_payment']);
        self::assertEquals(0.01,$totals['total']);
        self::assertTrue(D::readRequired($totals,'total')->isEqualTo('0.009'));
        self::assertTrue(D::readRequired($totals,'total_payment')->isEqualTo('0.009'));
        $db->table('expenses')->update(['state_type_id'=>'01']);
        self::assertEquals(0,(new DashboardData())->get_expense_totals(null,null,null)['totals']['total']);
        self::assertSame(0,$db->transactionLevel());
    }

    private function filters($input): array
    {
        return ['establishment_id'=>$input['establishment_id'],'period'=>'date','date_start'=>'2026-09-10',
            'date_end'=>'2026-09-10','month_start'=>'2026-09','month_end'=>'2026-09'];
    }

    public function test_legacy_summary_graph_and_balance_do_not_round_the_partial_groups(): void
    {
        [$db,,$input,$invoice]=$this->invoice();
        $note=$this->note($input); $this->amount($db,$invoice,'0.004'); $this->amount($db,$note,'0.004');
        $helper=new DashboardData(); $data=$helper->data($this->filters($input));
        self::assertSame('0.01',$data['general']['totals']['total']);
        self::assertTrue(D::readRequired($data['general']['totals'],'total')->isEqualTo('0.008'));
        self::assertSame(0.01,$data['general']['graph']['datasets'][0]['data'][12]);
        self::assertSame('0.01',$data['general']['graph']['datasets'][0]['data_exact'][12]);
        self::assertSame('0.01',$data['balance']['totals']['all_totals']);
        self::assertTrue(D::readRequired($data['balance']['totals'],'all_totals')->isEqualTo('0.008'));
        foreach ([['month','2026-09-01','2026-09-30'],['between_dates','2026-09-01','2026-09-30'],
            ['between_months','2026-09-01','2026-09-30']] as [$period,$start,$end]) {
            $general=$helper->getGeneralTotals(null,$start,$end,$period,'2026-09','2026-09');
            self::assertSame('0.01',$general['totals']['total']);
            self::assertSame(0.01,$general['graph']['datasets'][0]['data'][9]);
            self::assertTrue(D::readRequired($general['totals'],'total')->isEqualTo('0.008'));
        }
        self::assertSame(0,$db->transactionLevel());
    }

    public function test_month_series_keeps_distinct_years_and_the_document_day(): void
    {
        [$db,,$input,$invoice]=$this->invoice();
        $note=$this->note($input); $this->amount($db,$invoice,'0.004'); $this->amount($db,$note,'0.006');
        $db->table('documents')->where('id',$invoice->id)->update(['date_of_issue'=>'2026-12-31']);
        $db->table('sale_notes')->where('id',$note->id)->update(['date_of_issue'=>'2027-01-01']);
        $helper=new DashboardData();
        $general=$helper->getGeneralTotals(null,'2026-12-31','2027-01-01','between_months','2026-12','2027-01');
        self::assertSame(['2026-12m','2027-01m'],$general['graph']['labels']);
        self::assertSame([0.0,0.01],$general['graph']['datasets'][0]['data']);
        self::assertSame(['0.00','0.01'],$general['graph']['datasets'][0]['data_exact']);
        self::assertSame('0.01',$general['totals']['total']);
        $days=$helper->getGeneralTotals(null,'2026-12-31','2027-01-01','between_dates',null,null);
        self::assertSame(['2026-12-31d','2027-01-01d'],$days['graph']['labels']);
        self::assertSame([0.0,0.01],$days['graph']['datasets'][0]['data']);
    }

    public function test_full_legacy_response_keeps_one_snapshot_for_all_its_components(): void
    {
        [$db,$app,$input,$invoice]=$this->invoice();
        $note=$this->note($input); $this->amount($db,$invoice,'1'); $this->amount($db,$note,'1');
        $other=(new \ReflectionProperty(FiscalEmissionSchemaTest::class,'capsule'))->getValue($this->fixture)->getConnection('system');
        $changed=false;
        $db->setEventDispatcher(new \Illuminate\Events\Dispatcher($app));
        $db->listen(function ($event) use (&$changed,$other,$note) {
            if (!$changed && str_contains(strtolower($event->sql),'from "documents"')) {
                $changed=true;
                $other->table('sale_notes')->where('id',$note->id)->update(['total'=>'9',
                    'calculation_snapshot'=>json_encode(D::snapshot(['total'=>'9','exchange_rate_sale'=>'1']),JSON_THROW_ON_ERROR)]);
            }
        });
        $helper=new DashboardData(); $during=$helper->data($this->filters($input));
        self::assertTrue($changed);
        self::assertSame('1.00',$during['sale_note']['totals']['total']);
        self::assertSame('2.00',$during['general']['totals']['total']);
        self::assertSame('2.00',$during['balance']['totals']['all_totals']);
        $after=$helper->data($this->filters($input));
        self::assertSame('9.00',$after['sale_note']['totals']['total']);
        self::assertSame('10.00',$after['general']['totals']['total']);
        self::assertSame('10.00',$after['balance']['totals']['all_totals']);
        self::assertSame(0,$db->transactionLevel());
    }

    public function test_sale_note_globals_use_the_original_fraction_and_historical_rate(): void
    {
        [$db,,$input,$invoice]=$this->invoice();
        $db->table('documents')->where('id',$invoice->id)->update(['state_type_id'=>'11']);
        $note=$this->note($input); $this->amount($db,$note,'1/3','USD','0.01500000');
        $helper=new DashboardData();
        self::assertSame('0.01',$helper->sale_note_totals_global('2026-09-10','2026-09-10'));
        self::assertSame('0.00',$helper->document_totals_globals('2026-09-10','2026-09-10'));
        $totals=$helper->get_sale_note_totals(null,null,null)['totals'];
        self::assertTrue(D::readRequired($totals,'total')->isEqualTo('1/200'));
        self::assertSame('0.01',$totals['total_exact']);
    }

    public function test_net_sales_add_debits_subtract_credits_and_preserve_the_document_type_scope(): void
    {
        [$db,,$input,$invoice]=$this->invoice();
        $line=$invoice->items->first();
        $noteInput=new \ReflectionMethod($this->fixture,'noteInput');
        $credit=(new \App\CoreFacturalo\Facturalo())->save($noteInput->invoke($this->fixture,$input,$invoice,'07','09',
            [['affected_document_item_id'=>$line->id,'adjustment_amount'=>'23.20']]))->getDocument()->fresh();
        $debit=(new \App\CoreFacturalo\Facturalo())->save($noteInput->invoke($this->fixture,$input,$invoice,'08','02',
            [['affected_document_item_id'=>$line->id,'adjustment_amount'=>'11.60']]))->getDocument()->fresh();
        $this->amount($db,$invoice,'1/3','USD','3');
        $this->amount($db,$credit,'1/7','USD','7');
        $this->amount($db,$debit,'1/11','USD','11');
        $helper=new DashboardData(); $result=$helper->salesTotalByRange(null,null,null);
        self::assertSame('1.00',$result['total_exact']);
        self::assertTrue(D::readRequired($result,'total')->isEqualTo(1));
        $copy=(array)$db->table('documents')->where('id',$invoice->id)->first(); unset($copy['id']);
        $copy['external_id']=(string)\Illuminate\Support\Str::uuid(); $copy['document_type_id']='09';
        $copy['series']='TT01'; $copy['number']=1; $copy['filename']='dashboard-ignored-type'; $copy['unique_filename']='dashboard-ignored-type';
        $db->table('documents')->insert($copy);
        self::assertSame('1.00',$helper->salesTotalByRange(null,null,null)['total_exact']);
        $db->table('documents')->where('id',$credit->id)->update(['state_type_id'=>'11']);
        self::assertSame('2.00',$helper->salesTotalByRange(null,null,null)['total_exact']);
    }

    public function test_invalid_usd_rate_rolls_back_the_read_without_reusing_a_failed_snapshot(): void
    {
        [$db,,$input,$invoice]=$this->invoice(); $this->amount($db,$invoice,'1','USD','0');
        try {
            (new DashboardData())->salesTotalByRange(null,null,null);
            self::fail('Una tasa USD inválida no se sustituye por uno.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertNotEmpty($error->errors());
        }
        self::assertSame(0,$db->transactionLevel());
        $this->amount($db,$invoice,'1/3','USD','0.01500000');
        self::assertSame('0.01',(new DashboardData())->salesTotalByRange(null,null,null)['total_exact']);
        self::assertSame(0,$db->transactionLevel());
    }

    public function test_materialized_chart_and_range_keep_fractions_across_multiple_batches(): void
    {
        [$db,,$input,$invoice]=$this->invoice();
        $note=$this->note($input);
        $this->amount($db,$invoice,'1/3','USD','0.01500000'); $this->amount($db,$note,'1/3','USD','0.01500000');
        foreach (['documents'=>$invoice,'sale_notes'=>$note] as $table=>$model) {
            $copy=(array)$db->table($table)->where('id',$model->id)->first(); unset($copy['id']);
            foreach (array_chunk(range(1,500),100) as $chunk) {
                $rows=[];
                foreach ($chunk as $offset) {
                    $row=$copy; $row['external_id']=(string)\Illuminate\Support\Str::uuid(); $row['number']=1000+$offset;
                    $row['filename']='dashboard-volume-'.$offset;
                    if ($table==='documents') $row['unique_filename']='dashboard-volume-'.$offset;
                    $rows[]=$row;
                }
                $db->table($table)->insert($rows);
            }
        }
        $helper=new DashboardData();
        $range=$helper->salesTotalByRange(null,'2026-09-10','2026-09-10');
        self::assertTrue(D::readRequired($range,'total')->isEqualTo('5.01'));
        self::assertSame('5.01',$range['total_exact']);
        $general=$helper->getGeneralTotals(null,'2026-09-10','2026-09-10','date',null,null);
        self::assertSame('5.01',$general['totals']['total']);
        self::assertSame(5.01,$general['graph']['datasets'][0]['data'][12]);
        self::assertTrue(D::of($general['graph']['datasets'][0]['calculation_snapshot']['values']['12h'])->isEqualTo('5.01'));
        self::assertSame(0,$db->transactionLevel());
    }

    public function test_historical_rate_fraction_cannot_be_silently_rounded_to_eight_places(): void
    {
        [$db,,$input,$invoice]=$this->invoice(); $this->amount($db,$invoice,'1','USD','0.33333333');
        $snapshot=$invoice->calculation_snapshot;
        $snapshot['values']['total']=D::fraction(1); $snapshot['values']['exchange_rate_sale']=D::fraction('1/3');
        $db->table('documents')->where('id',$invoice->id)->update(['calculation_snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR)]);
        $kpiMethod=new \ReflectionMethod(\Modules\Dashboard\Helpers\DashboardKpi::class,'salesTotal');
        foreach ([fn()=>(new DashboardData())->salesTotalByRange(null,null,null),
            fn()=>$kpiMethod->invoke(new \Modules\Dashboard\Helpers\DashboardKpi(),null,'2026-09-10','2026-09-10')] as $calculate) {
            try {$calculate();self::fail('Una fracción de tasa no representable se debe rechazar.');}
            catch (\Illuminate\Validation\ValidationException $error) {self::assertArrayHasKey('exchange_rate_sale',$error->errors());}
            self::assertSame(0,$db->transactionLevel());
        }
        $this->amount($db,$invoice,'1/3','USD','0.01500000');
        self::assertSame('0.01',(new DashboardData())->salesTotalByRange(null,null,null)['total_exact']);
    }

}
