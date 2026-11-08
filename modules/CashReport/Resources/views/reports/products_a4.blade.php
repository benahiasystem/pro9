@extends('cashreport::layouts.a4')
@php

    $establishment = $cash->user->establishment

@endphp
<?php $title = 'Reporte de productos'; ?>

@section('styles')
table {
            width: 100%;
            border-spacing: 0;
            border: 1px solid black;
        }

        .celda {
            text-align: center;
            padding: 5px;
            border: 0.1px solid black;
        }

        th {
            padding: 5px;
            text-align: center;
            border-color: #000;
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
            font-size: 12px;
        }

        thead, thead tr th {
            font-weight: bold;
            background: #0088cc;
            color: white;
            text-align: center;
        }

        .td-custom {
            line-height: 0.1em;
        }

        .width-custom {
            width: 50%
        }
@endsection

@section('content')
{{-- ######## INICIO CLASIFICACION DE COBROS Y CAJA ######## --}}
@isset($classified) @include('cashreport::reports.partials.classified_balances') @endisset
{{-- ######## FIN CLASIFICACION DE COBROS Y CAJA ######## --}}
@if($documents->count())
    @php
        $currencySubtotals = \Modules\CashReport\Services\ProductsAmounts::subtotalsByOperation($documents);
        $displaySubtotals = \Modules\CashReport\Services\ProductsAmounts::display($documents, 'sub_total');
    @endphp
    @php
        $items_id=[];
        foreach ($documents as $item) {
            $validate=in_array($item['item_id'],$items_id);
            if (!$validate) {
                $items_id[]=$item['item_id'];
            }
        }
        
        
        $allTotal=0;
        //dd($items_id);
    @endphp
    @if ($is_garage)
        @include('cashreport::reports.partials.data_garage')
    @endif

    <div class="">
        <div class=" ">
            <table class="">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Producto</th>
                    <th>Operación</th>
                    <th>Moneda</th>
                    <th>Cantidad</th>
                    <th>Precio</th>
                    <th>Sub Total</th>
                    <th>Comprobante</th>
                </tr>
                </thead>
                <tbody>

                @foreach($documents as $item)
                    <tr>
                        <td class="celda">{{ $loop->iteration }}</td>
                        <td class="celda">{{ $item['description'] }}</td>
                        <td class="celda">{{ $item['record_type'] === 'purchase_item' ? 'Compra' : 'Venta' }}</td>
                        <td class="celda">{{ $item['currency_type_id'] ?: 'Sin moneda' }}</td>
                        <td class="celda">{{ $item['quantity'] }}</td>
                        <td class="celda"
                            style="text-align: right">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($item, 'unit_value')) }}</td>
                        <td class="celda"
                            style="text-align: right">{{ $displaySubtotals[$loop->index] }}</td>
                        <td class="celda">{{ $item['number_full'] }}</td>
                    </tr>
                @endforeach

                @foreach($currencySubtotals as $operation => $currencies)
                @foreach($currencies as $currency => $subtotal)
                <tr><td colspan="6" class="celda">{{ $operation }} {{ $currency }}</td><td class="celda" style="text-align: right">{{ \App\Support\Exact\Decimal::format($subtotal) }}</td><td class="celda"></td></tr>
                @endforeach
                @endforeach
                @if($documents->contains(fn($row) => !in_array($row['currency_type_id'], ['VES', 'USD'], true)))
                <tr><td colspan="8" class="celda">Las partidas sin moneda identificada no se incluyen en los totales.</td></tr>
                @endif
                </tbody>
            </table>
            <br>

            {{-- TOTALES --}}
            @if (!$is_garage)
                @include('cashreport::reports.partials.totals_sold_items')
            @endif
            
            {{-- <table class="">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Precio</th>
                    <th>Total</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($items_id as $item_id)
                    @php
                        $quantity_item=0;
                        $description='';
                        $unit_value=0;
                        $total_item=0;
                        $info='TOTAL CON IGV';
                    @endphp
                    <tr>
                    @foreach($documents as $item)
                    
                        @if ($item_id==$item['item_id'])
                            @php
                                $quantity_item+=$item['quantity'];
                                $description=$item['description'];
                                $unit_value=$item['unit_value'];
                                $total_item+=$item['total'];
                                
                            @endphp
                        @endif
                        @php
                            //dd($quantity_item);
                        @endphp
                        
                    @endforeach
                        <td class="celda">{{ $loop->iteration }}</td>
                            <td class="celda">{{ $description }}</td>
                            <td class="celda">{{ $quantity_item }}</td>
                            <td class="celda"
                                style="text-align: right">{{ App\CoreFacturalo\Helpers\Template\ReportHelper::setNumber($unit_value) }}</td>
                            <td class="celda"
                                style="text-align: right">{{ App\CoreFacturalo\Helpers\Template\ReportHelper::setNumber($total_item) }}</td>
                            <td >{{$info}}</td>
                        </tr>
                        @php
                            //$total+=$item['unit_value'];
                            $allTotal+=$total_item;
                        @endphp
                @endforeach
                
                <tr>
                    <td class="celda"></td>
                    <td class="celda"></td>
                    <td class="celda"></td>
                    <td class="celda"> Totales </td>
                    <td class="celda" style="text-align: right">
                        {{ App\CoreFacturalo\Helpers\Template\ReportHelper::setNumber($allTotal) }}
                    </td>
                    <td class="celda"></td>

                </tr>
                </tbody>
            </table> --}}
        </div>
    </div>
@else
    <div class="callout callout-info">
        <p>No se encontraron registros.</p>
    </div>
@endif
@endsection
