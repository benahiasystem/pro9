<?php

namespace Tests\Unit;

use App\Http\Requests\Tenant\PersonRequest;
use App\Services\ExportInvoicePolicy;
use App\Services\Fiscal\{FiscalDocumentPersistence, HkaPayloadBuilder};
use Illuminate\Support\Facades\{DB, Schema, Validator};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExportInvoiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.tenant', ['driver'=>'sqlite', 'database'=>':memory:', 'prefix'=>'']);
        Schema::connection('tenant')->create('cat_unit_types', function ($t) { $t->string('id'); $t->string('hka_code'); $t->boolean('active')->default(true); });
        DB::connection('tenant')->table('cat_unit_types')->insert(['id'=>'UND', 'hka_code'=>'C62']);
        Schema::connection('tenant')->create('countries', function ($t) { $t->string('id'); });
        DB::connection('tenant')->table('countries')->insert([['id'=>'VE'], ['id'=>'US'], ['id'=>'CO']]);
        Schema::connection('tenant')->create('persons', function ($t) {
            $t->increments('id'); $t->string('type'); $t->string('identity_document_type_id'); $t->string('number'); $t->string('name');
            $t->string('country_id'); $t->string('external_identity_type')->nullable(); $t->string('email')->nullable();
            $t->unique(['type','identity_document_type_id','country_id','external_identity_type','number']);
        });
    }

    public static function exports(): array
    {
        $rows = [];
        foreach (ExportInvoicePolicy::INCOTERMS as $operation => $term) foreach (['VES','USD'] as $currency) $rows[] = [$operation, $term, $currency];
        return $rows;
    }

    public static function snapshot(string $operation = '0201', string $currency = 'USD'): array
    {
        $d = json_decode(file_get_contents(__DIR__.'/../Fixtures/Hka/invoice.json'), true);
        $d['invoice']['operation_type_id'] = $operation;
        $d['customer'] = array_replace($d['customer'], ['identity_document_type_id'=>'ND', 'external_identity_type'=>'NIT', 'hka_identity_code'=>'NIT', 'number'=>'900123-4', 'country_id'=>'CO']);
        $d['currency_type_id'] = $currency;
        $d['exchange_rate_sale'] = '40.12345678';
        $d['fiscal_data']['catalog_snapshot']['operation_type'] = ['id'=>$operation, 'incoterm'=>ExportInvoicePolicy::INCOTERMS[$operation], 'exportation'=>1];
        $d['items'] = [array_replace($d['items'][0], ['affectation_igv_type_id'=>'EXP', 'quantity'=>1, 'unit_value'=>100, 'unit_price'=>100,
            'total_base_igv'=>100, 'total_value'=>100, 'total'=>100, 'total_discount'=>0, 'total_charge'=>0, 'total_igv'=>0, 'percentage_igv'=>0, 'iva_rate'=>['code'=>'E','percentage'=>0]])];
        $d['total_exportation'] = $d['total_value'] = $d['total'] = 100;
        $d['total_taxed'] = $d['total_exonerated'] = $d['total_igv'] = $d['total_discount'] = $d['total_charge'] = 0;
        $d['taxes'] = [['tax_kind'=>'IVA','code'=>'E','percentage'=>0,'base'=>100,'amount'=>0,'base_ves'=>4012.35,'amount_ves'=>0]];
        $d['currency_totals'] = ['taxed'=>0,'exempt'=>0,'exportation'=>4012.35,'iva'=>0,'total'=>4012.35,'discount'=>0,'charge'=>0,'tax_breakdown'=>$d['taxes']];
        $d['payments'] = [];
        return $d;
    }

    /** @dataProvider exports */
    public function test_export_payload_preserves_the_incoterm_buyer_and_currency_totals($operation, $term, $currency): void
    {
        $d = self::snapshot($operation, $currency);
        $before = $d;
        $p = (new HkaPayloadBuilder)->build($d)['documentoElectronico'];
        $h = $p['encabezado'];
        self::assertSame('01', $h['identificacionDocumento']['tipoDocumento']);
        self::assertSame($term, $h['identificacionDocumento']['tipoDeVenta']);
        self::assertSame('NIT', $h['comprador']['tipoIdentificacion']);
        self::assertSame('900123-4', $h['comprador']['numeroIdentificacion']);
        self::assertSame('CO', $h['comprador']['pais']);
        self::assertSame('No', $h['comprador']['notificar']);
        self::assertSame('E', $p['detallesItems'][0]['codigoImpuesto']);
        self::assertSame('0.00', $h['totales']['totalIVA']);
        self::assertSame('0.00', $h['totales']['montoGravadoTotal']);
        self::assertSame($currency === 'USD' ? '4012.35' : '100.00', $h['totales']['montoExentoTotal']);
        self::assertSame('VES', $h['identificacionDocumento']['moneda']);
        if ($currency === 'USD') {
            self::assertSame('100.00', $h['totalesOtraMoneda']['subtotal']);
            self::assertSame('USD', $h['totalesOtraMoneda']['moneda']);
            self::assertSame('100.00', $h['totalesOtraMoneda']['montoExentoTotal']);
            self::assertSame('40.12345678', $h['totalesOtraMoneda']['tipoCambio']);
        } else self::assertArrayNotHasKey('totalesOtraMoneda', $h);
        self::assertSame($before, $d);
    }

    public function test_export_lines_recompute_zero_tax_discounts_and_charges(): void
    {
        $line = FiscalDocumentPersistence::line(['item'=>['unit_type_id'=>'UND'], 'quantity'=>2, 'unit_price'=>100, 'affectation_igv_type_id'=>'EXP',
            'discounts'=>[['discount_type_id'=>'00','factor'=>0.1]], 'charges'=>[['factor'=>0.05]], 'total_igv'=>999], true);
        self::assertSame(190.0, $line['total_value']);
        self::assertSame(190.0, $line['total']);
        self::assertSame(0.0, $line['total_igv']);
        self::assertSame(['code'=>'E','percentage'=>0], $line['iva_rate']);
    }

    public function test_global_export_adjustments_preserve_local_totals_and_follow_hka_subtotal_rules(): void
    {
        $d = self::snapshot('0203', 'USD');
        $d['discounts'] = [['amount'=>10]];
        $d['charges'] = [['amount'=>5]];
        $d['total_discount'] = 10;
        $d['total_charge'] = 5;
        $d['total_exportation'] = $d['total_value'] = $d['total'] = 95;
        $d['taxes'][0]['base'] = 95;
        $d['taxes'][0]['base_ves'] = $d['currency_totals']['exportation'] = $d['currency_totals']['total'] = 3811.73;
        $before = $d;
        $p = (new HkaPayloadBuilder)->build($d)['documentoElectronico'];
        $h = $p['encabezado'];
        self::assertArrayNotHasKey('subtotalAntesDescuento', $h['totales']);
        self::assertSame('3811.73', $h['totales']['subtotal']);
        self::assertSame('0.00', $h['totales']['totalDescuento']);
        self::assertSame('0.00', $h['totales']['totalRecargos']);
        self::assertSame('401.23', $p['detallesItems'][0]['descuentoMonto']);
        self::assertSame('200.62', $p['detallesItems'][0]['recargoMonto']);
        self::assertSame('4012.35', $p['detallesItems'][0]['precioAntesDescuento']);
        self::assertSame('3811.73', $h['totales']['totalAPagar']);
        self::assertArrayNotHasKey('subtotalAntesDescuento', $h['totalesOtraMoneda']);
        self::assertSame('95.00', $h['totalesOtraMoneda']['subtotal']);
        self::assertSame('95.00', $h['totalesOtraMoneda']['totalAPagar']);
        self::assertSame($before, $d);
    }

    public function test_export_adjustments_distribute_cent_residuals_and_currency_rounding_without_duplicate_discount(): void
    {
        $d = self::snapshot('0201', 'VES');
        $template = $d['items'][0];
        $d['items'] = [];
        foreach ([33.33,33.33,33.34] as $value) $d['items'][] = array_replace($template, ['unit_value'=>$value,'unit_price'=>$value,'total_base_igv'=>$value,'total_value'=>$value,'total'=>$value]);
        // The first cent was already distributed by the commercial calculation.
        $d['items'][0]['discounts'] = [['amount'=>0.01, 'from_global_distribution'=>true]];
        $d['items'][0]['total_discount'] = 0.01;
        foreach (['total_base_igv','total_value','total'] as $field) $d['items'][0][$field] = 33.32;
        $d['discounts'] = [['amount'=>0.01]];
        $d['charges'] = [['amount'=>0.02]];
        $d['total_exportation'] = $d['total_value'] = $d['total'] = 100.01;
        $d['taxes'][0]['base'] = 100.01;
        $d['taxes'][0]['base_ves'] = 4012.75;
        $before = $d;
        $p = (new HkaPayloadBuilder)->build($d)['documentoElectronico'];
        self::assertEquals(0.01, array_sum(array_column($p['detallesItems'], 'descuentoMonto')));
        self::assertEquals(0.02, array_sum(array_column($p['detallesItems'], 'recargoMonto')));
        self::assertEqualsWithDelta(100.01, array_sum(array_column($p['detallesItems'], 'precioItem')), 0.0000001);
        self::assertSame('100.01', $p['encabezado']['totales']['totalAPagar']);
        self::assertSame($before, $d);
        $d['currency_type_id'] = 'USD';
        $d['currency_totals']['exportation'] = $d['currency_totals']['total'] = 4012.75;
        $p = (new HkaPayloadBuilder)->build($d)['documentoElectronico'];
        self::assertEqualsWithDelta(4012.75, array_sum(array_column($p['detallesItems'], 'precioItem')), 0.0000001);
        self::assertSame('4012.75', $p['encabezado']['totales']['totalAPagar']);
        self::assertSame('100.01', $p['encabezado']['totalesOtraMoneda']['totalAPagar']);
    }

    public function test_export_affectation_cannot_be_used_without_export_context(): void
    {
        $this->expectException(ValidationException::class);
        FiscalDocumentPersistence::line(['affectation_igv_type_id'=>'EXP']);
    }

    public function test_services_cannot_be_exported(): void
    {
        $this->expectException(ValidationException::class);
        FiscalDocumentPersistence::line(['affectation_igv_type_id'=>'EXP', 'item'=>['unit_type_id'=>'SERV']], true);
    }

    public function test_api_export_is_rejected_before_any_catalog_or_customer_mutation(): void
    {
        $this->expectException(ValidationException::class);
        ExportInvoicePolicy::assertInput(['operation_type_id'=>'0201'], false);
    }

    // ########### INICIO CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS
    public function test_item_import_catalog_excludes_export_affectation_even_when_active(): void
    {
        Schema::connection('tenant')->create('cat_currency_types', function ($t) { $t->string('id'); $t->boolean('active'); });
        Schema::connection('tenant')->create('cat_affectation_igv_types', function ($t) { $t->string('id'); $t->boolean('active'); });
        Schema::connection('tenant')->create('items', function ($t) { $t->increments('id'); $t->string('internal_id')->nullable(); });
        DB::connection('tenant')->table('cat_currency_types')->insert(['id'=>'VES','active'=>1]);
        DB::connection('tenant')->table('cat_affectation_igv_types')->insert([['id'=>'10','active'=>1],['id'=>'20','active'=>1],['id'=>'EXP','active'=>1]]);
        $catalogs = (new \App\Support\ItemImport\ItemImportCatalogs)->snapshot();
        self::assertSame(['10','20'], $catalogs['affectation_igv_type_ids']);
        $row = ['Producto','EXPORT-ITEM',null,null,'UND','VES',100,'EXP','NO',80,'EXP',1,0,null,null,null,null,null,null,null];
        $errors = (new \App\Support\ItemImport\ItemImportContract)->validateRow($row, 2, $catalogs);
        self::assertSame([8,11], array_column($errors, 'column'));
        $request = new \App\Http\Requests\Tenant\ItemRequest();
        $rules = $request->rules();
        $validator = Validator::make(['sale_affectation_igv_type_id'=>'EXP','purchase_affectation_igv_type_id'=>'EXP'],
            array_intersect_key($rules, array_flip(['sale_affectation_igv_type_id','purchase_affectation_igv_type_id'])));
        self::assertCount(2, $validator->errors()->toArray());
    }
    // ########### FIN CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS

    /** @dataProvider badSnapshots */
    public function test_incomplete_or_inconsistent_exports_are_rejected(string $path, $value): void
    {
        $d = self::snapshot(); data_set($d, $path, $value);
        $this->expectException(ValidationException::class);
        (new HkaPayloadBuilder)->build($d);
    }

    public static function badSnapshots(): array
    {
        return [['customer.country_id','VE'], ['customer.external_identity_type','ND'], ['customer.hka_identity_code','J'], ['total_igv',16],
            ['total_exonerated',100], ['total_exportation',99], ['currency_totals.exportation',0], ['items.0.affectation_igv_type_id','20'],
            ['items.0.item.unit_type_id','SERV'], ['fiscal_data.catalog_snapshot.operation_type.incoterm','EXW'], ['document_type_id','07'], ['items',[]]];
    }

    private function person(array $changes = []): array
    {
        return array_replace(['type'=>'customers','identity_document_type_id'=>'ND','external_identity_type'=>'NIT','number'=>'900123-4',
            'name'=>'Export customer', 'country_id'=>'CO', 'address'=>'Foreign address', 'addresses'=>[]], $changes);
    }

    private function validation(array $data)
    {
        $request = PersonRequest::create('/', 'POST', $data);
        $method = new \ReflectionMethod($request, 'prepareForValidation'); $method->setAccessible(true); $method->invoke($request);
        return [$request, Validator::make($request->all(), $request->rules())];
    }

    public function test_foreign_customer_keeps_the_number_and_country_and_clears_territory(): void
    {
        [$request, $v] = $this->validation($this->person(['number'=>'Ab12-4.5','department_id'=>'14','province_id'=>'0229','district_id'=>'000619',
            'addresses'=>[['address'=>'Other foreign address','country_id'=>'US','location_id'=>['14','0229','000619']]]]));
        self::assertFalse($v->fails(), json_encode($v->errors()->toArray()));
        self::assertSame('Ab12-4.5', $request->number);
        self::assertSame('CO', $request->country_id);
        self::assertNull($request->department_id);
        self::assertNull($request->nationality_id);
        self::assertSame([], $request->input('addresses.0.location_id'));
        self::assertSame('US', $request->input('addresses.0.country_id'));
    }

    public function test_foreign_identity_uniqueness_is_scoped_to_country_and_external_type(): void
    {
        DB::connection('tenant')->table('persons')->insert(array_diff_key($this->person(), array_flip(['address','addresses'])));
        self::assertTrue($this->validation($this->person())[1]->fails());
        self::assertFalse($this->validation($this->person(['country_id'=>'US','name'=>'Other name']))[1]->fails());
        self::assertFalse($this->validation($this->person(['external_identity_type'=>'RUT','name'=>'Other name']))[1]->fails());
    }

    /** @dataProvider invalidPeople */
    public function test_foreign_customers_require_the_actual_identifier_and_foreign_domicile(array $changes, string $field): void
    {
        $v = $this->validation($this->person($changes))[1];
        self::assertTrue($v->fails());
        self::assertArrayHasKey($field, $v->errors()->toArray());
    }

    public static function invalidPeople(): array
    {
        return [[['country_id'=>'VE'],'country_id'], [['country_id'=>'ZZ'],'country_id'], [['external_identity_type'=>'ND'],'external_identity_type'],
            [['external_identity_type'=>'NIT00'],'external_identity_type'], [['external_identity_type'=>'nit'],'external_identity_type'],
            [['number'=>'A 12'],'number'], [['number'=>'-12'],'number'], [['number'=>str_repeat('A',46)],'number'], [['address'=>''],'address'],
            [['addresses'=>[['address'=>'Other address','country_id'=>'VE']]],'addresses.0.country_id']];
    }
}
