<?php

namespace App\Support\Exact;

use Illuminate\Database\Eloquent\Model;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Explicit aggregate ownership; never infer or alter unrelated models from their names. */
final class CommercialCalculation
{
    private const OWNERS = [
        'purchase_items' => [\App\Models\Tenant\Purchase::class, 'purchase_id'],
        'sale_note_items' => [\App\Models\Tenant\SaleNote::class, 'sale_note_id'],
        'quotation_items' => [\App\Models\Tenant\Quotation::class, 'quotation_id'],
        'order_note_items' => [\Modules\Order\Models\OrderNote::class, 'order_note_id'],
        'purchase_order_items' => [\Modules\Purchase\Models\PurchaseOrder::class, 'purchase_order_id'],
        'purchase_quotation_items' => [\Modules\Purchase\Models\PurchaseQuotation::class, 'purchase_quotation_id'],
        'sale_opportunity_items' => [\Modules\Sale\Models\SaleOpportunity::class, 'sale_opportunity_id'],
        'contract_items' => [\Modules\Sale\Models\Contract::class, 'contract_id'],
        'fixed_asset_purchase_items' => [\Modules\Purchase\Models\FixedAssetPurchase::class, 'fixed_asset_purchase_id'],
    ];

    public static function supportsLine(Model $model): bool
    {
        return isset(self::OWNERS[$model->getTable()]) || $model->getTable() === 'document_items';
    }

    public static function line(Model $model): void
    {
        if (!isset(self::OWNERS[$model->getTable()]) || $model->unit_price === null || $model->quantity === null) return;
        $changed = !$model->calculation_snapshot || $model->isDirty(['quantity','unit_price','discounts','charges','affectation_igv_type_id','quantity_input','price_input']);
        if (!$changed) return;
        $affectation = $model->affectation_igv_type_id;
        if (!in_array($affectation, ['10','20','EXP'], true)) return;
        $input = $model->getAttributes();
        foreach (['quantity_input'=>'quantity','price_input'=>'unit_price'] as $source=>$field) {
            if ($model->exists && !$model->isDirty($field) && !array_key_exists($source,$input)
                && isset($model->calculation_snapshot['inputs'][$source])) {
                $input[$source]=$model->calculation_snapshot['inputs'][$source];
            }
        }
        [$class, $foreign] = self::OWNERS[$model->getTable()];
        if (!empty($input['price_input'])) {
            $owner = $class::findOrFail($model->$foreign);
            $input['currency_type_id'] = $owner->currency_type_id;
            $input['exchange_rate_sale'] = $owner->exchange_rate_sale;
        }
        $input['discounts'] = (array) ($model->discounts ?? []);
        $input['charges'] = (array) ($model->charges ?? []);
        $preserved=[];
        if ($model->exists) foreach (['quantity','unit_price'] as $field) {
            if (!$model->isDirty($field)) $preserved[$field]=$model->exactValue($field);
        }
        $line = LineCalculation::calculate($input, $affectation === '10' ? \App\Support\Venezuela\Localization::exactTaxPercentage() : '0', $preserved);
        foreach (Persistence::columns($model) as $field) if (isset($line[$field])) $model->$field = $line[$field];
        $model->calculation_snapshot = $line['calculation_snapshot'];
        $model->discounts = $line['discounts'] ?? [];
        $model->charges = $line['charges'] ?? [];
    }

    public static function afterLine(Model $model): void
    {
        if (!isset(self::OWNERS[$model->getTable()]) || !Persistence::enabled($model)) return;
        [$class, $foreign] = self::OWNERS[$model->getTable()];
        $owner = $class::query()->lockForUpdate()->find($model->$foreign);
        if (!$owner || !method_exists($owner, 'items')) return;
        self::summarize($owner);
    }

    public static function summarize(Model $owner): void
    {
        $items = $owner->items()->lockForUpdate()->get();
        $sum = fn ($rows, $field) => Decimal::sum($rows->map(fn ($r) => Decimal::read($r, $field)));
        $amount = fn ($v) => Decimal::inputRational(((array)$v)['amount_exact'] ?? ((array)$v)['amount'] ?? 0,'adjustments.amount');
        $discounts = collect((array) ($owner->discounts ?? []));
        $charges = collect((array) ($owner->charges ?? []));
        $distributed = Decimal::sum($items->flatMap(fn ($line) => collect((array)$line->discounts)->filter(fn ($v) => !empty(((array)$v)['from_global_distribution']))->map($amount)));
        $global = Decimal::sum($discounts->map($amount))->minus($distributed);
        if ($global->isNegative()) $global = Decimal::of(0);
        $taxedDiscount = Decimal::sum($discounts->filter(fn ($v) => in_array(((array)$v)['discount_type_id'] ?? '', ['02','04'], true))->map($amount))->minus($distributed);
        if ($taxedDiscount->isNegative()) $taxedDiscount = Decimal::of(0);
        $taxed = $sum($items->where('affectation_igv_type_id','10'), 'total_base_igv');
        $exempt = $sum($items->where('affectation_igv_type_id','20'), 'total_base_igv');
        $exemptDiscount = Decimal::sum($discounts->filter(fn ($v) => (((array)$v)['discount_type_id'] ?? '') === '05')->map($amount));
        if ($taxedDiscount->isGreaterThan($taxed) || $exemptDiscount->isGreaterThan($exempt)) {
            \App\Services\Fiscal\FiscalAmounts::error('discounts', 'El descuento supera su base.');
        }
        $iva = $sum($items, 'total_igv');
        if (!$taxed->isZero() && !$taxedDiscount->isZero()) $iva = $iva->multipliedBy($taxed->minus($taxedDiscount))->dividedBy($taxed);
        $globalCharge = Decimal::sum($charges->map($amount));
        $value = $sum($items, 'total_value')->minus($global)->plus($globalCharge);
        $other = $sum($items, 'total_other_taxes');
        $taxes = $iva->plus($other);
        $values = ['total_value'=>$value, 'total_taxed'=>$taxed->minus($taxedDiscount),
            'total_exonerated'=>$exempt->minus($exemptDiscount),
            'total_exportation'=>$items->contains('affectation_igv_type_id','EXP') ? $value : Decimal::of(0),
            'total_igv'=>$iva, 'total_taxes'=>$taxes, 'total_other_taxes'=>$other,
            'total_discount'=>$sum($items,'total_discount')->plus($global), 'total_charge'=>$sum($items,'total_charge')->plus($globalCharge),
            'total'=>$value->plus($taxes)->minus($discounts->contains(fn($v)=>in_array(((array)$v)['discount_type_id'] ?? '',['04','05'],true)) ? 0 : Decimal::read($owner,'total_prepayment'))];
        if ($values['total']->isNegative()) \App\Services\Fiscal\FiscalAmounts::error('total', 'El total no puede ser negativo.');
        $values['total_payable']=Decimal::of(Decimal::represent($values['total'],2));
        $values['settlement_adjustment']=$values['total_payable']->minus($values['total']);
        $values = array_intersect_key($values, array_flip(Persistence::columns($owner)));
        $owner->setExactValues($values)->saveQuietly();
        LoyaltyPoints::synchronize($owner);
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
