<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Content-Type" content="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Reporte de Cuentas</title>
</head>
<body>
    <div>
            <h3 align="center" class="title"><strong>Reporte de Cuentas por Cobrar</strong></h3>
        </div>
        <br>
        <div style="margin-top:20px; margin-bottom:15px;">
            <table>
                <tr>
                    <td data-type="s">
                        <p><b>Empresa: </b></p>
                    </td>
                @foreach($companies as $value)

                    <td data-type="s" align="center">
                        <p><strong>{{$value->name}}</strong></p>
                    </td>

                </tr>
                <tr>
                    <td data-type="s">
                        <p><strong>RIF: </strong></p>
                    </td>
                    <td data-type="s" align="center">{{$value->number}}</td>

                @endforeach

                    <td data-type="s">
                        <p><strong>Fecha: </strong></p>
                    </td>
                    <td data-type="s" align="center">
                        <p><strong>{{date('Y-m-d')}}</strong></p>
                    </td>
                </tr>
            </table>
        @if(!empty($records))
            <div class="">
                <div class=" ">
                    <table class="">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th class="text-center">Fecha Emisión</th>
                                <th>Número</th>

                                <th>Cliente</th>
                                <th>Moneda</th>
                                <th>Plataforma</th>
                                <th>Orden de compra</th>
                                <th>Por cobrar</th>
                                <th>Total</th>
                                <th>Saldo equivalente (Bs.)</th>
                                <th>Total documental equivalente (Bs.)</th>


                            </tr>
                        </thead>
                        <tbody>
                            @foreach($records as $key => $value)

                                @if(\App\Support\Exact\Decimal::readRequired($value, 'total_to_pay')->isPositive())
                                    <tr>
                                        <td data-type="s" class="celda">{{$loop->iteration}}</td>
                                        <td data-type="s" class="celda">{{$value['date_of_issue']}}</td>
                                        <td data-type="s" class="celda">{{$value['number_full']}}
                                            @if(!empty($value['fiscal_identity']['control_number']))<br>Control: {{$value['fiscal_identity']['control_number']}}@endif
                                            @if(!empty($value['fiscal_identity']['original_number_full']))<br>Reserva original: {{$value['fiscal_identity']['original_number_full']}}@endif
                                        </td>
                                        <td data-type="s" class="celda">{{$value['customer_name']}}</td>
                                        <td data-type="s" class="celda">{{$value['currency_type_id']}}</td>
                                        <td data-type="s" class="celda">
                                            @if(isset($value['web_platforms']))
                                                @foreach($value['web_platforms'] as $platform)
                                                    <label > {{$platform['name']}}</label>
                                                @endforeach
                                            @endif
                                        </td>
                                        <td data-type="s" class="celda">{{$value['purchase_order']}}</td>
                                        <td data-type="s" class="celda">{{$value['total_to_pay']}}</td>
                                        <td data-type="s" class="celda">{{$value['total']}}</td>
                                        <td data-type="s" class="celda">{{$value['balance_ves_equivalent']}}</td>
                                        <td data-type="s" class="celda">{{$value['total_ves_equivalent']}}</td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div>
                <p>No se encontraron registros.</p>
            </div>
        @endif
@isset($reportSummary)
@include('partials.exact_outstanding_summary', ['excel'=>true])
@endisset
</html>
