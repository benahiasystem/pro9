@extends('cashreport::layouts.a4')

<?php $title = 'Pagos asociados a caja'; ?>

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
@if(count($payments) > 0)
    <div class="">
        <div class=" ">
            <table>
                <thead>
                <tr>
                    <th>
                        #
                    </th>
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
                        <td class="celda">
                            {{ $loop->iteration }}
                        </td>
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
                            {{ $value['payment'] }}
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@else
    <div class="callout callout-info">
        <p>No se encontraron registros.
        </p>
    </div>
@endif
@endsection
