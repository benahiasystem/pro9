<?php
namespace App\Services\Fiscal;
use App\Models\Tenant\DispatchItem;
use Modules\Inventory\Models\Warehouse;
use Modules\Item\Models\{ItemLot,ItemLotsGroup};

final class DeliveryOrderInventory
{
    public static function apply(DispatchItem $line,int $direction): void
    {
        $order=$line->dispatch;if (!$order->discountsPhysicalStock()) return;
        $db=$order->getConnection();
        if (!$db->transactionLevel()) throw new \LogicException('El inventario de la orden requiere una transacción.');
        $warehouse=Warehouse::where('establishment_id',$order->establishment_id)->orderBy('id')->firstOrFail();
        $stock=$db->table('item_warehouse')->where('warehouse_id',$warehouse->id)->where('item_id',$line->item_id)->lockForUpdate()->first();
        if (!$stock || ($direction<0 && (float)$stock->stock<$line->quantity)) FiscalAmounts::error('items','Existencias insuficientes en el almacén de la sucursal.');
        $delta=$direction*$line->quantity;
        $db->table('item_warehouse')->where('warehouse_id',$warehouse->id)->where('item_id',$line->item_id)->increment('stock',$delta);
        $order->inventory_kardex()->create(['date_of_issue'=>$order->date_of_issue->format('Y-m-d'),'item_id'=>$line->item_id,'warehouse_id'=>$warehouse->id,'quantity'=>$delta]);
        if (!empty($line->item->IdLoteSelected)) {
            $lot=ItemLotsGroup::where('item_id',$line->item_id)->lockForUpdate()->findOrFail($line->item->IdLoteSelected);
            if ($direction<0 && $lot->quantity<$line->quantity) FiscalAmounts::error('items','Existencias insuficientes en el lote.');
            $lot->quantity+=$delta;$lot->save();
        }
        foreach ($line->item->lots ?? [] as $serial) {
            $lot=ItemLot::where('item_id',$line->item_id)->where('warehouse_id',$warehouse->id)->lockForUpdate()->findOrFail($serial->id ?? $serial['id']);
            if ($direction<0 && $lot->has_sale) FiscalAmounts::error('items','El serial ya fue utilizado.');
            $lot->has_sale=$direction<0;$lot->save();
        }
    }
}
