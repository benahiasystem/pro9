@extends('cashreport::layouts.ticket')
{{-- ######## INICIO CLASIFICACION DE COBROS Y CAJA ######## --}}
@section('content')
    <h3 class="section">Efectivo físico del turno completo</h3>
    @foreach($classified['balances_by_currency'] as $currency=>$balance)
        <p><strong>{{ $currency === 'VES' ? 'Bolívares (Bs.)' : 'Dólares (USD)' }}</strong></p>
        <table class="grid">
            @foreach(['beginning_balance'=>'Apertura','income'=>'Entradas','egress'=>'Salidas','final_balance'=>'Saldo final'] as $field=>$label)
                <tr><td>{{ $label }}</td><td class="text-right">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($balance,$field)) }}</td></tr>
            @endforeach
        </table>
    @endforeach
    <h3 class="section">Medios y cuentas</h3>
    @foreach($classified['groups'] as $group)
        <p><strong>{{ $group['method']['description'] }}</strong> · {{ \App\Services\Payments\Classification::kindLabel($group['method']['collection_kind'] ?? null) }}</p>
        <p>{{ $group['destination_label'] }} · {{ $group['currency_type_id'] }}</p>
        <table class="grid"><tr><td>Entradas</td><td class="text-right">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($group,'income')) }}</td></tr><tr><td>Salidas</td><td class="text-right">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($group,'egress')) }}</td></tr></table>
    @endforeach
    @isset($classified['credit_pending'])
        <h3 class="section">Crédito pendiente</h3>
        @foreach($classified['credit_pending'] as $currency=>$amount)<p>{{ $currency }} {{ \App\Support\Exact\Decimal::format($amount) }}</p>@endforeach
    @endisset
    @if($classified['show_detail'])
        <h3 class="section">Movimientos</h3>
        @foreach($classified['rows'] as $row)
            <p><strong>{{ $row['date'] }} {{ $row['number'] }}</strong></p>
            <p>{{ $row['method']['description'] }} · {{ \App\Services\Payments\Classification::kindLabel($row['method']['collection_kind'] ?? null) }}</p>
            <p>{{ $row['opening'] ? 'Apertura' : ($row['direction']==='income' ? 'Entrada' : 'Salida') }} · {{ $row['currency_type_id'] }} {{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($row,'amount')) }}</p>
            @if(\App\Support\Exact\Decimal::readRequired($row,'tax')->isPositive())<p>Incluye IGTF {{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($row,'tax')) }}</p>@endif
        @endforeach
    @endif
@endsection
{{-- ######## FIN CLASIFICACION DE COBROS Y CAJA ######## --}}
