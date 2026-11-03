<?php

namespace Tests\Feature;

use App\Models\Tenant\BankAccount;
use App\Services\Payments\Movement;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Finance\Models\GlobalPayment;
use Modules\Finance\Models\IncomePayment;
use Tests\TestCase;

final class BankCommissionPersistenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::purge('tenant');
        config(['fiscal_emission.operation_tables'=>[], 'database.connections.tenant'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'']]);
        $schema = Schema::connection('tenant');
        $schema->create('companies', function (Blueprint $t) { $t->increments('id'); $t->string('fiscal_environment')->default('demo'); $t->string('fiscal_emission_mode')->default('free_form'); });
        $schema->create('cat_currency_types', function (Blueprint $t) { $t->string('id')->primary(); $t->boolean('active'); });
        $schema->create('payment_method_types', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('description'); $t->string('hka_code')->nullable(); $t->string('collection_kind');
            $t->string('currency_type_id'); $t->string('cash_currency_type_id')->nullable(); $t->string('iva_rate_type_id')->nullable();
            $t->integer('bank_account_id')->nullable(); $t->string('commission_percentage')->nullable(); $t->string('minimum_commission')->nullable();
            $t->boolean('is_active')->default(true); $t->boolean('is_cash')->default(false); $t->boolean('is_credit')->default(false); $t->json('calculation_snapshot')->nullable();
        });
        $schema->create('banks', function (Blueprint $t) { $t->increments('id'); $t->string('description'); });
        $schema->create('bank_accounts', function (Blueprint $t) { $t->increments('id'); $t->integer('bank_id')->nullable(); $t->string('number')->nullable(); $t->string('currency_type_id'); $t->boolean('status')->default(true); $t->string('description'); });
        $schema->create('cash', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id'); $t->boolean('state'); });
        $schema->create('income', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id'); $t->string('currency_type_id'); $t->string('exchange_rate_sale'); $t->string('state_type_id')->default('05'); $t->date('date_of_issue'); $t->string('number')->default('1'); });
        $schema->create('income_payments', function (Blueprint $t) {
            $t->increments('id'); $t->integer('income_id'); $t->string('payment_method_type_id'); $t->date('date_of_payment'); $t->string('payment');
            $t->string('currency_type_id')->nullable(); $t->string('original_amount')->nullable(); $t->string('exchange_rate')->nullable();
            $t->json('payment_method_snapshot')->nullable(); $t->json('calculation_snapshot')->nullable(); $t->dateTime('reversed_at')->nullable();
        });
        $schema->create('document_payments', function (Blueprint $t) {
            $t->increments('id'); $t->integer('document_id'); $t->string('payment_method_type_id'); $t->date('date_of_payment');
            $t->string('payment'); $t->string('currency_type_id'); $t->string('original_amount'); $t->string('tax_amount');
            $t->string('exchange_rate'); $t->integer('receipt_parent_id')->nullable(); $t->json('payment_method_snapshot')->nullable();
            $t->json('calculation_snapshot')->nullable(); $t->dateTime('reversed_at')->nullable();
        });
        $schema->create('global_payments', function (Blueprint $t) { $t->increments('id'); $t->integer('payment_id'); $t->string('payment_type'); $t->integer('destination_id'); $t->string('destination_type'); $t->integer('user_id')->nullable(); $t->string('fiscal_environment'); $t->integer('cash_session_id')->nullable(); $t->json('report_context')->nullable(); $t->timestamps(); });
        $schema->create('payment_commissions', function (Blueprint $t) { $t->increments('id'); $t->integer('source_global_payment_id')->unique(); $t->string('currency_type_id'); $t->date('date_of_payment'); $t->string('amount'); $t->json('calculation_snapshot')->nullable(); });
        $db = DB::connection('tenant');
        $db->table('companies')->insert(['id'=>1]);
        $db->table('banks')->insert(['id'=>1,'description'=>'Banco']);
        $db->table('cat_currency_types')->insert(['id'=>'VES','active'=>1]);
        $db->table('bank_accounts')->insert(['id'=>1,'bank_id'=>1,'number'=>'123','currency_type_id'=>'VES','status'=>1,'description'=>'Principal']);
        $db->table('payment_method_types')->insert(['id'=>'04','description'=>'Transferencia','collection_kind'=>'account','currency_type_id'=>'VES','bank_account_id'=>1,'commission_percentage'=>'2','minimum_commission'=>'1']);
        $db->table('income')->insert(['id'=>1,'user_id'=>1,'currency_type_id'=>'VES','exchange_rate_sale'=>'1','date_of_issue'=>'2026-10-26']);
    }

    public function test_method_configuration_requires_compatible_account_and_exact_commissions(): void
    {
        $this->actingAs((new \App\Models\Tenant\User())->forceFill(['id'=>1,'type'=>'admin']));
        \Illuminate\Support\Facades\Route::post('/test-bank-method', [\Modules\Sale\Http\Controllers\PaymentMethodTypeController::class, 'store']);
        $input = ['id'=>'27','description'=>'Banco','collection_kind'=>'account','currency_type_id'=>'VES',
            'bank_id'=>1,'bank_account_id'=>1,'commission_percentage'=>'2.5','minimum_commission'=>'0.25'];
        $this->postJson('/test-bank-method', array_replace($input, ['bank_id'=>2]))->assertStatus(422);
        $this->postJson('/test-bank-method', array_replace($input, ['commission_percentage'=>'100.01']))->assertStatus(422);
        $this->postJson('/test-bank-method', array_replace($input, ['minimum_commission'=>'-1']))->assertStatus(422);
        $this->postJson('/test-bank-method', $input)->assertOk();
        $method = DB::connection('tenant')->table('payment_method_types')->where('id','27')->first();
        self::assertSame(1, $method->bank_account_id);
        self::assertSame('2.500000000000', $method->commission_percentage);
        self::assertSame('0.250000000000', $method->minimum_commission);
    }

    public function test_receipt_and_commission_are_atomic_and_frozen(): void
    {
        $db = DB::connection('tenant');
        $origin = $db->transaction(function () {
            $payment = IncomePayment::create(['income_id'=>1,'payment_method_type_id'=>'04','date_of_payment'=>'2026-10-26',
                'payment'=>'100','currency_type_id'=>'VES','original_amount'=>'100','exchange_rate'=>'1']);
            return $payment->global_payment()->create(['destination_id'=>1,'destination_type'=>BankAccount::class,'user_id'=>1,'fiscal_environment'=>'demo']);
        });
        self::assertSame(2, GlobalPayment::count());
        $fee = GlobalPayment::where('id', '!=', $origin->id)->firstOrFail();
        self::assertSame('100', Movement::row($origin)['amount']);
        self::assertSame('2', Movement::row($fee)['amount']);
        self::assertSame('egress', Movement::row($fee)['direction']);
        self::assertSame('123', $origin->report_context['method']['bank_account_number']);
        self::assertSame('Banco', $origin->report_context['method']['bank_name']);
        $db->transaction(fn () => \App\Services\Payments\ReceiptDestination::attach($origin->payment, ['payment_destination_id'=>1]));
        self::assertSame(2, GlobalPayment::count());
        self::assertSame(1, $db->table('payment_commissions')->count());
        $db->table('payment_method_types')->where('id','04')->update(['commission_percentage'=>'99']);
        $db->table('bank_accounts')->where('id',1)->update(['number'=>'456']);
        self::assertSame('2', Movement::row($fee->fresh())['amount']);
        self::assertSame('123', $origin->fresh()->report_context['method']['bank_account_number']);
        $origin->payment->forceFill(['reversed_at'=>now()])->save();
        self::assertNull(Movement::row($fee->fresh()));
    }

    public function test_minimum_above_receipt_rolls_back_receipt_and_movements(): void
    {
        try {
            \App\Services\Payments\BankCommission::calculate('0.50', null, '0.501');
            self::fail('El mínimo exacto supera el cobro antes del redondeo.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            self::assertArrayHasKey('commission', $exception->errors());
        }
        $db = DB::connection('tenant');
        $db->table('payment_method_types')->where('id','04')->update(['minimum_commission'=>'101']);
        try {
            $db->transaction(function () {
                $payment = IncomePayment::create(['income_id'=>1,'payment_method_type_id'=>'04','date_of_payment'=>'2026-10-26',
                    'payment'=>'100','currency_type_id'=>'VES','original_amount'=>'100','exchange_rate'=>'1']);
                $payment->global_payment()->create(['destination_id'=>1,'destination_type'=>BankAccount::class,'user_id'=>1,'fiscal_environment'=>'demo']);
            });
            self::fail('La comisión mayor al cobro debe rechazarse.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            self::assertArrayHasKey('commission', $exception->errors());
        }
        self::assertSame(0, $db->table('income_payments')->count());
        self::assertSame(0, $db->table('global_payments')->count());
        self::assertSame(0, $db->table('payment_commissions')->count());
    }

    public function test_destination_other_than_the_configured_account_is_rejected(): void
    {
        $db = DB::connection('tenant');
        $db->table('bank_accounts')->insert(['id'=>2,'bank_id'=>1,'number'=>'456','currency_type_id'=>'VES','status'=>1,'description'=>'Otra']);
        try {
            $db->transaction(function () {
                $payment = IncomePayment::create(['income_id'=>1,'payment_method_type_id'=>'04','date_of_payment'=>'2026-10-26',
                    'payment'=>'100','currency_type_id'=>'VES','original_amount'=>'100','exchange_rate'=>'1']);
                \App\Services\Payments\ReceiptDestination::attach($payment, ['payment_destination_id'=>2]);
            });
            self::fail('Debe rechazarse una cuenta distinta de la configurada.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            self::assertArrayHasKey('payment_destination_id', $exception->errors());
        }
        self::assertSame(0, $db->table('income_payments')->count());
        self::assertSame(0, $db->table('global_payments')->count());
    }

    public function test_invoice_commission_uses_principal_plus_igtf_received(): void
    {
        $db = DB::connection('tenant');
        $db->table('document_payments')->insert(['id'=>1,'document_id'=>1,'payment_method_type_id'=>'04',
            'date_of_payment'=>'2026-10-26','payment'=>'100','currency_type_id'=>'VES','original_amount'=>'100',
            'tax_amount'=>'3','exchange_rate'=>'1']);
        $db->transaction(function () {
            $payment = \App\Models\Tenant\DocumentPayment::withoutEagerLoads()->findOrFail(1);
            // The financial adapter reads the authorized parent through the relation.
            $payment->setRelation('associated_record_payment', \Modules\Finance\Models\Income::withoutEagerLoads()->findOrFail(1));
            $global = new GlobalPayment(['payment_id'=>1,'payment_type'=>get_class($payment), 'destination_id'=>1,
                'destination_type'=>BankAccount::class,'user_id'=>1,'fiscal_environment'=>'demo']);
            $global->setRelation('payment', $payment);
            $global->save();
        });
        $fee = $db->table('payment_commissions')->first();
        self::assertSame('2.060000000000', $fee->amount);
        $snapshot = json_decode($fee->calculation_snapshot, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['n'=>'103','d'=>'1'], $snapshot['values']['base']);
    }
}
