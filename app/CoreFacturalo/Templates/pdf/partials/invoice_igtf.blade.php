{{-- ######## INICIO PRESENTACION FACTURA A4/A5 ######## --}}
@foreach(($invoicePdf['igtf'] ?? []) as $tax)
<tr data-invoice-{{ $invoiceFormat }}-igtf>
    <td colspan="{{ $invoiceTaxColspan ?? $colspan_total }}" class="text-right pr-2">IGTF {{ $tax['percentage'] }}%: {{ $document->currency_type->symbol }}</td>
    <td class="text-right">{{ $tax['amount'] }}</td>
</tr>
@endforeach
{{-- ######## FIN PRESENTACION FACTURA A4/A5 ######## --}}
