@extends('cashreport::layouts.excel')
@php

$establishment = $cash->user->establishment;
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

            p>strong {
                margin-left: 5px;
                font-size: 12px;
            }

            thead, thead tr th {
                font-weight: bold;
                background: #0088cc;
                color: white;
                text-align: center;
            }
            .td-custom { line-height: 0.1em; }
            .width-custom { width: 50% }
@endsection

@section('content')
@if($documents->count())
            @php
                $total = 0;
                $subTotal = 0;
            @endphp
            <div class="">
                <div class=" ">
                    <table class="">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Producto</th>
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
                                    <td class="celda">{{ $item['quantity'] }}</td>
                                    <td class="celda" style="text-align: right">{{ App\CoreFacturalo\Helpers\Template\ReportHelper::setNumber($item['unit_value']) }}</td>
                                    <td class="celda" style="text-align: right">{{ App\CoreFacturalo\Helpers\Template\ReportHelper::setNumber($item['sub_total']) }}</td>
                                    <td class="celda">{{ $item['number_full'] }}</td>
                                </tr>
                                @php
                                    $total+=$item['unit_value'];
                                    $subTotal+=$item['sub_total']
                                @endphp
                            @endforeach

                            <tr>
                                <td class="celda"></td>
                                <td class="celda"></td>
                                <td class="celda"></td>
                                <td class="celda"> Totales </td>
                                <td class="celda" style="text-align: right">
                                    {{ App\CoreFacturalo\Helpers\Template\ReportHelper::setNumber($subTotal) }}
                                </td>
                                <td class="celda"></td>

                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="callout callout-info">
                <p>No se encontraron registros.</p>
            </div>
        @endif
@endsection
