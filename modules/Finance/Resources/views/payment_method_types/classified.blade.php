{{-- ######## INICIO CLASIFICACION DE COBROS Y CAJA ######## --}}
@if(!empty($records['classified']))
<h3>Medios clasificados por cuenta y moneda</h3>
<table>
    <thead><tr><th>Medio</th><th>Destino</th><th>Moneda</th><th>Apertura</th><th>Entradas</th><th>Salidas</th><th>Saldo</th></tr></thead>
    <tbody>
    @foreach($records['classified'] as $row)
        <tr><td>{{ $row['method']['description'] }}<br><small>{{ \App\Services\Payments\Classification::kindLabel($row['method']['collection_kind'] ?? null) }}</small></td><td>{{ $row['destination_label'] }}</td><td>{{ $row['currency_type_id'] }}</td>
            @foreach(['opening','income','egress','balance'] as $field)<td data-type="s" style="mso-number-format:'\@'">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($row,$field)) }}</td>@endforeach
        </tr>
    @endforeach
    </tbody>
</table>
@endif
{{-- ######## FIN CLASIFICACION DE COBROS Y CAJA ######## --}}
