<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Content-Type" content="application/pdf; charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Consolidado de items</title>
        <style>
        </style>
    </head>
    <body>
        <div>
            <p align="center" class="title"><strong>Consolidado de items por cliente/vendedor - Totales</strong></p>
        </div>
        <div style="margin-top:20px; margin-bottom:20px;">
            <table>
                <tr>
                    <td>
                        <p>@include('partials.report_company_header')</p>
                    </td>
                    <td>
                        <p><strong>RIF: </strong>{{$company->number}}</p>
                    </td>
                </tr>
                <tr>
                    <td>
                        <p><strong>Establecimiento: </strong>{{$establishment->address}} - {{$establishment->department->description}} - {{$establishment->district->description}}</p>
                    </td>
                    @inject('reportService', 'Modules\Report\Services\ReportService')

                    @if(isset($params['sellers']))
                        @php
                            $sellers = json_decode($params['sellers']);
                        @endphp
                        @if(count($sellers) > 0)
                        <td>
                            <p><strong>Usuario(s): </strong>
                            @foreach ($sellers as $seller_id)
                            - {{$reportService->getUserName($seller_id)}}
                            @endforeach
                            </p>
                        </td>
                        @endif
                    @endif
                    @if(isset($params['person_id']))
                    <td>
                        <p><strong>Cliente: </strong>{{$reportService->getPersonName($params['person_id'])}}</p>
                    </td>
                    @endif
                </tr>
            </table>
        </div>
        {{-- ######## INICIO CALCULOS EXACTOS PRO9 ######## --}}
        @include('report::sales_consolidated.exact_table', ['detail'=>false, 'excel'=>true])
        {{-- ######## FIN CALCULOS EXACTOS PRO9 ######## --}}
    </body>
</html>
