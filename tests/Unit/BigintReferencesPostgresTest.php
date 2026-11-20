<?php

namespace Tests\Unit;

use App\Database\SchemaInspector;
use App\Models\Tenant\{BankAccount,TransferAccountPayment};
use App\Support\Exact\Decimal;
use Illuminate\Http\Request;
use Modules\Finance\Http\Controllers\BalanceController;

/** @group postgres-integration */
final class BigintReferencesPostgresTest extends \PHPUnit\Framework\TestCase
{
    private $fixture;
    private $db;

    protected function setUp(): void
    {
        $this->fixture=new class extends FiscalEmissionSchemaTest {
            public function start(): void {parent::setUp();}
            public function stop(): void {parent::tearDown();}
        };
        $this->fixture->start();
        [$this->db]=(new \ReflectionMethod($this->fixture,'bootHkaFixture'))->invoke($this->fixture,false);
        \Tests\Support\PostgresTestEnvironment::assertServer($this->db);
    }

    protected function tearDown(): void
    {
        try {if ($this->fixture) $this->fixture->stop();}
        finally {parent::tearDown();}
    }

    /** @dataProvider technicalReferences */
    public function test_technical_references_keep_the_full_bigint_key_range(string $table,string $field): void
    {
        $columns=[];foreach(SchemaInspector::columns($this->db,$table) as $column)$columns[$column->name]=$column;
        self::assertSame('bigint',$columns[$field]->type,$table.'.'.$field.' must match its bigint target key');
        self::assertSame('9223372036854775807',$this->db->selectOne('SELECT CAST(CAST(? AS bigint) AS text) AS id',['9223372036854775807'])->id);
    }

    public static function technicalReferences(): array
    {
        return [
            'payment attachment'=>['payment_files','payment_id'],
            'restaurant table'=>['restaurant_item_order_statuses','table_id'],
            'restaurant item'=>['restaurant_item_order_statuses','item_id'],
            'temporary kardex'=>['temporary_kardex_records','inventory_kardex_id'],
            'origin branch'=>['origin_addresses','establishment_id'],
            'tip origin'=>['tips','origin_id'],
            'global destination'=>['global_payments','destination_id'],
            'global payment'=>['global_payments','payment_id'],
            'order zone'=>['order_notes','order_zone_id'],
            'order person'=>['order_notes','order_person_id'],
            'payment link'=>['payment_links','payment_id'],
            'activity origin'=>['system_activity_logs','origin_id'],
            'weighted cost origin'=>['weighted_average_costs','origin_id'],
        ];
    }

    public function test_no_technical_bigint_reference_has_a_check_that_narrows_its_target_key_range(): void
    {
        $checks=$this->db->select("SELECT t.relname AS table_name,a.attname AS field_name,
            pg_get_expr(c.conbin,c.conrelid) AS expression
            FROM pg_catalog.pg_constraint c
            JOIN pg_catalog.pg_class t ON t.oid=c.conrelid
            JOIN pg_catalog.pg_namespace n ON n.oid=t.relnamespace
            JOIN pg_catalog.pg_attribute a ON a.attrelid=t.oid AND a.attnum=c.conkey[1]
            WHERE c.contype='c' AND n.nspname='public' AND cardinality(c.conkey)=1
                AND a.atttypid='pg_catalog.int8'::regtype
                AND (a.attname='id' OR right(a.attname,3)='_id')");
        self::assertNotEmpty($checks);$violations=[];
        foreach($checks as $check){
            // Constraint expressions and column names come only from our marked temporary schema.
            $field=$this->db->getQueryGrammar()->wrap($check->field_name);
            $accepted=$this->db->selectOne('SELECT ('.$check->expression.') AS accepted FROM (SELECT CAST(? AS bigint) AS '.$field.') AS probe',['9223372036854775807'])->accepted;
            if(!(bool)$accepted)$violations[$check->table_name.'.'.$check->field_name]=$check->expression;
        }
        self::assertSame([],$violations,'Technical keys must accept the full signed bigint target range: '.json_encode($violations,JSON_THROW_ON_ERROR));
    }

    public function test_transfer_persists_and_resolves_distinct_payment_and_account_keys_above_javascript_integer_range(): void
    {
        $accounts=[];
        foreach(['9007199254740900','9007199254740901'] as $id){
            $account=new BankAccount(['bank_id'=>$this->db->table('banks')->value('id'),
                'description'=>'Cuenta temporal '.$id,'number'=>'TEMP-'.$id,'currency_type_id'=>'VES','initial_balance'=>'0']);
            $account->id=$id;$account->save();$accounts[]=$account;
        }
        $this->db->selectOne("SELECT setval(pg_get_serial_sequence('transfer_account_payments','id'), CAST(? AS bigint), false)",['9007199254740993']);
        (new BalanceController())->makeTransfer(Request::create('/','POST',['data'=>[
            'from'=>'B:'.$accounts[0]->id,'to'=>'B:'.$accounts[1]->id,'amount_transform'=>'0.1','amount_transform_temp'=>'0.1']]));
        $payments=TransferAccountPayment::orderBy('id')->get();
        self::assertSame(['9007199254740993','9007199254740994'],$payments->map(fn($payment)=>(string)$payment->id)->all());
        foreach($payments as $index=>$payment){
            $global=$payment->global_payment;
            self::assertSame((string)$payment->id,(string)$global->payment_id);
            self::assertSame((string)$payment->id,(string)$global->payment->id);
            self::assertSame((string)$accounts[1-$index]->id,(string)$global->destination_id);
            self::assertSame((string)$accounts[1-$index]->id,(string)$global->destination->id);
            self::assertTrue($payment->exactValue('amount')->isEqualTo($index===0?'0.1':'-0.1'));
        }
        self::assertTrue(Decimal::sum($payments->map(fn($payment)=>$payment->exactValue('amount')))->isZero());
        self::assertSame(0,$this->db->transactionLevel());
    }
}
