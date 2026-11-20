<?php
namespace Tests\Unit;
use App\Services\Fiscal\{HkaPayloadBuilder,FiscalAmounts};
use App\Support\Exact\Decimal as D;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
// ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
class HkaPayloadBuilderTest extends TestCase
{
    /** @dataProvider documents */
    public function test_payloads_match_the_pinned_contract($fixture,$type): void
    {
        $d=json_decode(file_get_contents(__DIR__.'/../Fixtures/Hka/'.$fixture.'.json'),true);
        $p=(new HkaPayloadBuilder())->build($d)['documentoElectronico'];
        self::assertSame($type,$p['encabezado']['identificacionDocumento']['tipoDocumento']);
        self::assertSame((string) $d['number'], $p['encabezado']['identificacionDocumento']['numeroDocumento']);
        if (isset($d['reference'])) self::assertSame((string) $d['reference']['number'], $p['encabezado']['identificacionDocumento']['numeroFacturaAfectada']);
        self::assertSame(in_array($fixture,['invoice','igtf-debit'],true)?'USD':'VES',$p['encabezado']['totalesOtraMoneda']['moneda']);
        self::assertSame(str_replace('-', '', $d['operation_key']), $p['encabezado']['identificacionDocumento']['transaccionId']);
        self::assertSame('10.123',$p['encabezado']['totalesOtraMoneda']['tipoCambio']);
        self::assertSame($fixture==='igtf-debit' ? '3.48' : ($fixture==='invoice'?'116.00':'1174.27'),$p['encabezado']['totalesOtraMoneda']['totalAPagar']);
        if(in_array($fixture,['invoice','igtf-debit'],true)){
            self::assertSame('VES',$p['encabezado']['identificacionDocumento']['moneda']);
            self::assertSame($fixture==='igtf-debit'?'35.23':'1174.27',$p['encabezado']['totales']['totalAPagar']);
        }
        self::assertArrayNotHasKey('token',$p);
        if($fixture==='igtf-debit') self::assertSame([],$p['detallesItems']);
    }
    public function documents(): array { return [['invoice','01'],['credit','02'],['debit','03'],['igtf-debit','03']]; }
    /** @dataProvider paymentConditions */
    public function test_new_payloads_include_frozen_receiver_contacts_and_payment_condition($condition, $label): void
    {
        $d = json_decode(file_get_contents(__DIR__.'/../Fixtures/Hka/invoice.json'), true);
        $d['payment_condition_id'] = $condition;
        $d['customer']['email'] = ' customer@example.test ';
        $d['customer']['telephone'] = ' 04121234567 ';
        $before = $d;
        $header = (new HkaPayloadBuilder())->build($d)['documentoElectronico']['encabezado'];
        self::assertSame($label, $header['identificacionDocumento']['tipoDePago']);
        self::assertSame(['customer@example.test'], $header['comprador']['correo']);
        self::assertSame(['04121234567'], $header['comprador']['telefono']);
        self::assertSame('No', $header['comprador']['notificar']);
        self::assertSame($before, $d, 'The builder cannot mutate frozen sale data.');
    }
    public function paymentConditions(): array { return [['01', 'Contado'], ['02', 'Crédito']]; }
    public function test_missing_optional_contacts_are_not_invented(): void
    {
        $d = json_decode(file_get_contents(__DIR__.'/../Fixtures/Hka/invoice.json'), true);
        $d['customer']['email'] = ' '; $d['customer']['telephone'] = null;
        $header = (new HkaPayloadBuilder())->build($d)['documentoElectronico']['encabezado'];
        self::assertArrayNotHasKey('correo', $header['comprador']);
        self::assertArrayNotHasKey('telefono', $header['comprador']);
        self::assertArrayNotHasKey('tipoDePago', $header['identificacionDocumento']);
        self::assertSame('No', $header['comprador']['notificar']);
    }
    public function test_missing_unit_price_blocks_preparation(): void
    {
        $snapshot = json_decode(file_get_contents(__DIR__.'/../Fixtures/Hka/invoice.json'), true);
        unset($snapshot['items'][0]['unit_value']);
        try {
            (new HkaPayloadBuilder())->build($snapshot);
            self::fail('El precio unitario ausente no se puede sustituir por cero.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('items', $exception->errors());
        }
    }

    /** @dataProvider ordinaryVesAmounts */
    public function test_ordinary_ves_payload_reconciles_frozen_lines_and_tax_groups(
        array $figures,
        array $expected,
        string $compensatingGlobalAdjustment = '0',
    ): void
    {
        $snapshot = json_decode(file_get_contents(__DIR__.'/../Fixtures/Hka/invoice.json'), true, 512, JSON_THROW_ON_ERROR);
        $prototype = $snapshot['items'][0];
        $snapshot = array_replace($snapshot, [
            'currency_type_id' => 'VES', 'items' => [], 'payments' => [], 'taxes' => [], 'total_discount' => '0', 'total_charge' => '0',
        ]);
        $taxGroups = [];
        $discounts = [];
        foreach ($figures as $figure) {
            [$code, $base, $tax] = $figure;
            $discount = $figure[3] ?? '0';
            $discounts[] = $discount;
            $rate = $code === 'E' ? '0' : '16';
            $snapshot['items'][] = array_replace($prototype, [
                'unit_value' => D::represent(D::of($base)->plus($discount)),
                'total_value' => $base, 'total_base_igv' => $base, 'quantity' => '1',
                'total_igv' => $tax, 'total' => D::represent(D::of($base)->plus($tax)),
                'total_discount' => $discount, 'total_charge' => '0', 'percentage_igv' => $rate,
                'iva_rate' => ['code' => $code, 'percentage' => $rate],
            ]);
            $taxGroups[$code] = [
                'tax_kind' => 'IVA', 'code' => $code, 'percentage' => $rate,
                'base' => D::represent(D::of($taxGroups[$code]['base'] ?? 0)->plus($base)),
                'amount' => D::represent(D::of($taxGroups[$code]['amount'] ?? 0)->plus($tax)),
            ];
        }
        $snapshot['taxes'] = array_values($taxGroups);
        $snapshot['total_taxed'] = $taxGroups['G']['base'] ?? '0';
        $snapshot['total_exonerated'] = $taxGroups['E']['base'] ?? '0';
        $snapshot['total_igv'] = D::represent(D::sum(array_column($taxGroups, 'amount')));
        $snapshot['total_value'] = D::represent(D::sum(array_column($taxGroups, 'base')));
        $snapshot['total'] = D::represent(D::of($snapshot['total_value'])->plus($snapshot['total_igv']));
        $snapshot['total_discount'] = D::represent(D::sum($discounts)->plus($compensatingGlobalAdjustment));
        $snapshot['total_charge'] = $compensatingGlobalAdjustment;
        $before = $snapshot;

        $payload = (new HkaPayloadBuilder())->build($snapshot)['documentoElectronico'];
        $totals = $payload['encabezado']['totales'];
        $lines = $payload['detallesItems'];
        foreach (['montoGravadoTotal', 'montoExentoTotal', 'totalIVA', 'totalAPagar'] as $index => $field) {
            self::assertSame($expected[$index], $totals[$field]);
        }
        self::assertSame($totals['totalAPagar'], D::represent(D::sum(array_column($lines, 'valorTotalItem')), 2, true));
        self::assertSame($totals['subtotal'], D::represent(D::sum(array_column($lines, 'precioItem')), 2, true));
        self::assertSame(D::represent($snapshot['total_discount'], 2, true), $totals['totalDescuento']);
        self::assertSame(D::represent($snapshot['total_charge'], 2, true), $totals['totalRecargos']);
        foreach ($totals['impuestosSubtotal'] as $tax) {
            $group = array_filter($lines, fn ($line) => $line['codigoImpuesto'] === $tax['codigoTotalImp']);
            self::assertSame($tax['baseImponibleImp'], D::represent(D::sum(array_column($group, 'precioItem')), 2, true));
            self::assertSame($tax['valorTotalImp'], D::represent(D::sum(array_column($group, 'valorIVA')), 2, true));
        }
        foreach ($lines as $index => $line) {
            self::assertSame($line['valorTotalItem'], D::represent(D::of($line['precioItem'])->plus($line['valorIVA']), 2, true));
            self::assertSame(D::represent($discounts[$index], 2, true), $line['descuentoMonto']);
            self::assertSame(D::represent($snapshot['items'][$index]['unit_value'], 4, true), $line['precioUnitario']);
            self::assertSame('0.00', $line['recargoMonto']);
            self::assertArrayNotHasKey('precioAntesDescuento', $line);
        }
        self::assertSame($before, $snapshot);
    }

    public function ordinaryVesAmounts(): array
    {
        return [
            'dos líneas exentas' => [[['E', '0.005', '0'], ['E', '0.005', '0']], ['0.00', '0.01', '0.00', '0.01']],
            'IVA redondeado por grupo' => [[['G', '0.035', '0.0056'], ['G', '0.035', '0.0056']], ['0.07', '0.00', '0.01', '0.08']],
            'base repartida entre alícuotas' => [[['E', '0.005', '0'], ['G', '0.005', '0.0008']], ['0.00', '0.01', '0.00', '0.01']],
            'base e IVA aportan una fracción de centavo' => [[['G', '0.0049', '0.000784']], ['0.01', '0.00', '0.00', '0.01']],
            'se conservan descuentos de línea y ajustes globales compensados' => [
                [['E', '0.005', '0', '0.01'], ['E', '0.005', '0', '0.01']],
                ['0.00', '0.01', '0.00', '0.01'],
                '1',
            ],
        ];
    }

    public function test_missing_identity_mapping_blocks_preparation(): void
    {
        $d=json_decode(file_get_contents(__DIR__.'/../Fixtures/Hka/invoice.json'),true);unset($d['customer']['hka_identity_code']);
        $this->expectException(ValidationException::class);(new HkaPayloadBuilder())->build($d);
    }
    public function test_missing_fiscal_payment_method_blocks_preparation(): void
    {
        $d = json_decode(file_get_contents(__DIR__.'/../Fixtures/Hka/invoice.json'), true);
        $d['payments'][0]['payment_method_snapshot']['hka_code'] = null;
        try {
            (new HkaPayloadBuilder())->build($d);
            self::fail('El pago sin medio fiscal no puede enviarse a HKA.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('payments', $exception->errors());
        }
    }
    public function test_exports_are_not_enabled_by_catalog_presence(): void
    {
        $d=json_decode(file_get_contents(__DIR__.'/../Fixtures/Hka/invoice.json'),true);$d['invoice']['operation_type_id']='0201';
        $this->expectException(ValidationException::class);(new HkaPayloadBuilder())->build($d);
    }
    public function test_payment_currency_and_tax_use_received_amount_and_current_precision(): void
    {
        $p=FiscalAmounts::payment(['payment'=>999,'original_amount'=>100,'currency_type_id'=>'USD','exchange_rate'=>10.1234,'igtf_status'=>'subject'], 'VES',1,true,3);
        self::assertSame('10.12340000',$p['exchange_rate']);self::assertSame('1012.34',$p['payment']);self::assertSame('3.00',$p['tax_amount']);
        self::assertSame('100.00',$p['original_amount']);
    }
    public function test_not_applicable_payments_do_not_collect_igtf(): void
    {
        $local=FiscalAmounts::payment(['payment'=>100],'VES',1,false,null);self::assertSame('not_applicable',$local['igtf_status']);self::assertSame('0.00',$local['tax_amount']);
    }
    public function test_rates_needing_more_than_eight_decimals_cannot_be_used_for_conversion(): void
    {
        $this->expectException(ValidationException::class);FiscalAmounts::payment(['original_amount'=>100,'currency_type_id'=>'USD','exchange_rate'=>'0.000000001'],'VES',1,false,null);
    }
    public function test_disabled_igtf_does_not_use_hka_catalog_rate(): void
    {
        $this->expectException(ValidationException::class);
        FiscalAmounts::payment(['payment'=>100,'igtf_status'=>'subject'],'USD',10,false,null);
    }
    public function test_retired_exception_is_rejected_even_with_its_reason(): void
    {
        $this->expectException(ValidationException::class);FiscalAmounts::payment(['payment'=>100,'igtf_status'=>'exempt','exemption_reason'=>'Anterior'],'USD',10,true,3);
    }
    public function test_unverified_hka_net_retention_blocks_emission_without_fabricating_payments(): void
    {
        $snapshot=json_decode(file_get_contents(__DIR__.'/../Fixtures/Hka/invoice.json'),true);
        $snapshot['fiscal_data']['iva_retention_prevision']=['amount'=>'12.00'];
        $snapshot['payments'][0]['payment']=104;
        $snapshot['payments'][0]['original_amount']=104;
        config(['iva_retention.demo_net_collection_verified'=>false]);
        try { (new HkaPayloadBuilder)->build($snapshot);self::fail('Unverified net collection transmitted'); }
        catch (ValidationException $e) { self::assertArrayHasKey('iva_retention',$e->errors()); }
        config(['iva_retention.demo_net_collection_verified'=>true]);
        $result=(new HkaPayloadBuilder)->build($snapshot);
        self::assertCount(1,$result['documentoElectronico']['encabezado']['totales']['formasPago']);
        self::assertSame('104.00',$result['documentoElectronico']['encabezado']['totales']['formasPago'][0]['monto']);
        self::assertSame(104,$snapshot['payments'][0]['payment']);
    }

}
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
