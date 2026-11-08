<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Reporte de huéspedes</title></head><body>
<h3>Reporte de huéspedes y comprobantes</h3>
<p>@include('partials.report_company_header') &nbsp; RIF: {{ $company->number }}</p>
<p>El total corresponde al importe original del comprobante, incluso si fue anulado o rechazado.</p>
<table><thead><tr><th>ID registro</th><th>Nombre</th><th>Tipo de identidad</th><th>Número</th><th>Sexo</th><th>Ocupación</th><th>Edad</th><th>Estado civil</th><th>Nacionalidad</th><th>Procedencia</th><th>Habitación</th><th>Entrada</th><th>Hora de entrada</th><th>Salida</th><th>Hora de salida</th><th>Comprobante</th><th>Estado</th><th>Moneda</th><th>Total original del comprobante</th></tr></thead><tbody>
@foreach($records as $hotel)
@php $document=$hotel->document; @endphp
<tr><td>{{ $hotel->id }}</td><td data-type="s">{{ $hotel->name }}</td><td data-type="s">{{ optional($hotel->identity_document_type)->description }}</td>
<td data-type="s">{{ $hotel->number }}</td><td data-type="s">{{ $hotel->sex }}</td><td data-type="s">{{ $hotel->ocupation }}</td><td>{{ $hotel->age }}</td><td data-type="s">{{ $hotel->civil_status }}</td>
<td data-type="s">{{ $hotel->nacionality }}</td><td data-type="s">{{ $hotel->origin }}</td><td>{{ $hotel->room_number }}</td>
<td data-type="s">{{ $hotel->date_entry }}</td><td data-type="s">{{ $hotel->time_entry }}</td><td data-type="s">{{ $hotel->date_exit }}</td><td data-type="s">{{ $hotel->time_exit }}</td>
<td data-type="s">{{ $document->number_full }}</td><td data-type="s">{{ optional($document->state_type)->description }}</td><td data-type="s">{{ $document->currency_type_id }}</td>
<td data-type="s" style="mso-number-format:'\@'">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($document,'total'),2,'.','') }}</td></tr>
@endforeach
</tbody></table>
@if($records->isEmpty())<p>No se encontraron registros.</p>@endif
</body></html>
