<?php

namespace Tests\Unit;

use App\Support\Exact\{Decimal, Payables};
use App\Models\Tenant\User;
use Illuminate\Support\Facades\{DB, Schema};
use Modules\Finance\Http\Controllers\ToPayController;
use Illuminate\Http\Request;
use Tests\TestCase;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
class ExactPayablesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp(); DB::purge('tenant');
        config(['database.connections.tenant'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'']]);
        auth()->setUser((new User())->forceFill(['id'=>1, 'type'=>'admin']));
        Schema::connection('tenant')->create('persons', function ($t) { $t->increments('id'); $t->string('name'); });
        DB::connection('tenant')->table('persons')->insert(['id'=>1, 'name'=>'Proveedor']);
        Schema::connection('tenant')->create('users',function ($t) { $t->increments('id'); $t->string('name'); });
        DB::connection('tenant')->table('users')->insert(['id'=>1,'name'=>'Operador']);
        foreach (['purchases','expenses'] as $table) Schema::connection('tenant')->create($table, function ($t) {
            $t->increments('id'); $t->integer('supplier_id')->default(1); $t->integer('user_id')->default(1); $t->integer('establishment_id')->default(1);
            $t->string('state_type_id')->default('01'); $t->string('document_type_id')->default('01'); $t->date('date_of_issue')->default('2026-10-17');
            $t->date('date_of_due')->nullable(); $t->string('series')->default('F'); $t->integer('number')->default(1);
            $t->text('total'); $t->text('total_payable')->nullable(); $t->json('calculation_snapshot')->nullable();
            $t->string('currency_type_id')->default('VES'); $t->text('exchange_rate_sale')->default('3.00000000');
        });
        foreach (['purchase_payments'=>'purchase_id', 'expense_payments'=>'expense_id'] as $table=>$foreign) Schema::connection('tenant')->create($table, function ($t) use ($foreign) {
            $t->increments('id'); $t->integer($foreign); $t->text('payment'); $t->json('calculation_snapshot')->nullable();
            $t->date('date_of_payment'); $t->dateTime('reverted_at')->nullable();
        });
    }

    public function test_liquidated_third_has_no_fictitious_payable_balance(): void
    {
        DB::connection('tenant')->table('purchases')->insert(['id'=>1, 'total'=>'0.333333333333', 'total_payable'=>'0.33',
            'calculation_snapshot'=>json_encode(Decimal::snapshot(['total'=>'1/3', 'total_payable'=>'0.33']))]);
        DB::connection('tenant')->table('purchase_payments')->insert(['purchase_id'=>1,'payment'=>'0.33','date_of_payment'=>'2026-10-17']);
        $row = iterator_to_array(Payables::dataset([]))[0];
        self::assertSame('0', $row['total_to_pay']);
        self::assertSame('1/300', (string)Decimal::readRequired($row, 'exact_balance'));
        self::assertSame('-1/300', (string)Decimal::readRequired($row, 'settlement_adjustment'));
        self::assertSame(0, Payables::summary([$row])['count']);
    }

    public function test_payment_fractions_reversals_batches_and_page_totals_are_consistent(): void
    {
        DB::connection('tenant')->table('purchases')->insert(['id'=>1, 'total'=>'1']);
        for ($id=1; $id<=3; $id++) DB::connection('tenant')->table('purchase_payments')->insert(['purchase_id'=>1,'payment'=>'0.333333333333',
            'calculation_snapshot'=>json_encode(Decimal::snapshot(['payment'=>'1/3'])), 'date_of_payment'=>'2026-10-17']);
        DB::connection('tenant')->table('purchase_payments')->insert(['purchase_id'=>1, 'payment'=>'99','date_of_payment'=>'2026-10-17','reverted_at'=>'2026-10-17 12:00:00']);
        for ($id=2; $id<=502; $id++) DB::connection('tenant')->table('purchases')->insert(['id'=>$id, 'total'=>'500']);
        DB::connection('tenant')->table('expenses')->insert(['id'=>1, 'total'=>'10', 'currency_type_id'=>'USD']);
        DB::connection('tenant')->enableQueryLog();
        $dataset = Payables::dataset([]);
        self::assertCount(503, $dataset);
        self::assertLessThanOrEqual(11, count(DB::connection('tenant')->getQueryLog()));
        $first = iterator_to_array($dataset)[0];
        self::assertSame('1', $first['total_payment']);
        self::assertSame('0', $first['total_to_pay']);
        $response = (new ToPayController())->records(new Request(['page'=>2,'per_page'=>50]));
        self::assertCount(50, $response['records']);
        self::assertSame(502, $response['meta']['total']);
        self::assertSame('250500', $response['summary']['VES_balance']);
        self::assertSame('10', $response['summary']['USD_balance']);
        self::assertSame('250530', $response['summary']['consolidated_balance_ves']);
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
