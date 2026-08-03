<?php

namespace Modules\Inventory\Helpers;


use App\Models\Tenant\Item;
use App\Models\Tenant\{
    Document,
    DocumentItem,
    Dispatch,
    DispatchItem,
    Purchase,
    PurchaseItem,
    SaleNote,
    SaleNoteItem,
};
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Modules\Inventory\Models\Inventory;
use Modules\Inventory\Models\InventoryKardex as InventoryKardexModel;
use Modules\Inventory\Models\Warehouse;


class InventoryValuedKardex
{

    public static function getTransformRecords($records)
    {

        return $records->transform(function ($row, $key) {
            /** @var Item $row */
            return $row->getReportValuedKardexCollection();
            /*** Movido al modelo **/
            $values_records = self::getValuesRecords($row->document_items, $row->sale_note_items);

            $quantity_sale = $values_records['quantity_sale'];
            $total_sales = $values_records['total_sales'];

            $item_cost = $quantity_sale * $row->purchase_unit_price;
            $valued_unit = $total_sales - $item_cost;

            return [

                'id' => $row->id,
                'item_description' => $row->description,
                'category_description' => optional($row->category)->name,
                'brand_description' => optional($row->brand)->name,
                'unit_type_id' => $row->unit_type_id,
                'quantity_sale' => number_format($quantity_sale,2, ".", ""),
                'purchase_unit_price' => number_format($row->purchase_unit_price,2, ".", ""),
                'total_sales' => number_format($total_sales,2, ".", ""),
                'item_cost' => number_format($item_cost,2, ".", ""),
                'valued_unit' => number_format($valued_unit,2, ".", ""),
                'warehouses' => $row->warehouses->transform(function($row, $key){
                    return [
                        'id' => $row->id,
                        'stock' => $row->stock,
                        'warehouse_description' => $row->warehouse->description,
                        'description' => "{$row->warehouse->description} | {$row->stock}",
                    ];
                }),

            ];

        });

    }


    /**
     *
     * Cantidad de unidades de la presentación
     *
     * @param  DocumentItem|SaleNoteItem $row
     * @return float
     */
    public static function getQuantityUnitByPresentation($row)
    {
        $presentation = $row->item->presentation ?? [];

        return (!empty($presentation)) ? $presentation->quantity_unit : 1;
    }


    public static function getValuesRecords($document_items, $sale_note_items)
    {
        //quantity

        $quantity_doc_items = $document_items->sum(function($row){

            $quantity = ($row->document->document_type_id == '07') ? -$row->quantity : $row->quantity;

            return $quantity * self::getQuantityUnitByPresentation($row);

        });

        // $quantity_sln_items = $sale_note_items->sum('quantity');

        $quantity_sln_items = $sale_note_items->sum(function($row){
            return $row->quantity * self::getQuantityUnitByPresentation($row);
        });

        $quantity_sale = $quantity_doc_items + $quantity_sln_items;


        //totals
        $sales_documents = $document_items->sum(function($row){
            $value_currency = self::calculateTotalCurrencyType($row->document, $row->total);
            return ($row->document->document_type_id == '07') ? -$value_currency : $value_currency;
        });

        $sales_sale_notes = $sale_note_items->sum(function($row){
            return self::calculateTotalCurrencyType($row->sale_note, $row->total);
        });

        $total_sales = $sales_documents + $sales_sale_notes;


        return [
            'quantity_sale' => $quantity_sale,
            'total_sales' => $total_sales,
        ];

    }


    public static function calculateTotalCurrencyType($record, $amount)
    {
        return ($record->currency_type_id === 'USD') ? $amount * $record->exchange_rate_sale : $amount;
    }


    public static function getDataFormatSunat($params)
    {
        $item = Item::findOrFail($params->item_id);

        $openingBalance = self::getOpeningBalance($params, $item);
        $all_record_items = self::getPeriodMovementItems($params, $item);
        $records = self::getRecordsFromItems($all_record_items, $openingBalance);

        if (($openingBalance['balance_quantity'] ?? 0) != 0) {
            array_unshift($records, self::buildOpeningBalanceRow($openingBalance, $params));
        }

        return [
            'item' => $item,
            'records' => $records
        ];
    }

