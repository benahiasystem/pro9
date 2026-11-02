<?php

namespace Modules\WhatsAppBot\Services\Facturalo;

use App\Services\ExchangeRates\ExchangeRateMath;
use App\Services\Payments\Classification;
use App\Support\Exact\Decimal as D;
use App\Support\Exact\LineCalculation;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Calculate the same confirmed VES amounts for the bot summary and invoice. */
final class DraftPricing
{
    public static function calculate(array $items, $exchangeRate, $discount = '0'): array
    {
        $rate = ExchangeRateMath::rate($exchangeRate, 'exchange_rate_sale');
        $base = D::of(0);
        $tax = D::of(0);
        $lines = [];
        foreach ($items as $item) {
            $currency = $item['price_currency'] ?? null;
            if (!in_array($currency, ['VES', 'USD'], true)) Classification::error('items', 'La moneda del precio no es válida.');
            $line = LineCalculation::calculate([
                'quantity'=>D::input($item['quantity'] ?? null, 'items.quantity'),
                'unit_price'=>'0', 'currency_type_id'=>'VES', 'exchange_rate_sale'=>$rate,
                'price_input'=>['amount'=>D::input($item['price'] ?? null, 'items.price'),
                    'currency_type_id'=>$currency, 'includes_iva'=>(bool) ($item['includes_iva'] ?? false)],
                'discounts'=>[], 'charges'=>[],
            ], '16');
            $base = $base->plus(D::read($line, 'total_value'));
            $tax = $tax->plus(D::read($line, 'total_igv'));
            $lines[] = array_replace($item, $line);
        }
        if (!$lines) Classification::error('items', 'Agregue al menos un producto.');
        $deduction = D::of(D::input($discount, 'discount_amount'));
        if ($deduction->isNegative() || (!$deduction->isZero() && $deduction->isGreaterThanOrEqualTo($base))) {
            Classification::error('discount_amount', 'El descuento debe ser menor que la base imponible.');
        }
        $discountBase = $base;
        $base = $base->minus($deduction);
        $tax = $tax->minus($deduction->multipliedBy('0.16'));
        $total = $base->plus($tax);
        return ['exchange_rate_sale'=>$rate, 'items'=>$lines, 'discount_base'=>D::represent($discountBase),
            'discount_amount'=>D::represent($deduction), 'total_value'=>D::represent($base),
            'total_igv'=>D::represent($tax), 'total'=>D::represent($total), 'total_payable'=>D::represent($total, 2, true)];
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
