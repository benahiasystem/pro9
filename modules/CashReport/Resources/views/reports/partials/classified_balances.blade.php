{{-- ######## INICIO CLASIFICACION DE COBROS Y CAJA ######## --}}
<h3>Efectivo físico del turno completo · VES / USD</h3>
<table class="grid">
    <thead><tr><th>Moneda</th><th>Apertura</th><th>Entradas</th><th>Salidas</th><th>Saldo final</th></tr></thead>
    <tbody>
    @foreach($classified['balances_by_currency'] as $currency => $balance)
        <tr><td>{{ $currency === 'VES' ? 'Bs.' : 'USD' }}</td>
            @foreach(['beginning_balance','income','egress','final_balance'] as $field)
                <td data-type="s" style="mso-number-format:'\@'" class="text-end">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($balance,$field)) }}</td>
            @endforeach
        </tr>
    @endforeach
    </tbody>
</table>
<h3>Cobros y desembolsos por medio, cuenta y moneda</h3>
<table class="grid">
    <thead><tr><th>Medio</th><th>Destino</th><th>Moneda</th><th>Entradas</th><th>Salidas</th></tr></thead>
    <tbody>
    @foreach($classified['groups'] as $group)
        <tr><td>{{ $group['method']['description'] }}<br><small>{{ \App\Services\Payments\Classification::kindLabel($group['method']['collection_kind'] ?? null) }}</small></td><td>{{ $group['destination_label'] }}</td><td>{{ $group['currency_type_id'] }}</td>
            <td data-type="s" style="mso-number-format:'\@'">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($group,'income')) }}</td>
            <td data-type="s" style="mso-number-format:'\@'">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($group,'egress')) }}</td></tr>
    @endforeach
    </tbody>
</table>
@isset($classified['credit_pending'])
<p>Crédito pendiente: Bs. {{ \App\Support\Exact\Decimal::format($classified['credit_pending']['VES']) }} · USD {{ \App\Support\Exact\Decimal::format($classified['credit_pending']['USD']) }}</p>
@endisset
{{-- ######## FIN CLASIFICACION DE COBROS Y CAJA ######## --}}
