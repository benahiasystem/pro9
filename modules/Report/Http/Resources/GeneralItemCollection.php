<?php
// ######## INICIO TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
// ######## FIN TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
// ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########

namespace Modules\Report\Http\Resources;

use App\Models\Tenant\PurchaseItem;
use Illuminate\Http\Resources\Json\ResourceCollection;

class GeneralItemCollection extends ResourceCollection
{

    public function toArray($request)
    {

        $apply_conversion_to_pen = filter_var($request->apply_conversion_to_pen, FILTER_VALIDATE_BOOLEAN);
        $first = $this->collection->first();
        if (!$first) return collect();
        $parent = $first instanceof \App\Models\Tenant\SaleNoteItem ? 'sale_note'
            : ($first instanceof PurchaseItem ? 'purchase' : 'document');
        $costs = \Modules\Report\Services\EstimatedPurchaseCost::forLines($this->collection, $parent, $apply_conversion_to_pen);

        return $this->collection->transform(function ($row, $key) use($apply_conversion_to_pen, $parent, $costs){

            /** @var \App\Models\Tenant\DocumentItem|\App\Models\Tenant\PurchaseItem|mixed|\App\Models\Tenant\SaleNoteItem|mixed $row */
            $resource = self::getDocument($row);
            $cost = $costs[spl_object_id($row)];
            $purchase_item = $cost['purchase_item'];
            $total_item_purchase = $cost['amount'];
            $document = $row->$parent;
            $currency = $resource['currency_type_id'];
            $quantity = \App\Support\Exact\Decimal::readRequired($row, 'quantity');
            $row_total = \App\Support\Exact\Decimal::readRequired($row, 'total');
            $row_unit_value = \App\Support\Exact\Decimal::readRequired($row, 'unit_value');
            $description_apply_conversion_to_pen = null;

            if($apply_conversion_to_pen && $currency === 'USD')
            {
                $row_total = \App\Support\Exact\Decimal::convert($row_total, 'USD', 'VES', $document->exchange_rate_sale);
                $row_unit_value = \App\Support\Exact\Decimal::convert($row_unit_value, 'USD', 'VES', $document->exchange_rate_sale);
                $currency = 'VES';
                $description_apply_conversion_to_pen = 'Se aplicó conversión a bolívares';
            }

            $utility_item = $row_total->minus($total_item_purchase);
            // $utility_item = $row->total - $total_item_purchase;

            $item = $row->getModelItem();
            $model = $item->model;
            $platform = $item->getWebPlatformModel();
            if($platform !== null){
                $platform = $platform->name;
            }
            $observation=null;
            $additional_information=null;
            if(get_class($row)== \App\Models\Tenant\PurchaseItem::class){
                /** @var \App\Models\Tenant\PurchaseItem $row */
                $purchase = $row->purchase;
                $observation=$purchase->observation;
            }
            if($resource['document_type_id']===80){
                $observation = $resource['observation']? $resource['observation'] : '';
            }
            if(get_class($row)== \App\Models\Tenant\Document::class){
                $additional_information=$resource['additional_information']?$resource['additional_information'][0] : '';
            }
            return [
                'id' => $row->id,
                'unit_type_id' => $row->item->unit_type_id,
                'internal_id' => $row->relation_item->internal_id,
                'description' => $row->item->description,
                'currency_type_id' => $currency,
                'original_currency_type_id' => $resource['currency_type_id'],
                'lot_has_sale' => self::getLotsHasSale($row),
                'date_of_issue' => $resource['date_of_issue'],
                'customer_name' => $resource['customer_name'],
                'purchase_order' => $resource['purchase_order'],
                'customer_number' => $resource['customer_number'],
                'brand' => $row->relation_item->brand->name,
                'series' => $resource['series'],
                'alone_number' => $resource['alone_number'],
                'quantity' => \App\Support\Exact\Decimal::represent($quantity),
                'unit_value' => \App\Support\Exact\Decimal::format($row_unit_value),
                'total' => \App\Support\Exact\Decimal::format($row_total),
                'total_number' => \App\Support\Exact\Decimal::represent($row_total),
                // 'total_number' => $row->total,
                'total_item_purchase' => \App\Support\Exact\Decimal::format($total_item_purchase),
                'is_set' => (bool) $row->relation_item->is_set,
                'utility_item' => \App\Support\Exact\Decimal::format($utility_item),
                'cost_estimate_source' => $cost['source'],
                'calculation_snapshot' => \App\Support\Exact\Decimal::snapshot(['quantity'=>$quantity,'unit_value'=>$row_unit_value,
                    'total'=>$row_total,'total_item_purchase'=>$total_item_purchase,'utility_item'=>$utility_item]),
                'factor' => 0,
                'document_type_description' => $resource['document_type_description'],
                'document_type_id' => $resource['document_type_id'],
                'web_platform_name' => optional($row->relation_item->web_platform)->name,
                'model' => $model,
                'platform' => $platform,
                // 'resource'=>$resource,
                'purchase_item'=>$purchase_item,
                'observation'=>$observation,
                'additional_information' => $additional_information,
                'description_apply_conversion_to_pen' => $description_apply_conversion_to_pen,
            ];
        });
    }

    public static function getLotsHasSale($row)
    {
        if (isset($row->item->lots)) {
            $class = get_class($row);
            if($class == 'App\Models\Tenant\PurchaseItem'){
                // para compras
                return collect($row->item->lots);
            }
            return collect($row->item->lots)->where('has_sale', 1);
        } else {
            return [];
        }
    }

    public static function getDocument($row)
    {

        $data = [];
        $data['purchase_order'] = null;

        if ($row->document) {
            /** @var \App\Models\Tenant\Document $document */
            $document = $row->document;
            $data['date_of_issue'] = $document->date_of_issue->format('Y-m-d');
            $data['customer_name'] = $document->customer->name;
            $data['customer_number'] = format_person_identity_document($document->customer);
            $data['series'] = $document->series;
            $data['alone_number'] = $document->number;
            $data['document_type_description'] = $document->document_type->description;
            $data['document_type_id'] = $document->document_type->id;
            $data['currency_type_id'] = $document->currency_type_id;
            $data['purchase_order'] = $document->purchase_order;
            $data['additional_information'] = $document->additional_information;

        } else if ($row->purchase) {
            /** @var \App\Models\Tenant\Purchase $document */
            $document = $row->purchase;
            $data['date_of_issue'] = $document->date_of_issue->format('Y-m-d');
            $data['customer_name'] = $document->supplier->name;
            $data['customer_number'] = format_person_identity_document($document->supplier);
            $data['series'] = $document->series;
            $data['alone_number'] = $document->number;
            $data['document_type_description'] = $document->document_type->description;
            $data['document_type_id'] = $document->document_type->id;
            $data['currency_type_id'] = $document->currency_type_id;

        } else if ($row->sale_note) {
            /** @var \App\Models\Tenant\SaleNote $document */
            $document = $row->sale_note;
            $data['date_of_issue'] = $document->date_of_issue->format('Y-m-d');
            $data['customer_name'] = $document->customer->name;
            $data['customer_number'] = format_person_identity_document($document->customer);
            $data['series'] = $document->series;
            $data['alone_number'] = $document->number;
            $data['document_type_description'] = 'NOTA DE VENTA';
            $data['document_type_id'] = 80;
            $data['currency_type_id'] = $document->currency_type_id;
            $data['purchase_order'] = $document->purchase_order;
            $data['observation'] = $document->observation;
        }

        return $data;
    }

}

// ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
