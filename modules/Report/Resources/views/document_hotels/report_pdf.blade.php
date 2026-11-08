<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Reporte de huéspedes</title>
<style>body{font-family:sans-serif;font-size:9px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #aaa;padding:4px;vertical-align:top}th{background:#e8edf3}thead{display:table-header-group}tr{page-break-inside:avoid}</style></head><body>
<h3>Reporte de huéspedes y comprobantes</h3>
<p>@include('partials.report_company_header') &nbsp; RIF: {{ $company->number }}</p>
<p>El total corresponde al importe original del comprobante, incluso si fue anulado o rechazado.</p>
<table><thead><tr><th>Huésped e identidad</th><th>Datos personales</th><th>Nacionalidad y procedencia</th><th>Habitación y estancia</th><th>Comprobante y estado</th><th>Moneda</th><th>Total original</th></tr></thead><tbody>
@foreach($records as $hotel)
@php $document=$hotel->document; @endphp
<tr><td>{{ $hotel->name }}<br>{{ optional($hotel->identity_document_type)->description }}: {{ $hotel->number }}</td>
<td>Sexo: {{ $hotel->sex }}<br>Edad: {{ $hotel->age }}<br>Estado civil: {{ $hotel->civil_status }}<br>Ocupación: {{ $hotel->ocupation }}</td>
<td>{{ $hotel->nacionality }}<br>{{ $hotel->origin }}</td>
<td>{{ $hotel->room_number }}<br>Entrada: {{ $hotel->date_entry }} {{ $hotel->time_entry }}<br>Salida: {{ $hotel->date_exit }} {{ $hotel->time_exit }}</td>
<td>{{ $document->number_full }}<br>{{ optional($document->state_type)->description }}</td><td>{{ $document->currency_type_id }}</td>
<td>{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($document,'total'),2,'.','') }}</td></tr>
@endforeach
</tbody></table>
@if($records->isEmpty())<p>No se encontraron registros.</p>@endif
</body></html>
