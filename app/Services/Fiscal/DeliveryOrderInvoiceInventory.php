<?php
namespace App\Services\Fiscal;
use App\Models\Tenant\{Company,Dispatch,DocumentItem,Item};
use Modules\Inventory\Models\{Warehouse,ItemWarehouse};
use App\Support\Exact\Decimal as D;
use Modules\Item\Models\{ItemLot,ItemLotsGroup};

/** Server-owned movement snapshot for invoices sourced from one or more delivery orders. */
final class DeliveryOrderInvoiceInventory
{
    public static function prepare(array $input): array
    {
        foreach ($input['items'] as &$line) unset($line['item']['dispatch_inventory_quantity']);unset($line);
        $refs=$input['dispatches_relateds'] ?? [];
        if (is_string($refs)) $refs=json_decode($refs,true) ?: [];
        if (empty($input['dispatch_id']) && !$refs) return $input;
        Company::lockForUpdate()->firstOrFail();$orders=[];
        if (!empty($input['dispatch_id'])) $orders[]=Dispatch::lockForUpdate()->findOrFail($input['dispatch_id']);
        foreach ($refs as $reference) {
            $parts=FiscalIdentity::parseNumberFull((string)$reference);if (!$parts) FiscalAmounts::error('dispatches_relateds','Referencia de orden inválida.');
            $orders[]=Dispatch::where('establishment_id',$input['establishment_id'])->where('series',$parts[0])->where('number',$parts[1])->lockForUpdate()->firstOrFail();
        }
        $covered=[];$tracked=[];$sourceAvailable=[];
        foreach (collect($orders)->unique('id') as $order) {
            DeliveryOrderInput::authorize((int)$order->establishment_id);
            if ((int)$order->establishment_id!==(int)$input['establishment_id'] || (int)$order->customer_id!==(int)$input['customer_id'] || $order->reference_document_id || in_array($order->state_type_id,['09','11'],true)) FiscalAmounts::error('dispatches_relateds','La orden debe estar vigente, sin facturar, y pertenecer al cliente y sucursal.');
            $source=$order->sale_note ?: $order->order_note;$sourceKey=$source ? get_class($source).':'.$source->id:null;
            if ($source && in_array($source->state_type_id,['09','11'],true)) FiscalAmounts::error('dispatches_relateds','El documento de origen de la orden debe estar vigente.');
            if ($source && !isset($sourceAvailable[$sourceKey])) $sourceAvailable[$sourceKey]=$source->items->groupBy('item_id')->map(fn($lines)=>D::sum($lines->map(fn($line)=>$line->exactValue('quantity')->multipliedBy($line->item->presentation->quantity_unit ?? 1))))->all();
            foreach ($order->items as $line) {
                $moved=$order->discountsPhysicalStock() ? $line->exactValue('quantity'):D::of(0);
                if ($moved->isZero() && $source) {
                    $moved=D::min($line->exactValue('quantity'),$sourceAvailable[$sourceKey][$line->item_id] ?? 0);
                    $sourceAvailable[$sourceKey][$line->item_id]=D::max(0,D::of($sourceAvailable[$sourceKey][$line->item_id] ?? 0)->minus($moved));
                }
                $covered[$line->item_id]=D::of($covered[$line->item_id] ?? 0)->plus($moved);
                $snapshot=(array)$line->item;
                if (!empty($snapshot['IdLoteSelected']) || !empty($snapshot['lots'])) {
                    if (!isset($tracked[$line->item_id])) $tracked[$line->item_id]=['quantity'=>0,'groups'=>[],'serials'=>[]];
                    $tracked[$line->item_id]['quantity']=D::of($tracked[$line->item_id]['quantity'])->plus($line->exactValue('quantity'));
                    if (!empty($snapshot['IdLoteSelected'])) $tracked[$line->item_id]['groups'][$snapshot['IdLoteSelected']]=D::of($tracked[$line->item_id]['groups'][$snapshot['IdLoteSelected']] ?? 0)->plus($line->exactValue('quantity'));
                    foreach ($snapshot['lots'] ?? [] as $serial) {
                        $serial=(array)$serial;
                        if (isset($tracked[$line->item_id]['serials'][$serial['id']])) FiscalAmounts::error('items','Las órdenes seleccionadas repiten un serial.');
                        $tracked[$line->item_id]['serials'][$serial['id']]=$serial;
                    }
                }
            }
        }
        foreach ($input['items'] as &$line) {
            $item=Item::findOrFail($line['item_id']);
            if ($item->is_set) FiscalAmounts::error('items','La conversión de órdenes requiere líneas de bienes individuales.');
            $factor=D::of($line['item']['presentation']['quantity_unit'] ?? 1);$quantity=D::of(D::input($line['quantity'], 'items.quantity'))->multipliedBy($factor);
            if (isset($tracked[$item->id])) {
                $source=$tracked[$item->id];
                if (!empty($source['assigned']) || !$factor->isEqualTo(1) || !$quantity->isEqualTo(D::of($source['quantity']))) FiscalAmounts::error('items','Conserve la cantidad completa de cada producto con lotes o seriales en una línea, usando su unidad original.');
                $tracked[$item->id]['assigned']=true;
                $line['item']['IdLoteSelected']=array_map(fn($id,$amount)=>['id'=>$id,'compromise_quantity'=>D::represent($amount)],array_keys($source['groups']),array_values($source['groups']));
                $line['item']['lots']=array_values(array_map(fn($serial)=>array_replace($serial,['has_sale'=>true]),$source['serials']));
            }
            $taken=D::min($quantity,$covered[$item->id] ?? 0);$covered[$item->id]=D::max(0,D::of($covered[$item->id] ?? 0)->minus($taken));
            if ($taken->isGreaterThan(0) && $taken->isLessThan($quantity) && (!empty($line['item']['lots']) || !empty($line['item']['IdLoteSelected']))) FiscalAmounts::error('items','Separe las cantidades ya trasladadas de las nuevas en líneas distintas para conservar lotes y seriales.');
            $line['item']['dispatch_inventory_quantity']=D::fraction($quantity->minus($taken));
        }
        unset($line);
        foreach ($tracked as $source) if (empty($source['assigned'])) FiscalAmounts::error('items','La factura debe conservar los productos con lotes y seriales de las órdenes.');
        return $input;
    }

