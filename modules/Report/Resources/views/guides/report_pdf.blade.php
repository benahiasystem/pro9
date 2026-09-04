<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="application/pdf; charset=utf-8"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Consolidado de items</title>
    <style>
        @page {
            margin: 12px 14px;
        }

        html, body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8px;
            margin: 0;
            padding: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: 1px solid #000;
        }

        .header-table {
            border: none;
            margin-bottom: 8px;
            table-layout: auto;
        }

        .header-table td {
            border: none;
            padding: 2px 4px;
            vertical-align: top;
        }

        .celda, th {
            text-align: center;
            padding: 3px 2px;
            border: 0.5px solid #000;
            word-wrap: break-word;
            overflow-wrap: break-word;
            vertical-align: middle;
        }

        th {
            background: #0088cc;
            color: #fff;
            font-weight: bold;
            font-size: 7.5px;
        }

        .title {
            font-weight: bold;
            padding: 4px;
            font-size: 14px;
            text-decoration: underline;
            text-align: center;
            margin: 0 0 6px 0;
        }

        p {
            margin: 2px 0;
            font-size: 8px;
        }

        p > strong {
            font-size: 8px;
        }

        .col-num { width: 4%; }
        .col-date { width: 8%; }
        .col-customer { width: 16%; }
        .col-seller { width: 9%; }
        .col-number { width: 9%; }
        .col-state { width: 8%; }
        .col-ship { width: 8%; }
        .col-order { width: 8%; }
        .col-product { width: 22%; }
        .col-qty { width: 8%; }

        .text-left { text-align: left; }
    </style>
</head>
<body>
<div>
    <p class="title"><strong>Consolidado de items por cliente/vendedor</strong></p>
</div>
<div>
    <table class="header-table">
        <tr>
            <td>
                <p>@include('partials.report_company_header')</p>
            </td>
            <td>
                <p><strong>Ruc: </strong>{{$company->number}}</p>
            </td>
        </tr>
        <tr>
            <td>
                <p><strong>Establecimiento: </strong>{{$establishment->address}}
                    - {{$establishment->department->description}} - {{$establishment->district->description}}</p>
            </td>

            @inject('reportService', 'Modules\Report\Services\ReportService')
            @if(isset($params['sellers']))
                @php
                    $sellers = json_decode($params['sellers'])
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
@if(!empty($records))
    @php
        $acum_total = 0;
    @endphp
    <table>
        <thead>
        <tr>
            <th class="col-num">#</th>
            <th class="col-date">F. Emisión</th>
            <th class="col-customer">Cliente</th>
            <th class="col-seller">Vendedor</th>
            <th class="col-number">Número</th>
            <th class="col-state">Estado</th>
            <th class="col-ship">F. Envío</th>
            <th class="col-order">O.Pedido</th>
            <th class="col-product">Producto</th>
            <th class="col-qty">Cantidad</th>
        </tr>
        </thead>
        <tbody>
        @foreach($records as $key => $value)
            @php
                /** @var \App\Models\Tenant\DispatchItem $value */
                $row = $value->getConsolidatedReportRow();
                $qty = $row['quantity'];
                $acum_total += $qty;
            @endphp
            <tr>
                <td class="celda">{{ $loop->iteration }}</td>
                <td class="celda">{{ $row['date_of_issue'] }}</td>
                <td class="celda text-left">{{ trim($row['customer_name'].($row['customer_number'] ? ' - '.$row['customer_number'] : '')) }}</td>
                <td class="celda">{{ $row['user_name'] }}</td>
                <td class="celda">{{ $row['number'] }}</td>
                <td class="celda">{{ $row['state_type_description'] }}</td>
                <td class="celda">{{ $row['date_of_shipping'] }}</td>
                <td class="celda">{{ $row['order_form_description'] }}</td>
                <td class="celda text-left">{{ $row['item_description'] }}</td>
                <td class="celda">{{ $row['quantity_formatted'] }}</td>
            </tr>
        @endforeach
        <tr>
            <td class="celda" colspan="8"></td>
            <td class="celda"><strong>Total</strong></td>
            <td class="celda">{{ number_format($acum_total, 2) }}</td>
        </tr>
        </tbody>
    </table>
@else
    <div class="callout callout-info">
        <p>No se encontraron registros.</p>
    </div>
@endif
</body>
</html>
