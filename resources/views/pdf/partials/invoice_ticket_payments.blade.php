{{-- ######## INICIO CLASIFICACION DE COBROS Y CAJA ######## --}}
@if (in_array((string) $document->payment_condition_id, ['02', '03'], true))
    <tr><td class="desc-ticket"><div style="font-size: 10px;">CONDICIÓN: CRÉDITO</div></td></tr>
    @foreach ($document->fee as $key => $quote)
        <tr><td class="desc-ticket text-uppercase"><div style="font-size: 10px;">
            &#8226; Cuota #{{ $key + 1 }} / Fecha: {{ $quote->date->format('d-m-Y') }} /
            Monto: {{ $quote->currency_type->symbol }}{{ $quote->amount }}
        </div></td></tr>
    @endforeach
@endif
@if ($payments->count())
    <tr><td class="desc-ticket"><div style="font-size: 10px;">PAGOS:</div></td></tr>
    @foreach ($payments as $row)
        <tr><td class="desc-ticket text-uppercase"><div style="font-size: 10px;">
            &#8226; @include('pdf.partials.invoice_receipt', ['row' => $row, 'document' => $document])
        </div></td></tr>
    @endforeach
@endif
{{-- ######## FIN CLASIFICACION DE COBROS Y CAJA ######## --}}
