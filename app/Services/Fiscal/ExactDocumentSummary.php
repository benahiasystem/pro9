<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\Document;
use App\Support\Exact\Decimal as D;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
final class ExactDocumentSummary
{
    public static function save(Document $document): void
    {
        if ($document->getConnection()->transactionLevel() === 0) {
            $document->getConnection()->transaction(fn()=>self::save($document));
            return;
        }
        // A newly inserted Eloquent model does not hydrate database defaults.
        // Read the persisted operand explicitly rather than inventing zero.
        if (!array_key_exists('total_prepayment', $document->getAttributes())) {
            $stored = $document->newQuery()->withoutEagerLoads()->select(['id','total_prepayment','calculation_snapshot'])
                ->lockForUpdate()->findOrFail($document->getKey());
            $document->total_prepayment = D::readRequired($stored, 'total_prepayment');
        }
        $export = \App\Services\ExportInvoicePolicy::isExport($document->invoice->operation_type_id ?? '');
        $items = $document->items()->withoutEagerLoads()->get();
        $discounts = collect((array) $document->discounts)->map(fn ($v) => (array) $v);
        $charges = collect((array) $document->charges)->map(fn ($v) => (array) $v);
        $amount = fn ($row) => D::inputRational($row['amount_exact'] ?? $row['amount'] ?? 0,'adjustments.amount');
        foreach ($discounts->merge($charges) as $row) if ($amount($row)->isNegative()) FiscalAmounts::error('discounts', 'Importe inválido.');
        $sum = fn ($rows, $field) => D::sum($rows->map(fn ($v) => D::readRequired($v, $field)));
        $allocationStats = \App\Support\Exact\HeaderAllocation::stats();
        foreach ($items as $line) \App\Support\Exact\HeaderAllocation::add($allocationStats, $line);
        $remainingDiscounts = \App\Support\Exact\HeaderAllocation::discounts($document->discounts, $allocationStats);
        $taxedDiscount = $remainingDiscounts['taxed'];
        $exemptDiscount = $remainingDiscounts['exempt'];
        $globalDiscount = $remainingDiscounts['global'];
        $globalCharge = D::sum($charges->map($amount));
        $taxedOriginal = $sum($items->where('affectation_igv_type_id', '10'), 'total_base_igv');
        $exemptOriginal = $sum($items->where('affectation_igv_type_id', '20'), 'total_base_igv');
        if ((!$export && ($taxedDiscount->isGreaterThan($taxedOriginal) || $exemptDiscount->isGreaterThan($exemptOriginal)))
            || ($export && $globalDiscount->isGreaterThan($sum($items, 'total_value')))) FiscalAmounts::error('discounts', 'El descuento supera su base.');
        $document->taxes()->whereNull('document_payment_id')->where('tax_kind', 'IVA')->delete();
        $taxed = $exempt = $iva = D::of(0);
        foreach ($items->groupBy(fn ($line) => ($line->iva_rate['code'] ?? '').':'.(string)D::readRequired($line,'percentage_igv')) as $group) {
            if ($export) continue;
            $first = $group->first();
            $base = $sum($group, 'total_base_igv');
            $original = $first->affectation_igv_type_id === '10' ? $taxedOriginal : $exemptOriginal;
            $discount = $first->affectation_igv_type_id === '10' ? $taxedDiscount : $exemptDiscount;
            $deduction = $original->isZero() ? D::of(0) : $discount->multipliedBy($base)->dividedBy($original);
            $base = $base->minus($deduction);
            $tax = $deduction->isZero() ? $sum($group, 'total_igv') : $base->multipliedBy(D::readRequired($first, 'percentage_igv'))->dividedBy(100);
            self::tax($document, $first->iva_rate['code'] ?? 'IVA', D::readRequired($first,'percentage_igv'), $base, $tax);
            if ($first->affectation_igv_type_id === '10') $taxed = $taxed->plus($base); else $exempt = $exempt->plus($base);
            $iva = $iva->plus($tax);
        }
        $values = [];
        if ($items->isNotEmpty()) {
            $value = $sum($items, 'total_value')->minus($globalDiscount)->plus($globalCharge);
            $values = ['total_taxed' => $taxed, 'total_exonerated' => $exempt, 'total_igv' => $iva,
                'total_value' => $value, 'total_exportation' => $export ? $value : D::of(0),
                'total_discount' => $sum($items, 'total_discount')->plus($globalDiscount),
                'total_charge' => $sum($items, 'total_charge')->plus($globalCharge)];
            if ($export) self::tax($document, 'E', 0, $value, D::of(0));
        }
        $taxes = $document->taxes()->get();
        $igtf = $sum($taxes->where('tax_kind', 'IGTF'), 'amount');
        $other = $taxes->whereNotIn('tax_kind', ['IVA', 'IGTF']);
        $values['total_base_other_taxes'] = $sum($other, 'base');
        $values['total_other_taxes'] = $igtf->plus($sum($other, 'amount'));
        $values['total_taxes'] = ($values['total_igv'] ?? D::read($document, 'total_igv'))->plus($values['total_other_taxes']);
        $advance = \App\Support\Exact\HeaderAllocation::includesAdvance($document->discounts) ? D::of(0) : D::read($document, 'total_prepayment');
        $values['total'] = ($values['total_value'] ?? D::read($document, 'total_value'))->plus($values['total_taxes'])->minus($advance);
        if ($values['total']->isNegative()) FiscalAmounts::error('total', 'El total no puede ser negativo.');
        $values['subtotal'] = $values['total']->minus($igtf);
        $values['total_payable'] = D::of(D::represent($values['total'], 2));
        $values['settlement_adjustment'] = $values['total_payable']->minus($values['total']);
        // Update calculated fields while keeping original operands (including periodic advances).
        $document->setExactValues($values);
        $legends = collect((array) $document->legends)->map(fn ($v) => (array) $v)->reject(fn ($v) => ($v['code'] ?? '') === '1000')->values()->all();
        $legends[] = ['code' => '1000', 'value' => \App\CoreFacturalo\Helpers\Number\NumberLetter::convertToLetter($document->total_payable)];
        $document->legends = $legends;
        \App\Support\Exact\HeaderAllocation::freeze($document, $items);
        $document->save();
        $equivalents = [];
        foreach (['taxed'=>'total_taxed', 'exempt'=>'total_exonerated', 'exportation'=>'total_exportation', 'discount'=>'total_discount',
            'charge'=>'total_charge', 'iva'=>'total_igv', 'other_taxes'=>'total_other_taxes', 'total'=>'total'] as $field => $source) {
            $equivalents[$field] = D::convert(D::read($document, $source), $document->currency_type_id, 'VES', $document->exchange_rate_sale);
        }
        $document->currency_totals()->updateOrCreate(['document_id' => $document->id], D::values($equivalents) + [
            'currency_type_id' => 'VES', 'calculation_snapshot' => D::snapshot($equivalents),
            'tax_breakdown' => $document->taxes()->get(['tax_kind','code','percentage','base_ves','amount_ves'])->toArray()]);
    }

    private static function tax(Document $document, $code, $rate, $base, $amount): void
    {
        $values = ['percentage' => D::of($rate), 'base' => $base, 'amount' => $amount,
            'base_ves' => D::convert($base, $document->currency_type_id, 'VES', $document->exchange_rate_sale),
            'amount_ves' => D::convert($amount, $document->currency_type_id, 'VES', $document->exchange_rate_sale)];
        $document->taxes()->create(D::values($values) + ['tax_kind' => 'IVA', 'code' => $code, 'calculation_snapshot' => array_replace($document->calculation_snapshot ?? [],D::snapshot($values))]);
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
