<?php

namespace App\Services;

use App\Support\Exact\Decimal;
use Illuminate\Database\Eloquent\Collection;
use Modules\Inventory\Models\InventoryConfiguration;
use Modules\Inventory\Models\ItemWarehouse;
use Modules\Inventory\Models\Warehouse;

class PosCatalogStock
{
    public static function forItems(Collection $items): array
    {
        if ($items->isEmpty()) {
            return [];
        }

        $items->loadMissing('sets.individual_item');
        $warehouse = Warehouse::forEstablishment(auth()->user()->establishment_id);
        $control = (bool) InventoryConfiguration::query()->value('stock_control');
        $ids = $items->modelKeys();
        foreach ($items as $item) {
            $ids = array_merge($ids, $item->sets->pluck('individual_item_id')->all());
        }
        $stocks = $warehouse ? ItemWarehouse::where('warehouse_id', $warehouse->id)
            ->whereIn('item_id', array_unique($ids))->get()->keyBy('item_id') : collect();

        $component = static function ($item, $quantity) use ($stocks) {
            $record = $stocks->get($item->id);
            return [
                'item_id' => $item->id,
                'description' => $item->description,
                'quantity' => Decimal::fraction($quantity),
                'stock' => $record ? Decimal::fraction($record->exactValue('stock')) : null,
            ];
        };

        $result = [];
        foreach ($items as $item) {
            $components = [];
            if ($item->is_set) {
                foreach ($item->sets as $set) {
                    if ($set->individual_item && $set->individual_item->unit_type_id !== 'SERV') {
                        $components[] = $component($set->individual_item, Decimal::readRequired($set, 'quantity'));
                    }
                }
            } elseif ($item->unit_type_id !== 'SERV') {
                $components[] = $component($item, 1);
            }
            $result[$item->id] = ['control' => $control, 'components' => $components];
        }

        return $result;
    }
}
