{{-- ######## INICIO MONEDA DEL MEDIO DE COBRO ######## --}}
<table>
    <thead><tr><th>Documento ID</th><th>Documento</th><th>Pago ID</th><th>Campo</th><th>Moneda</th><th>Numerador</th><th>Denominador</th></tr></thead>
    <tbody>
    @foreach ($records as $record)
        @foreach ($record->exact_snapshot['values'] as $field => $fraction)
            <tr>
                <td data-type="s">{{ $record->id }}</td><td data-type="s">{{ $record->invoice }}</td><td></td>
                <td data-type="s">{{ $field }}</td><td data-type="s">{{ $record->currency_type_id }}</td>
                <td data-type="s">{{ $fraction['n'] }}</td><td data-type="s">{{ $fraction['d'] }}</td>
            </tr>
        @endforeach
        @foreach ($record->payments as $payment)
            @foreach ($payment['exact_snapshot']['values'] as $field => $fraction)
                <tr>
                    <td data-type="s">{{ $record->id }}</td><td data-type="s">{{ $record->invoice }}</td><td data-type="s">{{ $payment['id'] }}</td>
                    <td data-type="s">{{ $field }}</td>
                    <td data-type="s">{{ $field === 'rate' ? 'VES/USD' : ($field === 'received' ? $payment['currency_type_id'] : $record->currency_type_id) }}</td>
                    <td data-type="s">{{ $fraction['n'] }}</td><td data-type="s">{{ $fraction['d'] }}</td>
                </tr>
            @endforeach
        @endforeach
    @endforeach
    </tbody>
</table>
{{-- ######## FIN MONEDA DEL MEDIO DE COBRO ######## --}}
