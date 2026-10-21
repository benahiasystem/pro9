{{-- ######## INICIO CALCULOS EXACTOS PRO9 ######## --}}
@php($componentDisplay = ($excel ?? false) ? [] : \App\Support\Exact\SalesConsolidated::displayComponents($reportSummary))
<table style="width:100%;margin-top:8px">
    <thead><tr><th colspan="2">Composición del total documental (Bs.)</th></tr></thead>
    <tbody>
    @foreach(\App\Support\Exact\SalesConsolidated::COMPONENT_LABELS as $field=>$label)
        @if($field === 'line_total' || !\App\Support\Exact\Decimal::readRequired($reportSummary, $field)->isZero())
        <tr><td>{{ $label }}</td><td data-type="s" style="text-align:right">
            @if($excel ?? false)
                {{ \App\Support\Exact\Decimal::represent(in_array($field, ['header_discount','header_prepayment'], true) ? \App\Support\Exact\Decimal::readRequired($reportSummary, $field)->negated() : \App\Support\Exact\Decimal::readRequired($reportSummary, $field)) }}
            @else
                {{ $componentDisplay[$field] }}
            @endif
        </td></tr>
        @endif
    @endforeach
    </tbody>
</table>
{{-- ######## FIN CALCULOS EXACTOS PRO9 ######## --}}
