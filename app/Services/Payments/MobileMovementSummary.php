<?php

namespace App\Services\Payments;

use App\Support\Exact\Decimal;

// ######## INICIO MONEDA DEL MEDIO DE COBRO ########
/** Accumulate captured movement fractions without mixing received currencies. */
final class MobileMovementSummary
{
    private array $currencies = [];
    private int $count = 0;
    private int $unclassified = 0;

    public function add(?array $movement): void
    {
        $this->count++;
        $currency = $movement['currency_type_id'] ?? null;
        $direction = $movement['direction'] ?? null;
        if (!in_array($currency, ['VES','USD'], true) || !in_array($direction, ['income','egress'], true)) {
            $this->unclassified++;
            return;
        }
        if (!isset($this->currencies[$currency])) $this->currencies[$currency] = ['input'=>Decimal::of(0),'output'=>Decimal::of(0),'count'=>0];
        $field = $direction === 'income' ? 'input' : 'output';
        $amount = Decimal::of($movement['calculation_snapshot']['values']['amount']);
        $this->currencies[$currency][$field] = $this->currencies[$currency][$field]->plus($amount);
        $this->currencies[$currency]['count']++;
    }

    public function result(): array
    {
        $groups=[];
        foreach ($this->currencies as $currency=>$totals) {
            $groups[$currency]=['currency_type_id'=>$currency,'count'=>$totals['count'],
                'total_input'=>Decimal::represent($totals['input']),
                'total_output'=>Decimal::represent($totals['output']),
                'balance'=>Decimal::represent($totals['input']->minus($totals['output'])),
                'calculation_snapshot'=>Decimal::snapshot(['total_input'=>$totals['input'],'total_output'=>$totals['output'],'balance'=>$totals['input']->minus($totals['output'])])];
        }
        // Preserve the old VES-only numeric fields when the result is unambiguous.
        $safe = !$this->unclassified && !isset($groups['USD']);
        $ves = $this->currencies['VES'] ?? ['input'=>Decimal::of(0),'output'=>Decimal::of(0)];
        return ['count'=>$this->count,'unclassified_count'=>$this->unclassified,'totals_by_currency'=>$groups ?: (object)[],
            'total_input'=>$safe ? (float)Decimal::represent($ves['input'],2,true) : null,
            'total_output'=>$safe ? (float)Decimal::represent($ves['output'],2,true) : null,
            'balance'=>$safe ? (float)Decimal::represent($ves['input']->minus($ves['output']),2,true) : null];
    }
}
// ######## FIN MONEDA DEL MEDIO DE COBRO ########
