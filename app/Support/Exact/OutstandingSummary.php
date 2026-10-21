<?php

namespace App\Support\Exact;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Shared documentary balances; received cash is a separate measure. */
final class OutstandingSummary
{
    /** Build once from the producer's rational operands, before serializing its DTO. */
    public static function equivalentValues($total, $balance, string $currency, $rate): array
    {
        return ['total_ves_equivalent'=>Decimal::convert($total,$currency,'VES',$rate),
            'balance_ves_equivalent'=>Decimal::convert($balance,$currency,'VES',$rate)];
    }

    public static function equivalents(array $row): array
    {
        $currency = $row['currency_type_id'] ?? null;
        if (!in_array($currency, ['VES','USD'], true)) throw new \UnexpectedValueException('Moneda inválida en el saldo.');
        $values = self::equivalentValues(Decimal::readRequired($row,'total'),Decimal::readRequired($row,'total_to_pay'),$currency,$row['exchange_rate_sale'] ?? null);
        $snapshot = $row['calculation_snapshot'];
        foreach ($values as $field=>$value) $snapshot['values'][$field] = Decimal::fraction($value);
        return array_merge($row, Decimal::values($values), ['calculation_snapshot'=>$snapshot]);
    }

    public static function summarize(iterable $rows): array
    {
        $values = array_fill_keys(['VES_total','VES_balance','USD_total','USD_balance','consolidated_total_ves','consolidated_balance_ves'], Decimal::of(0));
        $usdVesTotal = $usdVesBalance = Decimal::of(0);
        $count = 0;
        foreach ($rows as $row) {
            $balance = Decimal::readRequired($row,'total_to_pay');
            if (!$balance->isPositive()) continue;
            $currency = $row['currency_type_id'];
            if (!in_array($currency, ['VES','USD'], true)) throw new \UnexpectedValueException('Moneda inválida en el saldo.');
            $total = Decimal::readRequired($row,'total');
            $values[$currency.'_total'] = $values[$currency.'_total']->plus($total)->simplified();
            $values[$currency.'_balance'] = $values[$currency.'_balance']->plus($balance)->simplified();
            // VES is already its own equivalent. Do not parse/add the same fractions twice.
            if ($currency === 'USD') {
                $row = isset($row['total_ves_equivalent'],$row['balance_ves_equivalent']) ? $row : self::equivalents($row);
                $usdVesTotal = $usdVesTotal->plus(Decimal::readRequired($row,'total_ves_equivalent'))->simplified();
                $usdVesBalance = $usdVesBalance->plus(Decimal::readRequired($row,'balance_ves_equivalent'))->simplified();
            }
            $count++;
        }
        $values['consolidated_total_ves'] = $values['VES_total']->plus($usdVesTotal)->simplified();
        $values['consolidated_balance_ves'] = $values['VES_balance']->plus($usdVesBalance)->simplified();
        return array_merge(Decimal::values($values), ['count'=>$count, 'calculation_snapshot'=>Decimal::snapshot($values)]);
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
