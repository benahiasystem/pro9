{{-- ######## INICIO PRESENTACION FACTURA A4/A5 ######## --}}
<div data-invoice-{{ $invoiceFormat }}-identity style="text-align: left;">
    <div style="font-size: 14pt; font-weight: bold; margin-bottom: 5px;">{{ $document->document_type->description }}</div>
    <div style="font-size: 10pt;">N° de documento: <strong>{{ $invoicePdf['number'] }}</strong></div>
    <div style="font-size: 10pt;">N° de control: <strong>{{ $invoicePdf['control'] }}</strong></div>
</div>
{{-- ######## FIN PRESENTACION FACTURA A4/A5 ######## --}}
