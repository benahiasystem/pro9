<?php
namespace App\Services\Fiscal;

use App\Models\Tenant\{Company,Dispatch,Document,Establishment,Item,Person,User};
use App\Models\Tenant\Catalogs\{TransferReasonType,UnitType};
use App\CoreFacturalo\Requests\Inputs\Common\{EstablishmentInput,PersonInput};
use Modules\Dispatch\Models\{Driver,Transport,Dispatcher,OriginAddress};
use Illuminate\Support\Str;

/** Web input: resolve registered identities before any write or fiscal network I/O. */
final class DeliveryOrderInput
{
    public static function authorize(?int $establishment = null): void
    {
        $user=auth()->user();
        abort_unless($user instanceof User,403);
        abort_unless($user->type==='admin' || $user->searchModule('guia') || $user->searchLevel('dispatches'),403);
        if ($establishment!==null) abort_unless($user->type==='admin' || (int)$user->establishment_id===$establishment,403);
    }

    public static function order($id): Dispatch
    {
        self::authorize();$order=Dispatch::findOrFail($id);self::authorize((int)$order->establishment_id);return $order;
    }

    public static function resolve(array $input): array
    {
        self::authorize((int)($input['establishment_id'] ?? 0));
        $editing=!empty($input['id']) ? self::order($input['id']) : null;
        if ($editing) DeliveryOrderPersistence::assertEditable($editing);
        $company=Company::firstOrFail();
        if (!$editing && $company->getConnection()->getSchemaBuilder()->hasColumn('dispatches','creation_key') && !empty($input['creation_key'])) {
            $editing=Dispatch::where('creation_key',$input['creation_key'])->first();
            if ($editing) self::authorize((int)$editing->establishment_id);
        }
        $valued=(bool)\App\Models\Tenant\Configuration::first()->enabled_price_items_dispatch;
        $rules=['creation_key'=>'required|uuid','document_type_id'=>'required|in:09','establishment_id'=>'required|integer|exists:tenant.establishments,id',
            'date_of_issue'=>'required|date_format:Y-m-d','time_of_issue'=>'required|date_format:H:i:s','date_of_shipping'=>'required|date_format:Y-m-d|after_or_equal:date_of_issue',
            'transport_mode_type_id'=>'required|in:01,02','transfer_reason_type_id'=>['required',TransferReasonType::activeValidationRule()],
            'transfer_reason_description'=>'nullable|required_if:transfer_reason_type_id,24|string|max:255',
            'unit_type_id'=>['required',UnitType::activeValidationRule(),'in:KG,TON'],
            'total_weight'=>'required|numeric|gt:0|max:99999999.99','packages_number'=>'nullable|integer|min:0|max:2147483647',
            'origin_address_id'=>'required|integer|min:0','delivery_address_id'=>'required|integer|min:0',
            'customer_id'=>'nullable|required_unless:transfer_reason_type_id,04|integer',
            'observations'=>'nullable|string|max:1500','items'=>'required|array|min:1|max:300','items.*.item_id'=>'required|integer|exists:tenant.items,id',
            'items.*.quantity'=>'required|numeric|gt:0|max:99999999.9999','items.*.name_product_pdf'=>'nullable|string|max:255','items.*.unit_price'=>'nullable|numeric|min:0|max:99999999.9999',
            'driver_id'=>'nullable|integer|required_if:transport_mode_type_id,02','transport_id'=>'nullable|integer|required_if:transport_mode_type_id,02',
            'dispatcher_id'=>'nullable|integer|required_if:transport_mode_type_id,01','hka_data'=>'nullable|array',
            'hka_data.service_description'=>'nullable|string|max:255','hka_data.product_type_id'=>'nullable|integer|exists:tenant.cat_product_types,id',
            'hka_data.product_origin_id'=>'nullable|integer|exists:tenant.cat_product_origins,id','hka_data.taxation_product_id'=>'nullable|integer|exists:tenant.cat_taxation_products,id',
            'secondary_drivers'=>'nullable|array|max:2','secondary_transports'=>'nullable|array|max:2',
            'is_transport_m1l'=>'prohibited','license_plate_m1l'=>'prohibited','secondary_license_plates'=>'prohibited'];
        app('validator')->make($input,$rules)->validate();
        if ($company->fiscal_emission_mode==='digital' && $company->fiscal_environment==='demo' && $input['transfer_reason_type_id']==='21' && !trim($input['hka_data']['service_description'] ?? '')) FiscalAmounts::error('hka_data.service_description','Describa el servicio de reparación o perfeccionamiento.');
        $establishment=Establishment::findOrFail($input['establishment_id']);
        $reason=TransferReasonType::findOrFail($input['transfer_reason_type_id']);
        $customer=!empty($input['customer_id']) ? Person::where('type','customers')->where('enabled',true)->findOrFail($input['customer_id']) : null;
        if ($customer && (!$customer->identity_document_type->active || $customer->identity_document_type_id==='ND')) FiscalAmounts::error('customer_id','Seleccione un cliente domiciliado con identidad activa.');
        $origin=(int)$input['origin_address_id']===0 ? $establishment : OriginAddress::where('is_active',true)->where('establishment_id',$establishment->id)->findOrFail($input['origin_address_id']);
        if ($reason->id==='04') {
            $destinations=Establishment::where('id','!=',$establishment->id)->orderBy('id')->get()->concat(OriginAddress::where('is_active',true)->where('establishment_id','!=',$establishment->id)->orderBy('id')->get())->values();
            $delivery=$destinations->get((int)$input['delivery_address_id']-1);
            if (!$delivery) FiscalAmounts::error('delivery_address_id','Seleccione un almacén de destino autorizado.');
        } else $delivery=(int)$input['delivery_address_id']===0 ? $customer : \App\Models\Tenant\PersonAddress::where('person_id',$customer->id)->where('has_consigned',false)->findOrFail($input['delivery_address_id']);
        $needsDriver=$input['transport_mode_type_id']==='02' || !empty($input['has_transport_driver_01']);
        $driver=$needsDriver ? Driver::where('is_active',true)->findOrFail($input['driver_id'] ?? null) : null;
        $transport=$needsDriver ? Transport::where('is_active',true)->findOrFail($input['transport_id'] ?? null) : null;
        $dispatcher=$input['transport_mode_type_id']==='01' ? Dispatcher::where('is_active',true)->findOrFail($input['dispatcher_id']) : null;
        $rows=[];$usedSerials=[];
        $warehouse=\Modules\Inventory\Models\Warehouse::where('establishment_id',$establishment->id)->orderBy('id')->firstOrFail();
        foreach ($input['items'] as $row) {
            if (abs((float)$row['quantity']-round((float)$row['quantity'],4))>0.00000001) FiscalAmounts::error('items.quantity','Use como máximo cuatro decimales en la cantidad.');
            $item=Item::findOrFail($row['item_id']);
            if ($item->item_type_id!=='01' || $item->is_set || !$item->unit_type->active) FiscalAmounts::error('items','Seleccione bienes individuales con unidad activa.');
            $valuation=null;
            if ($valued && (float)($row['unit_price'] ?? 0)>0) {
                if ($item->currency_type_id!=='VES') FiscalAmounts::error('items.unit_price','Las órdenes valorizadas requieren precios en VES.');
                $calculated=FiscalDocumentPersistence::line(['quantity'=>$row['quantity'],'unit_price'=>$row['unit_price'],
                    'affectation_igv_type_id'=>$item->sale_affectation_igv_type_id,'item'=>['unit_type_id'=>$item->unit_type_id]]);
                $valuation=array_intersect_key($calculated,array_flip(['unit_value','total_value','percentage_igv','total_igv','total','iva_rate']));
            }
            $data=is_array($row['item'] ?? null) ? $row['item'] : [];$nested=is_array($data['item'] ?? null) ? $data['item'] : $data;
            $lot=$row['IdLoteSelected'] ?? $nested['IdLoteSelected'] ?? null;
            $serials=$row['lots'] ?? $nested['lots'] ?? [];
            if ($item->lots_enabled && !$lot) FiscalAmounts::error('items','Seleccione el lote del producto.');
            if ($lot) {
                $record=\Modules\Item\Models\ItemLotsGroup::where('item_id',$item->id)->findOrFail($lot);
                $returned=$editing && ($editing->discountsPhysicalStock() || empty($input['id'])) ? $editing->items->filter(fn($l)=>(int)$l->item_id===(int)$item->id && (int)($l->item->IdLoteSelected ?? 0)===(int)$lot)->sum('quantity') : 0;
                if ((float)$record->quantity+$returned < (float)$row['quantity']) FiscalAmounts::error('items','El lote no tiene existencias suficientes.');
            }
            $resolvedSerials=[];
            foreach ($serials ?: [] as $serial) {
                $record=\Modules\Item\Models\ItemLot::where('item_id',$item->id)->where('warehouse_id',$warehouse->id)->findOrFail($serial['id']);
                if (isset($usedSerials[$record->id])) FiscalAmounts::error('items','Un serial solo puede aparecer una vez en la orden.');
                $usedSerials[$record->id]=true;
                $owned=$editing && ($editing->discountsPhysicalStock() || empty($input['id'])) && $editing->items->contains(fn($l)=>(int)$l->item_id===(int)$item->id && collect($l->item->lots ?? [])->contains(fn($s)=>(int)((array)$s)['id']===(int)$record->id));
                if ($record->has_sale && !$owned) FiscalAmounts::error('items','El serial ya fue utilizado.');
                $resolvedSerials[]=$record->toArray();
            }
            if ($item->series_enabled && (float)count($resolvedSerials)!==(float)$row['quantity']) FiscalAmounts::error('items','La cantidad debe coincidir con los seriales seleccionados.');
            $rows[]=['item_id'=>$item->id,'quantity'=>$row['quantity'],'name_product_pdf'=>$row['name_product_pdf'] ?? null,'additional_data'=>$row['additional_data'] ?? null,
                'item'=>['description'=>$item->description,'model'=>$item->model,'internal_id'=>$item->internal_id,'item_type_id'=>$item->item_type_id,'item_code'=>$item->item_code,'item_code_gs1'=>$item->item_code_gs1,'unit_type_id'=>$item->unit_type_id,'hka_unit_code'=>$item->unit_type->hka_code,
                    'IdLoteSelected'=>$lot,'lots'=>$resolvedSerials,'weight'=>$row['weight'] ?? null,'unit_price'=>$valued ? ($row['unit_price'] ?? null) : null,'valuation'=>$valuation,'total'=>$valuation['total'] ?? null]];
        }
        $references=[];
        foreach (['reference_document_id'=>Document::class,'reference_sale_note_id'=>\App\Models\Tenant\SaleNote::class,'reference_quotation_id'=>\App\Models\Tenant\Quotation::class,'reference_order_note_id'=>\Modules\Order\Models\OrderNote::class,'reference_order_form_id'=>\Modules\Order\Models\OrderForm::class] as $field=>$model) {
            $references[$field]=$input[$field] ?? null;
            if ($references[$field]) {
                $record=$model::findOrFail($references[$field]);self::authorize((int)$record->establishment_id);
                if ((int)$record->establishment_id!==(int)$establishment->id || ($customer && (int)$record->customer_id!==(int)$customer->id) || in_array($record->state_type_id,['09','11'],true) || ($field==='reference_document_id' && $record->document_type_id!=='01')) FiscalAmounts::error($field,'El documento relacionado debe estar vigente y pertenecer a la sucursal y al cliente.');
                if (in_array($field,['reference_document_id','reference_sale_note_id','reference_order_note_id'],true)) {
                    $available=$record->items->groupBy('item_id')->map(fn($lines)=>$lines->sum(fn($line)=>$line->quantity*(float)($line->item->presentation->quantity_unit ?? 1)));
                    foreach (collect($rows)->groupBy('item_id') as $itemId=>$lines) if ($lines->sum('quantity')>($available[$itemId] ?? 0)+0.00001) FiscalAmounts::error('items','Las cantidades y productos deben corresponder al documento comercial de origen.');
                }
            }
        }
        $hasMovement=$references['reference_document_id'] || $references['reference_sale_note_id'] || $references['reference_order_note_id'];
        $identityFields=['establishment_id','series','date_of_issue','time_of_issue','date_of_shipping','customer_id','origin_address_id','delivery_address_id','transport_mode_type_id','transfer_reason_type_id','transfer_reason_description','driver_id','transport_id','dispatcher_id','total_weight','unit_type_id','packages_number','observations','hka_data','has_transport_driver_01','secondary_drivers','secondary_transports','reference_documents','custom_fields_data','date_delivery_to_transport'];
        $fingerprint=array_intersect_key($input,array_flip($identityFields))+$references;
        $fingerprint['items']=array_map(fn($r)=>[$r['item_id'],(string)$r['quantity'],$r['name_product_pdf'],$r['item']['IdLoteSelected'],array_column($r['item']['lots'],'id'),$r['item']['unit_price'],$r['item']['weight']],$rows);
        $sort=function(&$v) use (&$sort) {if (!is_array($v)) return;foreach($v as &$x)$sort($x);if (!array_is_list($v)) ksort($v);};$sort($fingerprint);
        $resolved=$references+['id'=>$input['id'] ?? null,'type'=>'dispatch','creation_key'=>$input['creation_key'],'creation_hash'=>hash('sha256',json_encode($fingerprint,JSON_THROW_ON_ERROR)),
            'user_id'=>auth()->id(),'external_id'=>(string)Str::uuid(),'establishment_id'=>$establishment->id,'establishment'=>EstablishmentInput::set($establishment->id),
            'document_type_id'=>'09','fiscal_environment'=>$company->fiscal_environment,'state_type_id'=>'01','ubl_version'=>'2.0','series'=>\App\Services\SeriesNumbering::normalizeCode($input['series'] ?? null),'number'=>$input['number'] ?? '#',
            'date_of_issue'=>$input['date_of_issue'],'time_of_issue'=>$input['time_of_issue'],'date_of_shipping'=>$input['date_of_shipping'],'customer_id'=>$customer->id ?? null,'customer'=>$customer ? PersonInput::set($customer->id) : null,
            'transfer_reason_type_id'=>$reason->id,'transfer_reason_description'=>$reason->id==='24' ? trim($input['transfer_reason_description']) : null,'discount_stock'=>(bool)$reason->discount_stock && !$hasMovement,
            'transport_mode_type_id'=>$input['transport_mode_type_id'],'unit_type_id'=>$input['unit_type_id'],'total_weight'=>$input['total_weight'],'packages_number'=>$input['packages_number'] ?? null,
            'origin_address_id'=>$input['origin_address_id'],'delivery_address_id'=>$input['delivery_address_id'],'origin'=>self::address($origin),'delivery'=>self::address($delivery),
            'driver_id'=>$driver->id ?? null,'driver'=>$driver ? $driver->only(['identity_document_type_id','number','name','license','telephone']) : null,
            'transport_id'=>$transport->id ?? null,'transport_data'=>$transport ? $transport->only(['plate_number','brand','model','tuc']) : null,
            'dispatcher_id'=>$dispatcher->id ?? null,'dispatcher'=>$dispatcher ? $dispatcher->only(['identity_document_type_id','number','name','address','number_mtc']) : null,
            'has_transport_driver_01'=>!empty($input['has_transport_driver_01']),'secondary_drivers'=>self::secondary($input['secondary_drivers'] ?? [],Driver::class),
            'secondary_transports'=>self::secondary($input['secondary_transports'] ?? [],Transport::class),'transshipment_indicator'=>!empty($input['transshipment_indicator']),
            'port_code'=>$input['port_code'] ?? null,'container_number'=>$input['container_number'] ?? null,'observations'=>$input['observations'] ?? '',
            'date_delivery_to_transport'=>$input['date_delivery_to_transport'] ?? null,'reference_documents'=>$input['reference_documents'] ?? [],'order_form_external'=>$input['order_form_external'] ?? null,
            'terms_condition'=>$input['terms_condition'] ?? null,'custom_fields_data'=>$input['custom_fields_data'] ?? [],'items'=>$rows,'hka_data'=>array_intersect_key($input['hka_data'] ?? [],array_flip(['service_description','product_type_id','product_origin_id','taxation_product_id']))];
        // Other existing tenants keep their local route until their schema is explicitly extended.
        if (!$company->getConnection()->getSchemaBuilder()->hasColumn('dispatches','creation_key')) unset($resolved['creation_key'],$resolved['creation_hash'],$resolved['hka_data']);
        return $resolved;
    }

    private static function secondary(array $rows,string $model): array
    {
        return array_map(fn($r)=>$model::where('is_active',true)->findOrFail($r['id'] ?? null)->only($model===Driver::class ? ['id','identity_document_type_id','number','name','license','telephone'] : ['id','plate_number','brand','model','tuc']),$rows);
    }

    public static function address($record): array
    {
        $data=$record->toArray();$location=$data['location_id'] ?? ($data['district_id'] ?? null);if (is_array($location)) $location=$location[2] ?? null;
        if (!$location || !trim($data['address'] ?? '') || $data['address']==='-') FiscalAmounts::error('address','Complete la dirección y la parroquia del origen y destino.');
        return ['country_id'=>'VE','location_id'=>$location,'address'=>$data['address'],'code'=>$data['code'] ?? '0000'];
    }
}
