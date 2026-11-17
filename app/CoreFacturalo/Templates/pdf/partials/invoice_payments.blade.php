{{-- ######## INICIO PRESENTACION FACTURA A4/A5 ######## --}}
@if($invoicePdf['receipts'])
<table class="full-width" data-invoice-{{ $invoiceFormat }}-payments>
    <thead>
    <tr><td width="{{ ($invoiceSideInfo ?? false) ? '62%' : '100%' }}"><strong>PAGOS:</strong></td>
        @if($invoiceSideInfo ?? false)
        <td width="38%">@if(isset($configurationInPdf) && $configurationInPdf->show_seller_in_pdf)<strong>Vendedor:</strong> {{ $document->seller ? $document->seller->name : $document->user->name }}@endif</td>
        @endif
    </tr>
    </thead>
    <tbody>
    @foreach($invoicePdf['receipts'] as $receipt)
    <tr><td @if($invoiceSideInfo ?? false) colspan="2" @endif>
        &#8226; @if($receipt['amount'] !== null){{ $receipt['net'] ? 'Recibido neto' : 'Recibido' }} {{ $receipt['currency'] }} {{ $receipt['amount'] }}@else Importe recibido no disponible @endif
        @if($receipt['previous']) (cobro previo)@endif
        @if($receipt['different_currency'])
        <br><span style="font-size: 8pt;">Aplicado {{ $invoicePdf['currency'] }} {{ $receipt['applied'] }}@if($receipt['rate']) — Tasa del cobro VES/USD: {{ $receipt['rate'] }}@endif</span>
        @endif
    </td></tr>
    @endforeach
    @foreach($invoicePdf['changes'] as $change)
    <tr><td @if($invoiceSideInfo ?? false) colspan="2" @endif>— Vuelto {{ $change['currency'] }} {{ $change['amount'] }}@if($change['equivalent']) <span style="font-size: 8pt;">(equivalente conservado)</span>@endif</td></tr>
    @endforeach
    </tbody>
</table>
@endif
@if($invoiceShowRate ?? true)
@include('pdf.partials.invoice_rate')
@endif
{{-- ######## FIN PRESENTACION FACTURA A4/A5 ######## --}}
