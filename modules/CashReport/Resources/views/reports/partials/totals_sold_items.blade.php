{{-- ######## INICIO CALCULOS EXACTOS PRO9 ######## --}}
@php
    $table_sold_items = \Modules\CashReport\Services\ProductsAmounts::sold($documents);
    $displaySold = \Modules\CashReport\Services\ProductsAmounts::display($table_sold_items, 'total');
    $sold_totals = [];
    foreach ($table_sold_items as $item) {
        $currency = $item['currency_type_id'];
        if (in_array($currency, ['VES', 'USD'], true)) {
            $sold_totals[$currency] = ($sold_totals[$currency] ?? \App\Support\Exact\Decimal::of(0))->plus($item['total']);
        }
    }
@endphp
<table>
    <thead>
        <tr><th colspan="7">Totales por producto</th></tr>
        <tr><th>#</th><th>Producto</th><th>Moneda</th><th>Unidad</th><th>Cantidad</th><th>Último V. Unitario</th><th>Total</th></tr>
    </thead>
    <tbody>
    @foreach ($table_sold_items as $item)
        <tr>
            <td class="celda">{{ $loop->iteration }}</td>
            <td class="celda">{{ $item['description'] }}</td>
            <td class="celda">{{ $item['currency_type_id'] ?: 'Sin moneda' }}</td>
            <td class="celda">{{ $item['unit_type_id'] ?: 'Sin unidad' }}</td>
            <td class="celda">{{ \App\Support\Exact\Decimal::represent($item['quantity']) }}</td>
            <td class="celda" style="text-align: right">{{ \App\Support\Exact\Decimal::format($item['unit_value'], 2, '.', '') }}</td>
            <td class="celda" style="text-align: right">{{ $displaySold[$loop->index] }}</td>
        </tr>
    @endforeach
    @foreach ($sold_totals as $currency => $total)
        <tr><td class="celda" colspan="6">Total {{ $currency }}</td><td class="celda" style="text-align: right">{{ \App\Support\Exact\Decimal::format($total, 2, '.', '') }}</td></tr>
    @endforeach
    @if(collect($table_sold_items)->contains(fn($row) => !in_array($row['currency_type_id'], ['VES', 'USD'], true)))
        <tr><td class="celda" colspan="7">Las partidas sin moneda identificada no se incluyen en los totales.</td></tr>
    @endif
    </tbody>
</table>
{{-- ######## FIN CALCULOS EXACTOS PRO9 ######## --}}
