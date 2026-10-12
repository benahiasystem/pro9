<?php

namespace Tests\Unit;

use App\Http\Requests\Tenant\PersonRequest;
use App\Models\Tenant\Person;
use App\Services\ThirdPartyInvoicePolicy;
use App\Services\Fiscal\{FiscalAmounts, HkaPayloadBuilder};
use Illuminate\Support\Facades\{DB, Schema, Validator};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ThirdPartyInvoiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.tenant', ['driver'=>'sqlite','database'=>':memory:','prefix'=>'']);
        Schema::connection('tenant')->create('countries', fn ($t) => $t->string('id'));
        DB::connection('tenant')->table('countries')->insert([['id'=>'VE'],['id'=>'CO']]);
        Schema::connection('tenant')->create('persons', function ($t) {
            $t->increments('id'); $t->string('type'); $t->string('identity_document_type_id');
            foreach (['number','name','country_id'] as $field) $t->string($field);
            foreach (['email','address','external_identity_type','third_party_activity'] as $field) $t->string($field)->nullable();
            $t->string('third_party_rif')->nullable()->unique();
            $t->boolean('enabled')->default(true); $t->boolean('is_third_party')->default(false);
        });
        Schema::connection('tenant')->create('companies', function ($t) {
            $t->increments('id'); $t->string('fiscal_emission_mode'); $t->string('fiscal_environment');
        });
        DB::connection('tenant')->table('companies')->insert(['fiscal_emission_mode'=>'digital','fiscal_environment'=>'demo']);
    }

    private function personData(array $extra = []): array
    {
        return array_replace(['type'=>'customers','identity_document_type_id'=>'1','number'=>'12345678',
            'name'=>'TERCERO PERSONA NATURAL','country_id'=>'VE','address'=>'CARACAS - DIRECCION DE PRUEBA',
            'is_third_party'=>true,'third_party_rif'=>' v-12345678-9 ','third_party_activity'=>'Servicio de prueba','email'=>null], $extra);
    }

    private function request(array $data): PersonRequest
    {
        $request = PersonRequest::create('/', 'POST', $data);
        $prepare = new \ReflectionMethod($request, 'prepareForValidation'); $prepare->setAccessible(true); $prepare->invoke($request);
        return $request;
    }

    public function test_third_party_rif_is_normalized_separately_from_the_client_cedula(): void
    {
        $r = $this->request($this->personData());
        self::assertFalse(Validator::make($r->all(), $r->rules())->fails());
        self::assertSame('12345678', $r->input('number'));
        self::assertSame('V123456789', $r->input('third_party_rif'));
        self::assertTrue($r->boolean('is_third_party'));
    }

    public static function invalidClients(): array
    {
        return [[['third_party_rif'=>'V123'],'third_party_rif'],[['third_party_rif'=>['V123456789']],'third_party_rif'],
            [['third_party_activity'=>''],'third_party_activity'],[['third_party_activity'=>['invalid']],'third_party_activity'],
            [['name'=>str_repeat('A',101)],'name'],[['address'=>''],'address'],
            [['identity_document_type_id'=>'ND','number'=>'NIT-1','external_identity_type'=>'NIT','country_id'=>'CO'],'identity_document_type_id'],
            [['type'=>'suppliers'],'is_third_party']];
    }

    /** @dataProvider invalidClients */
    public function test_invalid_third_party_client_data_is_rejected($extra, $field): void
    {
        $r = $this->request($this->personData($extra));
        self::assertTrue(Validator::make($r->all(),$r->rules())->errors()->has($field));
    }

    public function test_duplicate_rif_is_rejected_and_unchecking_preserves_complementary_data(): void
    {
        $id = DB::connection('tenant')->table('persons')->insertGetId(array_replace($this->personData(['third_party_rif'=>'V123456789']), ['enabled'=>1]));
        $r = $this->request($this->personData(['number'=>'23456789','name'=>'OTRO CLIENTE']));
        self::assertTrue(Validator::make($r->all(),$r->rules())->errors()->has('third_party_rif'));
        $r = $this->request($this->personData(['id'=>$id,'is_third_party'=>false,'third_party_rif'=>null,'third_party_activity'=>null]));
        self::assertSame('V123456789',$r->input('third_party_rif'));
        self::assertSame('Servicio de prueba',$r->input('third_party_activity'));
        self::assertFalse(Validator::make($r->all(),$r->rules())->fails());
        $data=$this->personData(['id'=>$id]);unset($data['is_third_party'],$data['third_party_rif'],$data['third_party_activity']);
        self::assertTrue($this->request($data)->boolean('is_third_party'));
    }

    public function test_server_resolves_the_selected_client_and_derives_transaction_99(): void
    {
        $id = DB::connection('tenant')->table('persons')->insertGetId($this->personData(['third_party_rif'=>'V123456789']));
        $input=['third_party_id'=>$id,'document_type_id'=>'01','operation_type_id'=>'0101','payment_condition_id'=>'01'];
        $resolved=ThirdPartyInvoicePolicy::resolveInput($input,true);
        self::assertSame('99',$resolved['fiscal_data']['transaction_type_id']);
        self::assertSame($id,$resolved['fiscal_data']['third_party_person_id']);
        self::assertSame('V123456789',$resolved['fiscal_data']['third_party']['rif']);
        foreach ([['enabled'=>0],['enabled'=>1,'is_third_party'=>0],['is_third_party'=>1,'type'=>'suppliers']] as $change) {
            DB::connection('tenant')->table('persons')->where('id',$id)->update($change);
            try {ThirdPartyInvoicePolicy::resolveInput($input,true);self::fail('An unavailable client must not be a titular');}
            catch(ValidationException $e){self::assertArrayHasKey('third_party_id',$e->errors());}
        }
    }

    public function test_other_channels_and_forged_fiscal_snapshots_are_rejected_before_persistence(): void
    {
        $base=['third_party_id'=>1,'document_type_id'=>'01','operation_type_id'=>'0101','payment_condition_id'=>'01'];
        foreach ([[$base,false],[$base+['source_module'=>'POS'],true],
            [array_replace($base,['operation_type_id'=>'0201']),true],
            [array_replace($base,['payment_condition_id'=>'02']),true],
            [['document_type_id'=>'01','fiscal_data'=>['transaction_type_id'=>'99','third_party'=>['rif'=>'J123456789']]],false]] as [$input,$web]) {
            try {ThirdPartyInvoicePolicy::assertInput($input,$web);self::fail('Unsupported input must fail');}
            catch(ValidationException $e){self::assertNotEmpty($e->errors());}
        }
        self::assertSame(0,DB::connection('tenant')->table('persons')->count());
        DB::connection('tenant')->table('companies')->update(['fiscal_environment'=>'production']);
        $this->expectException(ValidationException::class);ThirdPartyInvoicePolicy::assertInput($base,true);
    }

    public static function cases(): array {return [['J','VES'],['J','USD'],['V','VES'],['V','USD']];}

    public static function snapshot(string $letter='J', string $currency='USD'): array
    {
        $d=json_decode(file_get_contents(__DIR__.'/../Fixtures/Hka/invoice.json'),true);
        $d['payment_condition_id']='01';$d['currency_type_id']=$currency;$d['exchange_rate_sale']='40.12345678';
        $d['items'][0]['total_base_igv']=100;
        $d['payments'][0]['payment']=116;$d['payments'][0]['currency_type_id']=$currency;
        $d['payments'][0]['exchange_rate']=$currency==='USD'?'40.12345678':'1.00000000';
        $d['fiscal_data']=['transaction_type_id'=>'99','third_party_person_id'=>3,'third_party'=>[
            'person_id'=>3,'rif'=>$letter.'123456789','name'=>'TITULAR '.$letter,'address'=>'DIRECCION DEL TERCERO',
            'country_id'=>'VE','activity'=>'Venta y servicios','email'=>'tercero@example.test']];
        $d['currency_totals']=['taxed'=>FiscalAmounts::convert(100,$currency,'VES',$d['exchange_rate_sale']),
            'exempt'=>0,'iva'=>FiscalAmounts::convert(16,$currency,'VES',$d['exchange_rate_sale']),
            'total'=>FiscalAmounts::convert(116,$currency,'VES',$d['exchange_rate_sale']),'discount'=>0,'charge'=>0,
            'tax_breakdown'=>[['tax_kind'=>'IVA','code'=>'G','percentage'=>16,'base_ves'=>FiscalAmounts::convert(100,$currency,'VES',$d['exchange_rate_sale']),
                'amount_ves'=>FiscalAmounts::convert(16,$currency,'VES',$d['exchange_rate_sale'])]]];
        return $d;
    }

    /** @dataProvider cases */
    public function test_hka_third_party_contract_keeps_buyer_issuer_and_money_separate($letter,$currency): void
    {
        $d=self::snapshot($letter,$currency);$before=$d;
        $payload=(new HkaPayloadBuilder)->build($d)['documentoElectronico'];$h=$payload['encabezado'];
        self::assertSame('01',$h['identificacionDocumento']['tipoDocumento']);
        self::assertSame('99',$h['identificacionDocumento']['tipoTransaccion']);
        self::assertSame('Interna',$h['identificacionDocumento']['tipoDeVenta']);
        self::assertSame('Contado',$h['identificacionDocumento']['tipoDePago']);
        self::assertSame($d['customer']['name'],$h['comprador']['razonSocial']);
        self::assertSame('No',$h['comprador']['notificar']);
        self::assertSame([$letter,$letter.'-12345678-9','TITULAR '.$letter,'DIRECCION DEL TERCERO','Venta y servicios',['tercero@example.test']],array_values($h['tercero']));
        self::assertArrayNotHasKey('otrosTercero',$h);self::assertArrayNotHasKey('pais',$h['tercero']);self::assertArrayNotHasKey('person_id',$h['tercero']);
        self::assertSame('VES',$h['identificacionDocumento']['moneda']);
        self::assertSame($currency==='USD'?'4654.32':'116.00',$h['totales']['totalAPagar']);
        self::assertSame('G',$payload['detallesItems'][0]['codigoImpuesto']);
        self::assertSame('16.00',$payload['detallesItems'][0]['tasaIVA']);
        if($currency==='USD'){self::assertSame('USD',$h['totalesOtraMoneda']['moneda']);self::assertSame('116.00',$h['totalesOtraMoneda']['totalAPagar']);self::assertSame('40.12345678',$h['totalesOtraMoneda']['tipoCambio']);}
        self::assertSame($before,$d);
    }

    public function test_builder_rejects_missing_third_party_credit_unpaid_and_notes_and_omits_absent_email(): void
    {
        $base=self::snapshot();
        foreach ([['fiscal_data'=>['transaction_type_id'=>'99']],['payment_condition_id'=>'02'],['payments'=>[]],['document_type_id'=>'08']] as $change) {
            try {(new HkaPayloadBuilder)->build(array_replace($base,$change));self::fail('Invalid third-party fiscal operation');}
            catch(ValidationException $e){self::assertNotEmpty($e->errors());}
        }
        $base['fiscal_data']['third_party']['email']=null;
        self::assertArrayNotHasKey('correo',(new HkaPayloadBuilder)->build($base)['documentoElectronico']['encabezado']['tercero']);
    }

    public function test_hka_adjustments_preserve_local_iva_and_reconcile_usd_with_igtf(): void
    {
        $d=self::snapshot();
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
    }

    public function test_third_party_rif_activity_address_and_email_contract_limits_are_enforced(): void
    {
        $third=self::snapshot()['fiscal_data']['third_party'];
        foreach ([['rif'=>['J123456789']],['name'=>str_repeat('A',101)],['address'=>str_repeat('A',256)],
            ['activity'=>str_repeat('A',101)],['email'=>str_repeat('a',40).'@example.test'],['email'=>'invalid'],['activity'=>'   ']] as $change) {
            try {ThirdPartyInvoicePolicy::hkaThirdParty(array_replace($third,$change));self::fail('The conservative HKA limit must be enforced');}
            catch (ValidationException $e) {self::assertNotEmpty($e->errors());}
        }
    }

    public function test_usd_cent_allocation_keeps_exempt_sales_and_post_tax_discounts_balanced(): void
    {
        foreach ([13.21, 23.78, 100.01] as $base) {
            $d=self::snapshot();$igtf=round($base*.03,2);
            $d=array_replace($d,['total_taxed'=>0,'total_exonerated'=>$base,'total_value'=>$base,'total_igv'=>0,'total'=>$base+$igtf]);
            $d['items'][0]=array_replace($d['items'][0],['quantity'=>1,'unit_value'=>$base,'unit_price'=>$base,'total_base_igv'=>$base,
                'total_value'=>$base,'total'=>$base,'total_igv'=>0,'percentage_igv'=>0,'iva_rate'=>['code'=>'E','percentage'=>0],'affectation_igv_type_id'=>'20']);
            $d['taxes']=[['tax_kind'=>'IVA','code'=>'E','percentage'=>0,'base'=>$base,'amount'=>0],
                ['tax_kind'=>'IGTF','code'=>'IGTF','percentage'=>3,'base'=>$base,'amount'=>$igtf,'amount_ves'=>FiscalAmounts::convert($igtf,'USD','VES',$d['exchange_rate_sale'])]];
            $d['payments'][0]=array_replace($d['payments'][0],['payment'=>$base,'original_amount'=>$base,'tax_amount'=>$igtf]);
            $d['payments'][]=['payment'=>$igtf,'receipt_parent_id'=>1];
            foreach(['taxed'=>0,'exempt'=>$base,'iva'=>0,'total'=>$base+$igtf] as $key=>$value)$d['currency_totals'][$key]=FiscalAmounts::convert($value,'USD','VES',$d['exchange_rate_sale']);
            $h=(new HkaPayloadBuilder)->build($d)['documentoElectronico'];$t=$h['encabezado']['totales'];
            self::assertEqualsWithDelta((float)$t['totalAPagar'],(float)$t['subtotal']+(float)$t['totalIGTF'],0.001);
            self::assertEqualsWithDelta((float)$t['subtotal'],array_sum(array_column($h['detallesItems'],'precioItem')),0.001);
            self::assertSame('0.00',$t['totalIVA']);
        }
        $d=self::snapshot();$d['total_value']=99.99;$d['total']=115.99;$d['total_discount']=0.01;
        $d['payments'][0]['payment']=115.99;$d['payments'][0]['original_amount']=115.99;
        $d['currency_totals']['total']=FiscalAmounts::convert(115.99,'USD','VES',$d['exchange_rate_sale']);
        $h=(new HkaPayloadBuilder)->build($d)['documentoElectronico'];$t=$h['encabezado']['totales'];
        self::assertArrayHasKey('subtotalAntesDescuento',$t);
        self::assertEqualsWithDelta((float)$t['subtotal'],(float)$t['montoGravadoTotal']+(float)$t['montoExentoTotal']-(float)$t['totalDescuento'],0.001);
        self::assertEqualsWithDelta((float)$t['totalAPagar'],(float)$t['subtotal']+(float)$t['totalIVA'],0.001);
    }
}
