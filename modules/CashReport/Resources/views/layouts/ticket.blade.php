<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $header['cash_user_name'] }} - {{ $header['cash_date_opening'] }}</title>
    <style>
        body { font-family: sans-serif; font-size: 8.5px; color: #000; }
        table { width: 100%; border-collapse: collapse; }
        p { margin: 0 0 2px; }

        .ticket-header { text-align: center; margin-bottom: 6px; border-bottom: 1px dashed #000; padding-bottom: 4px; }
        .ticket-header .company-name { font-size: 10px; font-weight: bold; }
        .ticket-header .report-title { font-weight: bold; text-transform: uppercase; display: block; margin-top: 3px; }

        .ticket-meta { margin-bottom: 6px; }

        h3.section { font-size: 9px; margin: 6px 0 2px; border-bottom: 1px dashed #000; text-transform: uppercase; }

        table.grid th { border-bottom: 1px solid #000; padding: 2px 1px; text-align: left; font-size: 8px; }
        table.grid td { padding: 1px; font-size: 8px; }
        table.grid .text-end, table.grid .text-right { text-align: right; }
        table.grid tr.total td { font-weight: bold; border-top: 1px solid #000; }

        @yield('styles')
    </style>
</head>
<body>
    <div class="ticket-header">
        <span class="company-name">{{ $header['company_name'] }}</span><br>
        RUC: {{ $header['company_number'] }}<br>
        @if(!empty($header['establishment_address'])){{ $header['establishment_address'] }}<br>@endif
        <span class="report-title">{{ $title }}</span>
    </div>

    <div class="ticket-meta">
        <p><strong>Vendedor:</strong> {{ $header['cash_user_name'] }}</p>
        <p><strong>Apertura:</strong> {{ $header['cash_date_opening'] }} {{ $header['cash_time_opening'] }}</p>
        <p><strong>Cierre:</strong>
            @if($header['cash_state']) Caja aperturada @else {{ $header['cash_date_closed'] }} {{ $header['cash_time_closed'] }} @endif
        </p>
    </div>

    @yield('content')
</body>
</html>