    /**
     * Saldo inicial: movimientos antes del periodo + stock inicial/ajustes del periodo.
     */
    private static function getOpeningBalance($params, Item $item)
    {
        $emptyBalance = [
            'balance_quantity' => 0,
            'balance_unit_cost' => 0,
            'balance_total_cost' => 0,
        ];

        if (empty($params->date_start)) {
            return $emptyBalance;
        }

        $openingEndDate = Carbon::parse($params->date_start)->subDay()->format('Y-m-d');

        if ($openingEndDate >= '1900-01-01') {
            $beforeParams = (object) [
                'item_id' => $params->item_id,
                'establishment_id' => $params->establishment_id ?? null,
                'date_start' => '1900-01-01',
                'date_end' => $openingEndDate,
            ];

            $beforeItems = self::getKardexRecordItems($beforeParams, $item, false);
            $beforeRecords = self::getRecordsFromItems($beforeItems);

            if (!empty($beforeRecords)) {
                $last = end($beforeRecords);

                return [
                    'balance_quantity' => $last['balance_quantity'] ?? 0,
                    'balance_unit_cost' => $last['balance_unit_cost'] ?? 0,
                    'balance_total_cost' => $last['balance_total_cost'] ?? 0,
                ];
            }
        }

        $inPeriodInventoryItems = self::getInPeriodInventoryOpeningItems($params, $item);

        if ($inPeriodInventoryItems->isEmpty()) {
            return $emptyBalance;
        }

        $firstInventory = $inPeriodInventoryItems->first();
        $issueDate = $firstInventory->date_of_issue ?? $firstInventory->created_at;

        $records = self::getRecordsFromItems($inPeriodInventoryItems);

        if (empty($records)) {
            return $emptyBalance;
        }

        $last = end($records);

        return [
            'balance_quantity' => $last['balance_quantity'] ?? 0,
            'balance_unit_cost' => $last['balance_unit_cost'] ?? 0,
            'balance_total_cost' => $last['balance_total_cost'] ?? 0,
            'date_of_issue' => Carbon::parse($issueDate)->format('d-m-Y'),
        ];
    }

    /**
     * Movimientos del periodo excluyendo stock inicial/ajustes (ya van en saldo inicial).
     */
    private static function getPeriodMovementItems($params, Item $item)
    {
        return self::getKardexRecordItems($params, $item, true);
    }

    private static function getInPeriodInventoryOpeningItems($params, Item $item)
    {
        $items = collect();

        foreach (self::getInventoryKardexQuery($params, $item)->get() as $kardex) {
            if ($kardex->inventory_kardexable_type !== Inventory::class) {
                continue;
            }

            $inventory = $kardex->inventory_kardexable;

            if (!$inventory || !self::inventoryAppliesToValuedKardex($inventory, $kardex)) {
                continue;
            }

            $inventory->valued_kardex_sort_id = $kardex->id;
            $items->push($inventory);
        }

        return $items;
    }

    private static function getKardexRecordItems($params, Item $item, $excludeInventoryOpening = false)
    {
        $items = collect();

        foreach (self::getInventoryKardexQuery($params, $item)->get() as $kardex) {
            if ($excludeInventoryOpening && $kardex->inventory_kardexable_type === Inventory::class) {
                continue;
            }

            $recordItem = self::resolveRecordItemFromKardex($kardex, $item);

            if ($recordItem) {
                $recordItem->valued_kardex_sort_id = $kardex->id;
                $items->push($recordItem);
            }
        }

        return $items;
    }

    private static function getInventoryKardexQuery($params, Item $item)
    {
        $query = InventoryKardexModel::query()
            ->where('item_id', $item->id)
            ->whereBetween('date_of_issue', [$params->date_start, $params->date_end])
            ->orderBy('date_of_issue')
            ->orderBy('id');

        if (!empty($params->establishment_id)) {
            $warehouseIds = Warehouse::where('establishment_id', $params->establishment_id)->pluck('id');
            $query->whereIn('warehouse_id', $warehouseIds);
        }

        return $query->with('inventory_kardexable');
    }

    private static function resolveRecordItemFromKardex($kardex, Item $item)
    {
        $related = $kardex->inventory_kardexable;

        if (!$related) {
            return null;
        }

        switch ($kardex->inventory_kardexable_type) {
            case Document::class:
                if (!self::documentAppliesToValuedKardex($related) || self::documentKardexShouldBeSkipped($related)) {
                    return null;
                }

                return $related->items()->where('item_id', $item->id)->first();

            case Purchase::class:
                if (!self::purchaseAppliesToValuedKardex($related)) {
                    return null;
                }

                return $related->items()->where('item_id', $item->id)->first();

            case SaleNote::class:
                if (!self::saleNoteAppliesToValuedKardex($related)) {
                    return null;
                }

                return $related->items()->where('item_id', $item->id)->first();

            case Dispatch::class:
                if (!self::dispatchAppliesToValuedKardex($related) || self::dispatchKardexShouldBeSkipped($related)) {
                    return null;
                }

                return $related->items()->where('item_id', $item->id)->first();

            case Inventory::class:
                if (!self::inventoryAppliesToValuedKardex($related, $kardex)) {
                    return null;
                }

                return $related;

            default:
                return null;
        }
    }

