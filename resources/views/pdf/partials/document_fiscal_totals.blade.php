{{-- ######## INICIO PERSISTENCIA FISCAL VENEZUELA ######## --}}
@if($document instanceof \App\Models\Tenant\Document)
<table width="100%" style="font-size:10px; margin-top:8px">
    @if($document->document_type_id === '08')
    @foreach($document->payments->whereNull('reversed_at') as $payment)
    <tr><td colspan="2">@include('pdf.partials.invoice_receipt', ['row'=>$payment,'document'=>$document])</td></tr>
    @endforeach
    @endif
    {{-- ######## INICIO IGTF EN TOTALES DEL TICKET ######## --}}
    @if(!($omitTicketIgtf ?? false))
    @foreach($document->taxes->where('tax_kind', 'IGTF') as $tax)
    <tr><td>IGTF {{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::read($tax, 'percentage'), 2) }}%</td><td style="text-align:right">{{ $document->currency_type->symbol }} {{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::read($tax, 'amount'), 2) }}</td></tr>
    @endforeach
    @endif
    {{-- ######## FIN IGTF EN TOTALES DEL TICKET ######## --}}
    @foreach($document->payments->whereNull('receipt_parent_id')->whereNull('reversed_at') as $payment)
    @if($payment->currency_type_id !== $document->currency_type_id || \App\Support\Exact\Decimal::read($payment, 'tax_amount')->isPositive())
    @if($payment->original_amount !== null)
    <tr><td>{{ !empty($payment->payment_method_snapshot['receipt_origin']) ? 'Cobro previo recibido' : 'Cobro recibido' }} ({{ $payment->currency_type_id }})</td><td style="text-align:right">{{ \App\Support\Exact\Decimal::format($payment->cash_received_amount, 2) }}</td></tr>
    @else
    <tr><td>Importe recibido no disponible</td><td></td></tr>
    @endif
    {{-- ######## INICIO TASAS OCHO DECIMALES ######## --}}
    @if($payment->exchange_rate)
    <tr><td>Tasa del cobro VES/USD</td><td style="text-align:right">{{ str_replace('.', ',', \App\Services\ExchangeRates\ExchangeRateMath::rate($payment->exchange_rate)) }}</td></tr>
    @endif
    {{-- ######## FIN TASAS OCHO DECIMALES ######## --}}
    <tr><td>Principal aplicado ({{ $document->currency_type_id }})</td><td style="text-align:right">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::read($payment, 'payment'), 2) }}</td></tr>
    @endif
    @endforeach
    @foreach($document->received_retentions as $retention)
    @if(\App\Support\Exact\Decimal::read($retention, 'applied_amount')->isPositive())
    <tr><td>Retención {{ $retention->tax_kind }} — {{ $retention->voucher_number }}</td><td style="text-align:right">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::read($retention, 'applied_amount'), 2) }}</td></tr>
    @endif
    @endforeach
    @php($islrPrevision = $document->relationLoaded('fiscal_data') ? optional($document->getRelation('fiscal_data'))->islr_retention_prevision : null)
    @if($islrPrevision)
    <tr><td>Retención ISLR prevista (informativa)</td><td style="text-align:right">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($islrPrevision, 'amount'), 2) }}</td></tr>
    @endif
    @php($prevision = $document->relationLoaded('fiscal_data') ? optional($document->getRelation('fiscal_data'))->iva_retention_prevision : null)
    @if($prevision)
    <tr><td>Retención IVA prevista (informativa)</td><td style="text-align:right">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($prevision, 'amount'), 2) }}</td></tr>
    <tr><td>Saldo pendiente</td><td style="text-align:right">{{ \App\Support\Exact\Decimal::format($document->exactBalance(), 2) }}</td></tr>
    @endif
    @php($guaranteeAmount = $document->exactGuaranteeAmount())
    @if($guaranteeAmount->isPositive())
    <tr><td>Fondo de garantía</td><td style="text-align:right">{{ \App\Support\Exact\Decimal::format($guaranteeAmount, 2) }}</td></tr>
    @endif
    @if($document->currency_type_id === 'USD' && $document->currency_totals)
    <tr><td>Tasa VES/USD ({{ $document->exchange_rate_source }}, {{ optional($document->exchange_rate_date)->format('d/m/Y') }})</td><td style="text-align:right">{{ str_replace('.', ',', \App\Services\ExchangeRates\ExchangeRateMath::rate($document->exchange_rate_sale)) }}</td></tr>
    <tr><td>IVA en VES</td><td style="text-align:right">Bs. {{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::read($document->currency_totals, 'iva'), 2) }}</td></tr>
    <tr><td>Total en VES</td><td style="text-align:right">Bs. {{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::read($document->currency_totals, 'total'), 2) }}</td></tr>
    @endif
</table>
@endif
{{-- ######## FIN PERSISTENCIA FISCAL VENEZUELA ######## --}}
