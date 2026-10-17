<?php

namespace App\Support\Exact;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Pure, server-owned inclusive-price line calculation; no database or HKA side effects. */
final class LineCalculation
{
    /** Only for records loaded internally; HTTP result snapshots are removed at ingress. */
    public static function fromRecord(array $line, $percentage): array
    {
        $snapshot=$line['calculation_snapshot'] ?? [];
        if (is_string($snapshot)) $snapshot=json_decode($snapshot,true,512,JSON_THROW_ON_ERROR);
        $preserved=[];
        foreach (['quantity'=>'quantity_input','unit_price'=>'price_input'] as $field=>$source) {
            if (isset($snapshot['values'][$field],$line[$field])
                && Decimal::represent($snapshot['values'][$field])===Decimal::represent($line[$field])) {
                $preserved[$field]=Decimal::of($snapshot['values'][$field]);
                if (!array_key_exists($source,$line) && isset($snapshot['inputs'][$source])) $line[$source]=$snapshot['inputs'][$source];
            }
        }
        return self::calculate($line,$percentage,$preserved);
    }

    public static function calculate(array $line, $percentage, array $preserved = []): array
    {
        foreach (['quantity_input'=>['unit_price','amount'], 'price_input'=>['amount','currency_type_id']] as $field=>$required) {
            if (($line[$field] ?? null) === null) continue;
            if (!is_array($line[$field]) || array_diff($required,array_keys($line[$field]))) {
                \App\Services\Fiscal\FiscalAmounts::error('items.'.$field, 'Entrada original incompleta.');
            }
        }
        if (isset($line['price_input']['includes_iva']) && !is_bool($line['price_input']['includes_iva'])) {
            \App\Services\Fiscal\FiscalAmounts::error('items.price_input.includes_iva','Indique si el precio incluye IVA.');
        }
        $quantity = isset($preserved['quantity']) ? Decimal::of($preserved['quantity']) : Decimal::of(Decimal::input($line['quantity'], 'items.quantity'));
        if (!empty($line['quantity_input'])) {
            $source = $line['quantity_input'];
            $sourcePrice = Decimal::of(Decimal::input($source['unit_price'], 'items.quantity_input.unit_price'));
            if ($sourcePrice->isLessThanOrEqualTo(0)) \App\Services\Fiscal\FiscalAmounts::error('items.quantity', 'El precio debe ser positivo.');
            $quantity = Decimal::of(Decimal::input($source['amount'], 'items.quantity_input.amount'))->dividedBy($sourcePrice);
        }
        $price = isset($preserved['unit_price']) ? Decimal::of($preserved['unit_price']) : Decimal::of(Decimal::input($line['unit_price'], 'items.unit_price'));
        if (!empty($line['price_input'])) {
            $source = $line['price_input'];
            $price = Decimal::of(Decimal::input($source['amount'], 'items.price_input.amount'));
            if (($source['includes_iva'] ?? true) === false) $price = $price->multipliedBy(Decimal::of(1)->plus(Decimal::of($percentage)->dividedBy(100)));
            $price = Decimal::convert($price, $source['currency_type_id'], $line['currency_type_id'], $line['exchange_rate_sale'] ?? null);
        }
        if ($quantity->isLessThanOrEqualTo(0) || $price->isNegative()) \App\Services\Fiscal\FiscalAmounts::error('items', 'Cantidad o precio inválido.');
        $rate = Decimal::of($percentage);
        $unit = $price->dividedBy(Decimal::of(1)->plus($rate->dividedBy(100)));
        $gross = $unit->multipliedBy($quantity);
        $discountBase = $discountOther = $charges = Decimal::of(0);
        foreach ($line['discounts'] ?? [] as $i => $discount) {
            $discount = (array) $discount;
            $onBase = ($discount['discount_type_id'] ?? '') === '00';
            $base = $onBase ? $gross : $price->multipliedBy($quantity);
            $factor = isset($discount['percentage']) ? Decimal::of(Decimal::input($discount['percentage'], 'items.discounts.percentage'))->dividedBy(100) : Decimal::inputRational($discount['factor'] ?? 0,'items.discounts.factor');
            $amount = !empty($discount['is_amount']) || !empty($discount['from_global_distribution'])
                ? Decimal::inputRational($discount['amount_exact'] ?? $discount['amount_without_rounded'] ?? $discount['amount'] ?? 0,'items.discounts.amount')
                : $base->multipliedBy($factor);
            if ($amount->isNegative()) \App\Services\Fiscal\FiscalAmounts::error('items.discounts', 'Descuento inválido.');
            $discount['base'] = Decimal::represent($base);
            $discount['amount'] = Decimal::represent($amount);
            $discount['amount_exact'] = Decimal::fraction($amount);
            $line['discounts'][$i] = $discount;
            if ($onBase) $discountBase = $discountBase->plus($amount); else $discountOther = $discountOther->plus($amount);
        }
        foreach ($line['charges'] ?? [] as $i => $charge) {
            $charge = (array) $charge;
            $factor=isset($charge['percentage']) ? Decimal::inputRational($charge['percentage'],'items.charges.percentage')->dividedBy(100) : Decimal::inputRational($charge['factor'] ?? 0,'items.charges.factor');
            $amount = $gross->multipliedBy($factor);
            if ($amount->isNegative()) \App\Services\Fiscal\FiscalAmounts::error('items.charges', 'Cargo inválido.');
            $charge['base'] = Decimal::represent($gross);
            $charge['amount'] = Decimal::represent($amount);
            $charge['amount_exact'] = Decimal::fraction($amount);
            $line['charges'][$i] = $charge;
            $charges = $charges->plus($amount);
        }
        $base = $gross->minus($discountBase);
        $value = $base->minus($discountOther)->plus($charges);
        if ($base->isNegative() || $value->isNegative()) \App\Services\Fiscal\FiscalAmounts::error('items', 'El descuento supera el valor de la línea.');
        $iva = $base->multipliedBy($rate)->dividedBy(100);
        $values = ['quantity' => $quantity, 'unit_price' => $price, 'unit_value' => $unit,
            'total_base_igv' => $base, 'percentage_igv' => $rate,
            'total_discount' => $discountBase->plus($discountOther), 'total_charge' => $charges,
            'total_value' => $value, 'total_igv' => $iva, 'total_taxes' => $iva,
            'total_other_taxes' => Decimal::of(0), 'total_base_other_taxes' => Decimal::of(0),
            'percentage_other_taxes' => Decimal::of(0), 'total' => $value->plus($iva)];
        return array_replace($line, Decimal::values($values), ['calculation_snapshot' => Decimal::snapshot($values,
            ['quantity' => (string)$line['quantity'], 'unit_price' => (string)$line['unit_price'],
                'quantity_input'=>$line['quantity_input'] ?? null, 'price_input'=>$line['price_input'] ?? null])]);
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
