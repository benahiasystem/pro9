<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Comisiones pendientes</title>
@if(!$excel)<style>body{font-family:sans-serif;font-size:10px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #aaa;padding:4px}</style>@endif
</head><body><h3>Comisiones por cuentas pendientes</h3>
<p>@include('partials.report_company_header') &nbsp; RIF: {{ $company->number }}</p>
<p>Importes en moneda del documento. Se comisionan cobros posteriores al vencimiento dentro del plazo configurado.</p>
<p>Sin restricción activa o vencimiento no se calcula comisión. Una comisión fija sin moneda definida no se calcula.</p>
<table><thead><tr><th>Usuario</th><th>Moneda</th><th>Cobro sin comisión</th><th>Cobro elegible</th><th>Total cobrado</th><th>Comisión</th></tr></thead><tbody>
@foreach($records as $user)
@if(empty($user['currency_rows']))<tr><td data-type="s">{{ $user['user_name'] }}</td><td colspan="5">Sin cobros en el período</td></tr>@endif
@foreach($user['currency_rows'] as $row)
<tr><td data-type="s">{{ $user['user_name'] }}</td><td data-type="s">{{ $row['currency_type_id'] }}</td>
@foreach(['total_out_of_range','total_within_range','total_completed'] as $field)<td data-type="s" style="mso-number-format:'\@'">{{ $row[$field] }}</td>@endforeach
<td data-type="s" style="mso-number-format:'\@'">{{ $row['commission_reason'] ?? $row['commission'] }}</td></tr>
@endforeach
@endforeach
</tbody></table></body></html>
