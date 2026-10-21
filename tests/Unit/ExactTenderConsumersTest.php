<?php

namespace Tests\Unit;

use App\Models\Tenant\{SaleNote, SaleNotePayment};
use App\Support\Exact\{Decimal as D, SaleNoteBalance, TenderAllocation};
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
class ExactTenderConsumersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp(); DB::purge('tenant');
        config(['database.connections.tenant'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'']]);
        Schema::connection('tenant')->create('sale_notes',function ($t) {
            $t->increments('id'); $t->text('total'); $t->text('total_payable'); $t->json('calculation_snapshot')->nullable();
            $t->boolean('total_canceled')->default(false); $t->timestamps();
        });
        Schema::connection('tenant')->create('sale_note_payments',function ($t) {
            $t->increments('id'); $t->integer('sale_note_id'); $t->text('payment'); $t->text('change')->nullable();
            $t->string('payment_method_type_id')->nullable(); $t->json('calculation_snapshot')->nullable();
        });
        Schema::connection('tenant')->create('payment_method_types',function ($t) {
            $t->string('id'); $t->boolean('is_active')->default(true);
        });
        DB::connection('tenant')->table('payment_method_types')->insert([['id'=>'01'],['id'=>'04']]);
        foreach (['global_payments','payment_files','payment_link_payments'] as $name) Schema::connection('tenant')->create($name,function ($t) {
            $t->increments('id'); $t->string('payment_type'); $t->integer('payment_id');
        });
    }

    public function test_shared_php_javascript_cases_conserve_actual_tender_and_nonnegative_payments(): void
    {
        foreach (json_decode(file_get_contents(base_path('tests/fixtures/exact_tenders.json')),true) as $case) {
            try { $rows = TenderAllocation::calculate($case['payable'],$case['rows']); }
            catch (ValidationException $error) { self::assertTrue($case['invalid'] ?? false,$case['name']); continue; }
            self::assertFalse($case['invalid'] ?? false,$case['name']);
            self::assertSame($case['net'],array_column($rows,'payment'),$case['name']);
            self::assertSame($case['change'],array_column($rows,'change'),$case['name']);
            foreach ($rows as $row) {
                $net = D::readRequired($row,'payment'); $change = D::readRequired($row,'change');
                self::assertFalse($net->isNegative());
                self::assertTrue($net->plus($change)->isEqualTo(D::readRequired($row,'tendered_amount')));
                self::assertTrue(D::readRequired($row,'requested_payment')->plus(D::readRequired($row,'cash_adjustment'))
                    ->isEqualTo(D::readRequired($row,'tendered_amount')));
            }
        }
    }

    public function test_controller_persists_tender_change_and_adjustment_atomically_and_uses_payable_status(): void
    {
        DB::connection('tenant')->table('sale_notes')->insert(['id'=>1,'total'=>'500.004','total_payable'=>'500',
            'calculation_snapshot'=>json_encode(D::snapshot(['total'=>'500.004','total_payable'=>'500']))]);
        $note = SaleNote::withoutEagerLoads()->findOrFail(1);
        DB::connection('tenant')->transaction(function () use ($note) {
            (new \App\Http\Controllers\Tenant\SaleNoteController())->savePayments($note,[
                ['payment'=>'400','payment_method_type_id'=>'04'],['payment'=>'10','payment_method_type_id'=>'01'],
                ['payment'=>'100.004','payment_method_type_id'=>'01']]);
        });
        $values = SaleNoteBalance::values($note);
        self::assertSame('500',(string)$values['total_paid']);
        self::assertTrue($values['total_difference']->isZero());
        self::assertSame('-1/250',(string)$values['settlement_adjustment']);
        $last = SaleNotePayment::withoutEagerLoads()->orderByDesc('id')->firstOrFail();
        self::assertSame('25001/250',(string)$last->exactValue('requested_payment'));
        self::assertSame('-1/250',(string)$last->exactValue('cash_adjustment'));
        $dto = SaleNoteBalance::listing(SaleNote::query());
        self::assertSame('0',$dto['total_pending_paid_pen']);
        self::assertSame('500.004',$dto['total_pen']);
        self::assertSame('500',$dto['total_payable']);
        $last->delete();
        self::assertSame('100',(string)SaleNoteBalance::values($note)['total_difference']);
    }

    public function test_subprojection_debt_is_not_reported_as_paid_and_snapshot_is_reusable(): void
    {
        DB::connection('tenant')->table('sale_notes')->insert(['id'=>1,'total'=>'1','total_payable'=>'1']);
        DB::connection('tenant')->table('sale_note_payments')->insert(['sale_note_id'=>1,'payment'=>'1',
            'calculation_snapshot'=>json_encode(D::snapshot(['payment'=>'99999999999999/100000000000000']))]);
        $values = SaleNoteBalance::values(SaleNote::withoutEagerLoads()->findOrFail(1));
        self::assertFalse($values['total_difference']->isZero());
        $dto = SaleNoteBalance::transport($values);
        self::assertSame('0',$dto['total_difference']);
        self::assertSame('1/100000000000000',(string)D::readRequired($dto,'total_difference'));
    }

    public function test_failed_tender_does_not_insert_any_payment(): void
    {
        DB::connection('tenant')->table('sale_notes')->insert(['id'=>1,'total'=>'500','total_payable'=>'500']);
        try {
            DB::connection('tenant')->transaction(fn()=>(new \App\Http\Controllers\Tenant\SaleNoteController())->savePayments(
                SaleNote::withoutEagerLoads()->findOrFail(1),[['payment'=>'501','payment_method_type_id'=>'04']]));
            self::fail('No se puede convertir transferencia excedente en efectivo.');
        } catch (ValidationException $error) { self::assertSame(0,DB::connection('tenant')->table('sale_note_payments')->count()); }
    }

    public function test_batch_validates_methods_once_and_balance_get_does_not_write_or_use_stale_paid_flag(): void
    {
        $db=DB::connection('tenant');
        $db->table('sale_notes')->insert(['id'=>1,'total'=>'30','total_payable'=>'30']);
        $methodQueries=0;
        $db->listen(function ($query) use (&$methodQueries) { if (str_contains($query->sql,'from "payment_method_types"')) $methodQueries++; });
        (new \App\Http\Controllers\Tenant\SaleNoteController())->savePayments(SaleNote::withoutEagerLoads()->findOrFail(1),
            array_fill(0,30,['payment'=>'1','payment_method_type_id'=>'01']));
        self::assertSame(1,$methodQueries);
        $db->table('sale_notes')->where('id',1)->update(['total_canceled'=>false]);
        $writes=0;
        $db->listen(function ($query) use (&$writes) { if (preg_match('/^(insert|update|delete)/i',$query->sql)) $writes++; });
        $result=(new \App\Http\Controllers\Tenant\SaleNotePaymentController())->document(1);
        self::assertTrue($result['paid']);
        self::assertSame('30',$result['total_paid']);
        self::assertSame(0,$writes);
        self::assertFalse((bool)$db->table('sale_notes')->where('id',1)->value('total_canceled'));
    }

    public function test_failure_after_an_insert_rolls_back_and_does_not_leave_settlement_deferred(): void
    {
        $db=DB::connection('tenant');$db->table('sale_notes')->insert(['id'=>1,'total'=>'5','total_payable'=>'5']);
        $note=SaleNote::withoutEagerLoads()->findOrFail(1);
        try {
            \App\Support\Exact\Settlement::defer($note,function () use ($note) {
                $note->payments()->create(['payment'=>'1','payment_method_type_id'=>'01']);
                throw new \RuntimeException('Fixture failure');
            });
            self::fail('El lote fallido debe revertirse.');
        } catch (\RuntimeException $error) { self::assertSame('Fixture failure',$error->getMessage()); }
        self::assertSame(0,$note->payments()->count());
        (new \App\Http\Controllers\Tenant\SaleNoteController())->savePayments($note,[['payment'=>'5','payment_method_type_id'=>'01']]);
        self::assertTrue(SaleNote::withoutEagerLoads()->findOrFail($note->id)->total_canceled);
    }

    public function test_payment_request_rejects_precision_range_nonpositive_and_binary_inputs(): void
    {
        DB::connection('tenant')->table('sale_notes')->insert(['id'=>1,'total'=>'5','total_payable'=>'5']);
        $data=['sale_note_id'=>1,'date_of_payment'=>'2026-10-19','payment_method_type_id'=>'01','payment_destination_id'=>'cash','payment'=>'1.004'];
        $rules=(new \App\Http\Requests\Tenant\SaleNotePaymentRequest())->rules();
        self::assertTrue(\Illuminate\Support\Facades\Validator::make($data,$rules)->passes());
        foreach ([0.1,true,'0','-1','0.1234567890123','1000000000000000000'] as $input) {
            $validator=\Illuminate\Support\Facades\Validator::make(array_replace($data,['payment'=>$input]),$rules);
            self::assertTrue($validator->fails());self::assertTrue($validator->errors()->has('payment'));
        }
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
