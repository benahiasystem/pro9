{{-- ######## INICIO CLASIFICACION DE COBROS Y CAJA ######## --}}
@if($showInvoice58Condition ?? false)
    @php
        $ticketCondition = \App\CoreFacturalo\Helpers\Template\TemplateHelper::getDocumentPaymentCondition($document);
        if ($ticketCondition === '-') {
            $ticketCondition = ['01' => 'Contado', '02' => 'Crédito', '03' => 'Crédito'][(string) $document->payment_condition_id] ?? '-';
        }
    @endphp
    <tr><td class="desc-ticket text-left"><div style="font-size: 10px;">CONDICIÓN DE PAGO: {{ $ticketCondition }}</div></td></tr>
@endif
@if (in_array((string) $document->payment_condition_id, ['02', '03'], true))
    @if(!($showInvoice58Condition ?? false))<tr><td class="desc-ticket"><div style="font-size: 10px;">CONDICIÓN: CRÉDITO</div></td></tr>@endif
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
