<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Content-Type" content="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Ventas por marca</title>
    </head>
    <body>
        <div>
            <h3 align="center" class="title"><strong>Reporte de Ventas por Marca</strong></h3>
        </div>
        <br>
        <div style="margin-top:20px; margin-bottom:15px;">
            <table>
                <tr>
                    <td>
                        <p><b>Empresa: </b></p>
                    </td>
                    <td align="center" colspan="3">
                        <p>@include('partials.report_company_header', ['showEmpresaLabel' => false])</p>
                    </td>
                </tr>
                <tr>
                    <td>
                        <p><strong>RIF: </strong></p>
                    </td>
                    <td align="center" colspan="3">{{$company->number}}</td>
                </tr>
                <tr>
                    <td>
                        <p><strong>Fecha: </strong></p>
                    </td>
                    <td align="center" colspan="3">
                        <p>
                            @if($date_start == $date_end)
                                {{ \Carbon\Carbon::parse($date_start)->format('d/m/Y') }}
                            @else
                                {{ \Carbon\Carbon::parse($date_start)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($date_end)->format('d/m/Y') }}
                            @endif
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        <br>
        @if(!empty($records) && count($records) > 0)
            <div>
                <table border="1">
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
                                <td>{{$loop->iteration}}</td>
                                <td>{{$row['brand_name']}}</td>
                                <td>{{$row['unit_type_id'] ?? 'Sin unidad'}}</td>
                                <td data-type="s" style="mso-number-format:'\@'">{{$row['quantity_sold']}}</td>
                                <td data-type="s" style="mso-number-format:'\@'">{{$row['display']['total_sale_value']}}</td>
                                <td data-type="s" style="mso-number-format:'\@'">{{$row['display']['total_profit']}}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3">Totales</th>
                            <th>@foreach($report['units'] as $unit=>$quantity) {{ \App\Support\Exact\Decimal::represent($quantity) }} {{ $unit }}<br>@endforeach</th>
                            <th data-type="s" style="mso-number-format:'\@'">{{\App\Support\Exact\Decimal::format($report['totals']['total_sale_value'],2,'.','')}}</th>
                            <th data-type="s" style="mso-number-format:'\@'">{{\App\Support\Exact\Decimal::format($report['totals']['total_profit'],2,'.','')}}</th>
                        </tr>
                    </tfoot>
                </table>
                @if($report['unknown'])<p>Las partidas sin unidad identificada no se incluyen en los totales de cantidad.</p>@endif
                <p>La marca corresponde al catálogo actual; la ganancia es una estimación basada en compras o costos actuales.</p>
            </div>
        @else
            <div>
                <p>No se encontraron registros.</p>
            </div>
        @endif
