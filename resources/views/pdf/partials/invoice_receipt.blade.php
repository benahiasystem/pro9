{{-- ######## INICIO MONEDA DEL MEDIO DE COBRO ######## --}}
@php
    $receiptCurrency = $row->currency_type_id ?: $document->currency_type_id;
    $hasReceivedAmount = $row->original_amount !== null;
    $received = \App\Support\Exact\Decimal::read($row, $hasReceivedAmount ? 'original_amount' : 'payment');
    $change = \App\Support\Exact\Decimal::read($row, 'change');
    $tax = $row->receipt_parent_id ? \App\Support\Exact\Decimal::of(0) : \App\Support\Exact\Decimal::read($row, 'tax_amount');
    $tendered = $received->plus($change)->plus($tax);
    $methodSnapshot = $row->payment_method_snapshot ?? [];
    $isPriorReceipt = !empty($methodSnapshot['receipt_origin']);
    $methodName = $methodSnapshot['description'] ?? $row->payment_method_type?->description ?? 'No disponible';
    $adjustment = \App\Support\Exact\Decimal::read($row, 'payment_adjustment');
    $adjustmentText = \App\Support\Exact\Decimal::represent($adjustment);
    if (!\App\Support\Exact\Decimal::of($adjustmentText)->isEqualTo($adjustment)) {
        $fraction = \App\Support\Exact\Decimal::fraction($adjustment);
        $adjustmentText = $fraction['n'].'/'.$fraction['d'];
    }
@endphp
@if ($isPriorReceipt) Cobro previo — @elseif ($row->receipt_parent_id) Aplicación de IGTF — @endif{{ $methodName }}{{ $row->reference ? ' - '.$row->reference : '' }} —
@if ($hasReceivedAmount)
    Recibido {{ $receiptCurrency }} {{ \App\Support\Exact\Decimal::format($tendered) }}
@else
    Aplicado {{ $document->currency_type_id }} {{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::read($row, 'payment')) }}
@endif
@if ($hasReceivedAmount && $receiptCurrency !== $document->currency_type_id)
    — Aplicado {{ $document->currency_type_id }} {{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::read($row, 'payment')) }}
    {{-- ######## INICIO TASA ÚNICA EN FACTURA TICKET ######## --}}
    @if ($row->exchange_rate && !(($ticketInvoice ?? false) && $document instanceof \App\Models\Tenant\Document
        && $row->receipt_parent_id === null && $row->reversed_at === null))
        — Tasa del cobro (VES/USD) {{ $row->exchange_rate }}
    @endif
    {{-- ######## FIN TASA ÚNICA EN FACTURA TICKET ######## --}}
@endif
@if ($hasReceivedAmount && $change->isPositive()) — Vuelto {{ $receiptCurrency }} {{ \App\Support\Exact\Decimal::format($change) }} @endif
@if ($tax->isPositive()) — IGTF {{ $receiptCurrency }} {{ \App\Support\Exact\Decimal::format($tax) }} @endif
@if (!$adjustment->isZero()) — Ajuste de liquidación {{ $document->currency_type_id }} {{ \App\Support\Exact\Decimal::format($adjustment) }}@if (!\App\Support\Exact\Decimal::of(\App\Support\Exact\Decimal::represent($adjustment, 2))->isEqualTo($adjustment)) (exacto: {{ $adjustmentText }})@endif @endif
{{-- ######## FIN MONEDA DEL MEDIO DE COBRO ######## --}}
