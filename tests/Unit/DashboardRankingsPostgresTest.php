<?php

namespace Tests\Unit;

use App\Models\Tenant\{Item,Person};
use App\Support\Exact\Decimal as D;
use Carbon\Carbon;
use Modules\Dashboard\Helpers\DashboardSalePurchase;

/** @group postgres-integration */
final class DashboardRankingsPostgresTest extends \PHPUnit\Framework\TestCase
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

    private function filters(array $changes=[]): array
    {
        return array_replace(['establishment_id'=>null,'period'=>'date','date_start'=>'2026-11-10',
            'date_end'=>'2026-11-10','month_start'=>'2026-11','month_end'=>'2026-11',
            'enabled_move_item'=>false,'enabled_transaction_customer'=>false],$changes);
    }

    private function invoice(array $options=[]): array
    {
        $item=$options['item'] ?? Item::findOrFail($this->input['items'][0]['item_id']);
        $input=array_replace($this->input,['external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'date_of_issue'=>$options['date'] ?? '2026-11-10','payments'=>[],'payment_condition_id'=>'02',
            'currency_type_id'=>$options['currency'] ?? 'VES','exchange_rate_sale'=>$options['rate'] ?? '1']);
        $input['invoice']['date_of_due']=$input['date_of_issue'];
        $input['items'][0]=array_replace($input['items'][0],['item_id'=>$item->id,'item'=>$item->toArray(),
            'quantity'=>$options['quantity'] ?? '1','unit_price'=>$options['price'] ?? '1','affectation_igv_type_id'=>'20']);
        foreach (['price_input','quantity_input'] as $field) if (isset($options[$field])) $input['items'][0][$field]=$options[$field];
        if (isset($options['presentation'])) $input['items'][0]['item']['presentation']=$options['presentation'];
        if (isset($options['discounts'])) $input['discounts']=$options['discounts'];
        if (isset($options['person'])) {
            $person=$options['person'];$input['customer_id']=$person->id;
            $input['customer']=array_replace($input['customer'],['id'=>$person->id,'name'=>$person->name,'number'=>$person->number]);
        }
        return [(new \App\CoreFacturalo\Facturalo())->save($input)->getDocument()->fresh(),$input];
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

    private function newItem(int $index): Item
    {
        $item=Item::findOrFail($this->input['items'][0]['item_id'])->replicate();
        $item->description='Producto '.$index;$item->internal_id='QA-RANK-'.$index;$item->barcode='QA-RANK-'.$index;
        $item->saveQuietly();return $item;
    }

    public function test_purchase_range_converts_raw_total(): void
    {
        $this->purchase('1/3','1/7','2026-11-10','USD','0.01500000');
        $result=(new DashboardSalePurchase())->purchasesTotalByRange(null,'2026-11-10','2026-11-10');
        self::assertSame(0.01,$result['total']);
        self::assertTrue(D::readRequired($result,'total')->isEqualTo('1/200'));
        self::assertTrue(D::readRequired($result,'purchases_total')->isEqualTo('1/200'));
    }

    public function test_purchase_chart_uses_the_requested_dates_and_real_total_points_across_years(): void
    {
        $this->purchase('1','0','2025-12-10');$this->purchase('2','0','2026-01-10');
        $this->purchase('100','0','2026-11-10');
        $result=(new DashboardSalePurchase())->data($this->filters(['period'=>'between_dates','date_start'=>'2025-12-01','date_end'=>'2026-01-31']));
        self::assertSame('3.00',$result['purchase']['totals']['total']);
        self::assertSame(['Dic 2025','Ene 2026'],$result['purchase']['graph']['labels']);
        self::assertSame([1.0,2.0],$result['purchase']['graph']['datasets'][1]['data']);
        self::assertTrue(D::readRequired($result['purchase']['totals'],'total')->isEqualTo('3'));
    }

    public function test_customer_ranking_converts_credit_notes_at_their_historical_rate(): void
    {
        [$invoice,$input]=$this->invoice(['currency'=>'USD','rate'=>'2']);$line=$invoice->items->first();
        $note=(new \ReflectionMethod($this->fixture,'noteInput'))->invoke($this->fixture,$input,$invoice,'07','09',
            [['affected_document_item_id'=>$line->id,'adjustment_amount'=>'0.1']]);
        (new \App\CoreFacturalo\Facturalo())->save($note);
        $rows=(new DashboardSalePurchase())->data($this->filters())['top_customers'];
        self::assertCount(1,$rows);self::assertSame('1.80',$rows[0]['total']);
        self::assertTrue(D::readRequired($rows[0],'total')->isEqualTo('1.8'));
    }

    public function test_customer_order_uses_original_totals_when_both_display_as_zero(): void
    {
        $person=Person::findOrFail($this->input['customer_id'])->replicate();$person->number='87654321';$person->name='Mayor';$person->save();
        $this->invoice(['price'=>'0.003']);$this->invoice(['price'=>'0.004','person'=>$person]);
        $rows=(new DashboardSalePurchase())->data($this->filters())['top_customers'];
        self::assertCount(2,$rows);self::assertSame('Mayor',$rows[0]['name']);
        self::assertSame('0.00',$rows[0]['total']);self::assertTrue(D::readRequired($rows[0],'total')->isEqualTo('0.004'));
    }

    public function test_small_periodic_sales_remain_positive_in_both_rankings(): void
    {
        $this->invoice(['currency'=>'USD','rate'=>'0.01500000','price'=>'0.333333333333',
            'price_input'=>['amount'=>'0.005','currency_type_id'=>'VES','includes_iva'=>true]]);
        $result=(new DashboardSalePurchase())->data($this->filters());
        self::assertCount(1,$result['items_by_sales']);self::assertCount(1,$result['top_customers']);
        foreach ([$result['items_by_sales'][0],$result['top_customers'][0]] as $row) {
            self::assertSame('0.01',$row['total']);self::assertTrue(D::readRequired($row,'total')->isEqualTo('0.005'));
        }
    }

    public function test_product_totals_include_header_discount_and_physical_fractional_presentation(): void
    {
        $this->invoice(['quantity'=>'0.333333333333','quantity_input'=>['amount'=>'1','unit_price'=>'3'],
            'presentation'=>['quantity_unit'=>'3'],'discounts'=>[['discount_type_id'=>'05','amount'=>'0.1']]]);
        $rows=(new DashboardSalePurchase())->data($this->filters())['items_by_sales'];
        self::assertCount(1,$rows);self::assertSame('0.23',$rows[0]['total']);
        self::assertTrue(D::readRequired($rows[0],'total')->isEqualTo('7/30'));
        self::assertTrue(D::readRequired($rows[0],'move_quantity')->isEqualTo('1'));
    }

    public function test_monetary_credit_and_debit_adjust_amount_without_moving_more_stock(): void
    {
        [$invoice,$input]=$this->invoice();$line=$invoice->items->first();
        foreach ([['07','09','0.1'],['08','02','0.2']] as [$type,$reason,$amount]) {
            $note=(new \ReflectionMethod($this->fixture,'noteInput'))->invoke($this->fixture,$input,$invoice,$type,$reason,
                [['affected_document_item_id'=>$line->id,'adjustment_amount'=>$amount]]);
            (new \App\CoreFacturalo\Facturalo())->save($note);
        }
        $rows=(new DashboardSalePurchase())->data($this->filters())['items_by_sales'];
        self::assertCount(1,$rows);self::assertSame('1.10',$rows[0]['total']);
        self::assertTrue(D::readRequired($rows[0],'move_quantity')->isEqualTo('1'));
    }

    public function test_product_pagination_has_stable_id_ties_and_no_repeated_rows(): void
    {
        $this->invoice();for ($i=1;$i<=11;$i++) $this->invoice(['item'=>$this->newItem($i)]);
        $sut=new DashboardSalePurchase();$a=$sut->data($this->filters(['no_take'=>true,'page'=>1]))['items_by_sales'];
        $b=$sut->data($this->filters(['no_take'=>true,'page'=>2]))['items_by_sales'];
        self::assertSame(12,$a->total());self::assertCount(10,$a->items());self::assertCount(2,$b->items());
        $ids=array_merge(array_column($a->items(),'item_id'),array_column($b->items(),'item_id'));
        self::assertCount(12,array_unique($ids));self::assertCount(12,$ids);
    }

    public function test_quantity_ranking_orders_sub_cent_quantities_before_display_formatting(): void
    {
        $this->invoice(['quantity'=>'0.003']);$item=$this->newItem(99);
        $this->invoice(['quantity'=>'0.004','item'=>$item]);
        $rows=(new DashboardSalePurchase())->data($this->filters(['enabled_move_item'=>true]))['items_by_sales'];
        self::assertCount(2,$rows);self::assertSame((string)$item->id,$rows[0]['item_id']);
        self::assertTrue(D::readRequired($rows[0],'move_quantity')->isEqualTo('0.004'));
        self::assertSame('0.00',$rows[0]['total']);
    }

    public function test_a_partial_physical_credit_reverses_only_its_original_presentation_quantity(): void
    {
        [$invoice,$input]=$this->invoice(['presentation'=>['quantity_unit'=>'3']]);$line=$invoice->items->first();
        $note=(new \ReflectionMethod($this->fixture,'noteInput'))->invoke($this->fixture,$input,$invoice,'07','07',
            [['affected_document_item_id'=>$line->id,'quantity'=>'0.5']]);
        (new \App\CoreFacturalo\Facturalo())->save($note);
        $rows=(new DashboardSalePurchase())->data($this->filters())['items_by_sales'];
        self::assertCount(1,$rows);self::assertSame('0.50',$rows[0]['total']);
        self::assertTrue(D::readRequired($rows[0],'move_quantity')->isEqualTo('1.5'));
    }

    public function test_rankings_cross_the_500_parent_boundary_without_per_row_queries(): void
    {
        [$invoice]=$this->invoice();
        $header=(array)$this->db->table('documents')->where('id',$invoice->id)->first();unset($header['id']);
        $line=(array)$this->db->table('document_items')->where('document_id',$invoice->id)->first();unset($line['id']);
        $lines=[];
        for ($i=1;$i<=500;$i++) {
            $copy=$header;$copy['external_id']=(string)\Illuminate\Support\Str::uuid();$copy['number']=1000+$i;
            $copy['filename']='qa-dashboard-batch-'.$i;$copy['unique_filename']=$copy['filename'];
            $new=$line;$new['document_id']=$this->db->table('documents')->insertGetId($copy);$lines[]=$new;
            if (count($lines)===100) {$this->db->table('document_items')->insert($lines);$lines=[];}
        }
        $queries=0;$this->db->listen(function () use (&$queries) {$queries++;});
        $result=(new DashboardSalePurchase())->data($this->filters());
        self::assertCount(1,$result['items_by_sales']);self::assertCount(1,$result['top_customers']);
        self::assertSame('501.00',$result['items_by_sales'][0]['total']);
        self::assertSame('501.00',$result['top_customers'][0]['total']);
        self::assertTrue(D::readRequired($result['items_by_sales'][0],'move_quantity')->isEqualTo('501'));
        self::assertSame(501,$result['top_customers'][0]['transaction_quantity']);
        self::assertLessThanOrEqual(90,$queries,'The report must use parent/item batches, not one query per row.');
    }

    public function test_invalid_pagination_is_a_validation_error(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        (new DashboardSalePurchase())->data($this->filters(['no_take'=>true,'page'=>'0']));
    }

    public function test_purchase_and_rankings_share_one_snapshot_with_an_independent_writer(): void
    {
        [$invoice]=$this->invoice();$this->purchase('1','0','2026-11-10');
        $this->app['config']->set('database.connections.dashboard_rank_writer',$this->db->getConfig());
        $writer=$this->app['db']->connection('dashboard_rank_writer');$changed=false;
        $this->db->listen(function ($event) use ($writer,$invoice,&$changed) {
            if (!$changed && str_contains($event->sql,'from "purchases"')) {
                $changed=true;$writer->table('documents')->where('id',$invoice->id)->update(['state_type_id'=>'11']);
            }
        });
        try {
            $sut=new DashboardSalePurchase();$during=$sut->data($this->filters());self::assertTrue($changed);
            self::assertCount(1,$during['items_by_sales']);self::assertCount(1,$during['top_customers']);
            self::assertCount(0,$sut->data($this->filters())['items_by_sales']);self::assertSame(0,$this->db->transactionLevel());
            $this->db->beginTransaction();
            try {$this->expectException(\LogicException::class);$sut->data($this->filters());}
            finally {$this->db->rollBack();}
        } finally {$writer->disconnect();}
    }
}
