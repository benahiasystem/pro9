{{-- ######## INICIO CALCULOS EXACTOS PRO9 ######## --}}
@if(count($records))
<table style="table-layout:fixed;width:100%">
    <thead><tr><th style="width:50%">Producto</th><th style="width:18%">Cant.</th><th style="width:32%">Total Bs.</th></tr></thead>
    <tbody>
    @foreach($records as $value)
        <tr>
            <td style="text-align:left;overflow-wrap:break-word;padding:4px 1px">
                {{ $value['item_description'] }}<br><small>{{ $value['item_internal_id'] }} · {{ $value['item_unit_type_id'] }}</small>
            </td>
            <td style="text-align:right;padding:4px 1px">{{ $value['quantity'] }}</td>
            <td style="text-align:right;overflow-wrap:break-word;padding:4px 1px">
                {{ $value['display']['total_sale'] }}<br>
                @foreach($value['currency_totals'] as $currency=>$amount)
                    <small>{{ $currency }} {{ $value['display']['total_'.strtolower($currency)] }}</small><br>
                @endforeach
            </td>
        </tr>
    @endforeach
        <tr><td>Total (Bs.)</td><td style="text-align:right">{{ $reportSummary['quantity'] }}</td>
            <td style="text-align:right">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($reportSummary, 'total_sale'), 2) }}</td></tr>
    </tbody>
</table>
<p>Moneda original:<br>
    Bs. {{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($reportSummary, 'total_ves'), 2) }}<br>
    USD {{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($reportSummary, 'total_usd'), 2) }}
</p>
@else
<p>No se encontraron registros.</p>
@endif
{{-- ######## FIN CALCULOS EXACTOS PRO9 ######## --}}
