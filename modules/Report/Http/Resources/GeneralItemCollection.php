<?php

// ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########

namespace Modules\Report\Http\Resources;

use App\Models\Tenant\Purchase;
use App\Models\Tenant\PurchaseItem;
use Illuminate\Http\Resources\Json\ResourceCollection;

class GeneralItemCollection extends ResourceCollection
{

    /**
     * Compra ya resuelta por item y fecha de venta. null = memo apagado.
     *
     * @var array|null
     */
    private static $purchase_price_cache = null;

    public function toArray($request)
    {

        $apply_conversion_to_pen = $request->apply_conversion_to_pen == 'true';

        // Memo del precio de compra solo mientras se arma esta pagina: filas del
        // mismo producto y fecha repetian las mismas consultas. Fuera de toArray
        // (vistas de excel/pdf en jobs) queda apagado, para no arrastrar precios
        // entre ejecuciones de un worker.
        self::$purchase_price_cache = [];

        $rows = $this->collection->transform(function ($row, $key) use($apply_conversion_to_pen){

            /** @var \App\Models\Tenant\DocumentItem|\App\Models\Tenant\PurchaseItem|mixed|\App\Models\Tenant\SaleNoteItem|mixed $row */
            $resource = self::getDocument($row);
            $purchase_item = null;
            $total_item_purchase = self::getPurchaseUnitPrice($row,$resource,$purchase_item);
            $quantity_unit = 0;
            if (property_exists($row, 'item') && property_exists($row->item, 'presentation')) {
                $quantity_unit= $row->item->presentation->quantity_unit;
                $total_item_purchase *= $quantity_unit;
            }


            $row_total = $row->total;
            $row_unit_value = $row->unit_value;
            $description_apply_conversion_to_pen = null;

            if($apply_conversion_to_pen && $row->isCurrencyTypeUsd())
            {
                $row_total = $row->getConvertTotalToPen();
                $row_unit_value = $row->getConvertUnitValueToPen();
                $description_apply_conversion_to_pen = 'Se aplicó conversión a bolívares';
            }

            $utility_item = $row_total - $total_item_purchase;
            // $utility_item = $row->total - $total_item_purchase;
            // relation_item y web_platform llegan precargados desde records():
            // getModelItem() y getWebPlatformModel() hacian un find por fila, y el
            // Item arrastra 7 relaciones por su $with.
            $item = $row->relation_item;
            $model = $item->model;
            $platform = $item->web_platform;
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
                'currency_type_id' => $resource['currency_type_id'],
                'lot_has_sale' => self::getLotsHasSale($row),
                'date_of_issue' => $resource['date_of_issue'],
                'customer_name' => $resource['customer_name'],
                'purchase_order' => $resource['purchase_order'],
                'customer_number' => $resource['customer_number'],
                'brand' => $row->relation_item->brand->name,
                'series' => $resource['series'],
                'alone_number' => $resource['alone_number'],
                'quantity' => number_format($row->quantity, 2),
                'unit_value' => number_format($row_unit_value, 2),
                // 'unit_value' => number_format($row->unit_value, 2),
                'total' => number_format($row_total, 2),
                // 'total' => number_format($row->total, 2),
                'total_number' => $row_total,
                // 'total_number' => $row->total,
                'total_item_purchase' => number_format($total_item_purchase, 2),
                'is_set' => (bool) $row->relation_item->is_set,
                'utility_item' => number_format($utility_item, 2),
                'factor' => ($quantity_unit!=0) ? number_format($quantity_unit, 2) : 0,
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

        self::$purchase_price_cache = null;

        return $rows;
    }

    public static function getPurchaseUnitPrice($record, $resource = null,&$purchase_item = null)
    {
        if($resource === null){
            $resource = self::getDocument($record);
        }
        $purchase_unit_price = self::getIndividualPurchaseUnitPrice($record,$resource,$purchase_item) * $record->quantity;
        if ($record->relation_item->is_set) {
            $purchase_unit_price = 0;
            foreach ($record->relation_item->sets as $item_set) {
                $purchase_unit_price += (self::getIndividualPurchaseUnitPrice($item_set,$resource,$purchase_item) * $item_set->quantity) * $record->quantity;
            }
        } /*elseif() {
        }*/

        return $purchase_unit_price;
    }

    public static function getIndividualPurchaseUnitPrice($record, $resource, &$purchase_item = null)
    {
        $purchase_unit_price = 0;
        $currency_type_id = $resource['currency_type_id'];
        // Se busca la compra del producto en el dia o antes de su venta,
        // para sacar la ganancia correctamente

        $cache_key = $record->item_id.'|'.$resource['date_of_issue'];

        if (self::$purchase_price_cache !== null && array_key_exists($cache_key, self::$purchase_price_cache)) {
            [$purchase_item, $purchase] = self::$purchase_price_cache[$cache_key];
        } else {
            // Una sola consulta: la ultima compra (por id) del producto en esa
            // fecha o antes y, dentro de ella, su ultimo renglon. Antes eran
            // cuatro: la primera traia todo el historial de compras del producto
            // y dos cargaban la compra completa con items, pagos y relaciones.
            $purchase_item = PurchaseItem::query()
                ->setEagerLoads([])
                ->select(
                    'purchase_items.*',
                    'purchases.currency_type_id as purchase_currency_type_id',
                    'purchases.exchange_rate_sale as purchase_exchange_rate_sale'
                )
                ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
                ->where('purchase_items.item_id', $record->item_id)
                ->where('purchases.date_of_issue', '<=', $resource['date_of_issue'])
                ->orderByDesc('purchases.id')
                ->orderByDesc('purchase_items.id')
                ->first();

            $purchase = $purchase_item ? (object) [
                'currency_type_id' => $purchase_item->purchase_currency_type_id,
                'exchange_rate_sale' => $purchase_item->purchase_exchange_rate_sale,
            ] : null;

            if (self::$purchase_price_cache !== null) {
                self::$purchase_price_cache[$cache_key] = [$purchase_item, $purchase];
            }
        }

        if ($purchase) {
            $purchase_unit_price = $purchase_item->unit_price;
            $exchange_rate_sale = $purchase->exchange_rate_sale * 1;
            // Si la venta es en bolívares, y la compra del producto es en dolares, se hace la transformcaion
            if ($currency_type_id === 'VES') {
                if ($purchase->currency_type_id !== $currency_type_id) {
                    $purchase_unit_price = $purchase_unit_price * $exchange_rate_sale;
                }
            } else {
                // Si la venta es en dolares, y la compra del producto es en bolívares, se hace la transformcaion
                if ($purchase->currency_type_id !== $currency_type_id && $exchange_rate_sale !== 0) {
                    $purchase_unit_price = $purchase_unit_price / $exchange_rate_sale;
                }
            }
        }
        // TODO: revisar esta linea: Eliminando esta linea porque el precio de compra no puede ser igual al precio de venta,
        // en conculusión esta condición nunca será 0, para los productos que no tienen una compra luego de registrarse
        // $purchase_unit_price = ($purchase_item) ? $purchase_item->unit_price : $record->unit_price;

        if ($purchase_unit_price == 0 && $record->relation_item->purchase_unit_price > 0) {
            $purchase_unit_price = $record->relation_item->purchase_unit_price;
        }


        // if ($record->relation_item->purchase_unit_price > 0) {
        //     $purchase_unit_price = $record->relation_item->purchase_unit_price;
        // } else {
        //     $purchase_item = PurchaseItem::select('unit_price')->where('item_id', $record->item_id)->latest('id')->first();
        //     $purchase_unit_price = ($purchase_item) ? $purchase_item->unit_price : $record->unit_price;
        // }
        return $purchase_unit_price;
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
        /*$data['quantity'] = number_format($row->quantity,2);
        $data['total'] = number_format($row->total,2);
        $data['unit_type_id'] = $row->item->unit_type_id;
        $data['description'] = $row->item->description;*/
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
