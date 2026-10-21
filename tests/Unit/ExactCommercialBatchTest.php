<?php

namespace Tests\Unit;

use App\Models\Tenant\{SaleNote, SaleNoteItem};
use App\Support\Exact\{CommercialCalculation as C, Decimal as D, LineCalculation};
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
class ExactCommercialBatchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp(); DB::purge('tenant');
        config(['database.connections.tenant'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'']]);
        Schema::connection('tenant')->create('sale_notes',function ($t) {
            $t->increments('id');$t->json('calculation_snapshot')->nullable();$t->json('discounts')->nullable();$t->json('charges')->nullable();
            $t->boolean('point_system')->default(false);$t->boolean('created_from_pos')->default(false);$t->timestamps();
            foreach (['total','total_value','total_taxed','total_exonerated','total_exportation','total_igv','total_taxes','total_other_taxes',
                'total_discount','total_charge','total_prepayment','total_payable','settlement_adjustment'] as $field) $t->text($field)->default('0');
        });
        Schema::connection('tenant')->create('sale_note_items',function ($t) {
            $t->increments('id');$t->integer('sale_note_id');$t->string('affectation_igv_type_id');$t->json('calculation_snapshot')->nullable();
            $t->json('discounts')->nullable();$t->json('charges')->nullable();
            foreach (['quantity','unit_price','total','total_value','total_base_igv','total_igv','total_taxes','total_discount','total_charge','total_other_taxes'] as $field) $t->text($field)->default('0');
        });
        DB::connection('tenant')->table('sale_notes')->insert(['id'=>1,'discounts'=>json_encode([['discount_type_id'=>'03','amount'=>'1']])]);
    }

    private function addLine(int $id): void
    {
        $line = LineCalculation::calculate(['quantity'=>'1','unit_price'=>'1','affectation_igv_type_id'=>'20'],'0');
        $values = array_intersect_key($line,array_flip(['quantity','unit_price','total','total_value','total_base_igv','total_igv','total_taxes','total_discount','total_charge','total_other_taxes']));
        DB::connection('tenant')->table('sale_note_items')->insert($values + ['id'=>$id,'sale_note_id'=>1,'affectation_igv_type_id'=>'20',
            'calculation_snapshot'=>json_encode($line['calculation_snapshot'])]);
        C::afterLine(SaleNoteItem::withoutEagerLoads()->findOrFail($id));
    }

    public function test_partial_sets_do_not_recalculate_and_the_final_header_and_shares_are_atomic(): void
    {
        $owner = SaleNote::withoutEagerLoads()->findOrFail(1);
        C::defer($owner,function () { foreach ([1,2,3] as $id) $this->addLine($id); });
        self::assertSame('2',(string)$owner->exactValue('total'));
        foreach (DB::connection('tenant')->table('sale_note_items')->get() as $line) {
            self::assertSame('1/3',(string)D::readRequired($line,'header_discount'));
            self::assertSame('2/3',(string)D::readRequired($line,'report_total'));
        }
    }

    public function test_failure_rolls_back_and_does_not_leave_the_next_operation_deferred(): void
    {
        $owner = SaleNote::withoutEagerLoads()->findOrFail(1);
        try {
            C::defer($owner,function () { $this->addLine(1); throw new \RuntimeException('Fixture failure'); });
            self::fail('La transacción debía fallar.');
        } catch (\RuntimeException $exception) { self::assertSame('Fixture failure',$exception->getMessage()); }
        self::assertSame(0,DB::connection('tenant')->table('sale_note_items')->count());
        C::defer($owner,function () { $this->addLine(1); $this->addLine(2); });
        self::assertSame('1',(string)$owner->exactValue('total'));
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
