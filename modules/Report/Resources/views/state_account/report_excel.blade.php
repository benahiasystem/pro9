@php
    use App\Support\Exact\Decimal as D;
    use App\CoreFacturalo\Helpers\Template\TemplateHelper;
    $accountRows = \Modules\Report\Services\StateAccountAmounts::presentation($records);
    $fields = \Modules\Report\Services\StateAccountAmounts::FIELDS;
    $totals = [];
@endphp
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><title>Estado de cuenta</title></head><body>
<h3>Reporte de estado de cuenta</h3>
<table>
    <tr><td>Empresa</td><td>{{ $company->name }}</td><td>RIF</td><td>{{ $company->number }}</td></tr>
    <tr><td>Establecimiento</td><td>{{ $establishment->description }}</td><td>Fecha</td><td>{{ date('Y-m-d') }}</td></tr>
</table>
<p>Las notas convertidas se excluyen de los totales cuando la factura figura en el reporte.</p>
<table>
<thead><tr>
    <th>#</th><th>Usuario/Vendedor</th><th>Tipo Doc</th><th>Número</th><th>Fecha emisión</th><th>Fecha vencimiento</th>
    <th>Doc. afectado</th><th>Órdenes de entrega</th><th>Cliente</th><th>RIF</th><th>Estado</th><th>Moneda</th>
    <th>Forma de pago</th><th>Método de pago</th><th>Referencia de pago</th>
    <th>Total cargos</th><th>Total exento</th><th>Base imponible</th><th>Descuento total</th><th>Total IVA</th><th>Total</th><th>Total por pagar</th>
    <th>Cantidades por unidad</th><th>Productos</th>
    @foreach($categories as $category)<th>Partidas: {{ $category->name }}</th>@endforeach
    @foreach($categories_services as $category)<th>Servicios: {{ $category->name }}</th>@endforeach
</tr></thead><tbody>
@foreach($accountRows as $line)
    @php
        $value=$line['model']; $type=$line['document_type_id']; $currency=$line['currency_type_id'];
        $seller=\App\CoreFacturalo\Helpers\Template\ReportHelper::getSellerData($value);
        $person=$value->customer ?: $value->person;
        $target=($type === '07' || $type === '08') && $value->note ? ($value->note->affected_document ?: $value->note->data_affected_document) : null;
        $details=collect(TemplateHelper::getDetailedPayment($value))->flatten(1);
        $quantities=[]; $descriptions=[];
        foreach ($value->items as $item) {
            $unit=$item->item->unit_type_id ?? '';
            if ($unit !== '') $quantities[$unit]=($quantities[$unit] ?? D::of(0))->plus(D::read($item,'quantity'));
            $descriptions[]=$item->item->description ?? '';
        }
        if ($line['included_in_totals']) foreach ($fields as $field) $totals[$currency][$field]=($totals[$currency][$field] ?? D::of(0))->plus(D::readRequired($line,$field));
    @endphp
    <tr>
        <td>{{ $loop->iteration }}</td><td data-type="s">{{ ($filters['user_type'] ?? '') === 'CREADOR' ? optional($value->user)->name : ($seller->name ?? '') }}</td>
        <td>{{ $type }}{{ $line['converted'] ? ' (convertida)' : '' }}</td><td data-type="s">{{ $value->number_full }}</td><td>{{ optional($value->date_of_issue)->format('Y-m-d') }}</td>
        <td>{{ $value instanceof \App\Models\Tenant\Document ? optional(optional($value->invoice)->date_of_due)->format('Y-m-d') : optional($value->due_date)->format('Y-m-d') }}</td>
        <td>{{ $target ? \App\Services\Fiscal\FiscalIdentity::numberFull($target->series,$target->number) : '' }}</td>
        <td>@foreach(($value->guides ?: []) as $guide){{ $guide->number ?? '' }} @endforeach</td>
        <td data-type="s">{{ $person->name ?? '' }}</td><td data-type="s">{{ $person ? format_person_identity_document($person) : '' }}</td>
        <td>{{ optional($value->state_type)->description }}</td><td>{{ $currency }}</td><td>{{ $value->payment_condition->name ?? 'Contado' }}</td>
        <td data-type="s">{{ $details->map(fn($payment)=>$payment['display'] ?? $payment['description'] ?? '')->implode(' - ') }}</td>
        <td data-type="s">{{ $details->pluck('reference')->filter()->implode(' - ') }}</td>
        @foreach($fields as $field)<td data-type="s" style="mso-number-format:'\@'">{{ $line['display'][$field] ?? D::format(D::readRequired($line,$field),2,'.','') }}</td>@endforeach
        <td data-type="s">{{ collect($quantities)->map(fn($amount,$unit)=>D::represent($amount).' '.$unit)->implode('; ') }}</td><td data-type="s">{{ implode(', ', $descriptions) }}</td>
        @foreach($categories as $category)
            @php
                $amount=D::of(0);
                foreach ($value->items as $item) if (optional($item->relation_item)->category_id == $category->id) $amount=$amount->plus(D::read($item,'total'));
                if (in_array($value->state_type_id,['09','11'],true)) $amount=D::of(0); elseif ($type === '07') $amount=$amount->negated();
            @endphp
            <td data-type="s" style="mso-number-format:'\@'">{{ D::represent($amount) }}</td>
        @endforeach
        @foreach($categories_services as $category)
            @php
                $quantity=D::of(0);
                foreach ($value->items as $item) if (optional($item->relation_item)->category_id == $category->id && ($item->item->unit_type_id ?? '') === 'SERV') $quantity=$quantity->plus(D::read($item,'quantity'));
                if (in_array($value->state_type_id,['09','11'],true)) $quantity=D::of(0); elseif ($type === '07') $quantity=$quantity->negated();
            @endphp
            <td data-type="s" style="mso-number-format:'\@'">{{ D::represent($quantity) }}</td>
        @endforeach
    </tr>
@endforeach
@foreach($totals as $currency=>$amounts)
    <tr><td colspan="14"></td><td>Totales {{ $currency }}</td>
        @foreach($fields as $field)<td data-type="s" style="mso-number-format:'\@'">{{ D::format($amounts[$field],2,'.','') }}</td>@endforeach
    </tr>
@endforeach
</tbody></table></body></html>
