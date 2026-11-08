@php
    use App\Support\Exact\Decimal as D;
    $prepared=\Modules\Report\Services\ItemReportAmounts::presentation($records);
@endphp
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Reporte por producto</title></head><body>
<h3>Reporte por producto</h3><p>@include('partials.report_company_header') &nbsp; RIF: {{ $company->number }} &nbsp; Establecimiento: {{ $establishment->description }}</p>
<p>Importes de partidas en moneda documental, sin ajustes globales. Cantidades separadas por unidad documental.</p>
<table><thead><tr><th>#</th><th>Fecha</th><th>Tipo documento</th><th>Número</th><th>Documento</th><th>Cliente / proveedor</th><th>Producto</th>
@if($type !== 'purchase')<th>Plataforma</th>@endif
@if($extra)<th>Datos complementarios</th>@endif
<th>Unidad</th><th>Cantidad</th><th>Moneda</th><th>Importe de partida</th></tr></thead><tbody>
@foreach($prepared['rows'] as $line)
@php
    $value=$line['model']; $document=$line['document'];
    $person=$type === 'purchase' ? $document->supplier : $document->customer;
    $extraData=$extra && method_exists($value,'getPrintExtraData') ? collect($value->getPrintExtraData())->except(['image_url','image_url_medium','image_url_small'])->filter(fn($v)=>$v !== null && $v !== '') : collect();
@endphp
<tr><td>{{ $loop->iteration }}</td><td>{{ optional($document->date_of_issue)->format('Y-m-d') }}</td>
<td>{{ optional($document->document_type)->description }}</td><td data-type="s">{{ $document->number_full }}</td>
<td data-type="s">{{ $person ? format_person_identity_document($person) : '' }}</td><td data-type="s">{{ $person->name ?? '' }}</td><td data-type="s">{{ $value->item->description ?? '' }}</td>
@if($type !== 'purchase')<td data-type="s">{{ optional(optional($value->relation_item)->web_platform)->name }}</td>@endif
@if($extra)<td data-type="s">@foreach($extraData as $key=>$valueExtra){{ $key }}: {{ is_scalar($valueExtra) ? $valueExtra : json_encode($valueExtra,JSON_UNESCAPED_UNICODE) }}<br>@endforeach</td>@endif
<td>{{ $line['unit_type_id'] }}</td><td data-type="s" style="mso-number-format:'\@'">{{ $line['quantity'] }}</td>
<td>{{ $line['currency_type_id'] }}</td><td data-type="s" style="mso-number-format:'\@'">{{ $line['display_total'] }}</td></tr>
@endforeach
@foreach($prepared['amounts'] as $currency=>$total)
<tr><td colspan="{{ 7 + ($type !== 'purchase' ? 1 : 0) + ($extra ? 1 : 0) }}"></td><td colspan="2">Total {{ $currency }}</td><td></td><td data-type="s" style="mso-number-format:'\@'">{{ D::format($total,2,'.','') }}</td></tr>
@endforeach
@foreach($prepared['quantities'] as $unit=>$quantity)
<tr><td colspan="{{ 7 + ($type !== 'purchase' ? 1 : 0) + ($extra ? 1 : 0) }}"></td><td>{{ $unit }}</td><td data-type="s" style="mso-number-format:'\@'">{{ D::represent($quantity) }}</td></tr>
@endforeach
</tbody></table>@if($prepared['rows']->isEmpty())<p>No se encontraron registros.</p>@endif</body></html>
