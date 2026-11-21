<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Análisis comercial</title></head><body>
<h3>Reporte de análisis comercial</h3>
<p>@include('partials.report_company_header') &nbsp; RIF: {{ $company->number }}</p>
<p>Importes netos de facturas y notas; visitas y fechas corresponden a facturas vigentes. Categorías actuales y unidades documentales.</p>
@php
    $salesInBolivars = \App\Services\SalesDocumentCurrency::usesBolivars();
@endphp
<table><thead><tr><th>#</th><th>Cliente</th><th>Documento</th><th>Zona</th><th>Celular</th><th>Primera factura</th><th>Última factura</th>
<th>Días promedio</th><th>Facturas</th><th>Total VES</th>@unless($salesInBolivars)<th>Total USD</th><th>Otras monedas</th>@endunless
@foreach($categories as $category)<th>{{ $category }}</th>@endforeach<th>Contacto sugerido</th></tr></thead><tbody>
@foreach($records as $customer)
@php
    $row = \Modules\Report\Services\CommercialAnalysisAmounts::row($customer, $categories);
@endphp
<tr><td>{{ $loop->iteration }}</td><td data-type="s">{{ $row['customer_name'] }}</td><td data-type="s">{{ $row['customer_number'] }}</td><td data-type="s">{{ $row['zone'] }}</td><td data-type="s">{{ $row['telephone'] }}</td>
<td>{{ $row['first_document_date'] }}</td><td>{{ $row['last_document_date'] }}</td><td data-type="s" style="mso-number-format:'\@'">{{ $row['prom_difference_days'] }}</td><td>{{ $row['quantity_visit'] }}</td>
<td data-type="s" style="mso-number-format:'\@'">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($row,'total_ves'),2,'.','') }}</td>
@unless($salesInBolivars)
<td data-type="s" style="mso-number-format:'\@'">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($row,'total_usd'),2,'.','') }}</td>
<td>{{ collect($row['other_currencies'])->map(fn($value,$currency)=>$currency.' '.$value)->implode('; ') }}</td>
@endunless
@foreach($categories as $category)<td data-type="s">{{ $row[mb_strtoupper($category)] ?? '' }}</td>@endforeach<td>{{ $row['contact_date'] }}</td></tr>
@endforeach
</tbody></table></body></html>
