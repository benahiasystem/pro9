<?php

namespace App\Services\Fiscal;

use App\Services\ExchangeRates\ExchangeRateMath;
use App\Support\Exact\Decimal as D;

/** Adapt only a copy of domestic invoice money; local bases, adjustments and receipts stay frozen. */
final class HkaDomesticMoney
{
    private static function cents($value): \Brick\Math\BigRational { return D::of(D::represent($value,2))->multipliedBy(100); }
    private static function money($cents): string { return D::represent(D::of($cents)->dividedBy(100),2,true); }
    private static function group($code, $rate): string { return $code.':'.D::represent($rate,2,true); }

    /** Match each HKA item to the final tax base, including global discounts already persisted. */
    public static function adapt(array $payload, array $snapshot): array
    {
        if ($snapshot['currency_type_id'] === 'USD') return self::inVes($payload,$snapshot);
        $doc = &$payload['documentoElectronico'];
        $totals = &$doc['encabezado']['totales'];
        validator($snapshot, ['items.*.total_base_igv'=>'required|numeric|min:0'])->validate();
        $taxes = [];
        foreach ($snapshot['taxes'] as $tax) if ($tax['tax_kind'] === 'IVA') {
            $taxes[self::group($tax['code'], $tax['percentage'])] = $tax;
        }
        $groups = [];
        foreach ($doc['detallesItems'] as $index => $line) $groups[self::group($line['codigoImpuesto'], $line['tasaIVA'])][] = $index;
        foreach ($groups as $key => $indices) {
            if (!isset($taxes[$key])) FiscalAmounts::error('taxes', 'Falta la base fiscal conservada de las líneas con terceros o régimen especial.');
            $weights = array_map(fn ($i) => D::read($snapshot['items'][$i],'total_base_igv'), $indices);
            if (D::sum($weights)->isZero()) $weights=array_fill(0,count($indices),D::of(1));
            $bases = D::allocate(D::read($taxes[$key],'base'),$weights,2);
            $ivas = D::allocate(D::read($taxes[$key],'amount'),$weights,2);
            foreach ($indices as $position => $i) {
                $line = &$doc['detallesItems'][$i];
                $line['precioItem'] = $bases[$position];
                $line['valorIVA'] = $ivas[$position];
                $gross = self::cents(D::read($snapshot['items'][$i],'unit_value')->multipliedBy(D::read($snapshot['items'][$i],'quantity')));
                $discount = $gross->minus(self::cents($bases[$position]));
                if ($discount->isNegative()) FiscalAmounts::error('items', 'La base fiscal supera el precio original de la línea.');
                $line['descuentoMonto'] = self::money($discount);
                $line['recargoMonto'] = '0.00';
                if (!$discount->isZero()) $line['precioAntesDescuento'] = self::money($gross);
                $line['valorTotalItem'] = D::represent(D::of($bases[$position])->plus($ivas[$position]),2,true);
                unset($line);
            }
        }
        $base = D::sum(array_map(fn ($tax) => self::cents(D::read($tax,'base')), $taxes));
        $untaxed = self::cents(D::read($snapshot,'total_value'))->minus($base);
        // Pro9 charges do not increase its IVA base. Present that conserved amount as an E service.
        // This avoids taxing the charge or reporting it twice as both an item and a header surcharge.
        if ($untaxed->isGreaterThan(0)) {
            $doc['detallesItems'][] = ['numeroLinea'=>(string) (count($doc['detallesItems']) + 1),
                'indicadorBienoServicio'=>'2', 'descripcion'=>'Cargos no gravados de la venta', 'codigoPLU'=>'',
                'cantidad'=>'1', 'unidadMedida'=>'C62', 'precioUnitario'=>D::represent($untaxed->dividedBy(100),4,true),
                'descuentoMonto'=>'0.00', 'recargoMonto'=>'0.00', 'precioItem'=>self::money($untaxed),
                'codigoImpuesto'=>'E', 'tasaIVA'=>'0.00', 'valorIVA'=>'0.00', 'valorTotalItem'=>self::money($untaxed)];
            $found = false;
            foreach ($totals['impuestosSubtotal'] as &$tax) if ($tax['codigoTotalImp'] === 'E') {
                $tax['baseImponibleImp'] = self::money(self::cents($tax['baseImponibleImp'])->plus($untaxed)); $found = true;
            }
            unset($tax);
            if (!$found) $totals['impuestosSubtotal'][] = ['codigoTotalImp'=>'E','alicuotaImp'=>'0.00','baseImponibleImp'=>self::money($untaxed),'valorTotalImp'=>'0.00'];
        }
        $totals['nroItems'] = (string) count($doc['detallesItems']);
        $totals['montoExentoTotal'] = self::money(self::cents(D::read($snapshot,'total_exonerated'))->plus(D::max(0,$untaxed)));
        $totals['totalDescuento'] = self::money(D::max(0,$untaxed->negated()));
        $totals['totalRecargos'] = '0.00';
        $totals['montoTotalConIVA'] = self::money(self::cents(D::read($snapshot,'total_value'))->plus(self::cents(D::read($snapshot,'total_igv'))));
        if ($untaxed->isNegative()) $totals['subtotalAntesDescuento'] = self::money($base);
        // HKA validates totalAPagar against this tax row, in addition to the IGTF totals.
        $igtfGroups = [];
        foreach ($snapshot['taxes'] as $tax) if ($tax['tax_kind'] === 'IGTF') {
            $key = self::group('IGTF', $tax['percentage']);
            $igtfGroups[$key] = ['codigoTotalImp'=>'IGTF', 'alicuotaImp'=>D::represent($tax['percentage'],2,true),
                'baseImponibleImp'=>self::money(self::cents($igtfGroups[$key]['baseImponibleImp'] ?? 0)->plus(self::cents(D::read($tax,'base')))),
                'valorTotalImp'=>self::money(self::cents($igtfGroups[$key]['valorTotalImp'] ?? 0)->plus(self::cents(D::read($tax,'amount'))))];
        }
        foreach ($igtfGroups as $tax) $totals['impuestosSubtotal'][] = $tax;
        return $payload;
    }

