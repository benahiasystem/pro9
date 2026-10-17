<?php
namespace App\Services\Fiscal;
use App\Models\Tenant\DispatchItem;
use App\Support\Exact\Decimal as D;
use Modules\Inventory\Models\ItemWarehouse;
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
        $stock=ItemWarehouse::where('warehouse_id',$warehouse->id)->where('item_id',$line->item_id)->lockForUpdate()->first();
        $quantity=$line->exactValue('quantity');
        if (!$stock || ($direction<0 && $stock->exactValue('stock')->isLessThan($quantity))) FiscalAmounts::error('items','Existencias insuficientes en el almacén de la sucursal.');
        $delta=$quantity->multipliedBy($direction);
        $stock->stock=$stock->exactValue('stock')->plus($delta);$stock->save();
        $order->inventory_kardex()->create(['date_of_issue'=>$order->date_of_issue->format('Y-m-d'),'item_id'=>$line->item_id,'warehouse_id'=>$warehouse->id,'quantity'=>$delta]);
        if (!empty($line->item->IdLoteSelected)) {
            $lot=ItemLotsGroup::where('item_id',$line->item_id)->lockForUpdate()->findOrFail($line->item->IdLoteSelected);
            if ($direction<0 && $lot->exactValue('quantity')->isLessThan($quantity)) FiscalAmounts::error('items','Existencias insuficientes en el lote.');
            $lot->quantity=$lot->exactValue('quantity')->plus($delta);$lot->save();
        }
        foreach ($line->item->lots ?? [] as $serial) {
            $lot=ItemLot::where('item_id',$line->item_id)->where('warehouse_id',$warehouse->id)->lockForUpdate()->findOrFail($serial->id ?? $serial['id']);
            if ($direction<0 && $lot->has_sale) FiscalAmounts::error('items','El serial ya fue utilizado.');
            $lot->has_sale=$direction<0;$lot->save();
        }
    }
}
