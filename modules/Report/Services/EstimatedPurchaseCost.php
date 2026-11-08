<?php

namespace Modules\Report\Services;

use App\Models\Tenant\PurchaseItem;
use App\Support\Exact\Decimal as D;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** An estimate from the latest eligible purchase or the current catalogue, not a frozen cost. */
final class EstimatedPurchaseCost
{
    public static function forLine($line, string $date, string $currency, $documentRate, ?array $purchases = null): array
    {
        $item = $line->relation_item;
        if (!$item) throw new \UnexpectedValueException('No se encontró el producto para estimar su costo.');
        $quantity = D::readRequired($line, 'quantity')->multipliedBy(self::presentationFactor($line));
        if (!$item->is_set) {
            [$unit, $source, $purchaseItem] = self::unit($item, $date, $currency, $documentRate, $purchases);
            return ['amount'=>$unit->multipliedBy($quantity), 'source'=>$source, 'purchase_item'=>$purchaseItem];
        }
        $cost = D::of(0); $sources = []; $purchaseItem = null;
        foreach ($item->sets as $component) {
            $child = $component->relation_item;
            if (!$child) throw new \UnexpectedValueException('No se encontró un componente del producto compuesto.');
            [$unit, $source, $purchaseItem] = self::unit($child, $date, $currency, $documentRate, $purchases);
            $cost = $cost->plus($unit->multipliedBy(D::readRequired($component, 'quantity')));
            $sources[] = $source;
        }
        return ['amount'=>$cost->multipliedBy($quantity), 'source'=>'current_components:'.implode(',',array_unique($sources)), 'purchase_item'=>$purchaseItem];
    }

    /** Resolve purchase histories in bounded batches rather than querying once per sale. */
    public static function forLines(iterable $lines, $source, bool $convertToVes = false): array
    {
        $parent = is_bool($source) ? ($source ? 'sale_note' : 'document') : $source;
        if (!in_array($parent, ['document','sale_note','purchase'], true)) throw new \InvalidArgumentException('Origen de partidas no admitido.');
        $result = [];
        foreach (collect($lines)->chunk(500) as $chunk) {
            $batch = new \Illuminate\Database\Eloquent\Collection($chunk->values()->all());
            $batch->loadMissing([$parent, 'relation_item.sets.relation_item']);
            $needed = []; $maximumDate = null;
            foreach ($batch as $line) {
                $document = $line->$parent;
                if (!$document || !$line->relation_item) throw new \UnexpectedValueException('Falta el documento o producto del reporte.');
                $date = $document->date_of_issue->format('Y-m-d');
                if ($maximumDate === null || $date > $maximumDate) $maximumDate = $date;
                $items = $line->relation_item->is_set
                    ? $line->relation_item->sets->map(fn($component)=>$component->relation_item)
                    : collect([$line->relation_item]);
                foreach ($items as $item) {
                    if (!$item) throw new \UnexpectedValueException('No se encontró un componente del producto compuesto.');
                    $needed[$item->id][$date] = null;
                }
            }
            $remaining = array_sum(array_map('count', $needed));
            if ($remaining) {
                $candidates = self::candidates(array_keys($needed), $maximumDate)->lazy(500);
                foreach ($candidates as $candidate) {
                    $purchaseDate = $candidate->purchase->date_of_issue->format('Y-m-d');
                    foreach ($needed[$candidate->item_id] as $date=>&$match) {
                        if ($match === null && $purchaseDate <= $date) { $match = $candidate; $remaining--; }
                    }
                    unset($match);
                    if ($remaining === 0) break;
                }
            }
            foreach ($batch as $line) {
                $document = $line->$parent;
                $currency = $convertToVes && $document->currency_type_id === 'USD' ? 'VES' : $document->currency_type_id;
                $result[spl_object_id($line)] = self::forLine($line, $document->date_of_issue->format('Y-m-d'),
                    $currency, $document->exchange_rate_sale, $needed);
            }
        }
        return $result;
    }

    private static function unit($item, string $date, string $currency, $documentRate, ?array $purchases): array
    {
        $purchaseItem = $purchases !== null ? ($purchases[$item->id][$date] ?? null)
            : self::candidates([$item->id], $date)->first();
        if ($purchaseItem) {
            $purchase = $purchaseItem->purchase;
            $baseUnit = D::readRequired($purchaseItem, 'unit_price')->dividedBy(self::presentationFactor($purchaseItem));
            return [D::convert($baseUnit, $purchase->currency_type_id,
                $currency, $purchase->exchange_rate_sale), 'purchase', $purchaseItem];
        }
        $cost = D::readRequired($item, 'purchase_unit_price');
        if ($cost->isZero()) return [$cost, 'current_catalog', null];
        return [D::convert($cost, $item->currency_type_id, $currency, $documentRate), 'current_catalog', null];
    }

    private static function candidates(array $itemIds, string $maximumDate)
    {
        return PurchaseItem::withoutEagerLoads()
            ->with(['purchase'=>fn($q)=>$q->withoutEagerLoads()->select(['id','date_of_issue','currency_type_id','exchange_rate_sale'])])
            ->join('purchases as cost_purchase', 'cost_purchase.id', '=', 'purchase_items.purchase_id')
            ->whereIn('purchase_items.item_id', $itemIds)
            ->where('cost_purchase.date_of_issue', '<=', $maximumDate)
            ->whereNotIn('cost_purchase.state_type_id', ['09','11'])
            ->select('purchase_items.*')
            ->orderByDesc('cost_purchase.date_of_issue')->orderByDesc('purchase_items.purchase_id')->orderByDesc('purchase_items.id');
    }

    private static function presentationFactor($line)
    {
        $attributes = $line->getAttributes();
        $item = $attributes['item'] ?? null;
        if (is_string($item)) $item = \App\Support\Exact\JsonDecimals::decode($item);
        $presentation = (array)(((array)$item)['presentation'] ?? []);
        $value = $presentation['quantity_unit'] ?? '1';
        $factor = D::inputRational($value, 'presentation.quantity_unit');
        if (!$factor->isPositive()) throw new \UnexpectedValueException('La presentación requiere una cantidad positiva.');
        return $factor;
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