    private static function documentAppliesToValuedKardex($document)
    {
        return Document::where('id', $document->id)
            ->whereStateTypeAccepted()
            ->whereTypeUser()
            ->whereNull('sale_note_id')
            ->whereNull('order_note_id')
            ->whereNull('dispatch_id')
            ->where(function ($q) {
                $q->whereNull('sale_notes_relateds')
                    ->orWhere('sale_notes_relateds', '[]');
            })
            ->exists();
    }

    private static function documentKardexShouldBeSkipped($document)
    {
        if ($document->sale_note_id || $document->order_note_id) {
            return true;
        }

        if (!empty($document->sale_notes_relateds) && $document->sale_notes_relateds !== '[]') {
            return true;
        }

        if ($document->dispatch && optional($document->dispatch->transfer_reason_type)->discount_stock) {
            return true;
        }

        return false;
    }

    private static function purchaseAppliesToValuedKardex($purchase)
    {
        return Purchase::where('id', $purchase->id)
            ->whereStateTypeAccepted()
            ->whereTypeUser()
            ->exists();
    }

    private static function saleNoteAppliesToValuedKardex($saleNote)
    {
        return SaleNote::where('id', $saleNote->id)
            ->whereStateTypeAccepted()
            ->whereTypeUser()
            ->exists();
    }

    private static function dispatchAppliesToValuedKardex($dispatch)
    {
        return Dispatch::where('id', $dispatch->id)
            ->whereIn('transfer_reason_type_id', ['01', '02', '04', '13'])
            ->whereStateTypeAccepted()
            ->whereTypeUser()
            ->exists();
    }

    private static function dispatchKardexShouldBeSkipped($dispatch)
    {
        return $dispatch->reference_sale_note_id
            || $dispatch->reference_order_note_id
            || $dispatch->reference_document_id;
    }

    private static function inventoryAppliesToValuedKardex($inventory, $kardex)
    {
        if (!empty($inventory->warehouse_destination_id)) {
            return false;
        }

        return $kardex->quantity != 0;
    }

    private static function buildOpeningBalanceRow($openingBalance, $params)
    {
        return [
            'date_of_issue' => $openingBalance['date_of_issue'] ?? Carbon::parse($params->date_start)->format('d-m-Y'),
            'document_type_id' => '00',
            'series' => '',
            'number' => '',
            'operation_type' => 'STOCK INICIAL',
            'operation_type_code' => '',
            'type' => 'opening',
            'input_quantity' => $openingBalance['balance_quantity'],
            'input_unit_price' => $openingBalance['balance_unit_cost'],
            'input_total' => $openingBalance['balance_total_cost'],
            'output_quantity' => null,
            'output_unit_price' => null,
            'output_total' => null,
            'balance_quantity' => $openingBalance['balance_quantity'],
            'balance_unit_cost' => $openingBalance['balance_unit_cost'],
            'balance_total_cost' => $openingBalance['balance_total_cost'],
            'factor' => 0,
            'quantity' => 0,
            'total' => 0,
        ];
    }

    
    /**
     * 
     * Obtener coleccion con todos los registros
     *
     * @param  Collection $document_items
     * @param  Collection $purchase_items
     * @param  Collection $dispatch_items
     * @param  Collection $sale_note_items
     * @return Collection
     */
    public static function getAllRecordItems($document_items, $purchase_items, $dispatch_items, $sale_note_items = null)
    {
        $all_items = collect()->merge($document_items);

        $purchase_items->each(function($purchase) use($all_items){
            $all_items->push($purchase);
        });
        
        $dispatch_items->each(function($dispatch) use($all_items){
            $all_items->push($dispatch);
        });

        if ($sale_note_items) {
            $sale_note_items->each(function($sale_note_item) use($all_items){
                $all_items->push($sale_note_item);
            });
        }
        
        return $all_items;
    }


