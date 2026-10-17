<?php
namespace App\Services\Fiscal;
use App\Support\Exact\Decimal as D;

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
    private static function money($value): string { return D::represent($value, 2, true); }
    private static function date($value): string { return \Carbon\Carbon::parse($value)->format('d/m/Y'); }

    public function build(array $d): array
    {
        HkaIvaRetentionCapability::assertSnapshot($d);
        HkaIslrRetentionCapability::assertSnapshot($d);
        $types = ['01'=>'01','07'=>'02','08'=>'03'];
        if (!isset($types[$d['document_type_id'] ?? ''])) FiscalAmounts::error('document_type_id','Tipo no soportado por esta etapa HKA.');
        foreach (['number','name','address','country_id'] as $field) {
            if (empty($d['issuer'][$field])) FiscalAmounts::error('issuer.'.$field,'Falta el snapshot fiscal del emisor.');
        }
        $ordinaryNote=HkaNotePolicy::snapshot($d);
        if ($ordinaryNote) HkaNotePolicy::assertSnapshot($d);
        $fiscal = $d['fiscal_data'] ?? [];
        $specialRegime = isset($fiscal['special_tax_regime_id']);
        $regimeDescription = $specialRegime ? \App\Services\SpecialTaxRegimePolicy::description($fiscal['special_tax_regime_id'], $fiscal['catalog_snapshot']['special_tax_regime_id'] ?? []) : null;
        $thirdParty = ($fiscal['transaction_type_id'] ?? '01') === '99' || ($ordinaryNote && !empty($fiscal['third_party']));
        $operation = $d['invoice']['operation_type_id'] ?? '0101';
        $exportation = \App\Services\ExportInvoicePolicy::isExport($operation);
        if ((!$exportation && ($operation !== '0101' || ($d['total_exportation'] ?? 0) > 0)) || (!$thirdParty && !empty($fiscal['third_party'])) || !empty($fiscal['conditional_data'])) {
            FiscalAmounts::error('fiscal_data','La operación, régimen o condición fiscal seleccionada no está habilitada.');
        }
        if ($thirdParty && !$ordinaryNote) {
            if ($d['document_type_id'] !== '01' || $operation !== '0101' || ($d['payment_condition_id'] ?? null) !== '01') FiscalAmounts::error('fiscal_data', 'La transacción 99 requiere una Factura de venta interna al contado.');
            \App\Services\ThirdPartyInvoicePolicy::hkaThirdParty($fiscal['third_party'] ?? []);
        }
        if (($thirdParty || $specialRegime) && !$ordinaryNote) HkaDomesticInvoicePolicy::assertSnapshot($d);
        if ($exportation) {
            $term = \App\Services\ExportInvoicePolicy::INCOTERMS[$operation];
            if (empty($d['items'])) FiscalAmounts::error('items', 'La exportación requiere líneas de bienes.');
            if ($d['document_type_id'] !== '01' || ($fiscal['transaction_type_id'] ?? '01') !== '01') FiscalAmounts::error('document_type_id', 'Las notas y operaciones especiales de exportación no están habilitadas.');
            $saved = $fiscal['catalog_snapshot']['operation_type'] ?? [];
            if (($saved['id'] ?? null) !== $operation || ($saved['incoterm'] ?? null) !== $term) FiscalAmounts::error('fiscal_data', 'Falta el INCOTERM conservado de la exportación.');
            $buyer = $d['customer'];
            if (($buyer['identity_document_type_id'] ?? null) !== 'ND' || ($buyer['country_id'] ?? 'VE') === 'VE' || !preg_match('/^[A-Z]{2,4}$/D', $buyer['external_identity_type'] ?? '') || ($buyer['external_identity_type'] ?? '') === 'ND' || ($buyer['hka_identity_code'] ?? null) !== $buyer['external_identity_type']) FiscalAmounts::error('customer', 'La exportación requiere identificación y domicilio del comprador no domiciliado.');
            if (!D::read($d,'total_taxed')->isZero() || !D::read($d,'total_exonerated')->isZero() || !D::read($d,'total_igv')->isZero() || D::read($d,'total_exportation')->minus(D::read($d,'total_value'))->abs()->isGreaterThan('0.005')) FiscalAmounts::error('total_exportation', 'Los totales de exportación no corresponden a una operación con IVA 0 %.');
        }
        // Distribute global export adjustments on a copy so HKA receives each net E line once.
        // Its global-discount validations differ from the local export subtotal convention.
        if ($exportation) $d = self::exportAdjustmentsOnLines($d);
        $usdExport = $exportation && $d['currency_type_id'] === 'USD' ? $d : null;
        if ($usdExport) {
            $v = $d['currency_totals'] ?? [];
            if (!isset($v['exportation']) || D::read($v,'exportation')->minus(D::convert(D::read($d,'total_exportation'),'USD','VES',$d['exchange_rate_sale']))->abs()->isGreaterThan('0.005') || !D::of($v['taxed'] ?? -1)->isZero() || !D::of($v['exempt'] ?? -1)->isZero() || !D::of($v['iva'] ?? -1)->isZero()) FiscalAmounts::error('currency_totals', 'Faltan los totales de exportación conservados en VES.');
            // HKA validates its principal amounts in VES and divides by the VES/USD rate for OtraMoneda.
            $d = self::exportAmountsInVes($d);
        }
        if ($exportation) $d = self::exportCents($d);
        $transaction = $fiscal['transaction_type_id'] ?? '01';
        $provider = $fiscal['catalog_snapshot']['provider_type_id']['code'] ?? null;
        if (!in_array($transaction,$ordinaryNote ? ['02','03','04'] : ['01','98','99'],true) || ($transaction==='98' && $d['document_type_id']!=='08')) FiscalAmounts::error('fiscal_data','Transacción HKA no soportada.');
        $buyer = $d['customer'];
        $number = $buyer['fiscal_profile']['rif'] ?? ($buyer['number'] ?? '');
        $identity = !empty($buyer['fiscal_profile']['rif']) ? substr($buyer['fiscal_profile']['rif'], 0, 1) : ($buyer['hka_identity_code'] ?? null);
        if (!$identity) FiscalAmounts::error('customer','Falta una equivalencia de identidad HKA.');
        foreach (['name','address','country_id','number'] as $key) if (empty($buyer[$key])) FiscalAmounts::error('customer.'.$key,'Dato del comprador requerido para HKA.');
        $id = ['tipoDocumento'=>$types[$d['document_type_id']], 'numeroDocumento'=>(string)$d['number'],
            'serie'=>$d['series'], 'fechaEmision'=>self::date($d['date_of_issue']),
            'horaEmision'=>\Carbon\Carbon::parse($d['time_of_issue'])->format('h:i:s a'),
            'tipoDeVenta'=>$exportation ? $term : 'Interna', 'moneda'=>$d['currency_type_id'], 'tipoTransaccion'=>$transaction,
            'transaccionId'=>self::transactionId($d['operation_key']), 'sucursal'=>(string)($d['establishment']['code'] ?? '')];
        if ($regimeDescription !== null) $id['regimenEspTributacion'] = $regimeDescription;
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
            if ($exportation && (($line['affectation_igv_type_id'] ?? null) !== 'EXP' || ($line['item']['unit_type_id'] ?? '') === 'SERV' || !D::read($line,'total_igv')->isZero() || !D::read($line,'percentage_igv')->isZero() || ($line['iva_rate']['code'] ?? '') !== 'E')) FiscalAmounts::error('items', 'La exportación exige bienes con afectación Exportación y IVA 0 %.');
            $unit=$line['item']['hka_unit_code'] ?? null;
            if (!$unit) FiscalAmounts::error('items','Falta equivalencia HKA de unidad de medida.');
            $code=$line['iva_rate']['code'] ?? null;
            if (!in_array($code,['G','R','A','E'],true)) FiscalAmounts::error('items','Falta clasificación de alícuota HKA.');
            $lines[]=['numeroLinea'=>(string)($i+1), 'indicadorBienoServicio'=>($line['item']['unit_type_id'] ?? '')==='SERV' ? '2':'1',
                'descripcion'=>$line['item']['description'], 'codigoPLU'=>$line['item']['internal_id'] ?? '',
                'cantidad'=>D::represent(D::read($line,'quantity'), 4), 'unidadMedida'=>$unit, 'precioUnitario'=>D::represent($line['unit_value'], 4, true),
                'descuentoMonto'=>self::money($line['total_discount'] ?? 0), 'recargoMonto'=>self::money($line['total_charge'] ?? 0),
                'precioItem'=>self::money(D::read($line,'total_value')), 'codigoImpuesto'=>$code,
                'tasaIVA'=>self::money(D::read($line,'percentage_igv')), 'valorIVA'=>self::money(D::read($line,'total_igv')),
                'valorTotalItem'=>self::money(D::read($line,'total'))];
            if (($exportation || $thirdParty) && (($line['total_discount'] ?? 0) > 0 || ($line['total_charge'] ?? 0) > 0)) {
                $lines[$i]['precioAntesDescuento'] = \App\Services\ExchangeRates\ExchangeRateMath::finalAmount(
                    D::read($line,'unit_value')->multipliedBy(D::read($line,'quantity'))
                );
            }
        }
        if (!$lines && $transaction!=='98') FiscalAmounts::error('items','El documento requiere líneas.');
        $taxes=[];$igtf=D::of(0);$igtfVes=D::of(0);
        foreach ($d['taxes'] as $tax) {
            if ($tax['tax_kind']==='IGTF') { $igtf=$igtf->plus(D::read($tax,'amount'));$igtfVes=$igtfVes->plus(D::read($tax,'amount_ves'));continue; }
            if ($tax['tax_kind']!=='IVA') FiscalAmounts::error('taxes','El tributo adicional requiere una equivalencia HKA implementada.');
            $taxes[]=['codigoTotalImp'=>$tax['code'],'alicuotaImp'=>self::money(D::read($tax,'percentage')),
                'baseImponibleImp'=>self::money(D::read($tax,'base')),'valorTotalImp'=>self::money(D::read($tax,'amount'))];
        }
        if ($transaction==='98' && ($lines || !D::read($d,'total_igv')->isZero() || $igtf->isLessThanOrEqualTo(0) || D::read($d,'total')->minus($igtf)->abs()->isGreaterThan('0.005'))) FiscalAmounts::error('taxes','La transacción 98 requiere exclusivamente un cargo IGTF sin IVA ni artículos.');
        $forms=[];
        foreach ($d['payments'] as $p) {
            if (!empty($p['reversed_at']) || (!empty($p['receipt_parent_id']) && ($d['document_type_id']==='01' || $ordinaryNote))) continue;
            $method=$p['payment_method_snapshot']['hka_code'] ?? null;
            if (!$method) FiscalAmounts::error('payments','Falta equivalencia HKA de medio de pago.');
            $paymentTax=$p['tax_amount'];
            if ($ordinaryNote && $d['document_type_id'] === '08' && !array_filter($d['taxes'],fn ($t)=>$t['tax_kind'] === 'IGTF' && ($t['document_payment_id'] ?? null) === ($p['id'] ?? null))) $paymentTax=0;
            $forms[]=['forma'=>$method,'moneda'=>$p['currency_type_id'],'monto'=>self::money($p['original_amount']+$paymentTax),
                'fecha'=>self::date($p['date_of_payment']),'tipoCambio'=>(string)$p['exchange_rate']];
        }
        $forms=HkaNotePayments::append($d,$forms);
        $totals=['nroItems'=>(string)count($lines),'montoGravadoTotal'=>self::money(D::read($d,'total_taxed')),
            'montoExentoTotal'=>self::money($exportation ? $d['total_exportation'] : $d['total_exonerated']),
            'subtotal'=>self::money(D::read($d,'total_value')),
            'totalIVA'=>self::money(D::read($d,'total_igv')),'montoTotalConIVA'=>self::money(D::read($d,'total')->minus($igtf)),
            'totalAPagar'=>self::money(D::read($d,'total')),'totalDescuento'=>self::money($exportation ? 0 : $d['total_discount']),
            'totalRecargos'=>self::money($exportation ? 0 : D::read($d,'total_charge')),'totalIGTF'=>self::money($igtf),'totalIGTF_VES'=>self::money($igtfVes),
            'impuestosSubtotal'=>$taxes,'formasPago'=>$forms];
        $header=['identificacionDocumento'=>$id,'comprador'=>['tipoIdentificacion'=>$identity,'numeroIdentificacion'=>$number,
            'razonSocial'=>$buyer['name'],'direccion'=>$buyer['address'],'pais'=>$buyer['country_id']], 'totales'=>$totals];
        foreach (['email' => 'correo', 'telephone' => 'telefono'] as $local => $remote) {
            $contact = trim((string) ($buyer[$local] ?? ''));
            if ($contact !== '') $header['comprador'][$remote] = [$contact];
        }
        // These fields populate the fiscal PDF; HkaMail owns the existing automatic email flow.
        $header['comprador']['notificar'] = 'No';
        if ($thirdParty) $header['tercero'] = \App\Services\ThirdPartyInvoicePolicy::hkaThirdParty($fiscal['third_party']);
        if ($usdExport) {
            $u = $usdExport;
            $usdTaxes = [];
            $usdIgtf = D::of(0);
            foreach ($u['taxes'] as $t) {
                if ($t['tax_kind'] === 'IGTF') $usdIgtf=$usdIgtf->plus(D::read($t,'amount'));
                if ($t['tax_kind'] === 'IVA') $usdTaxes[] = ['codigoTotalImp'=>$t['code'], 'alicuotaImp'=>self::money(D::read($t,'percentage')), 'baseImponibleImp'=>self::money(D::read($t,'base')), 'valorTotalImp'=>self::money(D::read($t,'amount'))];
            }
            $header['totalesOtraMoneda'] = ['moneda'=>'USD', 'tipoCambio'=>(string) $u['exchange_rate_sale'],
                'montoGravadoTotal'=>'0.00', 'montoExentoTotal'=>self::money(D::sum(array_map(fn($r)=>D::read($r,'total_value'),$u['items']))),
                'subtotal'=>self::money(D::read($u,'total_value')), 'totalIVA'=>'0.00', 'totalAPagar'=>self::money(D::read($u,'total')),
                'montoTotalConIVA'=>self::money(D::read($u,'total')->minus($usdIgtf)),
                'totalDescuento'=>self::money(D::sum(array_map(fn($r)=>D::of($r['amount_exact'] ?? $r['amount']),$u['discounts'] ?? []))),
                'totalRecargos'=>self::money(D::sum(array_map(fn($r)=>D::of($r['amount_exact'] ?? $r['amount']),$u['charges'] ?? []))), 'impuestosSubtotal'=>$usdTaxes];
        }
        if ($d['currency_type_id']==='USD') {
            $v=$d['currency_totals'] ?? [];
            if (!$v) FiscalAmounts::error('currency_totals','Faltan totales persistidos en VES.');
            $vesTaxes=[];
            foreach ($v['tax_breakdown'] as $t) if($t['tax_kind']==='IVA') $vesTaxes[]=['codigoTotalImp'=>$t['code'],'alicuotaImp'=>self::money(D::read($t,'percentage')),'baseImponibleImp'=>self::money(D::read($t,'base_ves')),'valorTotalImp'=>self::money(D::read($t,'amount_ves'))];
            $header['totalesOtraMoneda']=['moneda'=>'VES','tipoCambio'=>(string)$d['exchange_rate_sale'],
                'montoGravadoTotal'=>self::money(D::read($v,'taxed')),'montoExentoTotal'=>self::money(D::read($v,'exempt')),
                'subtotal'=>self::money(D::sum([$v['taxed'],$v['exempt']])),'totalIVA'=>self::money(D::read($v,'iva')),
                'montoTotalConIVA'=>self::money(D::read($v,'total')->minus($igtfVes)),'totalAPagar'=>self::money(D::read($v,'total')),
                'totalDescuento'=>self::money(D::read($v,'discount')),'totalRecargos'=>self::money(D::read($v,'charge')),'impuestosSubtotal'=>$vesTaxes];
        }
        $payload=['documentoElectronico'=>['encabezado'=>$header,'detallesItems'=>$lines]];
        foreach (['issuer'=>'Emisor', 'customer'=>'Comprador'] as $role=>$label) {
            $profile = $d[$role]['fiscal_profile'] ?? [];
            if (SpecialTaxpayerPolicy::spe($profile, \Carbon\Carbon::parse($d['date_of_issue'])->format('Y-m-d'))) {
                $payload['documentoElectronico']['infoAdicional'][] = ['campo'=>'Condición fiscal '.$label,'valor'=>'Sujeto Pasivo Especial'];
            }
        }
        if ($ordinaryNote || $thirdParty || $specialRegime || (!$exportation && ($d['document_type_id'] === '01' || $transaction === '98') && ($d['currency_type_id'] === 'USD' || $igtf->isGreaterThan(0)))) $payload = HkaDomesticMoney::adapt($payload, $d);
        $payload=HkaNotePayments::reconcile($payload,$d);
        (new HkaSchemaValidator())->validate($payload);
        return $payload;
    }

    /** Translate a copy; document currency, receipts and frozen local amounts remain unchanged. */
    private static function exportAmountsInVes(array $d): array
    {
        $rate = $d['exchange_rate_sale'];
        foreach (['total_taxed','total_exonerated','total_exportation','total_value','total_igv','total','total_discount','total_charge'] as $field) {
            $converted = D::convert(D::read($d,$field), 'USD', 'VES', $rate);
            $d[$field] = D::represent($converted);
            $d['calculation_snapshot']['values'][$field] = D::fraction($converted);
        }
        foreach ($d['items'] as &$line) {
            foreach (['unit_value','unit_price','total_base_igv','total_value','total_igv','total_taxes','total','total_discount','total_charge'] as $field) {
                $converted=D::convert(D::read($line,$field),'USD','VES',$rate);
                $line[$field]=D::represent($converted);
                $line['calculation_snapshot']['values'][$field]=D::fraction($converted);
            }
            foreach ($line['discounts'] ?? [] as $i => $discount) $line['discounts'][$i]['amount'] = FiscalAmounts::convert($discount['amount'], 'USD', 'VES', $rate);
        }
        unset($line);
        foreach ($d['taxes'] as &$tax) foreach (['base','amount'] as $field) {
            $value=D::convert(D::read($tax,$field),'USD','VES',$rate);
            $tax[$field]=D::represent($value);$tax['calculation_snapshot']['values'][$field]=D::fraction($value);
        }
        unset($tax);
        foreach (['discounts','charges'] as $field) foreach ($d[$field] ?? [] as $i => $adjustment) $d[$field][$i]['amount'] = FiscalAmounts::convert($adjustment['amount'], 'USD', 'VES', $rate);
        $d['currency_type_id'] = 'VES';
        return $d;
    }

    /** One final allocation per provider field; fractions remain untouched in the local snapshot. */
    private static function exportCents(array $d): array
    {
        foreach (['total_base_igv','total_value','total','total_discount','total_charge'] as $field) {
            $weights = array_map(fn($line)=>D::read($line,$field),$d['items']);
            $target = in_array($field,['total_base_igv','total_value','total'],true) ? D::read($d,'total_value') : D::sum($weights);
            $amounts = D::allocate($target,$weights,2);
            foreach ($d['items'] as $i=>&$line) {
                $line[$field]=$amounts[$i];$line['calculation_snapshot']['values'][$field]=D::fraction($amounts[$i]);
            }
            unset($line);
        }
        return $d;
    }

    /** Preserve total adjustments exactly in cents, including already distributed discounts. */
    private static function exportAdjustmentsOnLines(array $d): array
    {
        $discount = D::sum(array_map(fn($v)=>$v['amount_exact'] ?? $v['amount'], $d['discounts'] ?? []));
        foreach ($d['items'] as $line) foreach ($line['discounts'] ?? [] as $row) if (!empty($row['from_global_distribution'])) $discount = $discount->minus(D::of($row['amount_exact'] ?? $row['amount']));
        $discount = D::max(0,$discount);
        $charge = D::sum(array_map(fn($v)=>$v['amount_exact'] ?? $v['amount'], $d['charges'] ?? []));
        if (!$discount->isZero() || !$charge->isZero()) {
            $weights = array_map(fn($line)=>D::read($line,'total_value'),$d['items']);
            $base = D::sum($weights);
            if ($discount->isGreaterThan($base)) FiscalAmounts::error('discounts', 'El descuento supera el valor exportado.');
            if ($base->isZero()) $weights = array_fill(0,count($weights),D::of(1));
            foreach ($d['items'] as $i=>&$line) {
                $share = D::of($weights[$i])->dividedBy(D::sum($weights));
                $deduction = $discount->multipliedBy($share);
                $addition = $charge->multipliedBy($share);
                $values = ['total_discount'=>D::read($line,'total_discount')->plus($deduction),
                    'total_charge'=>D::read($line,'total_charge')->plus($addition)];
                foreach (['total_base_igv','total_value','total'] as $field) $values[$field]=D::read($line,$field)->minus($deduction)->plus($addition);
                $line = array_replace($line,D::values($values));
                $line['calculation_snapshot']['values'] = array_replace($line['calculation_snapshot']['values'] ?? [],array_map([D::class,'fraction'],$values));
            }
            unset($line);
        }
        $d['discounts'] = $d['charges'] = [];
        return $d;
    }
}
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