    private static function inVes(array $payload, array $snapshot): array
    {
        $rate = $snapshot['exchange_rate_sale']; $frozen = $snapshot['currency_totals'] ?? [];
        foreach (['taxed'=>'total_taxed', 'exempt'=>'total_exonerated', 'iva'=>'total_igv', 'total'=>'total'] as $ves => $local) {
            if (!isset($frozen[$ves]) || D::read($frozen,$ves)->minus(D::convert(D::read($snapshot,$local),'USD','VES',$rate))->abs()->isGreaterThan('0.005')) FiscalAmounts::error('currency_totals', 'Los totales VES de la factura con terceros o régimen especial no corresponden al snapshot documental.');
        }
        // Each currency is represented independently from the original fractions.
        $local=$snapshot;$local['currency_type_id']='VES';
        $usdPayload=self::adapt($payload,$local);
        $copy=function(array $record,array $fields) use ($rate) {
            foreach ($fields as $field) {
                $value=D::convert(D::read($record,$field),'USD','VES',$rate);
                $record[$field]=D::represent($value);
                $record['calculation_snapshot']['values'][$field]=D::fraction($value);
            }
            return $record;
        };
        $local=$copy($snapshot,['total_taxed','total_exonerated','total_value','total_igv','total','total_discount','total_charge']);
        $local['currency_type_id']='VES';
        $local['items']=array_map(fn($line)=>$copy($line,['unit_value','unit_price','total_base_igv','total_igv','total_value','total','total_discount','total_charge']),$snapshot['items']);
        $local['taxes']=array_map(fn($tax)=>$copy($tax,['base','amount']),$snapshot['taxes']);
        $doc=&$payload['documentoElectronico'];$header=&$doc['encabezado'];
        $fields=['montoGravadoTotal'=>'total_taxed','montoExentoTotal'=>'total_exonerated','subtotal'=>'total_value','totalIVA'=>'total_igv','totalAPagar'=>'total'];
        foreach ($fields as $provider=>$source) $header['totales'][$provider]=D::represent(D::read($local,$source),2,true);
        $header['totales']['totalIGTF']=D::represent(D::sum(array_map(fn($tax)=>$tax['tax_kind']==='IGTF' ? D::read($tax,'amount') : D::of(0),$local['taxes'])),2,true);
        foreach ($header['totales']['impuestosSubtotal'] as &$tax) {
            $source=collect($local['taxes'])->first(fn($entry)=>self::group($entry['code'],$entry['percentage'])===self::group($tax['codigoTotalImp'],$tax['alicuotaImp']));
            if ($source) foreach (['baseImponibleImp'=>'base','valorTotalImp'=>'amount'] as $key=>$field) $tax[$key]=D::represent(D::read($source,$field),2,true);
        }
        unset($tax);
        foreach ($doc['detallesItems'] as $i=>&$line) {
            $line['precioUnitario']=D::represent(D::read($local['items'][$i],'unit_value'),4,true);
        }
        unset($line,$header,$doc);
        $payload=self::adapt($payload,$local);
        $doc=&$payload['documentoElectronico'];$header=&$doc['encabezado'];
        $otherFields=['montoGravadoTotal','montoExentoTotal','subtotal','subtotalAntesDescuento','totalIVA','montoTotalConIVA','totalAPagar','totalDescuento','totalRecargos','impuestosSubtotal'];
        $header['totalesOtraMoneda']=array_intersect_key($usdPayload['documentoElectronico']['encabezado']['totales'],array_flip($otherFields));
        $header['totalesOtraMoneda']['moneda']='USD';$header['totalesOtraMoneda']['tipoCambio']=(string)$rate;
        $header['identificacionDocumento']['moneda']='VES';
        if (isset($header['identificacionDocumento']['montoFacturaAfectada'])) {
            $reference=$snapshot['reference'] ?? [];
            if (($reference['currency_type_id'] ?? 'USD')==='USD') $header['identificacionDocumento']['montoFacturaAfectada']=D::represent(D::convert(D::read($reference,'total'),'USD','VES',$reference['exchange_rate_sale'] ?? $rate),2,true);
        }
        $totals = &$header['totales'];
        $taxed = $exempt = D::of(0);
        foreach ($doc['detallesItems'] as $line) {
            if (D::of($line['tasaIVA'])->isGreaterThan(0)) $taxed=$taxed->plus(self::cents($line['precioItem']));
            else $exempt=$exempt->plus(self::cents($line['precioItem']));
        }
        $totals['montoGravadoTotal'] = self::money($taxed);
        $totals['montoExentoTotal'] = self::money($exempt);
        $baseResidual = self::cents($totals['subtotal'])->plus(self::cents($totals['totalDescuento']))->minus($taxed)->minus($exempt);
        if (!$baseResidual->isZero()) self::adjustBase($doc, $baseResidual, false);
        // Allocate the unavoidable currency cent to IVA; never alter local snapshots or receipts.
        $iva = D::of(0);
        foreach ($totals['impuestosSubtotal'] as $tax) if ($tax['codigoTotalImp'] !== 'IGTF') $iva=$iva->plus(self::cents($tax['valorTotalImp']));
        $totals['totalIVA'] = self::money($iva);
        $residual = self::cents($totals['totalAPagar'])->minus(self::cents($totals['subtotal']))->minus($iva)->minus(self::cents($totals['totalIGTF']));
        if (!$residual->isZero()) {
            $taxIndex = null; $lineIndex = null;
            foreach ($totals['impuestosSubtotal'] as $i => $tax) if ($tax['codigoTotalImp'] !== 'IGTF' && D::of($tax['alicuotaImp'])->isGreaterThan(0)) $taxIndex = $i;
            if ($residual->abs()->isGreaterThan(2)) FiscalAmounts::error('currency_totals', 'No se puede conciliar el redondeo fiscal VES de la factura.');
            if ($taxIndex === null) {
                self::adjustBase($doc, $residual, true);
            } else {
                $code = $totals['impuestosSubtotal'][$taxIndex]['codigoTotalImp']; $percentage = $totals['impuestosSubtotal'][$taxIndex]['alicuotaImp'];
                foreach ($doc['detallesItems'] as $i => $line) if (self::group($line['codigoImpuesto'], $line['tasaIVA']) === self::group($code, $percentage)) $lineIndex = $i;
                $totals['impuestosSubtotal'][$taxIndex]['valorTotalImp'] = self::money(self::cents($totals['impuestosSubtotal'][$taxIndex]['valorTotalImp'])->plus($residual));
                $doc['detallesItems'][$lineIndex]['valorIVA'] = self::money(self::cents($doc['detallesItems'][$lineIndex]['valorIVA'])->plus($residual));
                $totals['totalIVA'] = self::money(self::cents($totals['totalIVA'])->plus($residual));
            }
        }
        $totals['montoTotalConIVA'] = self::money(self::cents($totals['totalAPagar'])->minus(self::cents($totals['totalIGTF'])));
        foreach ($doc['detallesItems'] as &$line) $line['valorTotalItem'] = self::money(self::cents($line['precioItem'])->plus(self::cents($line['valorIVA'])));
        unset($line);
        return $payload;
    }

