<?php

namespace App\Support\Exact;

use App\Models\Tenant\{Document, SaleNote, Person, Item};

// ######## INICIO CALCULOS EXACTOS PRO9 ########
final class LoyaltyPoints
{
    public static function earned($total, $settings): \Brick\Math\BigRational
    {
        $settings=(array)$settings;
        $base=Decimal::of($settings['point_system_sale_amount'] ?? 0);
        $points=Decimal::of($settings['quantity_of_points'] ?? 0);
        if ($base->isLessThanOrEqualTo(0) || $points->isNegative()) {
            \App\Services\Fiscal\FiscalAmounts::error('point_system_data','La base de puntos debe ser positiva y los puntos no negativos.');
        }
        return Decimal::of($total)->dividedBy($base)->multipliedBy($points)->simplified();
    }

    public static function synchronize($document, bool $reverse = false): void
    {
        if (!$document instanceof Document && !$document instanceof SaleNote) return;
        $old=$document->calculation_snapshot['loyalty'] ?? null;
        $eligible=$document->isPointSystem() && ($document instanceof Document
            ? $document->isDocumentTypeInvoice() && !$document->sale_note_id : $document->isCreatedFromPos());
        if (!$eligible && !$old) return;
        if (!Persistence::enabled($document)) return;
        $document->getConnection()->transaction(function () use ($document,$reverse) {
            $record=$document->newQuery()->withoutEagerLoads()->lockForUpdate()->findOrFail($document->getKey());
            $snapshot=$record->calculation_snapshot;
            $old=$snapshot['loyalty'] ?? null;
            $eligible=$record->isPointSystem() && ($record instanceof Document
                ? $record->isDocumentTypeInvoice() && !$record->sale_note_id : $record->isCreatedFromPos());
            $active=$eligible && !$reverse && !$record->isVoidedOrRejected();
            $items=$record->items()->without(['affectation_igv_type','price_type'])->get();
            if ($active && $items->isEmpty()) return; // Creation precedes authoritative line/header calculation.
            $earned=$active ? self::earned($record->exactValue('total'),$record->point_system_data) : Decimal::of(0);
            $spent=Decimal::of(0);$linePoints=[];
            if ($active) {
                $redeemed=$items->filter(fn($line)=>!empty($line->item->exchanged_for_points));
                $catalog=$redeemed->isEmpty() ? collect() : Item::withoutEagerLoads()->whereIn('id',$redeemed->pluck('item_id'))->get()->keyBy('id');
                foreach ($redeemed as $line) {
                    $source=$old['line_points'][(string)$line->id] ?? null;
                    if ($source===null) {
                        $item=$catalog->get($line->item_id);
                        if (!$item) \App\Services\Fiscal\FiscalAmounts::error('items','El producto del canje no existe.');
                        $source=Decimal::fraction($item->exactValue('quantity_of_points'));
                    }
                    if (Decimal::of($source)->isNegative()) \App\Services\Fiscal\FiscalAmounts::error('items','Puntos de canje inválidos.');
                    $linePoints[(string)$line->id]=$source;
                    $spent=$spent->plus(Decimal::of($source)->multipliedBy($line->exactValue('quantity')))->simplified();
                }
            }
            $next=['customer_id'=>(int)$record->customer_id,'active'=>$active,'earned'=>Decimal::fraction($earned),
                'spent'=>Decimal::fraction($spent),'line_points'=>$linePoints];
            if ($next===$old) return;
            $ids=array_values(array_unique(array_map('intval',array_filter([$old['customer_id'] ?? null,$record->customer_id]))));
            sort($ids,SORT_NUMERIC);
            $people=Person::withoutEagerLoads()->whereIn('id',$ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $oldNet=$old ? Decimal::of($old['earned'])->minus(Decimal::of($old['spent'])) : Decimal::of(0);
            foreach ($ids as $id) {
                $person=$people->get($id);
                if (!$person) \App\Services\Fiscal\FiscalAmounts::error('customer_id','El cliente de puntos no existe.');
                $balance=$person->exactValue('accumulated_points');
                if ($old && $id===$old['customer_id']) $balance=$balance->minus($oldNet);
                if ($id===$next['customer_id']) {
                    if ($active && !$spent->isZero() && $spent->isGreaterThan($balance)) {
                        \App\Services\Fiscal\FiscalAmounts::error('items','El canje supera los puntos disponibles antes de esta venta.');
                    }
                    $balance=$balance->plus($earned)->minus($spent);
                }
                $person->setExactValues(['accumulated_points'=>$balance])->save();
            }
            $snapshot['loyalty']=$next;
            $record->calculation_snapshot=$snapshot;
            $record->saveQuietly();
            $document->calculation_snapshot=$snapshot;
        });
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
