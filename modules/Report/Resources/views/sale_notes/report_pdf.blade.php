{{-- ######## INICIO MIGRACIÓN MONEDA VENEZUELA ######## --}}
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Content-Type" content="application/pdf; charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Nota de ventas</title>
        <style>
            @page {
              margin: 5;
            }
            html {
                font-family: sans-serif;
                font-size: 10px;
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
            <p align="center" class="title"><strong>Reporte Nota de Venta</strong></p>
        </div>
        <div style="margin-top:20px; margin-bottom:20px;">
            <table>
                <tr>
                    <td>
                        <p>@include('partials.report_company_header')</p>
                    </td>
                    <td>
                        <p><strong>Fecha: </strong>{{date('Y-m-d')}}</p>
                    </td>
                </tr>
                <tr>
                    <td>
                        <p><strong>RIF: </strong>{{$company->number}}</p>
                    </td>

                    @inject('reportService', 'Modules\Report\Services\ReportService')
                    @if($filters['seller_id'])
                    <td>
                        <p><strong>Usuario: </strong>{{$reportService->getUserName($filters['seller_id'])}}</p>
                    </td>
                    @endif
                </tr>
            </table>
        </div>
        @if($records->isNotEmpty())
            <div class="">
                <div class=" ">

                    @php
                        $report = \Modules\Report\Services\SaleNoteReportAmounts::presentation($records);
                        $displayByKey = $report['rows']->keyBy('report_key');
                    @endphp

                    <table class="">
                        <thead>
                            <tr>
                               <th>#</th>
                                <th class="text-center">Fecha Emisión</th>
                                <th class="text-center">Hora Emisión</th>
                                <th>Cliente</th>
                                <th>Nota de Venta</th>
                                <th>Estado</th>
                                <th class="text-center">Estado pago</th>
                                <th class="text-center">Moneda</th>
                                <th class="text-center">Plataforma</th>
                                <th class="text-center">Orden de compra</th>
                                {{-- <th class="text-center">Region</th> --}}
                                <th class="text-center">Comprobantes</th>
                                <th class="text-right" >T.Exportación</th>
                                <th class="text-right" >Total no sujeto</th>
                                <th class="text-right" >Total exento</th>
                                <th class="text-right">Base imponible</th>
                                {{-- ########## INICIO CAMBIO IGV A IVA --}}
                                <th class="text-right">T.IVA</th>
                                {{-- ######### FIN CAMBIO IGV A IVA --}}
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($records as $key => $value)
                                @php $display = $displayByKey['sale_note:'.$value->id]['display']; @endphp
                                <tr>
                                    <td class="celda">{{$loop->iteration}}</td>
                                    <td class="celda">{{$value->date_of_issue->format('Y-m-d')}}</td>
                                    <td class="celda">{{$value->time_of_issue}}</td>
                                    <td class="celda">{{$value->customer->name}}</td>
                                    <td class="celda">{{$value->number_full}}</td>
                                    <td class="celda">{{$value->state_type->description}}</td>
                                    <td class="celda">
                                        {{$value->total_canceled ? 'Pagado':'Pendiente'}}
                                    </td>

                                    <td class="celda">{{$value->currency_type_id}}</td>

                                    <td class="celda">
                                        @foreach ($value->getPlatformThroughItems() as $platform)
                                            <label class="d-block">{{$platform->name}}</label>
                                        @endforeach
                                    </td>
                                    <td class="celda">{{$value->purchase_order}}</td>
                                    {{-- <td class="celda">{{$value->customer->department->description}}</td> --}}
                                    <td class="celda">
                                        @foreach ($value->documents as $doc)
                                            <label class="d-block">{{$doc->number_full}}</label>
                                        @endforeach
                                    </td>

                                    <td class="celda">{{ $display['total_exportation'] }}</td>
                                    <td class="celda">{{ $display['total_unaffected'] }}</td>
                                    <td class="celda">{{ $display['total_exonerated'] }}</td>
                                    <td class="celda">{{ $display['total_taxed'] }}</td>
                                    <td class="celda">{{ $display['total_igv'] }}</td>
                                    <td class="celda">{{ $display['total'] }}</td>
                                </tr>


                            @endforeach
                            @foreach($report['totals'] as $currency => $amounts)
                            <tr>
                                <td class="celda" colspan="13"></td>
                                <td class="celda">Totales {{ $currency }}</td>
                                <td class="celda">{{ \App\Support\Exact\Decimal::format($amounts['total_taxed'],2,'.','') }}</td>
                                <td class="celda">{{ \App\Support\Exact\Decimal::format($amounts['total_igv'],2,'.','') }}</td>
                                <td class="celda">{{ \App\Support\Exact\Decimal::format($amounts['total'],2,'.','') }}</td>
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
    </body>
</html>

{{-- ######## FIN MIGRACIÓN MONEDA VENEZUELA ######## --}}
