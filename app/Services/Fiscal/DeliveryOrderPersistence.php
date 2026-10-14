<?php
namespace App\Services\Fiscal;
use App\Models\Tenant\{Company,Dispatch};

final class DeliveryOrderPersistence
{
    public static function save(array $input): Dispatch
    {
        $company=Company::query()->lockForUpdate()->firstOrFail();
        DeliveryOrderInput::authorize((int)$input['establishment_id']);
        if (empty($input['id'])) {
            $existing=Dispatch::where('creation_key',$input['creation_key'])->lockForUpdate()->first();
            if ($existing) {
                DeliveryOrderInput::authorize((int)$existing->establishment_id);
                abort_unless(hash_equals($existing->creation_hash,$input['creation_hash']),409,'La clave de creación corresponde a datos diferentes.');
                return $existing;
            }
            $order=new Dispatch();
        } else {
            $order=Dispatch::lockForUpdate()->findOrFail($input['id']);DeliveryOrderInput::authorize((int)$order->establishment_id);
            self::assertEditable($order);
            if ((int)$input['establishment_id']!==(int)$order->establishment_id || $input['series']!==$order->series) FiscalAmounts::error('series','La edición conserva la sucursal y la serie originales.');
            if ($input['transfer_reason_type_id']===$order->transfer_reason_type_id) $input['discount_stock']=$order->discount_stock;
            foreach ($order->items as $line) DeliveryOrderInventory::apply($line,1);
            $order->items()->delete();
            foreach (['series','number','establishment_id','fiscal_environment','external_id','creation_key','creation_hash','state_type_id'] as $field) $input[$field]=$order->$field;
        }
        $order->fill($input);$order->save();
        foreach ($input['items'] as $line) $order->items()->create($line);
        $order=$order->fresh();HkaDispatchEmission::register($order,$company);
        return $order;
    }

    public static function assertEditable(Dispatch $order): void
    {
        if (in_array($order->state_type_id,['09','11'],true) || in_array(optional(HkaDispatchEmission::record($order))->status,['pending','uncertain','confirmed'],true)) FiscalAmounts::error('document','La orden no permite edición o anulación local por su estado fiscal o comercial.');
        if ($order->reference_document_id || $order->generate_document) FiscalAmounts::error('document','La orden relacionada con una factura conserva sus líneas originales.');
    }
}
