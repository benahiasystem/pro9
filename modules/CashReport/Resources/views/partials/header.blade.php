{{-- Cabecera común A4. Espera $header (HeaderDataBuilder) y $title --}}
<div class="report-title">{{ $title }}</div>

<table class="report-box">
    <tr>
        <td class="label">Empresa:</td>
        <td class="value" style="text-transform: uppercase">{{ $header['company_name'] }}</td>
        <td class="label">RIF:</td>
        <td class="value">{{ $header['company_number'] }}</td>
    </tr>
    <tr>
        <td class="label">Establecimiento:</td>
        <td class="value" colspan="3">
            {{ $header['establishment_address'] }}
            @if(!empty($header['establishment_district_description'])) - {{ $header['establishment_district_description'] }} @endif
            @if(!empty($header['establishment_department_description'])) - {{ $header['establishment_department_description'] }} @endif
        </td>
    </tr>
</table>

<table class="report-box">
    <tr>
        <td class="label">Vendedor:</td>
        <td class="value">{{ $header['cash_user_name'] }}</td>
        <td class="label">Estado:</td>
        <td class="value">{{ $header['cash_state'] ? 'Aperturada' : 'Cerrada' }}</td>
    </tr>
    <tr>
        <td class="label">Apertura:</td>
        <td class="value">{{ $header['cash_date_opening'] }} {{ $header['cash_time_opening'] }}</td>
        <td class="label">Cierre:</td>
        <td class="value">
            @if($header['cash_state'])
                Caja aperturada
            @else
                {{ $header['cash_date_closed'] }} {{ $header['cash_time_closed'] }}
            @endif
        </td>
    </tr>
</table>
