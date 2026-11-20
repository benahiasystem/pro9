<?php

namespace Modules\WhatsAppBot\Services\Facturalo;

use App\Services\ExchangeRates\ExchangeRateMath;
use App\Services\Payments\Classification;
use App\Support\Exact\Decimal as D;
use App\Support\Exact\LineCalculation;
use App\Support\Venezuela\Localization;

// ######## INICIO CALCULOS EXACTOS SAB ########
/** Calculate the same confirmed VES amounts for the bot summary and invoice. */
final class DraftPricing
{
    public static function calculate(array $items, $exchangeRate, $discount = '0'): array
    {
        $rate = ExchangeRateMath::rate($exchangeRate, 'exchange_rate_sale');
        // ########## INICIO CAMBIO AFECTACIÓN IVA
        $taxPercentage = Localization::exactTaxPercentage();
        $taxed = D::of(0);
        $exempt = D::of(0);
        $tax = D::of(0);
        $lines = [];
        foreach ($items as $item) {
            $currency = $item['price_currency'] ?? null;
            if (!in_array($currency, ['VES', 'USD'], true)) Classification::error('items', 'La moneda del precio no es válida.');
            // Las confirmaciones guardadas antes de incluir la afectación se calcularon como gravadas.
            $affectation = $item['affectation_igv_type_id'] ?? '10';
            if (!in_array($affectation, Localization::SELECTABLE_AFFECTATION_IDS, true)) {
                Classification::error('items', 'Seleccione una afectación de IVA vigente para la venta.');
            }
            $price = D::input($item['price'] ?? null, 'items.price');
            $amount = $item['price_exact'] ?? $price;
            if (isset($item['price_exact'])
                && D::represent(LineCalculation::priceInput($amount, 'items.price')) !== D::canonical($price)) {
                Classification::error('items.price', 'El precio exacto no coincide con el borrador confirmado.');
            }
            $line = LineCalculation::calculate([
                'quantity'=>D::input($item['quantity'] ?? null, 'items.quantity'),
                'unit_price'=>'0', 'currency_type_id'=>'VES', 'exchange_rate_sale'=>$rate,
                'affectation_igv_type_id'=>$affectation,
                'price_input'=>['amount'=>$amount,
                    'currency_type_id'=>$currency, 'includes_iva'=>(bool) ($item['includes_iva'] ?? false)],
                'discounts'=>[], 'charges'=>[],
            ], $affectation === '10' ? $taxPercentage : '0');
            $lineValue = D::read($line, 'total_value');
            if ($affectation === '10') {
                $taxed = $taxed->plus($lineValue);
            } else {
                $exempt = $exempt->plus($lineValue);
            }
            $tax = $tax->plus(D::read($line, 'total_igv'));
            $lines[] = array_replace($item, $line);
        }
        if (!$lines) Classification::error('items', 'Agregue al menos un producto.');
        $deduction = D::of(D::input($discount, 'discount_amount'));
        if ($deduction->isNegative() || (!$deduction->isZero() && $deduction->isGreaterThanOrEqualTo($taxed))) {
            Classification::error('discount_amount', 'El descuento debe ser menor que la base imponible.');
        }
        $discountBase = $taxed;
        $taxed = $taxed->minus($deduction);
        $base = $taxed->plus($exempt);
        $tax = $tax->minus($deduction->multipliedBy($taxPercentage)->dividedBy(100));
        $total = $base->plus($tax);
        $amounts = [
            'discount_base' => $discountBase,
            'subtotal_before_discount' => $discountBase->plus($exempt),
            'total_taxed' => $taxed,
            'total_exonerated' => $exempt,
            'discount_amount' => $deduction,
            'total_value' => $base,
            'total_igv' => $tax,
            'total' => $total,
        ];

        return [
            'exchange_rate_sale' => $rate,
            'items' => $lines,
            ...D::values($amounts),
            'total_payable' => D::represent($total, 2, true),
            'calculation_snapshot' => D::snapshot($amounts),
        ];
        // ######### FIN CAMBIO AFECTACIÓN IVA
    }
}
// ######## FIN CALCULOS EXACTOS SAB ########
