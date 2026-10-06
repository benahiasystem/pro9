<?php
namespace Tests\Unit;
use App\Services\Fiscal\FiscalDocumentPersistence;
use Illuminate\Support\Facades\{Schema,DB};
use Tests\TestCase;
class FiscalLineCalculationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.tenant',['driver'=>'sqlite','database'=>':memory:','prefix'=>'']);
        Schema::connection('tenant')->create('cat_unit_types',function($t) {$t->string('id');$t->string('hka_code');$t->boolean('active')->default(true);});
        DB::connection('tenant')->table('cat_unit_types')->insert(['id'=>'UND','hka_code'=>'C62']);
    }
    private function line(array $changes=[]): array
    {
        return FiscalDocumentPersistence::line(array_replace(['quantity'=>2,'unit_price'=>116,'unit_value'=>999,'total_value'=>999,'total_base_igv'=>999,
            'affectation_igv_type_id'=>'10','item'=>['unit_type_id'=>'UND','cod_digemid'=>'retired']],$changes));
    }
    public function test_totals_are_recomputed_and_retired_item_metadata_is_removed(): void
    {
        $l=$this->line();self::assertSame(200.0,$l['total_value']);self::assertSame(32.0,$l['total_igv']);self::assertSame(232.0,$l['total']);
        self::assertSame('G',$l['iva_rate']['code']);self::assertArrayNotHasKey('cod_digemid',$l['item']);self::assertSame('C62',$l['item']['hka_unit_code']);
    }
    public function test_base_and_non_base_discounts_preserve_the_existing_iva_policy(): void
    {
        $l=$this->line(['discounts'=>[['discount_type_id'=>'00','factor'=>0.1],['discount_type_id'=>'01','factor'=>0.05]]]);
        self::assertSame(180.0,$l['total_base_igv']);self::assertSame(168.4,$l['total_value']);self::assertSame(28.8,$l['total_igv']);self::assertSame(197.2,$l['total']);
        self::assertSame(31.6,$l['total_discount']);
    }
    public function test_exempt_lines_keep_an_explicit_zero_rate_snapshot(): void
    {
        $l=$this->line(['affectation_igv_type_id'=>'20']);self::assertSame(232.0,$l['total']);self::assertSame(0.0,$l['total_igv']);self::assertSame('E',$l['iva_rate']['code']);
    }
    public function test_configured_reduced_iva_is_snapshotted_as_hka_reduced_rate(): void
    {
        config()->set('venezuela.tax.rate',0.08);
        $line=$this->line(['unit_price'=>108]);
        self::assertSame('R',$line['iva_rate']['code']);self::assertSame(8.0,$line['percentage_igv']);self::assertSame(16.0,$line['total_igv']);
    }
    public function test_inactive_units_cannot_be_persisted(): void
    {
        DB::connection('tenant')->table('cat_unit_types')->where('id','UND')->update(['active'=>false]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);$this->line();
    }
    public function test_fiscal_snapshots_reject_nested_credentials(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        FiscalDocumentPersistence::assertNoSecrets(['conditional_data'=>['jwt'=>'do-not-persist']]);
    }
}
