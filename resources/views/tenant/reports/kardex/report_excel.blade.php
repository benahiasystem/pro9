<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Content-Type" content="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Kardex</title>
    </head>
    <body>
        <div>
            <h3 align="center" class="title"><strong>Reporte Kardex</strong></h3>
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
                    <td>
                        <p><strong>Establecimiento: </strong></p>
                    </td>
                    <td align="center">{{$establishment->address}} - {{$establishment->department->description}} - {{$establishment->district->description}}</td>
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
                                <th>Fecha y hora del movimiento</th>
                                <th>Tipo de movimiento</th>
                                <th>N.º de documento</th>
                                <th>Entrada</th>
                                <th>Salida</th>
                                <th>Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($records as $key => $value)
                            @php
                                $row = \App\Support\Exact\LegacyKardexRow::from($value);
                                $balance = \App\Support\Exact\Decimal::of($balance)->plus($row['delta']);
                            @endphp
                            <tr>
                                <td class="celda">{{$value->id}}</td>
                                <td class="celda">{{$value->created_at}}</td>
                                <td class="celda">{{ $row['description'] }}</td>
                                <td class="celda" data-type="s">{{ $row['number'] }}</td>
                                <td class="celda" data-type="s">{{ \App\Support\Exact\Decimal::represent($row['entry']) }}</td>
                                <td class="celda" data-type="s">{{ \App\Support\Exact\Decimal::represent($row['exit']) }}</td>
                                <td class="celda" data-type="s">{{ \App\Support\Exact\Decimal::represent($balance) }}</td>
                            </tr>
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
    </body>
</html>
