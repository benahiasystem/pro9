{{-- ######## INICIO MONEDA DEL MEDIO DE COBRO ######## --}}
@php
    $receiptCurrency = $row->currency_type_id ?: $document->currency_type_id;
    $hasReceivedAmount = $row->original_amount !== null;
    $received = \App\Support\Exact\Decimal::read($row, $hasReceivedAmount ? 'original_amount' : 'payment');
    $change = \App\Support\Exact\Decimal::read($row, 'change');
    $tendered = $received->plus($change);
    $methodSnapshot = $row->payment_method_snapshot ?? [];
    $isPriorReceipt = !empty($methodSnapshot['receipt_origin']);
    $methodName = $methodSnapshot['description'] ?? $row->payment_method_type?->description ?? 'No disponible';
@endphp
@if ($isPriorReceipt) Cobro previo — @elseif ($row->receipt_parent_id) Aplicación de IGTF — @endif{{ $methodName }}{{ $row->reference ? ' - '.$row->reference : '' }} —
@if ($hasReceivedAmount)
    Recibido {{ $receiptCurrency }} {{ \App\Support\Exact\Decimal::format($tendered) }}
@else
    Aplicado {{ $document->currency_type_id }} {{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::read($row, 'payment')) }}
@endif
@if ($hasReceivedAmount && $receiptCurrency !== $document->currency_type_id)
    — Aplicado {{ $document->currency_type_id }} {{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::read($row, 'payment')) }}
    @if ($row->exchange_rate) — Tasa del cobro (VES/USD) {{ $row->exchange_rate }} @endif
@endif
@if ($hasReceivedAmount && $change->isPositive()) — Vuelto {{ $receiptCurrency }} {{ \App\Support\Exact\Decimal::format($change) }} @endif
{{-- ######## FIN MONEDA DEL MEDIO DE COBRO ######## --}}
