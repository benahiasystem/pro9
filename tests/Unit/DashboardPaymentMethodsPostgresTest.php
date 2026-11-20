<?php

namespace Tests\Unit;

use App\Support\Exact\Decimal as D;
use Modules\Dashboard\Helpers\DashboardData;

/** @group postgres-integration */
final class DashboardPaymentMethodsPostgresTest extends \PHPUnit\Framework\TestCase
{
    private $fixture;
    private $db;
    private $app;
    private array $input;

    protected function setUp(): void
    {
        $this->fixture=new class extends FiscalEmissionSchemaTest {
            public function start(): void {parent::setUp();}
            public function stop(): void {parent::tearDown();}
        };
        $this->fixture->start();
        [$this->db,$this->app,$this->input]=(new \ReflectionMethod($this->fixture,'bootHkaFixture'))->invoke($this->fixture,true);
        $this->db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $this->input['payments']=[]; $this->input['payment_condition_id']='02';
    }

    protected function tearDown(): void
    {
        try {if ($this->fixture) $this->fixture->stop();}
        finally {parent::tearDown();}
    }

    /** Controlled historical headers/receipts exercise the report reader, not receipt admission. */
    private function document(string $amount, string $date, string $currency='VES', string $rate='1'): int
    {
        $input=$this->input; $input['external_id']=(string)\Illuminate\Support\Str::uuid();
        $document=(new \App\CoreFacturalo\Facturalo())->save($input)->getDocument();
        $snapshot=$document->calculation_snapshot;
        $snapshot['values']['total']=D::fraction($amount); $snapshot['values']['exchange_rate_sale']=D::fraction($rate);
        $this->db->table('documents')->where('id',$document->id)->update([
            'total'=>D::represent($amount),'date_of_issue'=>$date,'state_type_id'=>'05',
            'currency_type_id'=>$currency,'exchange_rate_sale'=>$rate,'calculation_snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR),
        ]);
        return $document->id;
    }

    private function payment(int $document, string $amount, string $description, string $method='01', bool $reversed=false): void
    {
        $this->db->table('document_payments')->insert([
            'document_id'=>$document,'date_of_payment'=>'2026-11-10','payment_method_type_id'=>$method,
            'payment'=>D::represent($amount),'change'=>'0','currency_type_id'=>'VES','exchange_rate'=>'1',
            'original_amount'=>D::represent($amount),'operation_key'=>(string)\Illuminate\Support\Str::uuid(),
            'payment_method_snapshot'=>json_encode(['description'=>$description,'collection_kind'=>'cash'],JSON_THROW_ON_ERROR),
            'calculation_snapshot'=>json_encode(D::snapshot(['payment'=>$amount]),JSON_THROW_ON_ERROR),
            'reversed_at'=>$reversed ? '2026-11-11 10:00:00' : null,
        ]);
    }

    private function filters(): array
    {
        return ['period'=>'between_dates','date_start'=>'2026-11-01','date_end'=>'2026-11-30',
            'establishment_id'=>$this->input['establishment_id']];
    }

    public function test_payment_categories_sum_original_fractions_before_the_total_is_rounded(): void
    {
        $id=$this->document('1','2026-11-10','USD','0.01500000');
        $other=(string)$this->db->table('payment_method_types')->where('id','!=','01')->orderBy('id')->value('id');
        $this->payment($id,'1/3','Efectivo histórico');
        $this->payment($id,'1/3','Banco histórico',$other);
        $result=(new DashboardData())->paymentMethods($this->filters());
        self::assertSame(0.01,$result['total']);
        self::assertSame('0.01',$result['total_exact']);
        self::assertTrue(D::readRequired($result,'total')->isEqualTo('0.01'));
        self::assertSame(['0.01','0.01'],$result['values_exact']);
        self::assertSame([['n'=>'1','d'=>'200'],['n'=>'1','d'=>'200']],$result['values_fractions']);
        self::assertSame(['Banco histórico','Efectivo histórico'],$result['labels']);
        self::assertSame(0,$this->db->transactionLevel());
    }

    public function test_historical_method_and_large_amount_survive_catalog_renaming_and_reversals(): void
    {
        $id=$this->document('9007199254740993.01','2026-11-10');
        $this->payment($id,'9007199254740993.01','Nombre congelado');
        $this->payment($id,'100','Revertido','01',true);
        $this->db->table('payment_method_types')->where('id','01')->update(['description'=>'Nombre actual']);
        $result=(new DashboardData())->paymentMethods($this->filters());
        self::assertSame(['Nombre congelado'],$result['labels']);
        self::assertSame('9007199254740993.01',$result['total_exact']);
        self::assertSame('9007199254740993.01',$result['values_exact'][0]);
        self::assertTrue(D::of($result['values_fractions'][0])->isEqualTo('9007199254740993.01'));
    }

    public function test_all_records_really_includes_older_payments_and_preserves_date_and_branch_filters(): void
    {
        $this->payment($this->document('1','2025-01-10'),'1','Histórico');
        $this->payment($this->document('2','2026-11-10'),'2','Actual');
        $sut=new DashboardData();
        self::assertSame(3.0,$sut->paymentMethods(['period'=>'all'])['total']);
        self::assertSame('3.00',$sut->paymentMethods(['period'=>'all'])['total_exact']);
        self::assertSame('2.00',$sut->paymentMethods($this->filters())['total_exact']);
        self::assertSame('0.00',$sut->paymentMethods(array_replace($this->filters(),[
            'establishment_id'=>$this->input['establishment_id']+1]))['total_exact']);
    }

