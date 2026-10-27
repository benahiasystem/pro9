<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Content-Type" content="application/pdf; charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Reporte POS</title>
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
                font-size: 12px;
            }

            thead {
                font-weight: bold;
                background: #0088cc;
                color: white;
                text-align: center;
            }
            .td-custom { line-height: 0.1em; }
        </style>
    </head>
    <body>
        <div>
            <p align="center" class="title"><strong>Reporte general de ventas POS</strong></p>
        </div>
        <div style="margin-top:20px; margin-bottom:20px;">
            <table>
                <tr>
                    <td class="td-custom">
                        <p>@include('partials.report_company_header')</p>
                    </td>
                    <td class="td-custom">
                        <p><strong>Fecha reporte: </strong>{{date('Y-m-d')}}</p>
                    </td>
                </tr>
                <tr>
                    <td class="td-custom">
                        <p><strong>RIF: </strong>{{$company->number}}</p>
                    </td>
                </tr>

            </table>
        </div>
        @if(empty($classified_sessions))<p>No se encontraron registros.</p>@endif
        {{-- ######## INICIO CLASIFICACION DE COBROS Y CAJA ######## --}}
        @foreach(($classified_sessions ?? []) as $session)
            <h3>Caja {{ $session['cash']->id }} · {{ $session['header']['cash_user_name'] }}</h3>
            @include('cashreport::reports.partials.classified_balances',['classified'=>$session['classified']])
            @include('cashreport::reports.partials.classified_movements',['classified'=>$session['classified']])
        @endforeach
        {{-- ######## FIN CLASIFICACION DE COBROS Y CAJA ######## --}}
    </body>
</html>
