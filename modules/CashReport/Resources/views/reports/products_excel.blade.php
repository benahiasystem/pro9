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
{{-- ######## INICIO CLASIFICACION DE COBROS Y CAJA ######## --}}
@isset($classified) @include('cashreport::reports.partials.classified_balances') @endisset
{{-- ######## FIN CLASIFICACION DE COBROS Y CAJA ######## --}}
@if($documents->count())
            @php
                $currencySubtotals = \Modules\CashReport\Services\ProductsAmounts::subtotalsByOperation($documents);
                $displaySubtotals = \Modules\CashReport\Services\ProductsAmounts::display($documents, 'sub_total');
            @endphp
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
                                    <td data-type="s" class="celda" style="mso-number-format:'\@'">{{ $item['quantity'] }}</td>
                                    <td data-type="s" class="celda" style="mso-number-format:'\@'; text-align: right">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($item, 'unit_value'), 2, '.', '') }}</td>
                                    <td data-type="s" class="celda" style="mso-number-format:'\@'; text-align: right">{{ $displaySubtotals[$loop->index] }}</td>
                                    <td class="celda">{{ $item['number_full'] }}</td>
                                </tr>
                            @endforeach

                            @foreach($currencySubtotals as $operation => $currencies)
                            @foreach($currencies as $currency => $subtotal)
                            <tr><td colspan="6" class="celda">{{ $operation }} {{ $currency }}</td><td data-type="s" class="celda" style="mso-number-format:'\@'; text-align: right">{{ \App\Support\Exact\Decimal::format($subtotal, 2, '.', '') }}</td><td class="celda"></td></tr>
                            @endforeach
                            @endforeach
                            @if($documents->contains(fn($row) => !in_array($row['currency_type_id'], ['VES', 'USD'], true)))
                            <tr><td colspan="8" class="celda">Las partidas sin moneda identificada no se incluyen en los totales.</td></tr>
                            @endif
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
