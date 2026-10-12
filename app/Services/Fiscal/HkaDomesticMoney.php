<?php

namespace App\Services\Fiscal;

use App\Services\ExchangeRates\ExchangeRateMath;

/** Adapt only a copy of domestic invoice money; local bases, adjustments and receipts stay frozen. */
final class HkaDomesticMoney
{
    private static function cents($value): int { return (int) round((float) $value * 100); }
    private static function money(int $cents): string { return number_format($cents / 100, 2, '.', ''); }
    private static function group($code, $rate): string { return $code.':'.number_format((float) $rate, 2, '.', ''); }

    /** Match each HKA item to the final tax base, including global discounts already persisted. */
    public static function adapt(array $payload, array $snapshot): array
    {
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
            $weights = array_map(fn ($i) => self::cents($snapshot['items'][$i]['total_base_igv']), $indices);
            $sum = array_sum($weights);
            $base = self::cents($taxes[$key]['base']); $iva = self::cents($taxes[$key]['amount']);
            $cumulative = $priorBase = $priorIva = 0;
            foreach ($indices as $position => $i) {
                $cumulative += $sum ? $weights[$position] : 1;
                $share = ExchangeRateMath::rational($cumulative)->dividedBy($sum ?: count($indices));
                $currentBase = (int) (string) $share->multipliedBy($base)->toScale(0, \Brick\Math\RoundingMode::HALF_UP);
                $currentIva = (int) (string) $share->multipliedBy($iva)->toScale(0, \Brick\Math\RoundingMode::HALF_UP);
                $line = &$doc['detallesItems'][$i];
                $line['precioItem'] = self::money($currentBase - $priorBase);
                $line['valorIVA'] = self::money($currentIva - $priorIva);
                $gross = self::cents(ExchangeRateMath::multiply($snapshot['items'][$i]['unit_value'], $line['cantidad'], 2));
                $discount = $gross - ($currentBase - $priorBase);
                if ($discount < 0) FiscalAmounts::error('items', 'La base fiscal supera el precio original de la línea.');
                $line['descuentoMonto'] = self::money($discount);
                $line['recargoMonto'] = '0.00';
                if ($discount) $line['precioAntesDescuento'] = self::money($gross);
                $line['valorTotalItem'] = self::money($currentBase - $priorBase + $currentIva - $priorIva);
                $priorBase = $currentBase; $priorIva = $currentIva;
                unset($line);
            }
        }
        $base = array_sum(array_map(fn ($tax) => self::cents($tax['base']), $taxes));
        $untaxed = self::cents($snapshot['total_value']) - $base;
        // Pro9 charges do not increase its IVA base. Present that conserved amount as an E service.
        // This avoids taxing the charge or reporting it twice as both an item and a header surcharge.
        if ($untaxed > 0) {
            $doc['detallesItems'][] = ['numeroLinea'=>(string) (count($doc['detallesItems']) + 1),
                'indicadorBienoServicio'=>'2', 'descripcion'=>'Cargos no gravados de la venta', 'codigoPLU'=>'',
                'cantidad'=>'1', 'unidadMedida'=>'C62', 'precioUnitario'=>number_format($untaxed / 100, 4, '.', ''),
                'descuentoMonto'=>'0.00', 'recargoMonto'=>'0.00', 'precioItem'=>self::money($untaxed),
                'codigoImpuesto'=>'E', 'tasaIVA'=>'0.00', 'valorIVA'=>'0.00', 'valorTotalItem'=>self::money($untaxed)];
            $found = false;
            foreach ($totals['impuestosSubtotal'] as &$tax) if ($tax['codigoTotalImp'] === 'E') {
                $tax['baseImponibleImp'] = self::money(self::cents($tax['baseImponibleImp']) + $untaxed); $found = true;
            }
            unset($tax);
            if (!$found) $totals['impuestosSubtotal'][] = ['codigoTotalImp'=>'E','alicuotaImp'=>'0.00','baseImponibleImp'=>self::money($untaxed),'valorTotalImp'=>'0.00'];
        }
        $totals['nroItems'] = (string) count($doc['detallesItems']);
        $totals['montoExentoTotal'] = self::money(self::cents($snapshot['total_exonerated']) + max(0, $untaxed));
        $totals['totalDescuento'] = self::money(max(0, -$untaxed));
        $totals['totalRecargos'] = '0.00';
        $totals['montoTotalConIVA'] = self::money(self::cents($snapshot['total_value']) + self::cents($snapshot['total_igv']));
        if ($untaxed < 0) $totals['subtotalAntesDescuento'] = self::money($base);
        // HKA validates totalAPagar against this tax row, in addition to the IGTF totals.
        $igtfGroups = [];
        foreach ($snapshot['taxes'] as $tax) if ($tax['tax_kind'] === 'IGTF') {
            $key = self::group('IGTF', $tax['percentage']);
            $igtfGroups[$key] = ['codigoTotalImp'=>'IGTF', 'alicuotaImp'=>number_format((float) $tax['percentage'], 2, '.', ''),
                'baseImponibleImp'=>self::money(self::cents($igtfGroups[$key]['baseImponibleImp'] ?? 0) + self::cents($tax['base'])),
                'valorTotalImp'=>self::money(self::cents($igtfGroups[$key]['valorTotalImp'] ?? 0) + self::cents($tax['amount']))];
        }
        foreach ($igtfGroups as $tax) $totals['impuestosSubtotal'][] = $tax;
        if ($snapshot['currency_type_id'] === 'USD') return self::inVes($payload, $snapshot);
        return $payload;
    }

    private static function inVes(array $payload, array $snapshot): array
    {
        $rate = $snapshot['exchange_rate_sale']; $frozen = $snapshot['currency_totals'] ?? [];
        foreach (['taxed'=>'total_taxed', 'exempt'=>'total_exonerated', 'iva'=>'total_igv', 'total'=>'total'] as $ves => $local) {
            if (!isset($frozen[$ves]) || abs((float) $frozen[$ves] - FiscalAmounts::convert($snapshot[$local], 'USD', 'VES', $rate)) > 0.005) FiscalAmounts::error('currency_totals', 'Los totales VES de la factura con terceros o régimen especial no corresponden al snapshot documental.');
        }
        $doc = &$payload['documentoElectronico']; $header = &$doc['encabezado']; $usdTotals = $header['totales'];
        $otherFields = ['montoGravadoTotal','montoExentoTotal','subtotal','subtotalAntesDescuento','totalIVA','montoTotalConIVA','totalAPagar','totalDescuento','totalRecargos','impuestosSubtotal'];
        $header['totalesOtraMoneda'] = array_intersect_key($usdTotals, array_flip($otherFields));
        $header['totalesOtraMoneda']['moneda'] = 'USD'; $header['totalesOtraMoneda']['tipoCambio'] = (string) $rate;
        $header['identificacionDocumento']['moneda'] = 'VES';
        foreach (['montoGravadoTotal','montoExentoTotal','subtotal','subtotalAntesDescuento','totalIVA','montoTotalConIVA','totalAPagar','totalDescuento','totalRecargos','totalIGTF'] as $key) {
            if (isset($usdTotals[$key])) $header['totales'][$key] = ExchangeRateMath::multiply($usdTotals[$key], $rate, 2);
        }
        foreach ($header['totales']['impuestosSubtotal'] as &$tax) foreach (['baseImponibleImp','valorTotalImp'] as $key) $tax[$key] = ExchangeRateMath::multiply($tax[$key], $rate, 2);
        unset($tax);
        $groups = [];
        foreach ($doc['detallesItems'] as $index => $line) $groups[self::group($line['codigoImpuesto'], $line['tasaIVA'])][] = $index;
        foreach ($groups as $indices) foreach (['precioItem','valorIVA','descuentoMonto','recargoMonto'] as $key) {
            $cumulative = ExchangeRateMath::rational(0); $previous = 0;
            foreach ($indices as $index) {
                $cumulative = $cumulative->plus($doc['detallesItems'][$index][$key] ?? 0);
                $current = self::cents(ExchangeRateMath::finalAmount($cumulative->multipliedBy($rate)));
                $doc['detallesItems'][$index][$key] = self::money($current - $previous); $previous = $current;
            }
        }
        foreach ($doc['detallesItems'] as &$line) {
            $line['precioUnitario'] = ExchangeRateMath::multiply($line['precioUnitario'], $rate, 4);
            if (isset($line['precioAntesDescuento'])) $line['precioAntesDescuento'] = ExchangeRateMath::multiply($line['precioAntesDescuento'], $rate, 2);
        }
        unset($line);
        $totals = &$header['totales'];
        $taxed = $exempt = 0;
        foreach ($doc['detallesItems'] as $line) {
            if ((float) $line['tasaIVA'] > 0) $taxed += self::cents($line['precioItem']);
            else $exempt += self::cents($line['precioItem']);
        }
        $totals['montoGravadoTotal'] = self::money($taxed);
        $totals['montoExentoTotal'] = self::money($exempt);
        $baseResidual = self::cents($totals['subtotal']) + self::cents($totals['totalDescuento']) - $taxed - $exempt;
        if ($baseResidual) self::adjustBase($doc, $baseResidual, false);
        // Allocate the unavoidable currency cent to IVA; never alter local snapshots or receipts.
        $iva = 0;
        foreach ($totals['impuestosSubtotal'] as $tax) if ($tax['codigoTotalImp'] !== 'IGTF') $iva += self::cents($tax['valorTotalImp']);
        $totals['totalIVA'] = self::money($iva);
        $residual = self::cents($totals['totalAPagar']) - self::cents($totals['subtotal']) - $iva - self::cents($totals['totalIGTF']);
        if ($residual) {
            $taxIndex = null; $lineIndex = null;
            foreach ($totals['impuestosSubtotal'] as $i => $tax) if ($tax['codigoTotalImp'] !== 'IGTF' && (float) $tax['alicuotaImp'] > 0) $taxIndex = $i;
            if (abs($residual) > 2) FiscalAmounts::error('currency_totals', 'No se puede conciliar el redondeo fiscal VES de la factura.');
            if ($taxIndex === null) {
                self::adjustBase($doc, $residual, true);
            } else {
                $code = $totals['impuestosSubtotal'][$taxIndex]['codigoTotalImp']; $percentage = $totals['impuestosSubtotal'][$taxIndex]['alicuotaImp'];
                foreach ($doc['detallesItems'] as $i => $line) if (self::group($line['codigoImpuesto'], $line['tasaIVA']) === self::group($code, $percentage)) $lineIndex = $i;
                $totals['impuestosSubtotal'][$taxIndex]['valorTotalImp'] = self::money(self::cents($totals['impuestosSubtotal'][$taxIndex]['valorTotalImp']) + $residual);
                $doc['detallesItems'][$lineIndex]['valorIVA'] = self::money(self::cents($doc['detallesItems'][$lineIndex]['valorIVA']) + $residual);
                $totals['totalIVA'] = self::money(self::cents($totals['totalIVA']) + $residual);
            }
        }
        $totals['montoTotalConIVA'] = self::money(self::cents($totals['totalAPagar']) - self::cents($totals['totalIGTF']));
        foreach ($doc['detallesItems'] as &$line) $line['valorTotalItem'] = self::money(self::cents($line['precioItem']) + self::cents($line['valorIVA']));
        unset($line);
        return $payload;
    }

    private static function adjustBase(array &$doc, int $residual, bool $adjustSubtotal): void
    {
        if (abs($residual) > 2) FiscalAmounts::error('currency_totals', 'El redondeo de las bases fiscales supera dos centavos.');
        $selected = null;
        foreach ($doc['detallesItems'] as $i => $line) {
            if (self::cents($line['precioItem']) + $residual < 0) continue;
            $selected = $i;
            if ($line['codigoImpuesto'] === 'E') break;
        }
        if ($selected === null) FiscalAmounts::error('currency_totals', 'No hay una línea que admita el redondeo fiscal.');
        $line = &$doc['detallesItems'][$selected];
        $line['precioAntesDescuento'] = $line['precioAntesDescuento'] ?? ExchangeRateMath::multiply($line['precioUnitario'], $line['cantidad'], 2);
        $line['precioItem'] = self::money(self::cents($line['precioItem']) + $residual);
        $field = $residual > 0 ? 'recargoMonto' : 'descuentoMonto';
        $line[$field] = self::money(self::cents($line[$field]) + abs($residual));
        $totals = &$doc['encabezado']['totales'];
        $field = (float) $line['tasaIVA'] > 0 ? 'montoGravadoTotal' : 'montoExentoTotal';
        $totals[$field] = self::money(self::cents($totals[$field]) + $residual);
        foreach ($totals['impuestosSubtotal'] as &$tax) if (self::group($tax['codigoTotalImp'], $tax['alicuotaImp']) === self::group($line['codigoImpuesto'], $line['tasaIVA'])) $tax['baseImponibleImp'] = self::money(self::cents($tax['baseImponibleImp']) + $residual);
        unset($tax);
        if ($adjustSubtotal) $totals['subtotal'] = self::money(self::cents($totals['subtotal']) + $residual);
        if (isset($totals['subtotalAntesDescuento'])) $totals['subtotalAntesDescuento'] = self::money(self::cents($totals['subtotalAntesDescuento']) + $residual);
    }
}
