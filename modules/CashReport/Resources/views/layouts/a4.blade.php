<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $header['cash_user_name'] }} - {{ $header['cash_date_opening'] }}</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; color: #222; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }

        .report-title { text-align: center; font-size: 14px; font-weight: bold; text-transform: uppercase; margin: 0 0 8px; padding: 0; border: 0; }

        table.report-box { width: 100%; border: 1px solid #444 !important; border-collapse: collapse; margin: 0 0 8px; }
        table.report-box td { border: 0 !important; padding: 4px 8px; font-size: 9.5px; vertical-align: top; line-height: 1.3; }
        table.report-box td.label { width: 14%; font-weight: bold; color: #333; white-space: nowrap; }
        table.report-box td.value { width: 36%; }
        table.report-box td.td-custom, table.report-box td.width-custom { line-height: 1.3 !important; padding: 4px 8px !important; vertical-align: top !important; font-size: 9.5px !important; }
        table.report-box p { margin: 0 !important; padding: 0 !important; line-height: 1.3 !important; font-size: 9.5px !important; }
        table.report-box p strong { margin: 0 !important; font-size: 9.5px !important; }
        .report-content > div:first-child { margin: 0 0 8px !important; }

        h3.section { font-size: 11px; margin: 12px 0 4px; padding-bottom: 2px; border-bottom: 1px solid #999; text-transform: uppercase; }

        table.grid th { background: #f2f2f2; border: 1px solid #bbb; padding: 3px 4px; font-size: 9.5px; text-align: left; }
        table.grid td { border: 1px solid #ddd; padding: 3px 4px; font-size: 9.5px; }
        table.grid .text-end, table.grid .text-right { text-align: right; }
        table.grid .text-center { text-align: center; }
        table.grid tr.total td { font-weight: bold; background: #fafafa; }

        .report-content > div:first-child, .report-content > table:first-child { margin-top: 0 !important; }

        .report-footer { font-size: 8.5px; color: #666; border: 0px; }
        .report-footer .right { text-align: right; }

        @yield('styles')
    </style>
</head>
<body>
    @include('cashreport::partials.footer')
    @include('cashreport::partials.header')

    <div class="report-content">
        @yield('content')
    </div>
</body>
</html>