    public static function getDataAdditional($request, $params, $item)
    {

        $data = [];
        $data['internal_id'] = $item->internal_id;
        $data['table_five'] = '01';
        $data['description'] = $item->description;
        $data['unit_type_table_six'] = $item->findUnitTypeCodeTableSix();

        // dd($request->all(), $params, $item);
        if($request->period == 'month'){

            $data['period'] = Carbon::parse($request->month_end)->format('Y');
            $data['month'] = Carbon::parse($request->month_start)->format('m');

        }else{

            $data['period'] = "{$params->date_start} - {$params->date_end}";
            $data['month'] = null;

        }

        return $data;

    }

    /**
     * Retorna arreglo ordenado que contiene informacion de los documentos asociados al item para poder realizar calculos en el reporte
     */
    private static function transformItems($collection)
    {
        return $collection->map(function($row, $key){
                    return self::getTempData($row);
                })
                ->sortBy(function ($row) {
                    $timestamp = $row['sort_date_of_issue'] instanceof Carbon
                        ? $row['sort_date_of_issue']->timestamp
                        : 0;

                    return sprintf('%010d-%010d', $timestamp, $row['sort_kardex_id'] ?? 0);
                })
                ->values()
                ->all();
    }

    private static function getRecordsFromItems($collection, $openingBalance = null)
    {
        $new_collection = self::transformItems($collection);

        $data = [];
        $balance_quantity = $openingBalance['balance_quantity'] ?? 0;
        $balance_total_cost = $openingBalance['balance_total_cost'] ?? 0;
        $balance_unit_cost = $openingBalance['balance_unit_cost'] ?? 0;

        foreach ($new_collection as $key => $temp_data) {

            if($temp_data['model_type'] == 'document' && $temp_data['document_type_id'] == '07'){

                $affected_document = collect($data)->first(function($row) use($temp_data){
                    return $row['model_type'] == 'document' && in_array($row['document_type_id'], ['01', '03']) && $row['id'] === $temp_data['affected_document_id'];
                });

                $temp_data['input_unit_price'] = $affected_document['output_unit_price'];
                $temp_data['input_total'] = $temp_data['input_unit_price'] * $temp_data['input_quantity'];
                $temp_data['total'] = $temp_data['input_unit_price'] * $temp_data['input_quantity'];
            }

            if ($temp_data['type'] == 'output') {
                $previous_balance_unit_cost = isset($data[$key - 1])
                    ? $data[$key - 1]['balance_unit_cost']
                    : $balance_unit_cost;

                if ($previous_balance_unit_cost) {
                    $temp_data['output_unit_price'] = $previous_balance_unit_cost;
                    $temp_data['output_total'] = round($temp_data['output_unit_price'] * $temp_data['output_quantity'], 2);
                }
            }

            $balance_quantity += $temp_data['quantity'] * $temp_data['factor'];

            if ($temp_data['type'] == 'input') {
                $balance_total_cost += $temp_data['total'] * $temp_data['factor'];
            } else {
                $balance_total_cost += ($temp_data['output_total'] ?? 0) * $temp_data['factor'];
            }

            $balance_unit_cost = ($balance_quantity != 0)
                ? round($balance_total_cost / $balance_quantity, 4)
                : null;

            $temp_data['balance_quantity'] = $balance_quantity;
            $temp_data['balance_unit_cost'] = $balance_unit_cost;
            $temp_data['balance_total_cost'] = round($balance_total_cost, 2);

            $data[$key] = $temp_data;

        }

        return $data;

    }
    

    /**
     * 
     * Obtener fecha para ordenar documentos
     *
     * @param  $document
     * @return Carbon
     */
    public static function getDateForSort($document)
    {
        $date_of_issue = $document->date_of_issue ?? null;
        $time_of_issue = $document->time_of_issue ?? null;

        if($date_of_issue && $time_of_issue)
        {
            $date_format = $document->date_of_issue->format('Y-m-d').' '.$document->time_of_issue;

            return Carbon::parse($date_format);
        }

        if ($date_of_issue) {
            return Carbon::parse($date_of_issue->format('Y-m-d'));
        }

        return null;
    }


