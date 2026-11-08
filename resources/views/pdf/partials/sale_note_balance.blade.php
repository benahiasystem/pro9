@php
    $appliedTotal = \App\Support\Exact\Decimal::sum($payments->map(fn ($receipt) => \App\Support\Exact\Decimal::read($receipt, 'payment')));
    $documentTotal = \App\Support\Exact\Decimal::read($document, 'total');
    $payable = \App\Support\Exact\SaleNoteBalance::payable($document);
    $remaining = $payable->minus($appliedTotal);
    $adjustment = $payable->minus($documentTotal);
    $adjustmentDisplay = \App\Support\Exact\Decimal::represent($adjustment);
    if ($adjustmentDisplay === '0' && !$adjustment->isZero()) $adjustmentDisplay = $adjustment->isNegative() ? '>-0.000000000001' : '<0.000000000001';
@endphp
@if(!$payable->isEqualTo($documentTotal))
<tr><td><strong>AJUSTE DE LIQUIDACIÓN:</strong> {{ $document->currency_type->symbol }} {{ $adjustmentDisplay }}</td></tr>
<tr><td><strong>TOTAL LIQUIDABLE:</strong> {{ $document->currency_type->symbol }} {{ \App\Support\Exact\Decimal::format($payable, 2) }}</td></tr>
@endif
<tr><td><strong>SALDO:</strong> {{ $document->currency_type->symbol }} {{ \App\Support\Exact\Decimal::format($remaining, 2) }}</td></tr>
