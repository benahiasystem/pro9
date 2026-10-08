<?php
namespace App\Services\Fiscal;

// ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
/** Pure builder: snapshots in, versioned HKA payload out. Never performs network I/O. */
final class HkaPayloadBuilder
{
    public static function contractVersion(): string
    {
        return 'hka-ve-v1:'.hash_file('sha256', __DIR__.'/contracts/hka-ve-v1.json');
    }
    /** DEMO rejects UUID separators (validation 1002). Preserve the local UUID and map only at the HKA boundary. */
    public static function transactionId(string $operationKey): string
    {
        if (!preg_match('/\A[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}\z/i', $operationKey)) {
            FiscalAmounts::error('operation_key', 'La operación fiscal requiere un UUID válido.');
        }
        return strtolower(str_replace('-', '', $operationKey));
    }
    private static function money($value): string { return number_format((float) $value, 2, '.', ''); }
    private static function date($value): string { return \Carbon\Carbon::parse($value)->format('d/m/Y'); }

    public function build(array $d): array
    {
        $types = ['01'=>'01','07'=>'02','08'=>'03'];
        if (!isset($types[$d['document_type_id'] ?? ''])) FiscalAmounts::error('document_type_id','Tipo no soportado por esta etapa HKA.');
        foreach (['number','name','address','country_id'] as $field) {
            if (empty($d['issuer'][$field])) FiscalAmounts::error('issuer.'.$field,'Falta el snapshot fiscal del emisor.');
        }
        $fiscal = $d['fiscal_data'] ?? [];
        if (($d['invoice']['operation_type_id'] ?? '0101') !== '0101' || ($d['total_exportation'] ?? 0) > 0 || !empty($fiscal['special_tax_regime_id']) || !empty($fiscal['third_party']) || !empty($fiscal['conditional_data'])) {
            FiscalAmounts::error('fiscal_data','Exportaciones, regímenes y operaciones especiales aún no están implementados.');
        }
        $transaction = $fiscal['transaction_type_id'] ?? '01';
        $provider = $fiscal['catalog_snapshot']['provider_type_id']['code'] ?? null;
        if (!in_array($transaction,['01','98'],true) || ($transaction==='98' && $d['document_type_id']!=='08')) FiscalAmounts::error('fiscal_data','Transacción HKA no soportada.');
        $buyer = $d['customer'];
        $number = $buyer['number'] ?? '';
        $identity = $buyer['hka_identity_code'] ?? null;
        if (!$identity) FiscalAmounts::error('customer','Falta una equivalencia de identidad HKA.');
        foreach (['name','address','country_id','number'] as $key) if (empty($buyer[$key])) FiscalAmounts::error('customer.'.$key,'Dato del comprador requerido para HKA.');
        $id = ['tipoDocumento'=>$types[$d['document_type_id']], 'numeroDocumento'=>(string)$d['number'],
            'serie'=>$d['series'], 'fechaEmision'=>self::date($d['date_of_issue']),
            'horaEmision'=>\Carbon\Carbon::parse($d['time_of_issue'])->format('h:i:s a'),
            'tipoDeVenta'=>'Interna', 'moneda'=>$d['currency_type_id'], 'tipoTransaccion'=>$transaction,
            'transaccionId'=>self::transactionId($d['operation_key']), 'sucursal'=>(string)($d['establishment']['code'] ?? '')];
        if ($provider) $id['tipoProveedor'] = $provider;
        $conditions = ['01' => 'Contado', '02' => 'Crédito'];
        $condition = $d['payment_condition_id'] ?? null;
        if ($condition !== null && $condition !== '') {
            if (!isset($conditions[$condition])) FiscalAmounts::error('payment_condition_id', 'Condición de pago sin equivalencia HKA.');
            $id['tipoDePago'] = $conditions[$condition];
        }
        if (!in_array($d['currency_type_id'],['VES','USD'],true)) FiscalAmounts::error('currency_type_id','Moneda sin equivalencia HKA.');
        if (!empty($d['invoice']['date_of_due'])) $id['fechaVencimiento']=self::date($d['invoice']['date_of_due']);
        if (in_array($d['document_type_id'],['07','08'],true)) {
            $reference=$d['reference'] ?? [];
            foreach (['number','date_of_issue','total','control_number'] as $key) if (!isset($reference[$key]) || $reference[$key]==='') FiscalAmounts::error('reference.'.$key,'La nota requiere la referencia fiscal completa de la factura.');
            $id += ['serieFacturaAfectada'=>$reference['series'] ?? '', 'numeroFacturaAfectada'=>(string)$reference['number'],
                'fechaFacturaAfectada'=>self::date($reference['date_of_issue']), 'montoFacturaAfectada'=>self::money($reference['total']),
                'comentarioFacturaAfectada'=>$d['note']['note_description'] ?? ''];
        }
        $lines=[];
        foreach ($d['items'] as $i=>$line) {
            $unit=$line['item']['hka_unit_code'] ?? null;
            if (!$unit) FiscalAmounts::error('items','Falta equivalencia HKA de unidad de medida.');
            $code=$line['iva_rate']['code'] ?? null;
            if (!in_array($code,['G','R','A','E'],true)) FiscalAmounts::error('items','Falta clasificación de alícuota HKA.');
            $lines[]=['numeroLinea'=>(string)($i+1), 'indicadorBienoServicio'=>($line['item']['unit_type_id'] ?? '')==='SERV' ? '2':'1',
                'descripcion'=>$line['item']['description'], 'codigoPLU'=>$line['item']['internal_id'] ?? '',
                'cantidad'=>(string)$line['quantity'], 'unidadMedida'=>$unit, 'precioUnitario'=>number_format((float)$line['unit_value'], 4, '.', ''),
                'descuentoMonto'=>self::money($line['total_discount'] ?? 0), 'recargoMonto'=>self::money($line['total_charge'] ?? 0),
                'precioItem'=>self::money($line['total_value']), 'codigoImpuesto'=>$code,
                'tasaIVA'=>self::money($line['percentage_igv']), 'valorIVA'=>self::money($line['total_igv']),
                'valorTotalItem'=>self::money($line['total'])];
        }
        if (!$lines && $transaction!=='98') FiscalAmounts::error('items','El documento requiere líneas.');
        $taxes=[];$igtf=0;$igtfVes=0;
        foreach ($d['taxes'] as $tax) {
            if ($tax['tax_kind']==='IGTF') { $igtf+=$tax['amount'];$igtfVes+=$tax['amount_ves'];continue; }
            if ($tax['tax_kind']!=='IVA') FiscalAmounts::error('taxes','El tributo adicional requiere una equivalencia HKA implementada.');
            $taxes[]=['codigoTotalImp'=>$tax['code'],'alicuotaImp'=>self::money($tax['percentage']),
                'baseImponibleImp'=>self::money($tax['base']),'valorTotalImp'=>self::money($tax['amount'])];
        }
        if ($transaction==='98' && ($lines || (float)$d['total_igv']!==0.0 || $igtf<=0 || abs($d['total']-$igtf)>0.005)) FiscalAmounts::error('taxes','La transacción 98 requiere exclusivamente un cargo IGTF sin IVA ni artículos.');
        $forms=[];
        foreach ($d['payments'] as $p) {
            if (!empty($p['reversed_at']) || (!empty($p['receipt_parent_id']) && $d['document_type_id']==='01')) continue;
            $method=$p['payment_method_snapshot']['hka_code'] ?? null;
            if (!$method) FiscalAmounts::error('payments','Falta equivalencia HKA de medio de pago.');
            $forms[]=['forma'=>$method,'moneda'=>$p['currency_type_id'],'monto'=>self::money($p['original_amount']+$p['tax_amount']),
                'fecha'=>self::date($p['date_of_payment']),'tipoCambio'=>(string)$p['exchange_rate']];
        }
        $totals=['nroItems'=>(string)count($lines),'montoGravadoTotal'=>self::money($d['total_taxed']),
            'montoExentoTotal'=>self::money($d['total_exonerated']),'subtotal'=>self::money($d['total_value']),
            'totalIVA'=>self::money($d['total_igv']),'montoTotalConIVA'=>self::money($d['total']-$igtf),
            'totalAPagar'=>self::money($d['total']),'totalDescuento'=>self::money($d['total_discount']),
            'totalRecargos'=>self::money($d['total_charge']),'totalIGTF'=>self::money($igtf),'totalIGTF_VES'=>self::money($igtfVes),
            'impuestosSubtotal'=>$taxes,'formasPago'=>$forms];
        $header=['identificacionDocumento'=>$id,'comprador'=>['tipoIdentificacion'=>$identity,'numeroIdentificacion'=>$number,
            'razonSocial'=>$buyer['name'],'direccion'=>$buyer['address'],'pais'=>$buyer['country_id']], 'totales'=>$totals];
        foreach (['email' => 'correo', 'telephone' => 'telefono'] as $local => $remote) {
            $contact = trim((string) ($buyer[$local] ?? ''));
            if ($contact !== '') $header['comprador'][$remote] = [$contact];
        }
        // These fields populate the fiscal PDF; HkaMail owns the existing automatic email flow.
        $header['comprador']['notificar'] = 'No';
        if ($d['currency_type_id']==='USD') {
            $v=$d['currency_totals'] ?? [];
            if (!$v) FiscalAmounts::error('currency_totals','Faltan totales persistidos en VES.');
            $vesTaxes=[];
            foreach ($v['tax_breakdown'] as $t) if($t['tax_kind']==='IVA') $vesTaxes[]=['codigoTotalImp'=>$t['code'],'alicuotaImp'=>self::money($t['percentage']),'baseImponibleImp'=>self::money($t['base_ves']),'valorTotalImp'=>self::money($t['amount_ves'])];
            $header['totalesOtraMoneda']=['moneda'=>'VES','tipoCambio'=>(string)$d['exchange_rate_sale'],
                'montoGravadoTotal'=>self::money($v['taxed']),'montoExentoTotal'=>self::money($v['exempt']),
                'subtotal'=>self::money($v['taxed']+$v['exempt']),'totalIVA'=>self::money($v['iva']),
                'montoTotalConIVA'=>self::money($v['total']-$igtfVes),'totalAPagar'=>self::money($v['total']),
                'totalDescuento'=>self::money($v['discount']),'totalRecargos'=>self::money($v['charge']),'impuestosSubtotal'=>$vesTaxes];
        }
        $payload=['documentoElectronico'=>['encabezado'=>$header,'detallesItems'=>$lines]];
        (new HkaSchemaValidator())->validate($payload);
        return $payload;
    }
}
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
