<?php

namespace App\Services\Payments;

use App\Support\Exact\Decimal;

// ######## INICIO MONEDA DEL MEDIO DE COBRO ########
/** Running financial balances in each movement's actual currency. */
final class MovementBalances
{
    private array $balances = [];

    /** Apply a captured movement without rounding its accumulated balance. */
    public function applyExact(array $movement): \Brick\Math\BigRational
    {
        $currency = $movement['currency_type_id'] ?? null;
        if (!in_array($currency, ['VES', 'USD'], true)) {
            Classification::error('currency_type_id', 'El movimiento debe identificar su moneda recibida VES/USD.');
        }
        $direction = $movement['direction'] ?? null;
        if (!in_array($direction, ['income', 'egress'], true)) {
            Classification::error('direction', 'El movimiento debe identificar su dirección.');
        }
        $fraction = $movement['calculation_snapshot']['values']['amount'] ?? null;
        $amount = Decimal::of($fraction ?? $movement['amount']);
        if ($amount->isNegative()) Classification::error('payment', 'El movimiento financiero no puede ser negativo.');
        $previous = $this->balances[$currency] ?? Decimal::of(0);
        $this->balances[$currency] = $direction === 'income' ? $previous->plus($amount) : $previous->minus($amount);
        return $this->balances[$currency];
    }

    public function apply(array $movement): string
    {
        return Decimal::represent($this->applyExact($movement));
    }
}
// ######## FIN MONEDA DEL MEDIO DE COBRO ########
