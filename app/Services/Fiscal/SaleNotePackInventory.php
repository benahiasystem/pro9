<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\SaleNoteItem;
use App\Support\Exact\Decimal as D;
use Modules\Inventory\Models\{InventoryConfiguration, ItemWarehouse};

/** Moves the physical components frozen when a sale-note pack was created. */
final class SaleNotePackInventory
{
    public static function apply(SaleNoteItem $line, int $direction): bool
    {
        $snapshot=$line->calculation_snapshot['inputs']['sale_note_pack_inventory'] ?? null;
        if ($snapshot === null) return false;
        if ((int)($snapshot['item_id'] ?? 0)!==(int)$line->item_id || (int)($snapshot['sale_note_id'] ?? 0)!==(int)$line->sale_note_id) {
            FiscalAmounts::error('inventory','La composición del paquete no corresponde a la nota de venta.');
        }
        foreach ($snapshot['components'] as $component) {
            $delta=$line->exactValue('quantity')->multipliedBy(D::of($component['quantity']))->multipliedBy($direction);
            $stock=ItemWarehouse::query()->lockForUpdate()->firstOrNew(['item_id'=>$component['item_id'],'warehouse_id'=>$snapshot['warehouse_id']]);
            $stock->stock=$stock->exactValue('stock')->plus($delta);
            if ($delta->isNegative() && $stock->exactValue('stock')->isNegative() && InventoryConfiguration::firstOrFail()->stock_control) {
                FiscalAmounts::error('items','No hay inventario suficiente para los componentes del paquete.');
            }
            $stock->save();
            $line->sale_note->inventory_kardex()->create(['date_of_issue'=>date('Y-m-d'),'item_id'=>$component['item_id'],
                'warehouse_id'=>$snapshot['warehouse_id'],'quantity'=>$delta]);
        }
        return true;
    }
}
