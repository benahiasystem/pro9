<?php

namespace Tests\Unit;

use App\Services\Payments\ChangeDisbursementPlan;
use App\Support\Exact\Decimal as D;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ChangeDisbursementPlanTest extends TestCase
{
    public function test_split_across_currencies_preserves_exact_conversion_and_adjustment(): void
    {
        $result = ChangeDisbursementPlan::calculate('0.70', 'USD', '911.62430000', [
            ['currency_type_id'=>'USD', 'amount'=>'0.20'], ['currency_type_id'=>'VES', 'amount'=>'455.81'],
        ]);
        self::assertSame('0.20', $result['lines'][0]['amount']);
        self::assertSame('455.81', $result['lines'][1]['amount']);
        $sum = D::of($result['calculation_snapshot']['values']['source_equivalent']);
        self::assertSame('0.70', D::represent($sum, 2, true));
        self::assertTrue(D::of($result['calculation_snapshot']['values']['settlement_adjustment'])->isEqualTo($sum->minus('0.70')));
    }

    public function test_shortfalls_overpayments_and_subcent_cash_are_rejected(): void
    {
        foreach ([
            [['currency_type_id'=>'VES', 'amount'=>'900']],
            [['currency_type_id'=>'VES', 'amount'=>'1100']],
            [['currency_type_id'=>'USD', 'amount'=>'1.001']],
        ] as $lines) {
            try {
                ChangeDisbursementPlan::calculate('1.00', 'USD', '1000', $lines);
                self::fail('An invalid disbursement must be rejected.');
            } catch (ValidationException $exception) {
                self::assertNotEmpty($exception->errors());
            }
        }
    }

    public function test_change_in_bolivars_can_be_split_between_methods(): void
    {
        $result = ChangeDisbursementPlan::calculate('100.00', 'VES', '100', [
            ['currency_type_id'=>'USD', 'amount'=>'0.40'], ['currency_type_id'=>'VES', 'amount'=>'60.00'],
        ]);
        self::assertSame('100', $result['source_equivalent']);
        self::assertTrue(D::of($result['calculation_snapshot']['values']['settlement_adjustment'])->isZero());
    }

    public function test_methods_are_resolved_from_active_authorized_tenant_catalog(): void
    {
        config(['database.connections.tenant'=>['driver'=>'sqlite', 'database'=>':memory:', 'prefix'=>'']]);
        \Illuminate\Support\Facades\DB::purge('tenant');
        $schema = \Illuminate\Support\Facades\Schema::connection('tenant');
        $schema->create('cat_currency_types', function ($table) { $table->string('id')->primary(); $table->boolean('active'); });
        $schema->create('establishments', function ($table) { $table->id(); });
        $schema->create('bank_accounts', function ($table) { $table->id(); $table->boolean('status'); $table->string('currency_type_id'); $table->integer('establishment_id')->nullable(); });
        $schema->create('payment_method_types', function ($table) {
            $table->string('id')->primary(); $table->string('collection_kind'); $table->string('currency_type_id');
            $table->boolean('is_active'); $table->boolean('available_for_change'); $table->integer('bank_account_id')->nullable();
        });
        $db = \Illuminate\Support\Facades\DB::connection('tenant');
        $db->table('cat_currency_types')->insert([['id'=>'USD','active'=>1], ['id'=>'VES','active'=>1]]);
        $db->table('establishments')->insert(['id'=>7]);
        $db->table('bank_accounts')->insert(['id'=>1,'status'=>1,'currency_type_id'=>'VES','establishment_id'=>7]);
        $db->table('payment_method_types')->insert([
            ['id'=>'10','collection_kind'=>'cash','currency_type_id'=>'USD','is_active'=>1,'available_for_change'=>1,'bank_account_id'=>null],
            ['id'=>'11','collection_kind'=>'account','currency_type_id'=>'VES','is_active'=>1,'available_for_change'=>1,'bank_account_id'=>1],
            ['id'=>'12','collection_kind'=>'cash','currency_type_id'=>'VES','is_active'=>1,'available_for_change'=>0,'bank_account_id'=>null],
        ]);
        $lines = [['payment_method_type_id'=>'10','amount'=>'0.40','currency_type_id'=>'VES'],
            ['payment_method_type_id'=>'11','amount'=>'60.00','currency_type_id'=>'USD']];
        $plan = $db->transaction(fn () => ChangeDisbursementPlan::forMethods('100.00', 'VES', '100', $lines, 7, true));
        self::assertSame('USD', $plan['lines'][0]['currency_type_id'], 'client currency cannot override the method');
        self::assertSame('VES', $plan['lines'][1]['currency_type_id']);
        self::assertSame('cash', $plan['lines'][0]['payment_destination_id']);
        self::assertSame(1, $plan['lines'][1]['payment_destination_id']);
        $cash = ChangeDisbursementPlan::forMethods('100', 'VES', '100', [['payment_method_type_id'=>'12','amount'=>'100']], 7);
        self::assertSame('cash', $cash['lines'][0]['payment_destination_id']);
        $db->table('bank_accounts')->where('id', 1)->update(['establishment_id'=>null]);
        $mobile = ChangeDisbursementPlan::forMethods('100', 'VES', '100', [['payment_method_type_id'=>'11','amount'=>'100']], 7);
        self::assertSame(1, $mobile['lines'][0]['payment_destination_id']);
        $db->table('establishments')->insert(['id'=>8]);
        foreach ([['payment_method_type_id'=>'11','amount'=>'100']] as $line) {
            try {
                ChangeDisbursementPlan::forMethods('100', 'VES', '100', [$line], 8);
                self::fail('Disabled methods and accounts of another branch must be rejected.');
            } catch (ValidationException $exception) { self::assertNotEmpty($exception->errors()); }
        }
    }
}
