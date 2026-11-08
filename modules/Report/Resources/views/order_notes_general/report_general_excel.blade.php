<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Reporte general de pedidos</title></head><body>
<h3>Reporte general de pedidos</h3><p>@include('partials.report_company_header') &nbsp; RIF: {{ $company->number }}</p>
<p>Importe y descuentos de partidas sin repartir ajustes globales. Cantidades separadas por unidad.</p>
<table><thead><tr><th>#</th><th>Cliente</th><th>Documento</th><th>Dirección</th><th>Pedido</th><th>Estado</th><th>Moneda</th>
<th>Importe de partidas</th><th>Descuento de partidas</th><th>Descuento de pedido</th><th>Total pedido</th><th>Tipo de pago</th><th>Vendedor</th>
<th>Motivo</th><th>Observación</th><th>Productos</th><th>Categorías</th><th>Marcas</th><th>Cantidades por unidad</th><th>Documentos asociados</th><th>Órdenes de entrega</th></tr></thead><tbody>
@foreach($records as $value)
@php
    $amounts=\Modules\Report\Services\OrderGeneralAmounts::row($value);
    $products=[]; $categories=[]; $brands=[]; $motives=[];
    foreach ($value->items as $item) {
        $snapshot=(array)$item->item;
        if (!empty($snapshot['description'])) $products[]=$snapshot['description'];
        foreach (['category','brand'] as $field) {
            $entry=$snapshot[$field] ?? null;
            if (is_object($entry)) $entry=(array)$entry;
            if (is_array($entry)) $entry=$entry['name'] ?? $entry['description'] ?? null;
            if (is_scalar($entry) && $entry !== '') {
                if ($field === 'category') $categories[]=(string)$entry; else $brands[]=(string)$entry;
            }
        }
        foreach ((array)$item->discounts as $entry) {
            $entry=(array)$entry; if (!empty($entry['description'])) $motives[]=$entry['description'];
        }
    }
    $references=$value->documents->map(fn($doc)=>$doc->number_full)->concat($value->sale_notes->map(fn($doc)=>$doc->number_full))->unique()->implode(' - ');
    $dispatches=$value->getDispatches()->map(fn($dispatch)=>$dispatch->number_full)->implode(' - ');
@endphp
<tr><td>{{ $loop->iteration }}</td><td data-type="s">{{ $value->customer->name ?? '' }}</td><td data-type="s">{{ $value->customer ? format_person_identity_document($value->customer) : '' }}</td>
<td data-type="s">{{ $value->shipping_address }}</td><td data-type="s">{{ $value->number_full }}</td><td>{{ strtoupper(optional($value->state_type)->description ?? 'PENDIENTE') }}</td>
<td>{{ $amounts['currency_type_id'] }}</td>
@foreach(['subtotal_items','discount_items','total_discount','total'] as $field)<td data-type="s" style="mso-number-format:'\@'">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($amounts,$field),2,'.','') }}</td>@endforeach
<td data-type="s">{{ optional($value->payment_method_type)->description }}</td><td data-type="s">{{ optional($value->user)->name }}</td>
<td data-type="s">{{ implode(' - ',array_unique($motives)) }}</td><td data-type="s">{{ $value->observation }}</td>
<td data-type="s">{{ implode(' - ',array_unique($products)) }}</td><td data-type="s">{{ implode(' - ',array_unique($categories)) }}</td><td data-type="s">{{ implode(' - ',array_unique($brands)) }}</td>
<td data-type="s">{{ $amounts['quantity_by_unit'] }}</td><td data-type="s">{{ $references }}</td><td data-type="s">{{ $dispatches }}</td></tr>
@endforeach
</tbody></table></body></html>
