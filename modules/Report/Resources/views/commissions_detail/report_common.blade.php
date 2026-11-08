<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><title>Utilidades detalladas</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:9px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #777;padding:4px;text-align:left}th{background:#eee} .number{text-align:right}</style></head><body>
<h2>Utilidades detalladas estimadas</h2>
<p>{{ $company->name }} · RIF {{ $company->number }}</p>
<p>Precios por unidad y ganancia de partidas antes de ajustes globales. El costo se estima a partir de compras o del catálogo; no es un costo histórico congelado.</p>
<table><thead><tr><th>Fecha</th><th>Documento</th><th>Cliente</th><th>Producto</th><th>Cantidad</th><th>Unidad</th><th>Moneda</th><th>Costo unitario estimado</th><th>Precio unitario</th><th>Ganancia unitaria</th><th>Ganancia total</th></tr></thead><tbody>
@foreach($records as $row)
<tr><td data-type="s">{{ $row['date_of_issue'] }}</td><td data-type="s">{{ $row['type_document'] }} {{ $row['serie'] }}</td>
<td data-type="s">{{ $row['customer_number'] }} {{ $row['customer_name'] }}</td><td data-type="s">{{ $row['name'] }}</td>
<td data-type="s" class="number">{{ $row['quantity'] }}</td><td data-type="s">{{ $row['unit_type_id'] }}</td><td data-type="s">{{ $row['currency_type_id'] }}</td>
<td data-type="s" class="number">{{ $row['purchase_unit_price'] }}</td><td data-type="s" class="number">{{ $row['unit_price'] }}</td>
<td data-type="s" class="number">{{ $row['unit_gain'] }}</td><td data-type="s" class="number">{{ $row['display_profit'] }}</td></tr>
@endforeach
@foreach(\Modules\Report\Services\DetailedProfitReport::totals($records) as $currency=>$total)
<tr><td colspan="10">Ganancia total estimada · {{ $currency }}</td><td data-type="s" class="number">{{ \App\Support\Exact\Decimal::format($total,2,'.','') }}</td></tr>
@endforeach
</tbody></table></body></html>
