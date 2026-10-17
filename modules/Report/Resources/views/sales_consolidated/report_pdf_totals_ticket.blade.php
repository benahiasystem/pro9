<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type"
          content="application/pdf; charset=utf-8"/>
    <meta http-equiv="X-UA-Compatible"
          content="ie=edge">
    <title>Consolidado de items</title>
    <style>
        html {
            font-family: sans-serif;
            font-size: 9px;
        }

        table {
            width: 100%;
            border-spacing: 0;

        }

        .celda {
            text-align: center;
            padding: 5px;
        }

        .celda-item {
            text-align: left;
            padding: 5px;
        }

        th {
            padding: 5px;
            text-align: center;
        }

        .title {
            font-weight: bold;
            padding: 5px;
            font-size: 10px !important;
            text-decoration: underline;
        }

        p > strong {
            margin-left: 5px;
            font-size: 9px;
        }

        thead {
            font-weight: bold;
            text-align: center;
        }

        @page {

            margin: 0px 1px;
        }

    </style>
</head>
<body>
<div>
    <p align="center"
       class="title"><strong>Consolidado de items por cliente/vendedor - Totales</strong></p>
</div>
<div>

    @include('partials.report_company_header_ticket')<br>
    <strong>RIF: </strong>{{$company->number}}<br>
    <strong>Establecimiento: </strong>{{$establishment->address}}
    - {{$establishment->department->description}} - {{$establishment->district->description}}<br>
    @inject('reportService', 'Modules\Report\Services\ReportService')

    @if(isset($params['sellers']))
        @php
            $sellers = json_decode($params['sellers'])
        @endphp
        @if(count($sellers) > 0)

            <strong>Usuario(s): </strong>
            @foreach ($sellers as $seller_id)
    - {{$reportService->getUserName($seller_id)}}
            @endforeach
            <br>

        @endif
    @endif
    @if(isset($params['person_id']))
        <strong>Cliente: </strong>{{$reportService->getPersonName($params['person_id'])}}<br>

    @endif

</div>
        {{-- ######## INICIO CALCULOS EXACTOS PRO9 ######## --}}
        @include('report::sales_consolidated.exact_ticket_table', ['detail'=>false, 'excel'=>false])
        {{-- ######## FIN CALCULOS EXACTOS PRO9 ######## --}}
    </body>
</html>
