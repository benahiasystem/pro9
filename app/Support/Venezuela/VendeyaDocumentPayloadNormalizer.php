<?php

namespace App\Support\Venezuela;

final class VendeyaDocumentPayloadNormalizer
{
    public static function normalize(array $payload): array
    {
        if (strtoupper((string) ($payload['source_module'] ?? '')) !== 'VENDEYA') {
            return $payload;
        }

        // ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########
        $legacyCurrencyIds = ['P' . 'EN', 'V' . 'ED'];
        if (in_array($payload['codigo_tipo_moneda'] ?? null, $legacyCurrencyIds, true)) {
            $payload['codigo_tipo_moneda'] = Localization::nationalCurrencyId();
        }
        // ######## FIN MIGRACIÓN MONEDA VENEZUELA ########

        // ########## INICIO CAMBIO AFECTACIÓN IVA
        $totals = [
            'taxed' => 0.0,
            'unaffected' => 0.0,
            'exonerated' => 0.0,
            'tax' => 0.0,
            'value' => 0.0,
            'sale' => 0.0,
        ];

        foreach ($payload['items'] ?? [] as $index => $item) {
            $quantity = (float) ($item['cantidad'] ?? 0);
            $unitPrice = (float) ($item['precio_unitario'] ?? 0);
            $lineTotal = array_key_exists('total_item', $item)
                ? (float) $item['total_item']
                : $quantity * $unitPrice;
            $affectationId = (string) ($item['codigo_tipo_afectacion_igv'] ?? '10');

            if ($affectationId === '10') {
                $base = $lineTotal / Localization::taxMultiplier();
                $tax = $lineTotal - $base;

                $item['valor_unitario'] = $quantity > 0 ? $base / $quantity : 0;
                $item['total_base_igv'] = $base;
                $item['porcentaje_igv'] = Localization::taxPercentage();
                $item['total_igv'] = $tax;
                $item['total_impuestos'] = $tax;
                $item['total_valor_item'] = $base;

                $totals['taxed'] += $base;
                $totals['tax'] += $tax;
                $totals['value'] += $base;
            } else {
                $item['valor_unitario'] = $quantity > 0 ? $lineTotal / $quantity : 0;
                $item['total_base_igv'] = $lineTotal;
                $item['porcentaje_igv'] = 0;
                $item['total_igv'] = 0;
                $item['total_impuestos'] = 0;
                $item['total_valor_item'] = $lineTotal;

                if ($affectationId === '20') {
                    $totals['exonerated'] += $lineTotal;
                } else {
                    $totals['unaffected'] += $lineTotal;
                }
                $totals['value'] += $lineTotal;
            }

            $item['total_item'] = $lineTotal;
            $totals['sale'] += $lineTotal;
            $payload['items'][$index] = $item;
        }

        $payload['totales'] = array_merge($payload['totales'] ?? [], [
            'total_operaciones_gravadas' => $totals['taxed'],
            'total_operaciones_inafectas' => $totals['unaffected'],
            'total_operaciones_exoneradas' => $totals['exonerated'],
            'total_igv' => $totals['tax'],
            'total_impuestos' => $totals['tax'],
            'total_valor' => $totals['value'],
            'total_venta' => $totals['sale'],
        ]);
        // ######### FIN CAMBIO AFECTACIÓN IVA

        return $payload;
    }
}
