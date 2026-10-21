<?php

namespace App\Support\Exact;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Allocate frozen header components without changing the original line or its taxes. */
final class HeaderAllocation
{
    public const FIELDS = ['line_total', 'header_discount', 'header_charge', 'header_iva_adjustment',
        'header_other_taxes', 'header_prepayment', 'report_total'];
    private const HEADER_FIELDS = ['total', 'total_value', 'total_igv', 'total_other_taxes',
        'total_discount', 'total_charge', 'total_prepayment'];

    public static function adjustments($value): array
    {
        if ($value === null) return [];
        if (is_string($value)) $value = JsonDecimals::decode($value);
        return array_map(static fn($row)=>(array)$row, (array)$value);
    }

    public static function stats(): array
    {
        return array_fill_keys(['total', 'value', 'iva', 'other', 'discount', 'charge', 'taxed_base', 'export_base',
            'exempt_base', 'distributed', 'distributed_taxed', 'distributed_exempt', 'count'], Decimal::of(0));
    }

    public static function add(array &$stats, $line): void
    {
        foreach (['total'=>'total', 'value'=>'total_value', 'iva'=>'total_igv', 'other'=>'total_other_taxes',
            'discount'=>'total_discount', 'charge'=>'total_charge'] as $target=>$field) {
            $stats[$target] = $stats[$target]->plus(Decimal::readRequired($line, $field))->simplified();
        }
        $type = is_array($line) ? $line['affectation_igv_type_id'] : $line->affectation_igv_type_id;
        if (in_array($type, ['10', '20', 'EXP'], true)) {
            $field = $type === 'EXP' ? 'export_base' : ($type === '10' ? 'taxed_base' : 'exempt_base');
            $stats[$field] = $stats[$field]->plus(Decimal::readRequired($line, 'total_base_igv'))->simplified();
        }
        $discounts = is_array($line) ? ($line['discounts'] ?? []) : $line->discounts;
        foreach (self::adjustments($discounts) as $discount) {
            if (empty($discount['from_global_distribution'])) continue;
            $amount = self::amount($discount);
            $stats['distributed'] = $stats['distributed']->plus($amount)->simplified();
            if (($discount['discount_type_id'] ?? '') === '00') {
                $field = $type === '20' ? 'distributed_exempt' : 'distributed_taxed';
                $stats[$field] = $stats[$field]->plus($amount)->simplified();
            }
        }
        $stats['count'] = $stats['count']->plus(1);
    }

    /** The same eligibility rules are used by producers and report allocations. */
    public static function discounts($discounts, array $stats): array
    {
        $taxed = $exempt = $global = Decimal::of(0);
        foreach (self::adjustments($discounts) as $discount) {
            $amount = self::amount($discount);
            $global = $global->plus($amount);
            if (in_array($discount['discount_type_id'] ?? '', ['02', '04'], true)) $taxed = $taxed->plus($amount);
            if (($discount['discount_type_id'] ?? '') === '05') $exempt = $exempt->plus($amount);
        }
        return ['global'=>Decimal::max(0, $global->minus($stats['distributed'])),
            'taxed'=>Decimal::max(0, $taxed->minus($stats['distributed_taxed'])),
            'exempt'=>Decimal::max(0, $exempt->minus($stats['distributed_exempt']))];
    }

    public static function includesAdvance($discounts): bool
    {
        foreach (self::adjustments($discounts) as $row) {
            if (in_array($row['discount_type_id'] ?? '', ['04', '05'], true)) return true;
        }
        return false;
    }

    public static function key($header): string
    {
        $values = [];
        foreach (self::HEADER_FIELDS as $field) $values[$field] = Decimal::fraction(Decimal::readRequired($header, $field));
        $discounts = self::adjustments(is_array($header) ? ($header['discounts'] ?? []) : $header->discounts);
        $values['discounts'] = array_map(static fn($row)=>['type'=>(string)($row['discount_type_id'] ?? ''),
            'amount'=>Decimal::fraction(self::amount($row))], $discounts);
        return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
    }

    public static function context($header, array $stats): array
    {
        if ($stats['count']->isZero()) throw new \UnexpectedValueException('El documento no contiene partidas para repartir.');
        $discounts = is_array($header) ? ($header['discounts'] ?? []) : $header->discounts;
        $parts = self::discounts($discounts, $stats);
        // Export summaries apply their global discount to export value, without IVA pools.
        if ($stats['export_base']->isPositive() && $stats['taxed_base']->isZero() && $stats['exempt_base']->isZero()) {
            $parts['taxed'] = $parts['exempt'] = Decimal::of(0);
        }
        $discount = Decimal::readRequired($header, 'total_discount')->minus($stats['discount']);
        $charge = Decimal::readRequired($header, 'total_charge')->minus($stats['charge']);
        if ($discount->isNegative() || $charge->isNegative() || !$discount->isEqualTo($parts['global'])) {
            throw new \UnexpectedValueException('Los descuentos o cargos congelados no concuerdan con las partidas.');
        }
        $neutral = $discount->minus($parts['taxed'])->minus($parts['exempt']);
        if ($neutral->isNegative()) throw new \UnexpectedValueException('El descuento no concuerda con sus bases afectadas.');
        if ($parts['taxed']->isGreaterThan($stats['taxed_base']) || $parts['exempt']->isGreaterThan($stats['exempt_base'])) {
            throw new \UnexpectedValueException('El descuento supera su base afectada.');
        }
        $iva = Decimal::readRequired($header, 'total_igv')->minus($stats['iva']);
        if (!$iva->isZero() && $stats['iva']->isZero()) throw new \UnexpectedValueException('El ajuste de IVA no tiene partidas elegibles.');
        $other = Decimal::readRequired($header, 'total_other_taxes')->minus($stats['other']);
        $advance = self::includesAdvance($discounts) ? Decimal::of(0) : Decimal::readRequired($header, 'total_prepayment');
        $beforeAdvance = $stats['total']->minus($discount)->plus($charge)->plus($iva)->plus($other);
        if ($advance->isNegative() || $beforeAdvance->isLessThan($advance)
            || !$beforeAdvance->minus($advance)->isEqualTo(Decimal::readRequired($header, 'total'))) {
            throw new \UnexpectedValueException('Las partidas y ajustes no concuerdan con el total congelado del documento.');
        }
        return compact('stats', 'parts', 'neutral', 'charge', 'iva', 'other', 'advance', 'beforeAdvance');
    }

