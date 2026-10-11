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
        $operation = $d['invoice']['operation_type_id'] ?? '0101';
        $exportation = \App\Services\ExportInvoicePolicy::isExport($operation);
        if ((!$exportation && ($operation !== '0101' || ($d['total_exportation'] ?? 0) > 0)) || !empty($fiscal['special_tax_regime_id']) || !empty($fiscal['third_party']) || !empty($fiscal['conditional_data'])) {
            FiscalAmounts::error('fiscal_data','La operación, régimen o condición fiscal seleccionada no está habilitada.');
        }
        if ($exportation) {
            $term = \App\Services\ExportInvoicePolicy::INCOTERMS[$operation];
            if (empty($d['items'])) FiscalAmounts::error('items', 'La exportación requiere líneas de bienes.');
            if ($d['document_type_id'] !== '01' || ($fiscal['transaction_type_id'] ?? '01') !== '01') FiscalAmounts::error('document_type_id', 'Las notas y operaciones especiales de exportación no están habilitadas.');
            $saved = $fiscal['catalog_snapshot']['operation_type'] ?? [];
            if (($saved['id'] ?? null) !== $operation || ($saved['incoterm'] ?? null) !== $term) FiscalAmounts::error('fiscal_data', 'Falta el INCOTERM conservado de la exportación.');
            $buyer = $d['customer'];
            if (($buyer['identity_document_type_id'] ?? null) !== 'ND' || ($buyer['country_id'] ?? 'VE') === 'VE' || !preg_match('/^[A-Z]{2,4}$/D', $buyer['external_identity_type'] ?? '') || ($buyer['external_identity_type'] ?? '') === 'ND' || ($buyer['hka_identity_code'] ?? null) !== $buyer['external_identity_type']) FiscalAmounts::error('customer', 'La exportación requiere identificación y domicilio del comprador no domiciliado.');
            if ((float) $d['total_taxed'] !== 0.0 || (float) $d['total_exonerated'] !== 0.0 || (float) $d['total_igv'] !== 0.0 || abs(($d['total_exportation'] ?? 0) - $d['total_value']) > 0.005) FiscalAmounts::error('total_exportation', 'Los totales de exportación no corresponden a una operación con IVA 0 %.');
        }
        // Distribute global export adjustments on a copy so HKA receives each net E line once.
        // Its global-discount validations differ from the local export subtotal convention.
        if ($exportation) $d = self::exportAdjustmentsOnLines($d);
        $usdExport = $exportation && $d['currency_type_id'] === 'USD' ? $d : null;
        if ($usdExport) {
            $v = $d['currency_totals'] ?? [];
            if (!isset($v['exportation']) || abs($v['exportation'] - FiscalAmounts::convert($d['total_exportation'], 'USD', 'VES', $d['exchange_rate_sale'])) > 0.005 || (float) ($v['taxed'] ?? -1) !== 0.0 || (float) ($v['exempt'] ?? -1) !== 0.0 || (float) ($v['iva'] ?? -1) !== 0.0) FiscalAmounts::error('currency_totals', 'Faltan los totales de exportación conservados en VES.');
            // HKA validates its principal amounts in VES and divides by the VES/USD rate for OtraMoneda.
            $d = self::exportAmountsInVes($d);
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
            'tipoDeVenta'=>$exportation ? $term : 'Interna', 'moneda'=>$d['currency_type_id'], 'tipoTransaccion'=>$transaction,
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
            if ($exportation && (($line['affectation_igv_type_id'] ?? null) !== 'EXP' || ($line['item']['unit_type_id'] ?? '') === 'SERV' || (float) $line['total_igv'] !== 0.0 || (float) $line['percentage_igv'] !== 0.0 || ($line['iva_rate']['code'] ?? '') !== 'E')) FiscalAmounts::error('items', 'La exportación exige bienes con afectación Exportación y IVA 0 %.');
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
            if ($exportation && (($line['total_discount'] ?? 0) > 0 || ($line['total_charge'] ?? 0) > 0)) {
                $lines[$i]['precioAntesDescuento'] = \App\Services\ExchangeRates\ExchangeRateMath::finalAmount(
                    \App\Services\ExchangeRates\ExchangeRateMath::rational($line['unit_value'])->multipliedBy($line['quantity'])
                );
            }
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
            'montoExentoTotal'=>self::money($exportation ? $d['total_exportation'] : $d['total_exonerated']),
            'subtotal'=>self::money($d['total_value']),
            'totalIVA'=>self::money($d['total_igv']),'montoTotalConIVA'=>self::money($d['total']-$igtf),
            'totalAPagar'=>self::money($d['total']),'totalDescuento'=>self::money($exportation ? 0 : $d['total_discount']),
            'totalRecargos'=>self::money($exportation ? 0 : $d['total_charge']),'totalIGTF'=>self::money($igtf),'totalIGTF_VES'=>self::money($igtfVes),
            'impuestosSubtotal'=>$taxes,'formasPago'=>$forms];
        $header=['identificacionDocumento'=>$id,'comprador'=>['tipoIdentificacion'=>$identity,'numeroIdentificacion'=>$number,
            'razonSocial'=>$buyer['name'],'direccion'=>$buyer['address'],'pais'=>$buyer['country_id']], 'totales'=>$totals];
        foreach (['email' => 'correo', 'telephone' => 'telefono'] as $local => $remote) {
            $contact = trim((string) ($buyer[$local] ?? ''));
            if ($contact !== '') $header['comprador'][$remote] = [$contact];
        }
        // These fields populate the fiscal PDF; HkaMail owns the existing automatic email flow.
        $header['comprador']['notificar'] = 'No';
        if ($usdExport) {
            $u = $usdExport;
            $usdTaxes = [];
            $usdIgtf = 0;
            foreach ($u['taxes'] as $t) {
                if ($t['tax_kind'] === 'IGTF') $usdIgtf += $t['amount'];
                if ($t['tax_kind'] === 'IVA') $usdTaxes[] = ['codigoTotalImp'=>$t['code'], 'alicuotaImp'=>self::money($t['percentage']), 'baseImponibleImp'=>self::money($t['base']), 'valorTotalImp'=>self::money($t['amount'])];
            }
            $header['totalesOtraMoneda'] = ['moneda'=>'USD', 'tipoCambio'=>(string) $u['exchange_rate_sale'],
                'montoGravadoTotal'=>'0.00', 'montoExentoTotal'=>self::money(array_sum(array_column($u['items'], 'total_value'))),
                'subtotal'=>self::money($u['total_value']), 'totalIVA'=>'0.00', 'totalAPagar'=>self::money($u['total']),
                'montoTotalConIVA'=>self::money($u['total']-$usdIgtf),
                'totalDescuento'=>self::money(array_sum(array_column($u['discounts'] ?? [], 'amount'))),
                'totalRecargos'=>self::money(array_sum(array_column($u['charges'] ?? [], 'amount'))), 'impuestosSubtotal'=>$usdTaxes];
        }
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

    /** Translate a copy; document currency, receipts and frozen local amounts remain unchanged. */
    private static function exportAmountsInVes(array $d): array
    {
        $rate = $d['exchange_rate_sale'];
        foreach (['total_taxed','total_exonerated','total_exportation','total_value','total_igv','total','total_discount','total_charge'] as $field) $d[$field] = FiscalAmounts::convert($d[$field], 'USD', 'VES', $rate);
        foreach ($d['items'] as &$line) {
            foreach (['unit_value','unit_price'] as $field) $line[$field] = \App\Services\ExchangeRates\ExchangeRateMath::multiply($line[$field], $rate, 4);
            foreach (['total_base_igv','total_value','total_igv','total_taxes','total','total_discount','total_charge'] as $field) $line[$field] = FiscalAmounts::convert($line[$field] ?? 0, 'USD', 'VES', $rate);
            foreach ($line['discounts'] ?? [] as $i => $discount) $line['discounts'][$i]['amount'] = FiscalAmounts::convert($discount['amount'], 'USD', 'VES', $rate);
        }
        unset($line);
        // Converting each line can leave cent residuals against the frozen document total.
        $residual = round($d['total_value'] - array_sum(array_column($d['items'], 'total_value')), 2);
        if ($residual != 0) {
            $last = array_key_last($d['items']);
            while ($last > 0 && ($d['items'][$last]['total_value'] <= 0 || $d['items'][$last]['total_value'] + $residual < 0)) $last--;
            if ($d['items'][$last]['total_value'] + $residual < 0) FiscalAmounts::error('currency_totals', 'No se puede conciliar el redondeo de las líneas exportadas.');
            foreach (['total_base_igv','total_value','total'] as $field) $d['items'][$last][$field] = round($d['items'][$last][$field] + $residual, 2);
            $field = $residual > 0 ? 'total_charge' : 'total_discount';
            $d['items'][$last][$field] = round($d['items'][$last][$field] + abs($residual), 2);
        }
        foreach ($d['taxes'] as &$tax) foreach (['base','amount'] as $field) $tax[$field] = FiscalAmounts::convert($tax[$field], 'USD', 'VES', $rate);
        unset($tax);
        foreach (['discounts','charges'] as $field) foreach ($d[$field] ?? [] as $i => $adjustment) $d[$field][$i]['amount'] = FiscalAmounts::convert($adjustment['amount'], 'USD', 'VES', $rate);
        $d['currency_type_id'] = 'VES';
        return $d;
    }

    /** Preserve total adjustments exactly in cents, including already distributed discounts. */
    private static function exportAdjustmentsOnLines(array $d): array
    {
        $discount = array_sum(array_column($d['discounts'] ?? [], 'amount'));
        foreach ($d['items'] as $line) foreach ($line['discounts'] ?? [] as $row) if (!empty($row['from_global_distribution'])) $discount -= $row['amount'];
        $discount = max(0, (int) round($discount * 100));
        $charge = (int) round(array_sum(array_column($d['charges'] ?? [], 'amount')) * 100);
        if ($discount || $charge) {
            $weights = array_map(fn ($line) => (int) round($line['total_value'] * 100), $d['items']);
            $base = array_sum($weights);
            if ($discount > $base) FiscalAmounts::error('discounts', 'El descuento supera el valor exportado.');
            if (!$base) { $weights = array_fill(0, count($d['items']), 1); $base = count($weights); }
            $cumulative = $priorDiscount = $priorCharge = 0;
            foreach ($d['items'] as $i => &$line) {
                $cumulative += $weights[$i];
                $math = \App\Services\ExchangeRates\ExchangeRateMath::rational($cumulative)->dividedBy($base);
                $toDiscount = (int) (string) $math->multipliedBy($discount)->toScale(0, \Brick\Math\RoundingMode::HALF_UP);
                $toCharge = (int) (string) $math->multipliedBy($charge)->toScale(0, \Brick\Math\RoundingMode::HALF_UP);
                $lineDiscount = ($toDiscount - $priorDiscount) / 100;
                $lineCharge = ($toCharge - $priorCharge) / 100;
                $line['total_discount'] = round(($line['total_discount'] ?? 0) + $lineDiscount, 2);
                $line['total_charge'] = round(($line['total_charge'] ?? 0) + $lineCharge, 2);
                foreach (['total_base_igv','total_value','total'] as $field) $line[$field] = round($line[$field] - $lineDiscount + $lineCharge, 2);
                $priorDiscount = $toDiscount; $priorCharge = $toCharge;
            }
            unset($line);
        }
        $d['discounts'] = $d['charges'] = [];
        return $d;
    }
}
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
