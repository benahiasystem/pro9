<?php
namespace Tests\Unit;

use App\Models\Tenant\Item;
use App\Support\Exact\Decimal as D;
use Modules\Dashboard\Helpers\DashboardUtility;

/** @group postgres-integration */
final class DashboardUtilityPostgresTest extends \PHPUnit\Framework\TestCase
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

    private function invoice(array $options=[]): array
    {
        [$db,$app,$input]=(new \ReflectionMethod($this->fixture,'bootHkaFixture'))->invoke($this->fixture,false);
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $item=Item::findOrFail($input['items'][0]['item_id']);
        $item->currency_type_id=$options['item_currency'] ?? 'VES';
        $item->setExactValues(['purchase_unit_price'=>$options['cost'] ?? '0']); $item->saveQuietly();
        $input=array_replace($input,['payments'=>[],'payment_condition_id'=>'02',
            'currency_type_id'=>$options['currency'] ?? 'VES','exchange_rate_sale'=>$options['rate'] ?? '1']);
        $input['items'][0]=array_replace($input['items'][0],['quantity'=>$options['quantity'] ?? '1',
            'unit_price'=>$options['unit_price'] ?? '1','affectation_igv_type_id'=>'20','item'=>$item->fresh()->toArray()]);
        foreach (['price_input','quantity_input'] as $field) if (isset($options[$field])) $input['items'][0][$field]=$options[$field];
        if (isset($options['presentation'])) $input['items'][0]['item']['presentation']=$options['presentation'];
        if (isset($options['discounts'])) $input['discounts']=$options['discounts'];
        $invoice=(new \App\CoreFacturalo\Facturalo())->save($input)->getDocument()->fresh();
        return [$db,$app,$input,$invoice,$item];
    }

    public function test_utility_uses_the_original_usd_line_fraction_before_final_presentation(): void
    {
        [$db,,$input,$invoice]=$this->invoice(['currency'=>'USD','rate'=>'0.01500000',
            'unit_price'=>'0.333333333333','price_input'=>['amount'=>'0.005','currency_type_id'=>'VES','includes_iva'=>true]]);
        self::assertTrue(D::readRequired($invoice,'total')->isEqualTo('1/3'));
        $result=(new DashboardUtility())->calculateUtilityTotals($input['establishment_id'],'2026-09-10','2026-09-10',false);
        self::assertSame('0.01',$result['totals']['total_income']);
        self::assertTrue(D::readRequired($result['totals'],'total_income')->isEqualTo('0.005'));
        self::assertTrue(D::readRequired($result['totals'],'utility')->isEqualTo('0.005'));
        self::assertSame([0.01,0.0],$result['graph']['datasets'][0]['data']);
        self::assertTrue($result['cost_is_estimated']);
        self::assertSame(0,$db->transactionLevel());
    }

    public function test_utility_allocates_header_discounts_and_keeps_cost_and_physical_quantity_fractions(): void
    {
        [$db,,$input,$invoice]=$this->invoice(['cost'=>'1/7','quantity'=>'0.333333333333',
            'quantity_input'=>['amount'=>'1','unit_price'=>'3'],'presentation'=>['quantity_unit'=>'3'],
            'discounts'=>[['discount_type_id'=>'05','amount'=>'0.1']]]);
        self::assertTrue(D::readRequired($invoice,'total')->isEqualTo('7/30'));
        $result=(new DashboardUtility())->calculateUtilityTotals(null,'2026-09-10','2026-09-10',false);
        self::assertSame('0.23',$result['totals']['total_income']);
        self::assertTrue(D::readRequired($result['totals'],'total_income')->isEqualTo('7/30'));
        self::assertTrue(D::readRequired($result['totals'],'total_egress')->isEqualTo('1/7'));
        self::assertTrue(D::readRequired($result['totals'],'utility')->isEqualTo('19/210'));
        self::assertSame('0.09',$result['totals']['utility']);
        self::assertSame('0.00',(new DashboardUtility())->calculateUtilityTotals($input['establishment_id']+1,null,null,false)['totals']['utility']);
        self::assertSame(0,$db->transactionLevel());
    }

    public function test_utility_converts_current_catalog_cost_and_adds_expenses_before_rounding(): void
    {
        [$db,,$input]=$this->invoice(['currency'=>'USD','rate'=>'0.01500000','item_currency'=>'USD','cost'=>'1/3']);
        $db->table('expenses')->insert(['user_id'=>$input['user_id'],'fiscal_environment'=>'demo',
            'expense_type_id'=>$db->table('expense_types')->value('id'),'expense_reason_id'=>$db->table('expense_reasons')->value('id'),
            'establishment_id'=>$input['establishment_id'],'supplier_id'=>$input['customer_id'],'currency_type_id'=>'VES',
            'external_id'=>(string)\Illuminate\Support\Str::uuid(),'state_type_id'=>'05','date_of_issue'=>'2026-09-10',
            'time_of_issue'=>'12:00:00','supplier'=>json_encode($input['customer'],JSON_THROW_ON_ERROR),
            'exchange_rate_sale'=>'1','total'=>'0.004','calculation_snapshot'=>json_encode(D::snapshot(['total'=>'0.004','exchange_rate_sale'=>'1']),JSON_THROW_ON_ERROR)]);
        $helper=new DashboardUtility();
        $result=$helper->calculateUtilityTotals(null,'2026-09-10','2026-09-10',true);
        self::assertSame('0.01',$result['totals']['total_egress']);
        self::assertSame('0.01',$result['totals']['utility']);
        self::assertTrue(D::readRequired($result['totals'],'total_income')->isEqualTo('0.015'));
        self::assertTrue(D::readRequired($result['totals'],'total_egress')->isEqualTo('0.009'));
        self::assertTrue(D::readRequired($result['totals'],'utility')->isEqualTo('0.006'));
        $without=$helper->calculateUtilityTotals(null,'2026-09-10','2026-09-10',false);
        self::assertTrue(D::readRequired($without['totals'],'total_egress')->isEqualTo('0.005'));
        self::assertSame('0.01',$without['totals']['utility']);
        self::assertSame(0,$db->transactionLevel());
    }

    public function test_utility_keeps_one_snapshot_across_item_and_catalog_reads(): void
    {
        [$db,$app,$input,,$item]=$this->invoice(['cost'=>'0.1']);
        $other=(new \ReflectionProperty(FiscalEmissionSchemaTest::class,'capsule'))->getValue($this->fixture)->getConnection('system');
        $snapshot=$item->fresh()->calculation_snapshot; $snapshot['values']['purchase_unit_price']=D::fraction('0.9');
        $changed=false; $db->setEventDispatcher(new \Illuminate\Events\Dispatcher($app));
        $db->listen(function ($event) use (&$changed,$other,$item,$snapshot) {
            if (!$changed && str_contains(strtolower($event->sql),'from "document_items"')) {
                $changed=true;
                $other->table('items')->where('id',$item->id)->update(['purchase_unit_price'=>'0.9',
                    'calculation_snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR)]);
            }
        });
        $helper=new DashboardUtility();
        $during=$helper->calculateUtilityTotals(null,'2026-09-10','2026-09-10',false);
        self::assertTrue($changed); self::assertSame('0.90',$during['totals']['utility']);
        $after=$helper->calculateUtilityTotals(null,'2026-09-10','2026-09-10',false);
        self::assertSame('0.10',$after['totals']['utility']); self::assertSame(0,$db->transactionLevel());
    }
    private function globalFilters(array $input): array
    {
        return ['period'=>'date','date_start'=>'2026-09-10','establishment_id'=>$input['establishment_id']];
    }

    public function test_global_kpis_keep_the_original_sales_average_utility_and_settled_receivable(): void
    {
        [$db,,$input,$invoice]=$this->invoice(['currency'=>'USD','rate'=>'0.01500000',
            'unit_price'=>'0.333333333333','price_input'=>['amount'=>'0.005','currency_type_id'=>'VES','includes_iva'=>true]]);
        self::assertTrue(D::readRequired($invoice,'total_payable')->isEqualTo('0.33'));
        $result=(new \Modules\Dashboard\Helpers\DashboardData())->globalData($this->globalFilters($input));
        self::assertEquals(0.01,$result['monthly_sales']);
        self::assertEquals(0.01,$result['average_ticket']);
        self::assertTrue(D::readRequired($result,'monthly_sales')->isEqualTo('0.005'));
        self::assertTrue(D::readRequired($result,'average_ticket')->isEqualTo('0.005'));
        self::assertTrue(D::readRequired($result,'net_utility')->isEqualTo('0.005'));
        self::assertTrue(D::readRequired($result,'accounts_receivable')->isEqualTo('0.00495'));
        self::assertSame('0.00',$result['accounts_receivable_exact']);
        self::assertSame('0.01',$result['trend']['exact']['monthly_sales'][1]);
        self::assertSame(['n'=>'1','d'=>'200'],$result['trend']['calculation_snapshots'][1]['values']['monthly_sales']);
        self::assertSame(0,$db->transactionLevel());
    }

    public function test_month_goal_uses_the_unrounded_goal_sales_and_daily_forecast(): void
    {
        [$db,,$input]=$this->invoice(['currency'=>'USD','rate'=>'0.01500000',
            'unit_price'=>'0.333333333333','price_input'=>['amount'=>'0.005','currency_type_id'=>'VES','includes_iva'=>true]]);
        $db->table('configurations')->update(['dashboard_goal_enabled'=>true,'dashboard_goal_amount'=>'0.015']);
        $previous=\Carbon\Carbon::getTestNow();
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-10 12:00:00','America/Caracas'));
        try {
            $result=(new \Modules\Dashboard\Helpers\DashboardData())->monthGoal();
            self::assertEquals(33.3,$result['percent']);
            self::assertSame('33.3',$result['percent_exact']);
            self::assertEquals(0.02,$result['projected']);
            self::assertTrue(D::readRequired($result,'goal')->isEqualTo('0.015'));
            self::assertTrue(D::readRequired($result,'sales')->isEqualTo('0.005'));
            self::assertTrue(D::readRequired($result,'remaining')->isEqualTo('0.01'));
            self::assertTrue(D::readRequired($result,'projected')->isEqualTo('0.015'));
            self::assertTrue(D::readRequired($result,'percent')->isEqualTo('100/3'));
            self::assertTrue($result['enabled']);
        } finally {\Carbon\Carbon::setTestNow($previous);}
        self::assertSame(0,$db->transactionLevel());
    }

    public function test_global_response_keeps_its_sales_and_utility_in_the_same_read_snapshot(): void
    {
        [$db,$app,$input,$invoice]=$this->invoice();
        $other=(new \ReflectionProperty(FiscalEmissionSchemaTest::class,'capsule'))->getValue($this->fixture)->getConnection('system');
        $header=$invoice->calculation_snapshot;
        foreach (['total','total_value','total_payable'] as $field) $header['values'][$field]=D::fraction(3);
        $line=$invoice->items->first(); $lineSnapshot=$line->calculation_snapshot;
        foreach (['total','total_value','unit_value','unit_price'] as $field) $lineSnapshot['values'][$field]=D::fraction(3);
        $changed=false; $db->setEventDispatcher(new \Illuminate\Events\Dispatcher($app));
        $db->listen(function ($event) use (&$changed,$other,$invoice,$header,$line,$lineSnapshot) {
            if (!$changed && str_contains(strtolower($event->sql),'from "documents"')) {
                $changed=true;
                $other->transaction(function () use ($other,$invoice,$header,$line,$lineSnapshot) {
                    $other->table('documents')->where('id',$invoice->id)->update(['total'=>'3','total_value'=>'3','total_payable'=>'3',
                        'calculation_snapshot'=>json_encode($header,JSON_THROW_ON_ERROR)]);
                    $other->table('document_items')->where('id',$line->id)->update(['total'=>'3','total_value'=>'3','unit_value'=>'3','unit_price'=>'3',
                        'calculation_snapshot'=>json_encode($lineSnapshot,JSON_THROW_ON_ERROR)]);
                });
            }
        });
        $helper=new \Modules\Dashboard\Helpers\DashboardData();
        $during=$helper->globalData($this->globalFilters($input));
        self::assertTrue($changed);
        self::assertSame('1.00',$during['document_total_global']);
        self::assertSame('1.00',$during['monthly_sales_exact']);
        self::assertSame('1.00',$during['net_utility_exact']);
        $after=$helper->globalData($this->globalFilters($input));
        self::assertSame('3.00',$after['document_total_global']);
        self::assertSame('3.00',$after['monthly_sales_exact']);
        self::assertSame('3.00',$after['net_utility_exact']);
        self::assertSame(0,$db->transactionLevel());
    }

    public function test_receivables_kpi_uses_original_applied_receipts_and_ignores_reversals(): void
    {
        [$db,,$input,$invoice]=$this->invoice();
        foreach ([['1/3',null],['1/7','2026-09-10 13:00:00']] as [$amount,$reversed]) {
            $db->table('document_payments')->insert(['document_id'=>$invoice->id,'date_of_payment'=>'2026-09-10',
                'payment_method_type_id'=>'01','currency_type_id'=>'VES','exchange_rate'=>'1',
                'operation_key'=>(string)\Illuminate\Support\Str::uuid(),
                'payment_method_snapshot'=>'{"collection_kind":"cash","currency_type_id":"VES"}',
                'payment'=>D::represent($amount),'original_amount'=>D::represent($amount),'reversed_at'=>$reversed,
                'calculation_snapshot'=>json_encode(D::snapshot(['payment'=>$amount]),JSON_THROW_ON_ERROR)]);
        }
        $result=(new \Modules\Dashboard\Helpers\DashboardData())->globalData($this->globalFilters($input));
        self::assertEquals(0.67,$result['accounts_receivable']);
        self::assertTrue(D::readRequired($result,'accounts_receivable')->isEqualTo('2/3'));
        self::assertSame('0.67',$result['accounts_receivable_exact']);
        self::assertSame(0,$db->transactionLevel());
    }

    private function purchaseCost($db, array $input, $invoice, string $date, string $unit, string $state='01'): void
    {
        $purchase=$db->table('purchases')->insertGetId(['user_id'=>$input['user_id'],
            'external_id'=>(string)\Illuminate\Support\Str::uuid(),'establishment_id'=>$input['establishment_id'],
            'fiscal_environment'=>'demo','state_type_id'=>$state,'group_id'=>'01','document_type_id'=>'01','series'=>'FP01',
            'number'=>$db->table('purchases')->count()+1,'date_of_issue'=>$date,'time_of_issue'=>'12:00:00',
            'supplier_id'=>$input['customer_id'],'supplier'=>json_encode($input['customer'],JSON_THROW_ON_ERROR),
            'currency_type_id'=>'USD','exchange_rate_sale'=>'0.01500000','total'=>D::represent($unit)]);
        $line=(array)$db->table('document_items')->where('document_id',$invoice->id)->first();
        $line=array_intersect_key($line,array_flip($db->getSchemaBuilder()->getColumnListing('purchase_items')));
        unset($line['id']); $line['purchase_id']=$purchase;
        foreach (['unit_price','unit_value','total','total_value'] as $field) $line[$field]=D::represent($unit);
        $line['calculation_snapshot']=json_encode(D::snapshot(['quantity'=>'1','unit_price'=>$unit,'unit_value'=>$unit,
            'total'=>$unit,'total_value'=>$unit]),JSON_THROW_ON_ERROR);
        $db->table('purchase_items')->insert($line);
    }

    public function test_cost_preserves_catalog_priority_then_eligible_purchase_and_zero_service_cost(): void
    {
        [$db,,$input,$invoice,$item]=$this->invoice();
        $this->purchaseCost($db,$input,$invoice,'2026-09-09','1/3');
        $this->purchaseCost($db,$input,$invoice,'2026-09-11','99');
        $this->purchaseCost($db,$input,$invoice,'2026-09-10','88','11');
        $helper=new DashboardUtility();
        $fallback=$helper->calculateUtilityTotals(null,'2026-09-10','2026-09-10',false);
        self::assertTrue(D::readRequired($fallback['totals'],'total_egress')->isEqualTo('0.005'));
        $item->setExactValues(['purchase_unit_price'=>'0.2']); $item->saveQuietly();
        $catalog=$helper->calculateUtilityTotals(null,'2026-09-10','2026-09-10',false);
        self::assertTrue(D::readRequired($catalog['totals'],'total_egress')->isEqualTo('0.2'));
        self::assertSame('0.80',$catalog['totals']['utility']);
        self::assertSame('current_positive_catalog_then_eligible_purchase',$catalog['cost_policy']);
        $item->unit_type_id='SERV'; $item->saveQuietly();
        $service=$helper->calculateUtilityTotals(null,'2026-09-10','2026-09-10',false);
        self::assertTrue(D::readRequired($service['totals'],'total_egress')->isZero());
        self::assertSame('1.00',$service['totals']['utility']); self::assertSame(0,$db->transactionLevel());
    }

    public function test_price_credit_and_debit_do_not_reverse_or_duplicate_the_physical_cost(): void
    {
        [$db,,$input,$invoice]=$this->invoice(['cost'=>'0.1']);
        $line=$invoice->items->first(); $noteInput=new \ReflectionMethod($this->fixture,'noteInput');
        $credit=(new \App\CoreFacturalo\Facturalo())->save($noteInput->invoke($this->fixture,$input,$invoice,'07','09',
            [['affected_document_item_id'=>$line->id,'adjustment_amount'=>'0.1']]))->getDocument()->fresh();
        self::assertTrue(D::readRequired($credit,'total')->isEqualTo('0.1'));
        $helper=new DashboardUtility(); $afterCredit=$helper->calculateUtilityTotals(null,'2026-09-10','2026-09-10',false);
        self::assertSame('0.80',$afterCredit['totals']['utility']);
        self::assertTrue(D::readRequired($afterCredit['totals'],'total_egress')->isEqualTo('0.1'));
        (new \App\CoreFacturalo\Facturalo())->save($noteInput->invoke($this->fixture,$input,$invoice,'08','02',
            [['affected_document_item_id'=>$line->id,'adjustment_amount'=>'0.2']]));
        $afterDebit=$helper->calculateUtilityTotals(null,'2026-09-10','2026-09-10',false);
        self::assertSame('1.00',$afterDebit['totals']['utility']);
        self::assertTrue(D::readRequired($afterDebit['totals'],'total_income')->isEqualTo('1.1'));
        self::assertTrue(D::readRequired($afterDebit['totals'],'total_egress')->isEqualTo('0.1'));
        self::assertSame(0,$db->transactionLevel());
    }

    public function test_large_utility_and_global_kpi_keep_text_and_original_fractions(): void
    {
        [$db,,$input]=$this->invoice(['unit_price'=>'9007199254740993.005','cost'=>'1/3']);
        $result=(new DashboardUtility())->calculateUtilityTotals(null,'2026-09-10','2026-09-10',false);
        self::assertSame('9007199254740993.01',$result['totals']['total_income']);
        self::assertTrue(D::readRequired($result['totals'],'total_income')->isEqualTo('9007199254740993.005'));
        self::assertTrue(D::readRequired($result['totals'],'total_egress')->isEqualTo('1/3'));
        self::assertTrue(D::readRequired($result['totals'],'utility')->isEqualTo(D::of('9007199254740993.005')->minus('1/3')));
        $global=(new \Modules\Dashboard\Helpers\DashboardData())->globalData($this->globalFilters($input));
        self::assertSame('9007199254740993.01',$global['monthly_sales_exact']);
        self::assertSame('9007199254740993.01',$global['average_ticket_exact']);
        self::assertTrue(D::readRequired($global,'net_utility')->isEqualTo(D::of('9007199254740993.005')->minus('1/3')));
        self::assertSame(0,$db->transactionLevel());
    }

    public function test_six_month_global_trend_keeps_empty_months_and_original_last_month_fraction(): void
    {
        [$db,,$input]=$this->invoice(['currency'=>'USD','rate'=>'0.01500000',
            'unit_price'=>'0.333333333333','price_input'=>['amount'=>'0.005','currency_type_id'=>'VES','includes_iva'=>true]]);
        $result=(new \Modules\Dashboard\Helpers\DashboardData())->globalData([
            'period'=>'month','month_start'=>'2026-09','establishment_id'=>$input['establishment_id']]);
        self::assertSame(['Abr','May','Jun','Jul','Ago','Set'],$result['trend']['labels']);
        self::assertSame([0.0,0.0,0.0,0.0,0.0,0.01],$result['trend']['monthly_sales']);
        self::assertSame(['0.00','0.00','0.00','0.00','0.00','0.01'],$result['trend']['exact']['monthly_sales']);
        self::assertSame(['n'=>'1','d'=>'200'],$result['trend']['calculation_snapshots'][5]['values']['monthly_sales']);
        self::assertSame('0.01',$result['monthly_sales_exact']); self::assertSame(0,$db->transactionLevel());
    }

    public function test_zero_disabled_goal_keeps_its_sales_and_forecast_without_dividing_by_zero(): void
    {
        [$db]=$this->invoice(['currency'=>'USD','rate'=>'0.01500000',
            'unit_price'=>'0.333333333333','price_input'=>['amount'=>'0.005','currency_type_id'=>'VES','includes_iva'=>true]]);
        $previous=\Carbon\Carbon::getTestNow(); \Carbon\Carbon::setTestNow('2026-09-10 12:00:00');
        try {
            $result=(new \Modules\Dashboard\Helpers\DashboardData())->monthGoal();
            self::assertFalse($result['enabled']); self::assertSame('0.0',$result['percent_exact']);
            self::assertTrue(D::readRequired($result,'percent')->isZero());
            self::assertTrue(D::readRequired($result,'sales')->isEqualTo('0.005'));
            self::assertTrue(D::readRequired($result,'projected')->isEqualTo('0.015'));
            self::assertTrue(D::readRequired($result,'remaining')->isZero());
        } finally {\Carbon\Carbon::setTestNow($previous);}
        self::assertSame(0,$db->transactionLevel());
    }

}
