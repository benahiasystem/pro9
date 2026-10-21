<?php

namespace Tests\Unit;

use App\Models\Tenant\{SaleNote, SaleNotePayment};
use App\Support\Exact\{Decimal as D, SaleNoteBalance};
use Illuminate\Http\{JsonResponse, Request};
use PHPUnit\Framework\TestCase;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
class ExactMySqlContinuationTest extends TestCase
{
    public function test_tender_credit_check_and_reversal_use_the_same_mysql_fractions(): void
    {
        if (getenv('PRO9_FISCAL_MYSQL_TESTS') !== '1') $this->markTestSkipped('Requiere MySQL temporal aislado.');
        $fixture=new class extends FiscalEmissionSchemaTest {
            public bool $ready=false;
            public function start(): array { parent::setUp();$this->ready=true;return (new \ReflectionMethod(parent::class,'bootHkaFixture'))->invoke($this); }
            public function stop(): void { parent::tearDown(); }
        };
        try {
            [$db,$app,$input]=$fixture->start();
            $factory=$this->createMock(\Illuminate\Contracts\Routing\ResponseFactory::class);
            $factory->method('json')->willReturnCallback(fn($data,$status=200)=>new JsonResponse($data,$status));
            $app->instance(\Illuminate\Contracts\Routing\ResponseFactory::class,$factory);
            \Carbon\Carbon::setTestNow('2026-10-19 12:00:00');
            $db->table('cash')->insert(['user_id'=>auth()->id(),'date_opening'=>'2026-10-19','time_opening'=>'12:00:00','state'=>true]);
            $note=new SaleNote();$note->fill(array_intersect_key($input,array_flip($note->getFillable())));
            $note->forceFill(['prefix'=>'NV','document_type_id'=>'80','series'=>'NV01','number'=>'#','total'=>'0',
                'external_id'=>(string)\Illuminate\Support\Str::uuid(),'user_id'=>auth()->id(),'state_type_id'=>'01',
                'due_date'=>'2026-10-01','date_of_issue'=>'2026-10-01'])->save();
            $note->items()->create(array_replace($input['items'][0],['quantity'=>'1','unit_price'=>'500.004']));
            $note=SaleNote::withoutEagerLoads()->findOrFail($note->id);
            self::assertSame('500.004',D::represent($note->exactValue('total')));
            $credit=new \Modules\Finance\Http\Controllers\UnpaidController();
            $request=Request::create('/','GET',['model'=>'sale_note']);
            self::assertSame(18,$credit->customerExpiredDays($input['customer_id'],$request)->getData(true)['max_expired_days']);
            (new \App\Http\Controllers\Tenant\SaleNoteController())->savePayments($note,[
                ['payment'=>'400','payment_method_type_id'=>'04','date_of_payment'=>'2026-10-19'],
                ['payment'=>'10','payment_method_type_id'=>'01','date_of_payment'=>'2026-10-19'],
                ['payment'=>'100.004','payment_method_type_id'=>'01','date_of_payment'=>'2026-10-19'],
            ]);
            self::assertTrue($note->fresh()->total_canceled);
            self::assertTrue(SaleNoteBalance::values($note)['total_difference']->isZero());
            self::assertSame(0,$credit->customerExpiredDays($input['customer_id'],$request)->getData(true)['max_expired_days']);
            $payment=SaleNotePayment::withoutEagerLoads()->where('sale_note_id',$note->id)->orderByDesc('id')->firstOrFail();
            self::assertSame('-1/250',(string)$payment->exactValue('cash_adjustment'));
            self::assertSame('25001/250',(string)$payment->exactValue('requested_payment'));
            $db->beginTransaction();$payment->delete();
            self::assertSame('100',(string)SaleNoteBalance::values($note)['total_difference']);
            $db->rollBack();
            self::assertSame(3,$note->payments()->count());
            self::assertTrue(SaleNoteBalance::values($note)['total_difference']->isZero());
            $recordResource=(new \App\Http\Controllers\Tenant\SaleNotePaymentController())->records($note->id)->toArray($request);
            $row=$recordResource->last();
            self::assertSame('-1/250',(string)D::readRequired($row,'cash_adjustment'));
            SaleNotePayment::withoutEagerLoads()->findOrFail($payment->id)->delete();
            self::assertSame('100',(string)SaleNoteBalance::values($note)['total_difference']);
            self::assertSame(18,$credit->customerExpiredDays($input['customer_id'],$request)->getData(true)['max_expired_days']);

            // Closing a fraction whose twelve-place representation is 1 must not
            // compare that representation against the smaller authoritative debt.
            $db->table('configurations')->update(['auto_send_hka'=>false]);
            $closingInput=array_replace($input,['external_id'=>(string)\Illuminate\Support\Str::uuid(),
                'currency_type_id'=>'USD','exchange_rate_sale'=>'3.00000000','payment_condition_id'=>'02','payments'=>[]]);
            $closingInput['items'][0]=array_replace($closingInput['items'][0],['quantity'=>'1','unit_price'=>'1','affectation_igv_type_id'=>'20']);
            $closing=(new \App\CoreFacturalo\Facturalo())->save($closingInput)->getDocument()->fresh();
            $tiny=D::of(1)->dividedBy('100000000000000000000');
            $closing->guarantee_fund()->create(['amount'=>$tiny,'base'=>'1','percentage'=>'0']);
            $closing->unsetRelation('guarantee_fund');
            self::assertTrue($closing->exactBalance()->isEqualTo(D::of(1)->minus($tiny)));
            $receipt=\App\Services\Fiscal\FiscalDocumentPersistence::payment($closing,[
                'original_amount'=>'1','currency_type_id'=>'USD','exchange_rate'=>'3.00000000',
                'payment_method_type_id'=>'01','date_of_payment'=>'2026-10-19','financial_intermediation'=>'none',
                'operation_key'=>(string)\Illuminate\Support\Str::uuid(),
            ]);
            self::assertTrue($receipt->exactValue('payment')->isEqualTo(D::of(1)->minus($tiny)));
            self::assertTrue($receipt->exactValue('payment_adjustment')->isEqualTo($tiny->negated()));
            self::assertTrue($closing->fresh()->exactBalance()->isZero());
        } finally {
            \Carbon\Carbon::setTestNow();
            if ($fixture->ready) $fixture->stop();
        }
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
