{{-- ######## INICIO IGTF EN TOTALES DEL TICKET ######## --}}
@foreach(collect($document->taxes ?? [])->where('tax_kind', 'IGTF') as $ticketTax)
    <tr data-ticket-inline-igtf>
        <td colspan="{{ $labelSpan }}" class="{{ $labelClass }}">IGTF @if(data_get($ticketTax, 'percentage') !== null){{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::read($ticketTax, 'percentage'), 2) }}% @endif: {{ $document->currency_type->symbol }}</td>
        <td colspan="{{ $amountSpan ?? 1 }}" class="{{ $amountClass }}">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::read($ticketTax, 'amount'), 2) }}</td>
    </tr>
@endforeach
{{-- ######## FIN IGTF EN TOTALES DEL TICKET ######## --}}
