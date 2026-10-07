{{-- ######## INICIO PERSISTENCIA FISCAL VENEZUELA ######## --}}
@if($document instanceof \App\Models\Tenant\Document)
<table width="100%" style="font-size:10px; margin-top:8px">
    @foreach($document->taxes->where('tax_kind', 'IGTF') as $tax)
    <tr><td>IGTF {{ number_format($tax->percentage, 2) }}%</td><td style="text-align:right">{{ $document->currency_type->symbol }} {{ number_format($tax->amount, 2) }}</td></tr>
    @endforeach
    @foreach($document->payments->whereNull('receipt_parent_id') as $payment)
    @if($payment->currency_type_id !== $document->currency_type_id || $payment->tax_amount > 0)
    <tr><td>Cobro recibido ({{ $payment->currency_type_id }})</td><td style="text-align:right">{{ number_format($payment->cash_received_amount, 2) }}</td></tr>
    {{-- ######## INICIO TASAS OCHO DECIMALES ######## --}}
    <tr><td>Tasa del cobro VES/USD</td><td style="text-align:right">{{ str_replace('.', ',', \App\Services\ExchangeRates\ExchangeRateMath::rate($payment->exchange_rate)) }}</td></tr>
    {{-- ######## FIN TASAS OCHO DECIMALES ######## --}}
    <tr><td>Principal aplicado ({{ $document->currency_type_id }})</td><td style="text-align:right">{{ number_format($payment->payment, 2) }}</td></tr>
    @endif
    @endforeach
    @foreach($document->received_retentions as $retention)
    <tr><td>Retención {{ $retention->tax_kind }} — {{ $retention->voucher_number }}</td><td style="text-align:right">{{ number_format($retention->applied_amount, 2) }}</td></tr>
    @endforeach
    @if($document->guarantee_amount > 0)
    <tr><td>Fondo de garantía</td><td style="text-align:right">{{ number_format($document->guarantee_amount, 2) }}</td></tr>
    @endif
    @if($document->currency_type_id === 'USD' && $document->currency_totals)
    <tr><td>Tasa VES/USD ({{ $document->exchange_rate_source }}, {{ optional($document->exchange_rate_date)->format('d/m/Y') }})</td><td style="text-align:right">{{ str_replace('.', ',', \App\Services\ExchangeRates\ExchangeRateMath::rate($document->exchange_rate_sale)) }}</td></tr>
    <tr><td>IVA en VES</td><td style="text-align:right">Bs. {{ number_format($document->currency_totals->iva, 2) }}</td></tr>
    <tr><td>Total en VES</td><td style="text-align:right">Bs. {{ number_format($document->currency_totals->total, 2) }}</td></tr>
    @endif
</table>
@endif
{{-- ######## FIN PERSISTENCIA FISCAL VENEZUELA ######## --}}
