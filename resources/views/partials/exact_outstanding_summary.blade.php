{{-- ######## INICIO CALCULOS EXACTOS PRO9 ######## --}}
<table style="width:100%;margin-top:10px">
    <thead><tr><th colspan="2">Resumen del conjunto filtrado</th></tr></thead>
    <tbody>
    @foreach(['consolidated_total_ves'=>'Total documental equivalente (Bs.)', 'consolidated_balance_ves'=>'Saldo consolidado (Bs.)',
        'VES_total'=>'Total original VES', 'VES_balance'=>'Saldo original VES', 'USD_total'=>'Total original USD', 'USD_balance'=>'Saldo original USD'] as $field=>$label)
        <tr><td>{{ $label }}</td><td data-type="s" style="text-align:right">{{ ($excel ?? false) ? $reportSummary[$field] : \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($reportSummary,$field),2) }}</td></tr>
    @endforeach
    </tbody>
</table>
{{-- ######## FIN CALCULOS EXACTOS PRO9 ######## --}}
