<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Content-Type" content="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Reporte</title>
    </head>
    <body>
        <div>
            <h3 align="center" class="title"><strong>Reporte Cuentas Por Pagar</strong></h3>
        </div>
        <br>
        <div style="margin-top:20px; margin-bottom:15px;">
            <table>
                <tr>
                    <td>
                        <p><b>Empresa: </b></p>
                    </td>
                    <td align="center">
                        <p>@include('partials.report_company_header', ['showEmpresaLabel' => false])</p>
                    </td>
                    <td>
                        <p><strong>Fecha: </strong></p>
                    </td>
                    <td align="center">
                        <p><strong>{{date('Y-m-d')}}</strong></p>
                    </td>
                </tr>
                <tr>
                    <td>
                        <p><strong>RIF: </strong></p>
                    </td>
                    <td align="center">{{$company->number}}</td>

                </tr>
            </table>
        </div>
        <br>
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
                                <th>Vendedor</th>
                                <th>Moneda</th>
                                <th>Por Pagar</th>
                                <th>Total</th>
                                <th>Saldo equivalente (Bs.)</th>
                                <th>Total documental equivalente (Bs.)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($records as $key => $value)

                                @if(\App\Support\Exact\Decimal::readRequired($value, 'total_to_pay')->isPositive())
                                    <tr>
                                        <td class="celda" data-type="s">{{$loop->iteration}}</td>
                                        <td class="celda" data-type="s">{{$value['date_of_issue']}}</td>
                                        <td class="celda" data-type="s">{{$value['number_full']}}</td>
                                        <td class="celda" data-type="s">{{$value['supplier_name']}}</td>
                                        <td class="celda" data-type="s">{{ $value['creator_name'] }}</td>
                                        <td class="celda" data-type="s">{{$value['currency_type_id']}}</td>
                                        <td class="celda" data-type="s">{{$value['total_to_pay']}}</td>
                                        <td class="celda" data-type="s">{{$value['total']}}</td>
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
</body>
</html>
