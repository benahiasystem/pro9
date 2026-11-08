<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Reporte de habitaciones</title></head><body>
<h3>Reporte de habitaciones y estancias</h3>
<p>@include('partials.report_company_header') &nbsp; RIF: {{ $company->number }}</p>
<p>Las habitaciones reflejan su estado actual. Las noches se calculan entre las fechas registradas; las pernoctaciones son una estimación según las personas registradas.</p>
<table><thead><tr><th>Disponibles</th><th>Ocupadas</th><th>Limpieza</th><th>Mantenimiento</th><th>Otro estado</th></tr></thead><tbody><tr>
@foreach(['DISPONIBLE','OCUPADO','LIMPIEZA','MANTENIMIENTO','OTRO'] as $status)<td>{{ $report['counts'][$status] }}</td>@endforeach
</tr></tbody></table>
<table><thead><tr><th>Habitación</th><th>Estado actual</th><th>Última actualización</th></tr></thead><tbody>
@foreach($report['rooms'] as $room)<tr><td data-type="s">{{ $room['name'] }}</td><td data-type="s">{{ $room['status'] }}</td><td data-type="s">{{ $room['updated_at'] }}</td></tr>@endforeach
</tbody></table>
<h4>Estancias seleccionadas</h4>
<table><thead><tr><th>ID estancia</th><th>Huésped</th><th>Nacionalidad</th><th>Residencia</th><th>Tipo de identidad</th><th>Identidad</th><th>Entrada</th><th>Salida</th><th>Habitación</th><th>Moneda registrada</th><th>Precio unitario inicial registrado</th><th>Personas registradas</th><th>Arribos</th><th>Noches</th><th>Pernoctaciones estimadas</th><th>Motivo</th><th>Categoría actual</th></tr></thead><tbody>
@foreach($report['stays'] as $row)<tr>
<td>{{ $row['id'] }}</td><td data-type="s">{{ $row['name'] }}</td><td data-type="s">{{ $row['nationality'] }}</td><td data-type="s">{{ $row['country'] }}</td><td data-type="s">{{ $row['identity_type'] }}</td><td data-type="s">{{ $row['identity_number'] }}</td>
<td data-type="s">{{ $row['input_date'] }}</td><td data-type="s">{{ $row['output_date'] }}</td><td data-type="s">{{ $row['room'] }}</td><td data-type="s">{{ $row['currency'] ?? 'Sin identificar' }}</td><td data-type="s" style="mso-number-format:'\@'">{{ $row['rate'] ?? 'Sin dato' }}</td>
<td>{{ $row['people'] }}</td><td>{{ $row['arrival'] ?? 'Sin dato' }}</td><td>{{ $row['nights'] ?? 'Sin dato' }}</td><td>{{ $row['person_nights'] ?? 'Sin dato' }}</td><td data-type="s">{{ $row['notes'] }}</td><td data-type="s">{{ $row['category'] }}</td></tr>@endforeach
<tr><td colspan="12">Totales; noches con fechas válidas</td><td>{{ $report['arrivals'] }}</td><td>{{ $report['nights'] }}</td><td>{{ $report['person_nights'] }}</td></tr>
</tbody></table>
<p>Estancias sin llegada válida: {{ $report['unknown_arrivals'] }}. Estancias sin fechas válidas para contar noches: {{ $report['unknown_nights'] }}.</p>
</body></html>
