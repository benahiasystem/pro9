@php
    $methodSnapshot = $row->payment_method_snapshot ?? [];
    $methodName = $methodSnapshot['description'] ?? $row->payment_method_type?->description ?? 'No disponible';
    $receivedCurrency = $row->currency_type_id;
@endphp
{{ $methodName }}{{ $row->reference ? ' - '.$row->reference : '' }} — Aplicado {{ $document->currency_type_id }} {{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::read($row, 'payment')) }}
@if ($receivedCurrency && $row->original_amount !== null && $receivedCurrency !== $document->currency_type_id)
    — Recibido {{ $receivedCurrency }} {{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::read($row, 'original_amount')) }}
    @if ($row->exchange_rate) — Tasa del cobro (VES/USD) {{ $row->exchange_rate }} @endif
@endif