    public static function apply(DocumentItem $line,int $direction): bool
    {
        if (!isset($line->item->dispatch_inventory_quantity)) return false;
        $quantity=D::of($line->item->dispatch_inventory_quantity);if ($quantity->isLessThanOrEqualTo(0)) return true;
        $document=$line->document;$db=$document->getConnection();
        $warehouse=$line->warehouse_id ? Warehouse::findOrFail($line->warehouse_id) : Warehouse::where('establishment_id',$document->establishment_id)->orderBy('id')->firstOrFail();
        if ((int)$warehouse->establishment_id!==(int)$document->establishment_id) FiscalAmounts::error('warehouse','El almacén debe pertenecer a la sucursal de la factura.');
        if (!$line->warehouse_id) $line->forceFill(['warehouse_id'=>$warehouse->id])->saveQuietly();
        $stock=ItemWarehouse::where('item_id',$line->item_id)->where('warehouse_id',$warehouse->id)->lockForUpdate()->first();
        if (!$stock || ($direction<0 && $stock->exactValue('stock')->isLessThan($quantity))) FiscalAmounts::error('items','Existencias insuficientes para la cantidad pendiente de traslado.');
        $delta=$quantity->multipliedBy($direction);$stock->stock=$stock->exactValue('stock')->plus($delta);$stock->save();
        $document->inventory_kardex()->create(['date_of_issue'=>$document->date_of_issue->format('Y-m-d'),'item_id'=>$line->item_id,'warehouse_id'=>$warehouse->id,'quantity'=>$delta]);
        $lots=$line->item->IdLoteSelected ?? null;
        if ($lots) {
            $groups=is_array($lots) ? $lots : [['id'=>$lots,'compromise_quantity'=>$quantity]];
            foreach ($groups as $group) {
                $group=(array)$group;$lot=ItemLotsGroup::where('item_id',$line->item_id)->lockForUpdate()->findOrFail($group['id']);$amount=is_array($lots) ? D::of($group['compromise_quantity'] ?? 0)->multipliedBy($line->item->presentation->quantity_unit ?? 1) : $quantity;
                if ($direction<0 && $lot->exactValue('quantity')->isLessThan($amount)) FiscalAmounts::error('items','El lote no tiene existencias suficientes.');
                $lot->quantity=$lot->exactValue('quantity')->plus(D::of($amount)->multipliedBy($direction));$lot->save();
            }
        }
        foreach ($line->item->lots ?? [] as $serial) {
            $serial=(array)$serial;$lot=ItemLot::where('item_id',$line->item_id)->where('warehouse_id',$warehouse->id)->lockForUpdate()->findOrFail($serial['id']);
            if ($direction<0 && $lot->has_sale) FiscalAmounts::error('items','El serial ya fue utilizado.');$lot->has_sale=$direction<0;$lot->save();
        }
        return true;
    }
}
