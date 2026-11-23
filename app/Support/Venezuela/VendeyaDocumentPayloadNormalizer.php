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

        return self::normalizeInvoice($payload);
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