    public function test_parent_and_receipts_share_one_snapshot_and_foreign_transactions_are_rejected(): void
    {
        $id=$this->document('3','2026-11-10'); $this->payment($id,'1','Histórico');
        $this->app['config']->set('database.connections.dashboard_payment_writer',$this->db->getConfig());
        $writer=$this->app['db']->connection('dashboard_payment_writer'); $changed=false;
        $this->db->listen(function ($event) use ($writer,$id,&$changed) {
            if (!$changed && str_contains($event->sql,'from "documents"')) {
                $changed=true;
                $writer->table('document_payments')->insert([
                    'document_id'=>$id,'date_of_payment'=>'2026-11-10','payment_method_type_id'=>'01','payment'=>'2','change'=>'0',
                    'currency_type_id'=>'VES','exchange_rate'=>'1','original_amount'=>'2','operation_key'=>(string)\Illuminate\Support\Str::uuid(),
                    'payment_method_snapshot'=>json_encode(['description'=>'Histórico'],JSON_THROW_ON_ERROR),
                    'calculation_snapshot'=>json_encode(D::snapshot(['payment'=>'2']),JSON_THROW_ON_ERROR),
                ]);
            }
        });
        try {
            $sut=new DashboardData();
            $during=$sut->paymentMethods($this->filters()); self::assertTrue($changed);
            self::assertSame(1.0,$during['total']);
            self::assertSame('1.00',$during['total_exact']);
            self::assertSame('3.00',$sut->paymentMethods($this->filters())['total_exact']);
            try {
                $this->db->transaction(fn()=>$sut->paymentMethods($this->filters()));
                self::fail('El reporte no puede entrar en una escritura ajena.');
            } catch (\LogicException $error) {
                self::assertStringContainsString('lectura consistente',$error->getMessage());
            }
            self::assertSame(0,$this->db->transactionLevel());
        } finally {$writer->disconnect();}
    }

    public function test_notes_share_the_applied_amount_basis_and_exclude_changed_or_voided_parents(): void
    {
        foreach ([[false,'05','1/3'],[true,'05','100'],[false,'11','100']] as [$changed,$state,$amount]) {
            $controller=new \App\Http\Controllers\Tenant\SaleNoteController();
            $note=\App\Models\Tenant\SaleNote::create($controller->mergeData(array_replace($this->input,[
                'id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]])));
            $snapshot=$note->calculation_snapshot; $snapshot['values']['total']=D::fraction('1');
            $snapshot['values']['exchange_rate_sale']=D::fraction('0.01500000');
            $this->db->table('sale_notes')->where('id',$note->id)->update([
                'date_of_issue'=>'2026-11-10','changed'=>$changed,'state_type_id'=>$state,
                'currency_type_id'=>'USD','exchange_rate_sale'=>'0.01500000','total'=>'1',
                'calculation_snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR),
            ]);
            $this->db->table('sale_note_payments')->insert([
                'sale_note_id'=>$note->id,'date_of_payment'=>'2026-11-10','payment_method_type_id'=>'01',
                'payment'=>D::represent($amount),'payment_method_snapshot'=>json_encode(['description'=>'Nota histórica'],JSON_THROW_ON_ERROR),
                'calculation_snapshot'=>json_encode(D::snapshot(['payment'=>$amount]),JSON_THROW_ON_ERROR),
            ]);
        }
        $result=(new DashboardData())->paymentMethods($this->filters());
        self::assertSame(['Nota histórica'],$result['labels']);
        self::assertSame('0.01',$result['total_exact']);
        self::assertTrue(D::readRequired($result,'total')->isEqualTo('0.005'));
        self::assertSame([['n'=>'1','d'=>'200']],$result['values_fractions']);
    }

    public function test_legacy_receipts_prefer_the_recorded_movement_and_keep_a_numeric_text_label(): void
    {
        $id=$this->document('1','2026-11-10');
        $this->payment($id,'0.1','');
        $first=$this->db->table('document_payments')->where('document_id',$id)->value('id');
        $this->db->table('document_payments')->where('id',$first)->update(['payment_method_snapshot'=>'{}']);
        $this->db->table('global_payments')->insert([
            'fiscal_environment'=>'demo','destination_type'=>\App\Models\Tenant\BankAccount::class,
            'payment_id'=>$first,'payment_type'=>\App\Models\Tenant\DocumentPayment::class,
            'report_context'=>json_encode(['method'=>['description'=>'Método histórico de movimiento']],JSON_THROW_ON_ERROR),
        ]);
        $this->payment($id,'0.2','');
        $this->db->table('payment_method_types')->where('id','01')->update(['description'=>'0']);
        $result=(new DashboardData())->paymentMethods($this->filters());
        self::assertSame(['0','Método histórico de movimiento'],$result['labels']);
        self::assertSame(['0.20','0.10'],$result['values_exact']);
        self::assertTrue(D::readRequired($result,'total')->isEqualTo('0.3'));
    }
}
