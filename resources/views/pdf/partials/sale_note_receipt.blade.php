{{-- ######## INICIO MONEDA DEL MEDIO DE COBRO ######## --}}
@php
    $receiptCurrency = $row->currency_type_id ?: $document->currency_type_id;
    $snapshot = $row->calculation_snapshot ?? [];
    if (is_string($snapshot)) $snapshot = json_decode($snapshot, true) ?: [];
    $method = $row->payment_method_snapshot ?? [];
    $isPriorReceipt = !empty($method['receipt_origin']);
    $change = $isPriorReceipt ? \App\Support\Exact\Decimal::of(0) : (isset($snapshot['values']['received_change'])
        ? \App\Support\Exact\Decimal::of($snapshot['values']['received_change'])
        : ($receiptCurrency === $document->currency_type_id ? \App\Support\Exact\Decimal::read($row, 'change') : null));
    $hasOriginal = $row->original_amount !== null;
    $received = \App\Support\Exact\Decimal::read($row, $hasOriginal ? 'original_amount' : 'payment');
    $methodName = $method['description'] ?? $row->payment_method_type?->description ?? 'No disponible';
@endphp
@if ($isPriorReceipt) Cobro previo — @endif{{ $methodName }}{{ $row->reference ? ' - '.$row->reference : '' }} —
@if ($hasOriginal && $change !== null)
    Recibido {{ $receiptCurrency }} {{ \App\Support\Exact\Decimal::format($received->plus($change)) }}
@else
    Aplicado {{ $document->currency_type_id }} {{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::read($row, 'payment')) }}
@endif
@if ($hasOriginal && $change !== null && $receiptCurrency !== $document->currency_type_id)
    — Aplicado {{ $document->currency_type_id }} {{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::read($row, 'payment')) }}
    @if ($row->exchange_rate) — Tasa del cobro (VES/USD) {{ $row->exchange_rate }} @endif
@endif
@if ($change !== null && $change->isPositive()) — Vuelto {{ $receiptCurrency }} {{ \App\Support\Exact\Decimal::format($change) }} @endif
{{-- ######## FIN MONEDA DEL MEDIO DE COBRO ######## --}}
