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
    <style>
        html {
            font-family: sans-serif;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-spacing: 0;
            border: 1px solid black;
        }
        .celda {
            text-align: center;
            padding: 5px;
            border: 0.1px solid black;
            font-size: 9px;
            word-wrap: break-word;
        }
        th {
            padding: 5px;
            font-size: 8px;
            word-wrap: break-word;
            text-align: center;
            border-color: #0088cc;
            border: 0.1px solid black;
        }
        .title {
            font-weight: bold;
            padding: 5px;
            font-size: 20px !important;
            text-decoration: underline;
        }
        p > strong {
            margin-left: 5px;
            font-size: 13px;
        }
        thead {
            font-weight: bold;
            background: #0088cc;
            color: white;
            text-align: center;
        }
        @page {
            margin: 6px;
        }
    </style>
</head>
<body>
@php
    $costs = $type === 'sale' ? \Modules\Report\Services\EstimatedPurchaseCost::forLines(
        $records, (string)$document_type_id === '80', filter_var($request_apply_conversion_to_pen, FILTER_VALIDATE_BOOLEAN)) : [];
@endphp
@if(!empty($records))
    <div class="">
        <div class=" ">
            <table class="" style="table-layout:fixed;">
                <thead>
                <?php
                $plus = 4;
                if ($document_type_id != '80' && $type == 'sale') {
                    // para añadir dpto, prov y dist se le resta 1 al width de 9 elementos
                    $plus = 3;
                }
                ?>
                <tr width="100%">
                    @include('report::general_items.partials.report_pdf_header',[
                                                  'document_type_id'=>$document_type_id,
                                                  'type'=>$type,
                                                  'plus'=>$plus,
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
                            @include('report::general_items.partials.report_pdf_body_sale',[
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
                                    @include('report::general_items.partials.report_pdf_body_sale',[
                                                                           'document_type_id'=>$document_type_id,
                                                                          'document'=>$document,
                                                                          'type'=>$type,
                                                                          'value'=>$value,
                                                                          'key'=>$key,
                                                                          'item'=>$item,
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
                            @include('report::general_items.partials.report_pdf_body_sale',[
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
                                    @include('report::general_items.partials.report_pdf_body_sale',
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
                    @foreach($records as $key => $value)

                        <?php

                        /**@var \App\Models\Tenant\PurchaseItem $value */
                        /** @var  \App\Models\Tenant\Purchase $document */
                        $document = $value->purchase;
                        ?>
                        @if ($document)
                            @include('report::general_items.partials.report_pdf_body_purchase',
                                                            [
                                                                'value'=>$value,
                                                                'document'=>$document,

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