    private static function getTempData($record_item)
    {

        $temp_data = [];

        if($record_item instanceof DocumentItem){

            $document = $record_item->document;
            $affected_document_id = null;

            if($document->document_type_id == '07'){
                $affected_document_id = $document->note->affected_document_id;
                $type = 'input';
            }else{
                $type ='output';
            }

            $input_quantity = null;
            $input_unit_price = null;
            $input_total = null;
            $output_quantity = null;
            $output_unit_price = null;
            $output_total = null;
            $operation_type = null;

            if($type == 'input'){

                $input_quantity =  $record_item->quantity;
                $input_unit_price =  $record_item->unit_value;
                $input_total = $record_item->total_value;
                $operation_type = 'DEVOLUCIÓN';
                $operation_type_code = '05';
                $factor = 1;

            }else{

                $output_quantity =  $record_item->quantity;
                $output_unit_price =  $record_item->unit_value;
                $output_total =  $record_item->total_value;
                $operation_type = 'VENTA';
                $factor = -1;
                $operation_type_code = '01';

            }
            // dd($document);

            $temp_data = [
                'id' => $document->id,
                'type' => $type,
                // 'type' => 'output',
                'model_type' => 'document',
                'date_of_issue' => $document->date_of_issue->format('d-m-Y'),
                'sort_date_of_issue' => self::getDateForSort($document),
                'time_of_issue' => $document->time_of_issue,
                'document_type_id' => $document->document_type_id,
                'series' => $document->series,
                'number' => $document->number,
                'operation_type' => $operation_type,
                'operation_type_code' => $operation_type_code,

                'input_quantity' => $input_quantity,
                'input_unit_price' => $input_unit_price,
                'input_total' => $input_total,

                'output_quantity' => $output_quantity,
                'output_unit_price' => $output_unit_price,
                'output_total' => $output_total,

                'factor' => $factor,
                'quantity' => $record_item->quantity,
                'total' => $record_item->total_value,

                'balance_quantity' => 0,
                'balance_unit_cost' => 0,
                'balance_total_cost' => 0,

                'affected_document_id' => $affected_document_id,
            ];

        }else if($record_item instanceof PurchaseItem){

            $document = $record_item->purchase;

            $temp_data = [
                'id' => $document->id,
                'type' => 'input',
                'model_type' => 'purchase',
                'date_of_issue' => $document->date_of_issue->format('d-m-Y'),
                'sort_date_of_issue' => self::getDateForSort($document),
                'time_of_issue' => $document->time_of_issue,
                'document_type_id' => $document->document_type_id,
                'series' => $document->series,
                'number' => $document->number,
                'operation_type' => 'COMPRA',
                'operation_type_code' => '02',

                'input_quantity' => $record_item->quantity,
                'input_unit_price' => $record_item->unit_value,
                'input_total' => $record_item->total_value,

                'output_quantity' => null,
                'output_unit_price' => null,
                'output_total' => null,

                'factor' => 1,
                'quantity' => $record_item->quantity,
                'total' => $record_item->total_value,

                'balance_quantity' => 0,
                'balance_unit_cost' => 0,
                'balance_total_cost' => 0,
                'affected_document_id' => null,
            ];

        }else if($record_item instanceof SaleNoteItem){

            $document = $record_item->sale_note;

            $temp_data = [
                'id' => $document->id,
                'type' => 'output',
                'model_type' => 'sale_note',
                'date_of_issue' => $document->date_of_issue->format('d-m-Y'),
                'sort_date_of_issue' => self::getDateForSort($document),
                'time_of_issue' => $document->time_of_issue,
                'document_type_id' => '80',
                'series' => $document->series,
                'number' => $document->number,
                'operation_type' => 'VENTA',
                'operation_type_code' => '01',

                'input_quantity' => null,
                'input_unit_price' => null,
                'input_total' => null,

                'output_quantity' => $record_item->quantity,
                'output_unit_price' => $record_item->unit_value,
                'output_total' => $record_item->total_value,

                'factor' => -1,
                'quantity' => $record_item->quantity,
                'total' => $record_item->total_value,

                'balance_quantity' => 0,
                'balance_unit_cost' => 0,
                'balance_total_cost' => 0,
                'affected_document_id' => null,
            ];

        }else if($record_item instanceof DispatchItem){


            $type = (in_array($record_item->dispatch->transfer_reason_type_id, ['01', '04', '13'])) ? 'output' : 'input';

            // $type = ($record_item->dispatch->transfer_reason_type_id == '01') ? 'output' : 'input';
            $document = $record_item->dispatch;

            $input_quantity = null;
            $input_unit_price = null;
            $input_total = null;
            $output_quantity = null;
            $output_unit_price = null;
            $output_total = null;
            $operation_type = null;

            if($type == 'input'){

                $input_quantity =  $record_item->quantity;
                $input_unit_price =  $record_item->relation_item->purchase_unit_value;
                $input_total = $record_item->quantity * $record_item->relation_item->purchase_unit_value;
                $operation_type = 'COMPRA';
                $operation_type_code = $record_item->dispatch->transfer_reason_type_id;
                $factor = 1;

            }else{

                $output_quantity =  $record_item->quantity;
                $output_unit_price =  $record_item->relation_item->sale_unit_value;
                $output_total =  $record_item->quantity * $record_item->relation_item->sale_unit_value;

                $operation_type = null;
                $operation_type_code = null;

                if($document->transfer_reason_type_id == '04'){
                    $operation_type = $document->transfer_reason_type->description;
                    $operation_type_code = '11';

                }elseif($document->transfer_reason_type_id == '13'){
                    $operation_type = $document->transfer_reason_description ?? $document->transfer_reason_type->description;
                    $operation_type_code = '99';

                }else{
                    $operation_type = 'VENTA';
                    $operation_type_code = $record_item->dispatch->transfer_reason_type_id;
                }

                $factor = -1;

            }
            // dd($document);
            $temp_data = [
                'id' => $document->id,
                'type' => $type,
                'model_type' => 'dispatch',
                'date_of_issue' => $document->date_of_issue->format('d-m-Y'),
                'sort_date_of_issue' => self::getDateForSort($document),
                'time_of_issue' => $document->time_of_issue,
                'document_type_id' => $document->document_type_id,
                'series' => $document->series,
                'number' => $document->number,
                'operation_type' => $operation_type,
                'operation_type_code' => $operation_type_code,
                // 'operation_type_code' => $record_item->dispatch->transfer_reason_type_id,
                'input_quantity' =>  $input_quantity,
                'input_unit_price' => $input_unit_price,
                'input_total' => $input_total,

                'output_quantity' => $output_quantity,
                'output_unit_price' => $output_unit_price,
                'output_total' => $output_total,

                'factor' => $factor,
                'quantity' => $record_item->quantity,
                'total' => $output_total ?? $input_total,

                'balance_quantity' => 0,
                'balance_unit_cost' => 0,
                'balance_total_cost' => 0,
                'affected_document_id' => null,

            ];

        }else if($record_item instanceof Inventory){

            $movementType = self::getInventoryMovementType($record_item);
            $quantity = abs($record_item->quantity);
            $unit_price = $record_item->item->purchase_unit_price ?? 0;
            $total_value = round($unit_price * $quantity, 2);
            $issueDate = $record_item->date_of_issue ?? $record_item->created_at;

            $temp_data = [
                'id' => $record_item->id,
                'type' => $movementType,
                'model_type' => 'inventory',
                'date_of_issue' => Carbon::parse($issueDate)->format('d-m-Y'),
                'sort_date_of_issue' => Carbon::parse($issueDate),
                'time_of_issue' => null,
                'document_type_id' => '00',
                'series' => '',
                'number' => '',
                'operation_type' => strtoupper($record_item->description ?: 'AJUSTE DE INVENTARIO'),
                'operation_type_code' => '16',
                'input_quantity' => $movementType === 'input' ? $quantity : null,
                'input_unit_price' => $movementType === 'input' ? $unit_price : null,
                'input_total' => $movementType === 'input' ? $total_value : null,
                'output_quantity' => $movementType === 'output' ? $quantity : null,
                'output_unit_price' => $movementType === 'output' ? $unit_price : null,
                'output_total' => $movementType === 'output' ? $total_value : null,
                'factor' => $movementType === 'input' ? 1 : -1,
                'quantity' => $quantity,
                'total' => $total_value,
                'balance_quantity' => 0,
                'balance_unit_cost' => 0,
                'balance_total_cost' => 0,
                'affected_document_id' => null,
            ];

        }

        return self::appendSortMetadata($temp_data, $record_item);
    }

    private static function getInventoryMovementType(Inventory $inventory)
    {
        if ((string) $inventory->type === '1') {
            return 'input';
        }

        if (in_array((string) $inventory->type, ['2', '3'], true)) {
            return 'output';
        }

        if ($inventory->inventory_transaction_id && $inventory->transaction) {
            return $inventory->transaction->type === 'input' ? 'input' : 'output';
        }

        return $inventory->quantity >= 0 ? 'input' : 'output';
    }

    private static function appendSortMetadata(array $temp_data, $record_item)
    {
        $temp_data['sort_kardex_id'] = $record_item->valued_kardex_sort_id ?? 0;

        if (empty($temp_data['sort_date_of_issue'])) {
            $temp_data['sort_date_of_issue'] = Carbon::parse('1900-01-01');
        }

        return $temp_data;
    }

}
