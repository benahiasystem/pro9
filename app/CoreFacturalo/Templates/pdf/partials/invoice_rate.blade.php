<table class="full-width" style="margin-top: {{ $invoiceFormat === 'a5' ? '4px' : '10px' }};" data-invoice-{{ $invoiceFormat }}-rate>
    <tr><td>Tasa de Cambio: {{ $invoicePdf['rate'] ? 'Bs. '.$invoicePdf['rate'] : 'No disponible' }}</td></tr>
</table>