    private static function adjustBase(array &$doc, \Brick\Math\BigRational $residual, bool $adjustSubtotal): void
    {
        if ($residual->abs()->isGreaterThan(2)) FiscalAmounts::error('currency_totals', 'El redondeo de las bases fiscales supera dos centavos.');
        $selected = null;
        foreach ($doc['detallesItems'] as $i => $line) {
            if (self::cents($line['precioItem'])->plus($residual)->isNegative()) continue;
            $selected = $i;
            if ($line['codigoImpuesto'] === 'E') break;
        }
        if ($selected === null) FiscalAmounts::error('currency_totals', 'No hay una línea que admita el redondeo fiscal.');
        $line = &$doc['detallesItems'][$selected];
        $line['precioAntesDescuento'] = $line['precioAntesDescuento'] ?? ExchangeRateMath::multiply($line['precioUnitario'], $line['cantidad'], 2);
        $line['precioItem'] = self::money(self::cents($line['precioItem'])->plus($residual));
        $field = $residual->isGreaterThan(0) ? 'recargoMonto' : 'descuentoMonto';
        $line[$field] = self::money(self::cents($line[$field])->plus($residual->abs()));
        $totals = &$doc['encabezado']['totales'];
        $field = D::of($line['tasaIVA'])->isGreaterThan(0) ? 'montoGravadoTotal' : 'montoExentoTotal';
        $totals[$field] = self::money(self::cents($totals[$field])->plus($residual));
        foreach ($totals['impuestosSubtotal'] as &$tax) if (self::group($tax['codigoTotalImp'], $tax['alicuotaImp']) === self::group($line['codigoImpuesto'], $line['tasaIVA'])) $tax['baseImponibleImp'] = self::money(self::cents($tax['baseImponibleImp'])->plus($residual));
        unset($tax);
        if ($adjustSubtotal) $totals['subtotal'] = self::money(self::cents($totals['subtotal'])->plus($residual));
        if (isset($totals['subtotalAntesDescuento'])) $totals['subtotalAntesDescuento'] = self::money(self::cents($totals['subtotalAntesDescuento'])->plus($residual));
    }
}
