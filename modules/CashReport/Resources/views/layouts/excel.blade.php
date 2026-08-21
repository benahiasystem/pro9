{{-- Layout para exportación Excel (FromView). Cada reporte define @section('content') con sus tablas --}}
<table>
    <tr><td colspan="6"><strong>{{ $header['company_name'] }}</strong></td></tr>
    <tr><td colspan="6"><strong>RUC:</strong> {{ $header['company_number'] }}</td></tr>
    <tr><td colspan="6"><strong>{{ strtoupper($title) }}</strong></td></tr>
    <tr><td colspan="6"></td></tr>
    <tr><td colspan="6"><strong>Vendedor:</strong> {{ $header['cash_user_name'] }}</td></tr>
    <tr><td colspan="6"><strong>Apertura:</strong> {{ $header['cash_date_opening'] }} {{ $header['cash_time_opening'] }}</td></tr>
    <tr><td colspan="6"><strong>Cierre:</strong> @if($header['cash_state']) Caja aperturada @else {{ $header['cash_date_closed'] }} {{ $header['cash_time_closed'] }} @endif</td></tr>
    <tr><td colspan="6"></td></tr>
</table>

@yield('content')
