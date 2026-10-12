<?php
namespace Tests\Unit;

use App\Services\SpecialTaxRegimePolicy;
use App\Services\Fiscal\{HkaPayloadBuilder, FiscalAmounts};
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SpecialTaxRegimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.tenant', ['driver'=>'sqlite','database'=>':memory:','prefix'=>'']);
        Schema::connection('tenant')->create('companies', function ($t) {
            $t->increments('id'); $t->string('fiscal_emission_mode'); $t->string('fiscal_environment');
        });
        DB::connection('tenant')->table('companies')->insert(['fiscal_emission_mode'=>'digital','fiscal_environment'=>'demo']);
        Schema::connection('tenant')->create('cat_special_tax_regime', function ($t) {
            $t->unsignedInteger('id')->primary(); $t->string('description'); $t->boolean('active');
        });
        foreach (SpecialTaxRegimePolicy::NAMES as $id=>$description) DB::connection('tenant')->table('cat_special_tax_regime')->insert(compact('id','description')+['active'=>1]);
    }

    public static function cases(): array
    {
        $cases=[];
        foreach (SpecialTaxRegimePolicy::NAMES as $id=>$name) foreach (['VES','USD'] as $currency) foreach ([false,true] as $third) $cases[]=[$id,$name,$currency,$third];
        return $cases;
    }

    public static function snapshot($id=1,$currency='VES',$third=false): array
    {
        $d=ThirdPartyInvoiceTest::snapshot('J',$currency);
        if (!$third) $d['fiscal_data']=['transaction_type_id'=>'01'];
        $d['fiscal_data']['special_tax_regime_id']=$id;
        $d['fiscal_data']['catalog_snapshot']['special_tax_regime_id']=['id'=>$id,'description'=>SpecialTaxRegimePolicy::NAMES[$id],'active'=>1];
        return $d;
    }

    /** @dataProvider cases */
    public function test_seven_regimes_both_currencies_and_ownerships_preserve_money_and_iva($id,$name,$currency,$third): void
    {
        $d=self::snapshot($id,$currency,$third);$before=$d;
        $p=(new HkaPayloadBuilder)->build($d)['documentoElectronico'];$h=$p['encabezado'];
        self::assertSame($name,$h['identificacionDocumento']['regimenEspTributacion']);
        self::assertSame('01',$h['identificacionDocumento']['tipoDocumento']);
        self::assertSame($third?'99':'01',$h['identificacionDocumento']['tipoTransaccion']);
        self::assertSame('Interna',$h['identificacionDocumento']['tipoDeVenta']);
        self::assertSame('Contado',$h['identificacionDocumento']['tipoDePago']);
        self::assertSame('VES',$h['identificacionDocumento']['moneda']);
        self::assertSame($currency==='USD'?'4654.32':'116.00',$h['totales']['totalAPagar']);
        self::assertSame('G',$p['detallesItems'][0]['codigoImpuesto']);
        self::assertSame('16.00',$p['detallesItems'][0]['tasaIVA']);
        self::assertSame($d['customer']['name'],$h['comprador']['razonSocial']);
        self::assertSame($third,isset($h['tercero']));
        if ($currency==='USD') self::assertSame('116.00',$h['totalesOtraMoneda']['totalAPagar']);
        self::assertSame($before,$d);
        self::assertArrayNotHasKey('special_tax_regime_id',$h['identificacionDocumento']);
    }

    public function test_selection_resolves_only_active_tenant_options_and_rejects_forged_snapshots(): void
    {
        $base=['document_type_id'=>'01','payment_condition_id'=>'01','currency_type_id'=>'VES','fiscal_data'=>['special_tax_regime_id'=>1]];
        SpecialTaxRegimePolicy::assertInput($base,true);
        self::assertCount(7,SpecialTaxRegimePolicy::options());
        DB::connection('tenant')->table('cat_special_tax_regime')->where('id',1)->update(['active'=>0]);
        self::assertCount(6,SpecialTaxRegimePolicy::options());
        $cases=[[$base,true],[$base,false],[array_replace($base,['source_module'=>'POS']),true],
            [array_replace($base,['source_module'=>'GARAGE']),true],[array_replace($base,['document_type_id'=>'80']),true],
            [array_replace($base,['operation_type_id'=>'0201']),true],[array_replace($base,['payment_condition_id'=>'02']),true],
            [array_replace($base,['is_itinerant'=>true]),true],
            [array_replace($base,['fiscal_data'=>['catalog_snapshot'=>[]]]),true],
            [array_replace($base,['fiscal_data'=>['special_tax_regime_id'=>2,'regimenEspTributacion'=>'inventado']]),true],
            [array_replace($base,['fiscal_data'=>['special_tax_regime_id'=>8]]),true]];
        foreach ($cases as [$input,$web]) {
            try {SpecialTaxRegimePolicy::assertInput($input,$web);self::fail('Excluded or forged operation');}
            catch (ValidationException $e) {self::assertNotEmpty($e->errors());}
        }
        DB::connection('tenant')->table('companies')->update(['fiscal_environment'=>'production']);
        $this->expectException(ValidationException::class);
        SpecialTaxRegimePolicy::assertInput(array_replace($base,['fiscal_data'=>['special_tax_regime_id'=>2]]),true);
    }

    public function test_frozen_regime_survives_catalog_changes_and_missing_regime_is_omitted(): void
    {
        $d=self::snapshot(1);$expected=(new HkaPayloadBuilder)->build($d);
        DB::connection('tenant')->table('cat_special_tax_regime')->where('id',1)->update(['description'=>'MODIFICADO','active'=>0]);
        self::assertSame($expected,(new HkaPayloadBuilder)->build($d));
        $d['fiscal_data']=['transaction_type_id'=>'01'];
        self::assertArrayNotHasKey('regimenEspTributacion',(new HkaPayloadBuilder)->build($d)['documentoElectronico']['encabezado']['identificacionDocumento']);
        $fiscal=new \App\Models\Tenant\DocumentFiscalData(['special_tax_regime_id'=>1,'catalog_snapshot'=>['special_tax_regime_id'=>['description'=>SpecialTaxRegimePolicy::NAMES[1]]]]);
        $document=new \App\Models\Tenant\Document();$document->setRelation('fiscal_data',$fiscal);
        self::assertSame(SpecialTaxRegimePolicy::NAMES[1],SpecialTaxRegimePolicy::view($document)['special_tax_regime_description']);
    }

    public function test_invalid_description_length_identity_scope_and_incomplete_payments_fail(): void
    {
        $base=self::snapshot();
        $variants=[];
        foreach (['payments'=>[],'payment_condition_id'=>'02','document_type_id'=>'08','source_module'=>'POS','fiscal_environment'=>'production','fiscal_emission_mode'=>'free_form','invoice'=>['operation_type_id'=>'0201']] as $field=>$value) $variants[]=array_replace($base,[$field=>$value]);
        foreach (['description'=>str_repeat('A',56),'id'=>2] as $field=>$value) {
            $d=$base;$d['fiscal_data']['catalog_snapshot']['special_tax_regime_id'][$field]=$value;$variants[]=$d;
        }
        $d=$base;$d['payments'][0]['payment']=115.99;$variants[]=$d;
        $d=$base;$d['payments'][0]['reversed_at']='2026-10-12';$variants[]=$d;
        $d=$base;$d['payments'][0]['receipt_parent_id']=1;$variants[]=$d;
        foreach ($variants as $d) {
            try {(new HkaPayloadBuilder)->build($d);self::fail('Invalid frozen operation');}
            catch (ValidationException $e) {self::assertNotEmpty($e->errors());}
        }
    }
    public function test_regime_money_including_discounts_charges_exempt_and_igtf_is_adapted_once(): void
    {
        $d=self::snapshot(1,'USD',false);
        $d['items'][0]=array_replace($d['items'][0],['total_base_igv'=>90,'total_value'=>95,'total_discount'=>10,'total_charge'=>5,'total_igv'=>14.4,'total'=>109.4]);
        $d['items'][]=array_replace($d['items'][0],['quantity'=>1,'unit_value'=>25.25,'unit_price'=>25.25,
            'total_base_igv'=>25.25,'total_value'=>25.25,'total_discount'=>0,'total_charge'=>0,'total_igv'=>0,'percentage_igv'=>0,'total'=>25.25,
            'iva_rate'=>['code'=>'E','percentage'=>0],'affectation_igv_type_id'=>'20',
            'item'=>['description'=>'Servicio exento','unit_type_id'=>'SERV','hka_unit_code'=>'E48']]);
        $d['discounts']=[['discount_type_id'=>'02','amount'=>10]];$d['charges']=[['amount'=>5]];
        $d=array_replace($d,['total_taxed'=>80,'total_exonerated'=>25.25,'total_value'=>115.25,'total_discount'=>20,'total_charge'=>10,'total_igv'=>12.8,'total'=>131.89]);
        $d['taxes']=[['tax_kind'=>'IVA','code'=>'G','percentage'=>16,'base'=>80,'amount'=>12.8],
            ['tax_kind'=>'IVA','code'=>'E','percentage'=>0,'base'=>25.25,'amount'=>0],
            ['tax_kind'=>'IGTF','code'=>'IGTF','percentage'=>3,'base'=>128.05,'amount'=>3.84,'amount_ves'=>FiscalAmounts::convert(3.84,'USD','VES',$d['exchange_rate_sale'])]];
        $d['payments'][0]=array_replace($d['payments'][0],['payment'=>128.05,'original_amount'=>128.05,'tax_amount'=>3.84,'igtf_status'=>'subject']);
        $d['payments'][]=['payment'=>3.84,'receipt_parent_id'=>1];
        foreach(['taxed'=>80,'exempt'=>25.25,'iva'=>12.8,'total'=>131.89,'discount'=>20,'charge'=>10] as $key=>$value) $d['currency_totals'][$key]=FiscalAmounts::convert($value,'USD','VES',$d['exchange_rate_sale']);
        $before=$d;$h=(new HkaPayloadBuilder)->build($d)['documentoElectronico'];$t=$h['encabezado']['totales'];
        self::assertCount(3,$h['detallesItems']);self::assertSame('E',$h['detallesItems'][2]['codigoImpuesto']);
        self::assertSame('0.00',$t['totalDescuento']);self::assertSame('0.00',$t['totalRecargos']);
        self::assertSame('80.00',$h['encabezado']['totalesOtraMoneda']['impuestosSubtotal'][0]['baseImponibleImp']);
        self::assertSame('35.25',$h['encabezado']['totalesOtraMoneda']['montoExentoTotal']);
        self::assertSame('131.89',$h['encabezado']['totales']['formasPago'][0]['monto']);
        self::assertCount(1,$t['formasPago']);
        self::assertEqualsWithDelta((float)$t['totalAPagar'],(float)$t['subtotal']+(float)$t['totalIVA']+(float)$t['totalIGTF'],0.001);
        self::assertEqualsWithDelta((float)$t['totalAPagar'],(float)$t['montoTotalConIVA']+(float)$t['totalIGTF'],0.001);
        self::assertEqualsWithDelta((float)$t['totalIVA'],array_sum(array_column($h['detallesItems'],'valorIVA')),0.001);
        self::assertSame($before,$d);
        $d['fiscal_data']=array_replace($d['fiscal_data'],ThirdPartyInvoiceTest::snapshot()['fiscal_data']);
        $both=(new HkaPayloadBuilder)->build($d)['documentoElectronico'];
        self::assertSame($h['detallesItems'],$both['detallesItems']);
        self::assertSame($h['encabezado']['totales'],$both['encabezado']['totales']);
        self::assertSame($h['encabezado']['totalesOtraMoneda'],$both['encabezado']['totalesOtraMoneda']);
    }


    public function test_tenant_with_the_same_local_id_cannot_offer_another_tenants_regime(): void
    {
        $original=config('database.connections.tenant');
        config()->set('database.connections.regime_other', ['driver'=>'sqlite','database'=>':memory:','prefix'=>'']);
        Schema::connection('regime_other')->create('cat_special_tax_regime',function($t){$t->unsignedInteger('id')->primary();$t->string('description');$t->boolean('active');});
        DB::connection('regime_other')->table('cat_special_tax_regime')->insert(['id'=>1,'description'=>SpecialTaxRegimePolicy::NAMES[1],'active'=>0]);
        self::assertCount(7,SpecialTaxRegimePolicy::options());
        $manager=DB::getFacadeRoot();
        DB::swap(new class($manager) {
            private $db;public function __construct($db){$this->db=$db;}
            public function connection($name=null){return $this->db->connection($name==='tenant'?'regime_other':$name);}
        });
        try{self::assertCount(0,SpecialTaxRegimePolicy::options());}
        finally{DB::swap($manager);}
        self::assertCount(7,SpecialTaxRegimePolicy::options());
    }
}
