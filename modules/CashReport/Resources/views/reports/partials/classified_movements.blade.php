{{-- ######## INICIO CLASIFICACION DE COBROS Y CAJA ######## --}}
@if($classified['show_detail'])
<h3>Movimientos del turno</h3>
<table class="grid">
    <thead><tr><th>Fecha / documento</th><th>Medio</th><th>Movimiento</th><th>Moneda</th><th>Principal</th><th>IGTF</th><th>Total recibido / desembolsado</th></tr></thead>
    <tbody>
    @foreach($classified['rows'] as $row)
        <tr><td>{{ $row['date'] }}<br>{{ $row['number'] }}<br>{{ $row['party'] }}</td><td>{{ $row['method']['description'] }}</td>
            <td>{{ $row['opening'] ? 'Apertura' : ($row['direction'] === 'income' ? 'Entrada' : 'Salida') }}</td><td>{{ $row['currency_type_id'] }}</td>
            @foreach(['principal','tax','amount'] as $field)
                <td data-type="s" style="mso-number-format:'\@'">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($row,$field)) }}</td>
            @endforeach
        </tr>
    @endforeach
    </tbody>
</table>
@endif
{{-- ######## FIN CLASIFICACION DE COBROS Y CAJA ######## --}}
