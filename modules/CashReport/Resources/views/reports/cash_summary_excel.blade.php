@extends('cashreport::layouts.excel')

<?php $title = 'Reporte de caja'; ?>

@php
// ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########
$currency_symbol = \App\Support\Venezuela\Localization::currencySymbol(null);
// ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
@endphp

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
        thead {
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
<div style="margin-top:20px; margin-bottom:20px;">
    <table>
        <tr>
            <td colspan="2" class="td-custom">
                <p><strong>Montos de operación: </strong></p>
            </td>
        </tr>
        <tr>
            <td class="td-custom">
                <p><strong>Saldo inicial: </strong>{{ $currency_symbol }} {{$data['cash_beginning_balance']}}</p>
            </td>
            <td class="td-custom">
                <p><strong>Ingreso: </strong>{{ $currency_symbol }} {{$data['cash_income']}} </p>
            </td>
        </tr>
        <tr>
            <td class="td-custom">
                <p><strong>Saldo final: </strong>{{ $currency_symbol }} {{$data['cash_final_balance']}} </p>
            </td>
            <td class="td-custom">
                <p><strong>Egreso: </strong>{{ $currency_symbol }} {{$data['cash_egress']}} </p>
            </td>
        </tr>

        <tr>
            <td class="td-custom">
                <p>
                    <strong>
                        Total caja:
                    </strong>
                    {{ $currency_symbol }} {{$data['total_cash_payment_method_type_01']}}
                    {{-- (Saldo inicial + Efectivo) --}}
                </p>
            </td>
            <td class="td-custom">
            </td>
        </tr>

        <tr>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
        </tr>
        <tr>
            <td class="td-custom">
                <p>
                    <strong> Notas de Débito: </strong> {{ $currency_symbol }} {{$data['nota_debito']}}
                </p>
            </td>
            <td class="td-custom">
                <p>
                    <strong>
                        Notas de Crédito:
                    </strong>
                    {{ $currency_symbol }} {{ $data['nota_credito'] }}
                </p>
            </td>
        </tr>
        <tr>
            <td class="td-custom">
                <p><strong>Por cobrar: </strong>{{ $currency_symbol }} {{$data['credit']}} </p>
            </td>
            <td class="td-custom">
                {{--<p><strong>Egreso: </strong>{{ $currency_symbol }} {{$data['cash_egress']}} </p>--}}
            </td>
        </tr>
    </table>
</div>
@if($data['cash_documents_total']>0)
    <div class="">
        <div class=" ">
            <table>
                <thead>
                <tr>
                    <th>#</th>
                    <th>Descripcion</th>
                    <th>Suma</th>
                </tr>
                </thead>
                <tbody>
                @foreach($data['methods_payment'] as $item)
                    <tr>
                        <td class="celda">
                            {{ $item['iteracion'] }}
                        </td>
                        <td class="celda">
                            {{ $item['name'] }}
                        </td>
                        <td class="celda">
                            {{ $item['sum'] }}
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <br>
            <table class="">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Tipo transacción</th>
                    <th>Tipo documento</th>
                    <th>Documento</th>
                    <th>Fecha emisión</th>
                    <th>Cliente/Proveedor</th>
                    <th>N° Documento</th>
                    <th>Moneda</th>
                    <th>Total</th>
                </tr>
                </thead>
                <tbody>
                @foreach($data['all_documents'] as $key => $value)
                    <tr>
                        <td class="celda">
                            {{ $loop->iteration }}{{--
                            <br> {!! $value['usado'] !!}  <br> <strong>{{$value['tipo']}}</strong> --}}
                        </td>
                        <td class="celda">
                            {{ $value['type_transaction'] }}
                        </td>
                        <td class="celda">
                            {{ $value['document_type_description'] }}
                        </td>
                        <td class="celda">
                            {{ $value['number'] }}
                        </td>
                        <td class="celda">
                            {{ $value['date_of_issue'] }}
                        </td>
                        <td class="celda">
                            {{ $value['customer_name'] }}
                        </td>
                        <td class="celda">
                            {{ $value['customer_number'] }}
                        </td>
                        <td class="celda">
                            {{ $value['currency_type_id'] }}
                        </td>
                        <td class="celda">
                            {{ $value['total_string'] }}
                        </td>
                    </tr>
                @endforeach
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
