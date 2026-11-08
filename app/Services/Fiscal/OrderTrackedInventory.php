<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\PurchaseItem;
use App\Support\Exact\Decimal as D;
use Illuminate\Database\Eloquent\Relations\Relation;
use Modules\Item\Models\ItemLot;
use Modules\Order\Models\OrderNoteItem;

final class OrderTrackedInventory
{
    public static function verifyAssociation(OrderNoteItem $line, ?array $lots, ?array $serials): ?array
    {
        if ($lots === null || $serials === null) return $serials;
        $expected=[];
        foreach ($lots['lots'] as $lot) $expected[(string)$lot['id']]=D::of($lot['quantity']);
        $counts=[]; $mapping=[];
        foreach ($serials['serial_ids'] as $id) {
            $serial=ItemLot::query()->lockForUpdate()->findOrFail($id);
            $type=Relation::getMorphedModel($serial->item_loteable_type) ?: $serial->item_loteable_type;
            if ($type !== PurchaseItem::class) FiscalAmounts::error('items','El serial no conserva una partida de compra que identifique su lote.');
            $source=PurchaseItem::withoutEagerLoads()->whereKey($serial->item_loteable_id)->lockForUpdate()->first();
            $lotId=(string)($source->item_lot_group_id ?? '');
            if (!$source || (int)$source->item_id !== (int)$line->item_id || !isset($expected[$lotId])) {
                FiscalAmounts::error('items','El serial no pertenece a los lotes seleccionados.');
            }
            $mapping[(string)$id]=(int)$lotId; $counts[$lotId]=($counts[$lotId] ?? 0)+1;
        }
        foreach ($expected as $id=>$quantity) {
            if (!$quantity->isEqualTo($counts[$id] ?? 0)) FiscalAmounts::error('items','La cantidad de seriales no corresponde a cada lote seleccionado.');
        }
        $serials['lot_groups']=$mapping;
        $data=(array)$line->item;
        $data['lots']=array_map(function ($serial) use ($mapping) {
            $serial=(array)$serial; $serial['lot_group_id']=$mapping[(string)$serial['id']]; return $serial;
        },$data['lots']);
        $data['lot_serial_assignments_verified']=true;
        $line->item=$data;
        return $serials;
    }

    public static function apply(OrderNoteItem $line, int $direction): bool
    {
        $lots=OrderLotInventory::apply($line,$direction);
        $serials=OrderSerialInventory::apply($line,$direction,!$lots);
        return $lots || $serials;
    }
}