    public static function line($line, array $context): array
    {
        extract($context, EXTR_SKIP);
        $type = is_array($line) ? $line['affectation_igv_type_id'] : $line->affectation_igv_type_id;
        $value = Decimal::readRequired($line, 'total_value');
        $base = Decimal::readRequired($line, 'total_base_igv');
        $weight = $stats['value']->isZero() ? Decimal::of(1) : $value;
        $weightTotal = $stats['value']->isZero() ? $stats['count'] : $stats['value'];
        $deduction = self::share($neutral, $weight, $weightTotal);
        if ($type === '10') $deduction = $deduction->plus(self::share($parts['taxed'], $base, $stats['taxed_base']));
        if ($type === '20') $deduction = $deduction->plus(self::share($parts['exempt'], $base, $stats['exempt_base']));
        $values = ['line_total'=>Decimal::readRequired($line, 'total'), 'header_discount'=>$deduction,
            'header_charge'=>self::share($charge, $weight, $weightTotal),
            'header_iva_adjustment'=>self::share($iva, Decimal::readRequired($line, 'total_igv'), $stats['iva']),
            'header_other_taxes'=>self::share($other, $weight, $weightTotal)];
        $remaining = $values['line_total']->minus($deduction)->plus($values['header_charge'])
            ->plus($values['header_iva_adjustment'])->plus($values['header_other_taxes']);
        if ($remaining->isNegative()) throw new \UnexpectedValueException('Un ajuste produciría una partida negativa.');
        $values['header_prepayment'] = self::share($advance, $remaining, $beforeAdvance);
        $values['report_total'] = $remaining->minus($values['header_prepayment'])->simplified();
        return $values;
    }

    /** Called only by authorized producers, inside their commercial transaction. */
    public static function freeze($owner, iterable $items): void
    {
        if ($owner->getConnection()->transactionLevel() === 0) throw new \LogicException('El reparto requiere la transacción comercial.');
        $stats = self::stats();
        foreach ($items as $line) self::add($stats, $line);
        if ($stats['count']->isZero()) return;
        $context = self::context($owner, $stats);
        $key = self::key($owner);
        $snapshot = $owner->calculation_snapshot ?? Decimal::snapshot([]);
        $snapshot['inputs']['header_allocation_key'] = $key;
        $owner->calculation_snapshot = $snapshot;
        $relation = $owner->items();
        $grammar = $owner->getConnection()->getQueryGrammar();
        $table = $grammar->wrapTable($relation->getRelated()->getTable());
        $foreign = $grammar->wrap($relation->getForeignKeyName());
        $flush = static function (array $rows) use ($owner, $table, $foreign): void {
            $cases = $ids = $bindings = [];
            foreach ($rows as [$id, $json]) { $cases[] = 'WHEN ? THEN ?'; $bindings[] = $id; $bindings[] = $json; $ids[] = $id; }
            $bindings[] = $owner->getKey();
            $owner->getConnection()->update('UPDATE '.$table.' SET calculation_snapshot = CASE id '.implode(' ', $cases)
                .' ELSE calculation_snapshot END WHERE '.$foreign.' = ? AND id IN ('.implode(',', array_fill(0, count($ids), '?')).')', array_merge($bindings, $ids));
        };
        $batch = [];
        foreach ($items as $line) {
            $snapshot = $line->calculation_snapshot ?? Decimal::snapshot([]);
            foreach (self::line($line, $context) as $field=>$value) $snapshot['values'][$field] = Decimal::fraction($value);
            $snapshot['inputs']['header_allocation_key'] = $key;
            $line->calculation_snapshot = $snapshot;
            $batch[] = [$line->getKey(), json_encode($snapshot, JSON_THROW_ON_ERROR)];
            if (count($batch) === 500) { $flush($batch); $batch = []; }
        }
        if ($batch) $flush($batch);
    }

    private static function amount(array $row): \Brick\Math\BigRational
    {
        if (!array_key_exists('amount_exact',$row) && !array_key_exists('amount',$row)) {
            throw new \UnexpectedValueException('Falta el importe conservado del ajuste.');
        }
        $amount = Decimal::inputRational($row['amount_exact'] ?? $row['amount'] ?? null, 'adjustments.amount');
        if ($amount->isNegative()) throw new \UnexpectedValueException('El ajuste no puede ser negativo.');
        return $amount;
    }

    private static function share($amount, $weight, $total): \Brick\Math\BigRational
    {
        if ($amount->isZero() || $weight->isZero()) return Decimal::of(0);
        if ($total->isZero()) throw new \UnexpectedValueException('El ajuste requiere una base positiva.');
        return $amount->multipliedBy($weight)->dividedBy($total)->simplified();
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
