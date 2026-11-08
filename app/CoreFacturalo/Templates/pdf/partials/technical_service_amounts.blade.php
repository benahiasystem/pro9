@php
    $amounts = \App\Support\Exact\TechnicalServicePdfAmounts::values($document);
    $currency = $document->currency_type_id ?: 'VES';
@endphp
<table class="full-width mt-10 mb-10">
    <tbody>
        <tr><td class="text-right font-bold">COSTO DEL SERVICIO ({{ $currency }}):</td><td class="text-right font-bold">{{ $amounts['cost'] }}</td></tr>
        <tr><td class="text-right font-bold">PRODUCTOS Y AJUSTES ({{ $currency }}):</td><td class="text-right font-bold">{{ $amounts['products'] }}</td></tr>
        @if($amounts['product_rounding'] !== '0.00')
            <tr><td class="text-right">AJUSTE DE REDONDEO:</td><td class="text-right">{{ $amounts['product_rounding'] }}</td></tr>
        @endif
        <tr><td class="text-right font-bold">TOTAL ({{ $currency }}):</td><td class="text-right font-bold">{{ $amounts['total'] }}</td></tr>
        <tr><td class="text-right font-bold">COBROS REGISTRADOS ({{ $currency }}):</td><td class="text-right font-bold">{{ $amounts['paid'] }}</td></tr>
        @if($amounts['balance_rounding'] !== '0.00')
            <tr><td class="text-right">AJUSTE DE REDONDEO DEL SALDO:</td><td class="text-right">{{ $amounts['balance_rounding'] }}</td></tr>
        @endif
        <tr><td class="text-right font-bold">SALDO A PAGAR ({{ $currency }}):</td><td class="text-right font-bold">{{ $amounts['balance'] }}</td></tr>
        @if($amounts['has_legacy_advance'])
            <tr><td class="text-right">ANTICIPO INFORMADO (REFERENCIAL, NO SE RESTA):</td><td class="text-right">{{ $amounts['legacy_advance'] }}</td></tr>
        @endif
    </tbody>
</table>
