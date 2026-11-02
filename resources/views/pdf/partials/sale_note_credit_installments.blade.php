{{-- ######## INICIO CLASIFICACION DE COBROS Y CAJA ######## --}}
@if (in_array((string) $document->payment_condition_id, ['02', '03'], true))
    <table class="full-width">
        <tr><td class="m27-label">CONDICIÓN DE PAGO: Crédito</td></tr>
        @foreach ($document->fee as $key => $quote)
            <tr><td class="m27-value">&#8226; Cuota #{{ $key + 1 }} / {{ $quote->date->format('d-m-Y') }} /
                {{ $quote->currency_type->symbol }} {{ $quote->amount }}</td></tr>
        @endforeach
    </table>
@endif
{{-- ######## FIN CLASIFICACION DE COBROS Y CAJA ######## --}}
