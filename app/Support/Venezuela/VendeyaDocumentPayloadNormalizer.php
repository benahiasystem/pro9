<?php

namespace App\Support\Venezuela;

use App\Support\Exact\Decimal;
use Illuminate\Validation\ValidationException;

final class VendeyaDocumentPayloadNormalizer
{
    public static function normalize(array $payload): array
    {
        if (strtoupper((string) ($payload['source_module'] ?? '')) !== 'VENDEYA') {
            return $payload;
        }

        // ######## INICIO CONTRATO INSTALACIÓN NUEVA ########
        if (!in_array($payload['codigo_tipo_moneda'] ?? null, [Localization::nationalCurrencyId(), Localization::secondaryCurrencyId()], true)) {
            throw ValidationException::withMessages([
                'codigo_tipo_moneda' => 'Seleccione una moneda vigente: VES o USD.',
            ]);
        }
        // ######## FIN CONTRATO INSTALACIÓN NUEVA ########

        // ########## INICIO CAMBIO AFECTACIÓN IVA
        if (($payload['codigo_tipo_documento'] ?? null) === '01') {
            return self::normalizeInvoice($payload);
        }

        $totals = [
            'taxed' => 0.0,
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
            $affectationId = self::affectation($item, $index);

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

                $totals['exonerated'] += $lineTotal;
                $totals['value'] += $lineTotal;
            }

            $item['total_item'] = $lineTotal;
            $totals['sale'] += $lineTotal;
            $payload['items'][$index] = $item;
        }

        $payload['totales'] = array_merge($payload['totales'] ?? [], [
            'total_operaciones_gravadas' => $totals['taxed'],
            'total_operaciones_exoneradas' => $totals['exonerated'],
            'total_igv' => $totals['tax'],
            'total_impuestos' => $totals['tax'],
            'total_valor' => $totals['value'],
            'total_venta' => $totals['sale'],
        ]);
        // ######### FIN CAMBIO AFECTACIÓN IVA

        return $payload;
    }

    // ########## INICIO CAMBIO AFECTACIÓN IVA
    private static function normalizeInvoice(array $payload): array
    {
        $zero = Decimal::of(0);
        $taxed = $exempt = $tax = $sale = $zero;
        $multiplier = Localization::exactTaxMultiplier();
        $percentage = Localization::taxPercentage();

        foreach ($payload['items'] ?? [] as $index => $item) {
            $field = "items.{$index}";
            $taxable = self::affectation($item, $index) === '10';
            $quantity = Decimal::of(Decimal::input($item['cantidad'] ?? 0, "$field.cantidad"));
            $lineTotal = array_key_exists('total_item', $item)
                ? Decimal::of(Decimal::input($item['total_item'] ?? 0, "$field.total_item"))
                : $quantity->multipliedBy(Decimal::input($item['precio_unitario'] ?? 0, "$field.precio_unitario"));
            $base = $taxable ? $lineTotal->dividedBy($multiplier) : $lineTotal;
            $lineTax = $lineTotal->minus($base);
            $unitValue = $quantity->isPositive() ? $base->dividedBy($quantity) : $zero;

            $payload['items'][$index] = array_replace($item, Decimal::values([
                'valor_unitario' => $unitValue,
                'total_base_igv' => $base,
                'total_igv' => $lineTax,
                'total_impuestos' => $lineTax,
                'total_valor_item' => $base,
                'total_item' => $lineTotal,
            ]), ['porcentaje_igv' => $taxable ? $percentage : 0]);

            if ($taxable) {
                $taxed = $taxed->plus($base)->simplified();
            } else {
                $exempt = $exempt->plus($base)->simplified();
            }
            $tax = $tax->plus($lineTax)->simplified();
            $sale = $sale->plus($lineTotal)->simplified();
        }

        $payload['totales'] = array_replace($payload['totales'] ?? [], Decimal::values([
            'total_operaciones_gravadas' => $taxed,
            'total_operaciones_exoneradas' => $exempt,
            'total_igv' => $tax,
            'total_impuestos' => $tax,
            'total_valor' => $taxed->plus($exempt),
            'total_venta' => $sale,
        ]));

        return $payload;
    }

    private static function affectation(array $item, int|string $index): string
    {
        $code = (string) ($item['codigo_tipo_afectacion_igv'] ?? '10');
        if (!in_array($code, Localization::selectableAffectationIds(), true)) {
            throw ValidationException::withMessages([
                "items.{$index}.codigo_tipo_afectacion_igv" => 'Seleccione Gravado o Exento.',
            ]);
        }

        return $code;
    }
    // ######### FIN CAMBIO AFECTACIÓN IVA
}
