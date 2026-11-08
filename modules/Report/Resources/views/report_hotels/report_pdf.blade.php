<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Reporte de habitaciones</title>
<style>body{font-family:sans-serif;font-size:9px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #aaa;padding:4px;vertical-align:top}th{background:#e8edf3}thead{display:table-header-group}tr{page-break-inside:avoid}</style></head><body>
<h3>Reporte de habitaciones y estancias</h3>
<p>@include('partials.report_company_header') &nbsp; RIF: {{ $company->number }}</p>
<p>Estado actual de habitaciones: disponibles {{ $report['counts']['DISPONIBLE'] }}, ocupadas {{ $report['counts']['OCUPADO'] }}, limpieza {{ $report['counts']['LIMPIEZA'] }}, mantenimiento {{ $report['counts']['MANTENIMIENTO'] }}, otros {{ $report['counts']['OTRO'] }}.</p>
<p>Las noches se calculan entre las fechas registradas; las pernoctaciones son una estimación según las personas registradas.</p>
<table><thead><tr><th>Habitación</th><th>Estado actual</th><th>Última actualización</th></tr></thead><tbody>
@foreach($report['rooms'] as $room)<tr><td>{{ $room['name'] }}</td><td>{{ $room['status'] }}</td><td>{{ $room['updated_at'] ?? 'Sin dato' }}</td></tr>@endforeach
</tbody></table>
<h4>Estancias seleccionadas</h4>
<table><thead><tr><th>Huésped e identidad</th><th>Nacionalidad y residencia</th><th>Habitación y categoría actual</th><th>Entrada y salida</th><th>Precio unitario inicial registrado</th><th>Personas</th><th>Noches</th><th>Pernoctaciones estimadas</th><th>Motivo</th></tr></thead><tbody>
@foreach($report['stays'] as $row)<tr><td>{{ $row['name'] }}<br>{{ $row['identity_type'] }} {{ $row['identity_number'] }}</td><td>{{ $row['nationality'] }}<br>{{ $row['country'] }}</td><td>{{ $row['room'] }}<br>{{ $row['category'] }}</td><td>{{ $row['input_date'] }}<br>{{ $row['output_date'] }}</td><td>{{ $row['rate'] === null ? 'Sin dato' : (($row['currency'] ?? 'Moneda no identificada').' '.$row['rate']) }}</td><td>{{ $row['people'] }}</td><td>{{ $row['nights'] ?? 'Sin dato' }}</td><td>{{ $row['person_nights'] ?? 'Sin dato' }}</td><td>{{ $row['notes'] }}</td></tr>@endforeach
<tr><td colspan="6">Arribos: {{ $report['arrivals'] }}</td><td>{{ $report['nights'] }}</td><td>{{ $report['person_nights'] }}</td><td></td></tr></tbody></table>
<p>Estancias sin llegada válida: {{ $report['unknown_arrivals'] }}. Estancias sin fechas válidas para contar noches: {{ $report['unknown_nights'] }}.</p>
</body></html>
