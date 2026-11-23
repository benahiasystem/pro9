@php
    use App\Support\Exact\Decimal as D;
    $prepared = \Modules\Report\Services\PurchaseReportAmounts::presentation($records, \Modules\Report\Services\PurchaseReportAmounts::converted($filters['apply_conversion_to_pen'] ?? false));
    $fields = $excel ? ['total_perception','total_exonerated','total_unaffected','total_free','total_taxed','total_igv','total'] : ['total_perception','total_taxed','total_igv','total'];
    $labels = ['total_perception'=>'Percepción','total_exonerated'=>'Total exento','total_unaffected'=>'Total no sujeto','total_free'=>'Gratuito','total_taxed'=>'Base imponible','total_igv'=>'IVA','total'=>'Total'];
@endphp
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Reporte de compras</title>
@if(!$excel)<style>body{font-family:sans-serif;font-size:9px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #aaa;padding:3px}th{background:#e8edf3}.num{text-align:right}</style>@endif
</head><body>
<h3>Reporte de compras</h3>
<p>@include('partials.report_company_header') &nbsp; RIF: {{ $company->number }} &nbsp; Establecimiento: {{ $establishment->description }}</p>
@inject('reportService', 'Modules\Report\Services\ReportService')
@if(!empty($filters['seller_id']))<p>Usuario: {{ $reportService->getUserName($filters['seller_id']) }}</p>@endif
@if(!empty($filters['person_id']))<p>Proveedor: {{ $reportService->getPersonName($filters['person_id']) }}</p>@endif
<table><thead><tr>
<th>#</th><th>Tipo</th><th>Número</th><th>Emisión</th><th>Vencimiento</th><th>Proveedor</th><th>Documento</th><th>Cliente</th><th>Pago</th><th>Estado</th><th>Observación</th><th>Moneda</th>
@foreach($fields as $field)<th>{{ $labels[$field] }}</th>@endforeach
</tr></thead><tbody>
@foreach($prepared['rows'] as $line)
@php $value=$line['model']; @endphp
<tr><td>{{ $loop->iteration }}</td><td>{{ optional($value->document_type)->id }}</td><td data-type="s">{{ $value->number_full }}</td>
<td>{{ optional($value->date_of_issue)->format('Y-m-d') }}</td><td>{{ optional($value->date_of_due)->format('Y-m-d') }}</td>
<td data-type="s">{{ optional($value->supplier)->name }}</td><td data-type="s">{{ $value->supplier ? format_person_identity_document($value->supplier) : '' }}</td>
<td data-type="s">{{ optional($value->customer)->name }} {{ $value->customer ? format_person_identity_document($value->customer) : '' }}</td><td data-type="s">{{ $value->payments->map(fn($payment)=>optional($payment->payment_method_type)->description)->filter()->implode(' - ') }}</td>
<td>{{ optional($value->state_type)->description }}</td><td data-type="s">{{ $value->observation }}</td><td>{{ $line['currency_type_id'] }}{{ $line['converted'] ? ' (convertido de USD)' : '' }}</td>
@foreach($fields as $field)<td class="num" @if($excel) data-type="s" style="mso-number-format:'\@'" @endif>{{ $line['display'][$field] }}</td>@endforeach
</tr>
@endforeach
@foreach($prepared['totals'] as $currency=>$amounts)
<tr><td colspan="11"></td><td>Totales {{ $currency }}</td>
@foreach($fields as $field)<td class="num" @if($excel) data-type="s" style="mso-number-format:'\@'" @endif>{{ D::format($amounts[$field],2,'.','') }}</td>@endforeach
</tr>
@endforeach
</tbody></table>
@if($prepared['rows']->isEmpty())<p>No se encontraron registros.</p>@endif
</body></html>
