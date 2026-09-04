<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="application/pdf; charset=utf-8"/>
    <title>Consolidado de items - Guías</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 14mm 12mm 14mm 12mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8px;
            color: #111;
            margin: 0;
            padding: 0;
        }

        .title {
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            text-decoration: underline;
            margin: 0 0 8px 0;
        }

        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .meta td {
            border: none;
            padding: 1px 0;
            font-size: 8px;
            vertical-align: top;
        }

        .meta .label {
            font-weight: bold;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.data thead {
            display: table-header-group;
        }

        table.data th {
            background-color: #0088cc;
            color: #ffffff;
            font-size: 7.5px;
            font-weight: bold;
            text-align: center;
            padding: 5px 3px;
            border: 0.6px solid #006999;
        }

        table.data td {
            font-size: 7.5px;
            padding: 4px 3px;
            border: 0.5px solid #555;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .c-num { width: 4%; text-align: center; }
        .c-dates { width: 11%; text-align: center; }
        .c-customer { width: 23%; text-align: left; }
        .c-guide { width: 16%; text-align: left; }
        .c-product { width: 36%; text-align: left; }
        .c-qty { width: 10%; text-align: right; }

        .line-main {
            font-weight: bold;
            font-size: 7.5px;
        }

        .line-sub {
            font-size: 6.5px;
            color: #333;
            margin-top: 1px;
        }

        .total-row td {
            background-color: #f2f2f2;
            font-weight: bold;
            font-size: 8px;
            padding: 5px 3px;
        }

        .total-label {
            text-align: right;
        }

        .empty {
            text-align: center;
            padding: 20px;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="title">Consolidado de items por cliente/vendedor</div>

    <table class="meta">
        <tr>
            <td style="width: 68%;">
                @include('partials.report_company_header')
            </td>
            <td style="width: 32%;">
                <span class="label">RUC:</span> {{ $company->number }}
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="label">Establecimiento:</span>
                {{ $establishment->address }}
                - {{ optional($establishment->department)->description }}
                - {{ optional($establishment->district)->description }}
            </td>
        </tr>
        @inject('reportService', 'Modules\Report\Services\ReportService')
        @if(!empty($params['sellers']))
            @php $sellers = is_string($params['sellers']) ? json_decode($params['sellers']) : $params['sellers']; @endphp
            @if(is_array($sellers) && count($sellers) > 0)
                <tr>
                    <td colspan="2">
                        <span class="label">Usuario(s):</span>
                        @foreach ($sellers as $seller_id)
                            {{ $reportService->getUserName($seller_id) }}@if(!$loop->last), @endif
                        @endforeach
                    </td>
                </tr>
            @endif
        @endif
        @if(!empty($params['person_id']))
            <tr>
                <td colspan="2">
                    <span class="label">Cliente:</span>
                    {{ $reportService->getPersonName($params['person_id']) }}
                </td>
            </tr>
        @endif
    </table>

    @if(!empty($records) && count($records) > 0)
        @php $acum_total = 0; @endphp
        <table class="data">
            <thead>
                <tr>
                    <th class="c-num">#</th>
                    <th class="c-dates">Fechas</th>
                    <th class="c-customer">Cliente / Vendedor</th>
                    <th class="c-guide">Guía</th>
                    <th class="c-product">Producto</th>
                    <th class="c-qty">Cant.</th>
                </tr>
            </thead>
            <tbody>
                @foreach($records as $value)
                    @php
                        /** @var \App\Models\Tenant\DispatchItem $value */
                        $row = $value->getConsolidatedReportRow();
                        $acum_total += (float) $row['quantity'];
                        $dateIssue = $row['date_of_issue']
                            ? \Illuminate\Support\Carbon::parse($row['date_of_issue'])->format('d/m/Y')
                            : '-';
                        $dateShip = $row['date_of_shipping']
                            ? \Illuminate\Support\Carbon::parse($row['date_of_shipping'])->format('d/m/Y')
                            : '-';
                    @endphp
                    <tr>
                        <td class="c-num">{{ $loop->iteration }}</td>
                        <td class="c-dates">
                            <div class="line-main">{{ $dateIssue }}</div>
                            <div class="line-sub">Envío: {{ $dateShip }}</div>
                        </td>
                        <td class="c-customer">
                            <div class="line-main">{{ $row['customer_name'] ?: '-' }}</div>
                            @if(!empty($row['customer_number']))
                                <div class="line-sub">{{ $row['customer_number'] }}</div>
                            @endif
                            <div class="line-sub">Vend: {{ $row['user_name'] ?: '-' }}</div>
                        </td>
                        <td class="c-guide">
                            <div class="line-main">{{ $row['number'] ?: '-' }}</div>
                            <div class="line-sub">{{ $row['state_type_description'] ?: '-' }}</div>
                            @if(!empty($row['order_form_description']))
                                <div class="line-sub">OP: {{ $row['order_form_description'] }}</div>
                            @endif
                        </td>
                        <td class="c-product">
                            {{ $row['item_description'] ?: '-' }}
                        </td>
                        <td class="c-qty">{{ $row['quantity_formatted'] }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="5" class="total-label">Total</td>
                    <td class="c-qty">{{ number_format($acum_total, 2) }}</td>
                </tr>
            </tbody>
        </table>
    @else
        <div class="empty">No se encontraron registros.</div>
    @endif
</body>
</html>
