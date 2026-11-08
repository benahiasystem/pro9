<?php
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type"
          content="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>REPORTE PRODUCTOS</title>
</head>
<body>
@php
    $costs = $costs ?? ($type === 'sale' ? \Modules\Report\Services\EstimatedPurchaseCost::forLines(
        $records, (string)$document_type_id === '80', filter_var($request_apply_conversion_to_pen, FILTER_VALIDATE_BOOLEAN)) : []);
@endphp
@if(!empty($records))
    <div>
        <div class=" ">
            <table>
                <thead>
                <tr>
                    @include('report::general_items.partials.report_excel_header',[
                                'document_type_id'=>$document_type_id,
                                'type'=>$type,
                            ])
                </tr>
                </thead>
                <tbody>
                @if($type == 'sale')
                    @if($document_type_id == '80')
                        @foreach($records as $key => $value)
                            <?php
                            if(isset($qty)) unset($qty);
                            /** @var \App\Models\Tenant\DocumentItem $value */
                            $series = '';
                            if (isset($value->item->lots)) {
                                $series_data = collect($value->item->lots)->where('has_sale', 1)->pluck('series')->toArray();
                                $series = implode(" - ", $series_data);
                            }
                            // $purchase_unit_price = 0;
                            // if($value->relation_item->purchase_unit_price > 0){
                            //     $purchase_unit_price = $value->relation_item->purchase_unit_price;
                            // }else{
                            //     $purchase_item = \App\Models\Tenant\PurchaseItem::select('unit_price')->where('item_id', $value->item_id)->latest('id')->first();
                            //     $purchase_unit_price = ($purchase_item) ? $purchase_item->unit_price : $value->unit_price;
                            // }
                            $cost = $costs[spl_object_id($value)];
                            /** @var \App\Models\Tenant\Item $item */
                            $item = $value->relation_item;
                            $model = $item->model;
                            $document = $value->sale_note;
                            $platform = $item->getWebPlatformModel();
                            if ($platform !== null) {
                                $platform = $platform->name;
                            }
                            $pack = $item->is_set ? $value->relation_item->sets : null;
                            ?>
                            @include('report::general_items.partials.report_excel_body_sale',[
                                      'document_type_id'=>$document_type_id,
                                      'document'=>$document,
                                      'type'=>$type,
                                      'value'=>$value,
                                      'key'=>$key,
                                      'item'=>$item,
                                  ])
                            @if($pack !== null)
                                @foreach($pack as $item_pack)
                                    <?php
                                    /** @var \App\Models\Tenant\ItemSet $item_pack */
                                    /** @var \App\Models\Tenant\Item $item */
                                    $item = $item_pack->relation_item;
                                    $qty = \App\Support\Exact\Decimal::readRequired($item_pack, 'quantity');
                                    ?>
                                    @include('report::general_items.partials.report_excel_body_sale',[
                                                                           'document_type_id'=>$document_type_id,
                                                                           'document'=>$document,
                                                                           'type'=>$type,
                                                                           'value'=>$value,
                                                                           'key'=>$key,
                                                                           'item'=>$item,
                                                                           'qty'=>$qty,
                                                                       ])

                                @endforeach
                            @endif
                        @endforeach
                    @else
                        @foreach($records as $key => $value)
                            <?php
                                if(isset($qty)) unset($qty);
                            /** @var \App\Models\Tenant\DocumentItem $value */
                            $series = '';
                            if (isset($value->item->lots)) {
                                $series_data = collect($value->item->lots)->where('has_sale', 1)->pluck('series')->toArray();
                                $series = implode(" - ", $series_data);
                            }
                            $cost = $costs[spl_object_id($value)];
                            $item = $value->relation_item;
                            $model = $item->model;
                            /** @var  \App\Models\Tenant\Document $document */
                            $document = $value->document;
                            $purchseOrder = $document->purchase_order;
                            $platform = $item->getWebPlatformModel();
                            if ($platform !== null) {
                                $platform = $platform->name;
                            }
                            $pack = $item->is_set ? $value->relation_item->sets : null;
                            $item = $value->item;
                            $stablihsment = \App\CoreFacturalo\Helpers\Template\ReportHelper::getLocationData($document);
                            ?>
                            @include('report::general_items.partials.report_excel_body_sale',
                                    [
                                        'document_type_id'=>$document_type_id,
                                        'document'=>$document,
                                        'type'=>$type,
                                        'value'=>$value,
                                        'key'=>$key,
                                        'item'=>$item,
                                        'stablihsment'=>$stablihsment,
                                    ])
                            @if($pack !== null)
                                @foreach($pack as $item_pack)
                                    <?php
                                    /** @var \App\Models\Tenant\ItemSet $item_pack */
                                    /** @var \App\Models\Tenant\Item $item */
                                    $item = $item_pack->relation_item;
                                    $qty = \App\Support\Exact\Decimal::readRequired($item_pack, 'quantity');
                                    // dd($item);
                                    ?>
                                    @include('report::general_items.partials.report_excel_body_sale',
                                                                       [
                                                                           'document_type_id'=>$document_type_id,
                                                                           'document'=>$document,
                                                                           'type'=>$type,
                                                                           'value'=>$value,
                                                                           'key'=>$key,
                                                                           'item'=>$item,
                                                                           'qty'=>$qty,
                                                                           'stablihsment'=>$stablihsment,
                                                                       ])
                                @endforeach
                            @endif
                        @endforeach
                    @endif
                @else
                    @foreach($records as  $value)
                        <?php
                        /** @var \App\Models\Tenant\SaleNoteItem  $value */
                        $purchase = $value->purchase;

                        ?>
                        @if($purchase !== null)
                            @include('report::general_items.partials.report_excel_body_purchase',
                                                                       [
                                                                           'document_type_id'=>$document_type_id,
                                                                           'value'=>$value,
                                                                           'purchase'=>$purchase,
                                                                       ])

                        @endif
                    @endforeach
                @endif
                </tbody>
            </table>
        </div>
    </div>
@else
    <div>
        <p>No se encontraron registros.</p>
    </div>
@endif
</body>
</html>
