{{-- ######## INICIO PRESENTACION FACTURA A4/A5 ######## --}}
@if($invoicePdf['retentions'])
<table class="full-width {{ $invoiceFormat === 'a5' ? 'invoice-a5-fiscal' : '' }}" style="{{ $invoiceFormat === 'a5' ? 'border-top: 0.5pt solid #999;' : '' }} margin-top: {{ $invoiceFormat === 'a5' ? '4px' : '10px' }};" data-invoice-{{ $invoiceFormat }}-retentions>
    <tr><td colspan="2"><strong>RETENCIONES APLICADAS</strong></td></tr>
    @foreach($invoicePdf['retentions'] as $retention)
    <tr>
        <td>Retención {{ $retention['kind'] }} — {{ $retention['number'] }}@if($retention['date']) — {{ $retention['date'] }}@endif</td>
        <td class="text-right">{{ $invoicePdf['currency'] }} {{ $retention['applied'] }}</td>
    </tr>
    <tr><td colspan="2" style="font-size: 8pt; padding-bottom: {{ $invoiceFormat === 'a5' ? '1px' : '5px' }};">
        {{ implode(' / ', $retention['details']) }}@if($retention['percentage'] !== null) / {{ $retention['percentage'] }}%@endif
        @if($retention['amount'] !== null && $retention['currency'] !== $invoicePdf['currency']) / Retenido {{ $retention['currency'] }} {{ $retention['amount'] }}; aplicado {{ $invoicePdf['currency'] }} {{ $retention['applied'] }}@endif
    </td></tr>
    @endforeach
</table>
@endif
@if($invoicePdf['forecasts'])
<table class="full-width {{ $invoiceFormat === 'a5' ? 'invoice-a5-fiscal' : '' }}" style="{{ $invoiceFormat === 'a5' ? 'border-top: 0.5pt solid #999;' : '' }} margin-top: {{ $invoiceFormat === 'a5' ? '3px' : '8px' }};" data-invoice-{{ $invoiceFormat }}-forecasts>
    <tr><td colspan="2"><strong>RETENCIONES PREVISTAS{{ $invoicePdf['historical_forecasts'] ? ' DEL DOCUMENTO GUARDADO' : '' }} — INFORMATIVAS</strong></td></tr>
    @foreach($invoicePdf['forecasts'] as $forecast)
    <tr><td>Retención {{ $forecast['kind'] }} prevista (informativa), {{ $forecast['percentage'] }}%</td><td class="text-right">{{ $invoicePdf['currency'] }} {{ $forecast['amount'] }}</td></tr>
    <tr><td>Pendiente de aplicar {{ $forecast['kind'] }}</td><td class="text-right">{{ $invoicePdf['currency'] }} {{ $forecast['remaining'] }}</td></tr>
    @endforeach
</table>
@endif
@if($invoicePdf['balance'] !== null || $invoicePdf['guarantee'] !== null || $invoicePdf['ves_totals'])
<table class="full-width {{ $invoiceFormat === 'a5' ? 'invoice-a5-fiscal' : '' }}" style="{{ $invoiceFormat === 'a5' ? 'border-top: 0.5pt solid #999;' : '' }} margin-top: {{ $invoiceFormat === 'a5' ? '3px' : '8px' }};" data-invoice-{{ $invoiceFormat }}-balance>
    @if($invoicePdf['guarantee'] !== null)<tr><td>Fondo de garantía</td><td class="text-right">{{ $invoicePdf['currency'] }} {{ $invoicePdf['guarantee'] }}</td></tr>@endif
    @if($invoicePdf['balance'] !== null)<tr><td><strong>Saldo pendiente</strong></td><td class="text-right"><strong>{{ $invoicePdf['currency'] }} {{ $invoicePdf['balance'] }}</strong></td></tr>@endif
    @if($invoicePdf['ves_totals'])
    <tr><td>IVA en VES</td><td class="text-right">Bs. {{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::read($invoicePdf['ves_totals'], 'iva')) }}</td></tr>
    <tr><td>Total en VES</td><td class="text-right">Bs. {{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::read($invoicePdf['ves_totals'], 'total')) }}</td></tr>
    @endif
</table>
@endif
@if($invoicePdf['later_igtf'])
<table class="full-width {{ $invoiceFormat === 'a5' ? 'invoice-a5-fiscal' : '' }}" style="{{ $invoiceFormat === 'a5' ? 'border-top: 0.5pt solid #999;' : '' }} margin-top: {{ $invoiceFormat === 'a5' ? '4px' : '10px' }};" data-invoice-{{ $invoiceFormat }}-later-igtf>
    <tr><td colspan="2"><strong>IGTF POSTERIOR — REFERENCIAS</strong></td></tr>
    <tr><td colspan="2" style="font-size: 8pt;">Importes originales de las notas; no se suman al total de esta factura.</td></tr>
    @foreach($invoicePdf['later_igtf'] as $reference)
    <tr><td>ND {{ $reference['number'] }} — {{ $reference['status'] }}</td><td class="text-right">{{ $reference['currency'] }} {{ $reference['amount'] }}</td></tr>
    <tr><td colspan="2" style="font-size: 8pt;">N° de control: {{ $reference['control'] }} / Origen: {{ $reference['origin'] }}</td></tr>
        @foreach($reference['adjustments'] as $adjustment)
        <tr><td>Ajuste NC {{ $adjustment['number'] }} — {{ $adjustment['status'] }}</td><td class="text-right">{{ $adjustment['currency'] }} {{ $adjustment['amount'] }}</td></tr>
            @foreach($adjustment['refunds'] as $refund)
            <tr><td>{{ $refund['reversed'] ? 'Devolución revertida' : 'Devolución registrada' }}</td><td class="text-right">{{ $refund['currency'] }} {{ $refund['amount'] }}</td></tr>
            @endforeach
        @endforeach
    @endforeach
</table>
@endif
{{-- ######## FIN PRESENTACION FACTURA A4/A5 ######## --}}
