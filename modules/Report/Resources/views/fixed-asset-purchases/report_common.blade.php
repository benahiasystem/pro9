@php
    use App\Support\Exact\Decimal as D;
    $prepared = \Modules\Report\Services\PurchaseReportAmounts::presentation($records, false);
    $fields = ['total_perception','total_exonerated','total_unaffected','total_free','total_taxed','total_igv','total'];
    $labels = ['total_perception'=>'Percepción','total_exonerated'=>'Exonerado','total_unaffected'=>'Inafecto','total_free'=>'Gratuito','total_taxed'=>'Gravado','total_igv'=>'IVA','total'=>'Total'];
@endphp
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Compras de activos fijos</title>
@if(!$excel)<style>body{font-family:sans-serif;font-size:9px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #aaa;padding:3px}th{background:#e8edf3}.num{text-align:right}</style>@endif
</head><body>
<h3>Compras de activos fijos</h3>
<p>@include('partials.report_company_header') &nbsp; RIF: {{ $company->number }} &nbsp; Establecimiento: {{ optional($establishment)->description }}</p>
@inject('reportService', 'Modules\Report\Services\ReportService')
@if(!empty($filters['seller_id']))<p>Usuario: {{ $reportService->getUserName($filters['seller_id']) }}</p>@endif
@if(!empty($filters['person_id']))<p>Proveedor: {{ $reportService->getPersonName($filters['person_id']) }}</p>@endif
<table><thead><tr>
<th>#</th><th>Tipo</th><th>Número</th><th>Emisión</th><th>Vencimiento</th><th>Proveedor</th><th>Documento</th><th>Estado</th><th>Moneda</th>
@foreach($fields as $field)<th>{{ $labels[$field] }}</th>@endforeach
</tr></thead><tbody>
@foreach($prepared['rows'] as $line)
@php $value=$line['model']; $supplier=$value->supplier; @endphp
<tr><td>{{ $loop->iteration }}</td><td>{{ optional($value->document_type)->id }}</td><td data-type="s">{{ $value->number_full }}</td>
<td>{{ optional($value->date_of_issue)->format('Y-m-d') }}</td><td>{{ optional($value->date_of_due)->format('Y-m-d') }}</td>
<td data-type="s">{{ $supplier->name ?? '' }}</td><td data-type="s">{{ $supplier ? format_person_identity_document($supplier) : '' }}</td>
<td>{{ optional($value->state_type)->description }}</td><td>{{ $line['currency_type_id'] }}</td>
@foreach($fields as $field)<td class="num" @if($excel) data-type="s" style="mso-number-format:'\@'" @endif>{{ $line['display'][$field] }}</td>@endforeach
</tr>
@endforeach
@foreach($prepared['totals'] as $currency=>$amounts)
<tr><td colspan="8"></td><td>Totales {{ $currency }}</td>
@foreach($fields as $field)<td class="num" @if($excel) data-type="s" style="mso-number-format:'\@'" @endif>{{ D::format($amounts[$field],2,'.','') }}</td>@endforeach
</tr>
@endforeach
</tbody></table>
@if($prepared['rows']->isEmpty())<p>No se encontraron registros.</p>@endif
</body></html>
