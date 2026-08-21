@extends('cashreport::layouts.a4')
<?php use App\CoreFacturalo\Helpers\Functions\GeneralPdfHelper; ?>

<?php $title = 'Reporte general con pagos'; ?>

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
            border: 0.1px solid #000;
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

        thead tr th {
            font-weight: bold;
            background: #0088cc;
            color: white;
            text-align: center;
        }

        .width-custom {
            width: 50%
        }
@endsection

@section('content')
<div style="margin-top:10px; margin-bottom:20px;">
    <table class="report-box">
        <tr>
            <td colspan="2" class="td-custom">
                <p>
                    <strong>
                        Montos de operación:
                    </strong>
                </p>
            </td>
        </tr>
        <br>
        <tr>
            <td class="td-custom">
                <p>
                    <strong>
                        Total Ingresos:
                    </strong>
                    S/ {{ GeneralPdfHelper::setNumberFormat($data['total_income']) }}
                </p>
            </td>
            <td class="td-custom">
                <p>
                    <strong>
                        Total Egresos:
                    </strong>
                    S/ {{ GeneralPdfHelper::setNumberFormat($data['total_egress']) }}
                </p>
            </td>
        </tr>
        <tr>
            <td class="td-custom">
                <p>
                    <strong>
                        Saldo inicial:
                    </strong>
                    S/ {{ GeneralPdfHelper::setNumberFormat($data['cash_beginning_balance']) }}
                </p>
            </td>
            <td class="td-custom">
                <p>
                    <strong>
                        Saldo final:
                    </strong>
                    S/ {{ GeneralPdfHelper::setNumberFormat($data['total_balance']) }}
                </p>
            </td>
        </tr>

    </table>
</div>
@if(count($group_payments) > 0)
    <div class="">
        <div class=" ">

            @foreach($group_payments as $payment_method_type_id => $payments)

            <table>
                <thead>
                    <tr>
                        <th>
                            <h2>{{ GeneralPdfHelper::getPaymentMethodTypeDescription($payment_method_type_id) }}</h2>
                        </th>
                    </tr>
                </thead>
            </table>

            <table>
                <thead>
                <tr>
                    {{-- <th>
                        #
                    </th> --}}
                    <th>
                        Tipo transacción
                    </th>
                    <th>
                        Tipo documento
                    </th>
                    <th>
                        Documento
                    </th>
                    <th>
                        Fecha emisión
                    </th>
                    <th>
                        Cliente/Proveedor
                    </th>
                    <th>
                        N° Documento
                    </th>
                    <th>
                        Moneda
                    </th>
                    <th>
                        Total
                    </th>
                </tr>
                </thead>
                <tbody>
                    @foreach($payments as $key => $value)
                        <tr>
                            {{-- <td class="celda">
                                {{ $loop->iteration }}
                            </td> --}}
                            <td class="celda">
                                {{ $value['type_transaction_description'] }}
                            </td>
                            <td class="celda">
                                {{ $value['document_type_description'] }}
                            </td>
                            <td class="celda">
                                {{ $value['number_full'] }}
                            </td>
                            <td class="celda">
                                {{ $value['date_of_issue'] }}
                            </td>
                            <td class="celda">
                                {{ $value['acquirer_name'] }}
                            </td>
                            <td class="celda">
                                {{ $value['acquirer_number'] }}
                            </td>
                            <td class="celda">
                                {{ $value['currency_type_id'] }}
                            </td>
                            <td class="celda">
                               {{$value['type_transaction'] == 'egress' ? '-' : ''}}{{ GeneralPdfHelper::setNumberFormat($value['payment']) }}
                            </td>
                        </tr>
                    @endforeach

                    {{-- para pago en efectivo se agrega gastos --}}
                    @if ($payment_method_type_id === '01') 
                        @foreach($expense_payments as $key => $value)
                            <tr>
                                {{-- <td class="celda">
                                    {{ $loop->iteration }}
                                </td> --}}
                                <td class="celda">
                                    {{ $value['type_transaction_description'] }}
                                </td>
                                <td class="celda">
                                    {{ $value['document_type_description'] }}
                                </td>
                                <td class="celda">
                                    {{ $value['number_full'] }}
                                </td>
                                <td class="celda">
                                    {{ $value['date_of_issue'] }}
                                </td>
                                <td class="celda">
                                    {{ $value['acquirer_name'] }}
                                </td>
                                <td class="celda">
                                    {{ $value['acquirer_number'] }}
                                </td>
                                <td class="celda">
                                    {{ $value['currency_type_id'] }}
                                </td>
                                <td class="celda">
                                    -{{ GeneralPdfHelper::setNumberFormat($value['payment']) }}
                                </td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
            <br><br>
            @endforeach

        </div>
    </div>
@else
    <div class="callout callout-info">
        <p>No se encontraron registros.
        </p>
    </div>
@endif
@endsection
