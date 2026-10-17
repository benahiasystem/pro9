{{-- ######## INICIO CALCULOS EXACTOS PRO9 ######## --}}
@if(count($records))
<table>
    <thead><tr>
        <th>#</th>
        @if($detail)<th>Documento</th>@endif
        <th>Cod. Interno</th><th>Producto</th><th>Unidad</th><th>Categoría</th>
        <th>Cantidad</th><th>Total de venta (Bs.)</th><th>Moneda original</th>
    </tr></thead>
    <tbody>
    @foreach($records as $value)
        <tr>
            <td class="celda">{{ $loop->iteration }}</td>
            @if($detail)<td class="celda" data-type="s">{{ \App\Services\Fiscal\FiscalIdentity::numberFull($value['series'], $value['number']) }}</td>@endif
            <td class="celda" data-type="s">{{ $value['item_internal_id'] }}</td>
            <td class="celda" data-type="s">{{ $value['item_description'] }}</td>
            <td class="celda" data-type="s">{{ $value['item_unit_type_id'] }}</td>
            <td class="celda" data-type="s">{{ $value['category_name'] }}</td>
            <td class="celda" data-type="s">{{ $value['quantity'] }}</td>
            <td class="celda" data-type="s">{{ $excel ? $value['total_sale'] : \App\Support\Exact\Decimal::format($value['display']['total_sale'], 2) }}</td>
            <td class="celda" data-type="s">
                @foreach($value['currency_totals'] as $currency=>$amount)
                    {{ $currency }} {{ $excel ? $amount : $value['display']['total_'.strtolower($currency)] }}<br>
                @endforeach
            </td>
        </tr>
    @endforeach
        <tr>
            <td class="celda" colspan="{{ $detail ? 6 : 5 }}">Total</td>
            <td class="celda" data-type="s">{{ $reportSummary['quantity'] }}</td>
            <td class="celda" data-type="s">{{ $excel ? $reportSummary['total_sale'] : \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($reportSummary, 'total_sale'), 2) }}</td>
            <td class="celda" data-type="s">Bs. {{ $excel ? $reportSummary['currency_totals']['VES'] : \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($reportSummary, 'total_ves'), 2) }}<br>USD {{ $excel ? $reportSummary['currency_totals']['USD'] : \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($reportSummary, 'total_usd'), 2) }}</td>
        </tr>
    </tbody>
</table>
@else
<p>No se encontraron registros.</p>
@endif
{{-- ######## FIN CALCULOS EXACTOS PRO9 ######## --}}
