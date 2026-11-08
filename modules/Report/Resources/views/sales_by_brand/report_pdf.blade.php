<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Content-Type" content="application/pdf; charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Ventas por marca</title>
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
            }
            th {
                padding: 5px;
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
            p>strong {
                margin-left: 5px;
                font-size: 13px;
            }
            thead {
                font-weight: bold;
                background: #0088cc;
                color: white;
                text-align: center;
            }
        </style>
    </head>
    <body>
        <div>
            <p align="center" class="title"><strong>Reporte de ventas por marca</strong></p>
        </div>
        <div style="margin-top:20px; margin-bottom:20px;">
            <table>
                <tr>
                    <td>
                        <p>@include('partials.report_company_header')</p>
                    </td>
                    <td>
                        <p>
                        <strong>Fecha: </strong>
                        @if($date_start == $date_end)
                            {{ \Carbon\Carbon::parse($date_start)->format('d/m/Y') }}
                        @else
                            {{ \Carbon\Carbon::parse($date_start)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($date_end)->format('d/m/Y') }}
                        @endif
                        </p>
                    </td>
                </tr>
                <tr>
                    <td>
                        <p><strong>RIF: </strong>{{$company->number}}</p>
                    </td>
                </tr>
            </table>
        </div>
        @if(!empty($records) && count($records) > 0)
            <div>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Marca actual</th>
                            <th>Unidad</th>
                            <th class="text-center">Cantidad vendida</th>
                            <th class="text-center">Ventas con IVA (Bs.)</th>
                            <th class="text-center">Ganancia estimada (Bs.)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $report = \Modules\Report\Services\BrandSalesAmounts::presentation($records);
                        @endphp
                        @foreach($report['rows'] as $row)
                            <tr>
                                <td class="celda">{{$loop->iteration}}</td>
                                <td class="celda">{{$row['brand_name']}}</td>
                                <td class="celda">{{$row['unit_type_id'] ?? 'Sin unidad'}}</td>
                                <td class="celda">{{$row['quantity_sold']}}</td>
                                <td class="celda">{{$row['display']['total_sale_value']}}</td>
                                <td class="celda">{{$row['display']['total_profit']}}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th class="celda" colspan="3">Totales</th>
                            <th class="celda">@foreach($report['units'] as $unit=>$quantity) {{ \App\Support\Exact\Decimal::represent($quantity) }} {{ $unit }}<br>@endforeach</th>
                            <th class="celda">{{\App\Support\Exact\Decimal::format($report['totals']['total_sale_value'],2,'.','')}}</th>
                            <th class="celda">{{\App\Support\Exact\Decimal::format($report['totals']['total_profit'],2,'.','')}}</th>
                        </tr>
                    </tfoot>
                </table>
                @if($report['unknown'])<p>Las partidas sin unidad identificada no se incluyen en los totales de cantidad.</p>@endif
                <p>La marca corresponde al catálogo actual; la ganancia es una estimación basada en compras o costos actuales.</p>
            </div>
        @else
            <div class="callout callout-info">
                <p>No se encontraron registros.</p>
            </div>
        @endif
    </body>
</html>
