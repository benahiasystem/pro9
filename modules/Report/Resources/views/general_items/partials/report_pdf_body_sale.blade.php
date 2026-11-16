{{-- ######## INICIO CALCULOS EXACTOS SAB ######## --}}
@php
    $decimal = \App\Support\Exact\Decimal::class;
    $amounts = \Modules\Report\Services\GeneralItemAmounts::forLine($value, $document,
        filter_var($request_apply_conversion_to_pen, FILTER_VALIDATE_BOOLEAN), $cost);
    $values = $amounts['values'];
    $currency = $amounts['currency'];
    $isComponent = isset($qty);
    $rowItem = $isComponent ? $item : $value->item;
    $relationItem = $isComponent ? $item : $value->relation_item;
    $quantity = $isComponent ? $decimal::of($qty) : $values['quantity'];
    $unitPrice = $isComponent
        ? \Modules\Report\Services\GeneralItemAmounts::catalogPrice($relationItem, 'sale_unit_price', $document, $currency)
        : $values['unit_price'];
    $total = $isComponent
        ? \Modules\Report\Services\GeneralItemAmounts::catalogPrice($relationItem, 'purchase_unit_price', $document, $currency)->multipliedBy($quantity)
        : $values['total'];
    $description = ($isComponent ? '(Item de pack) - ' : '').mb_substr($rowItem->description ?? '', 0, 50);
    $isDocument = (string)$document_type_id !== '80' && $type === 'sale';
    $seller = \App\CoreFacturalo\Helpers\Template\ReportHelper::getSellerData($value);
    $stablihsment = $stablihsment ?? ['district'=>'','department'=>'','province'=>''];
@endphp
<tr>
    <td class="celda">{{ $document->date_of_issue->format('Y-m-d') }}</td>
    <td class="celda">{{ optional($seller)->name }}</td>
    @if($isDocument)
        <td class="celda">{{ $stablihsment['district'] }}</td>
        <td class="celda">{{ $stablihsment['department'] }}</td>
        <td class="celda">{{ $stablihsment['province'] }}</td>
    @endif
    <td class="celda">{{ $document->series }}</td>
    <td class="celda">{{ $document->number }}</td>
    <td class="celda">{{ $document->purchase_order }}</td>
    <td class="celda">{{ $web_platform ?? $platform ?? '' }}</td>
    <td class="celda">{{ $document->customer->identity_document_type_id }}</td>
    <td class="celda">{{ format_person_identity_document($document->customer) }}</td>
    <td class="celda">{{ $document->customer->name }}</td>
    <td class="celda">{{ $currency }}</td>
    <td class="celda">{{ $rowItem->unit_type_id ?? '' }}</td>
    <td class="celda">{{ optional($relationItem->brand)->name }}</td>
    <td class="celda">{{ $description }}</td>
    <td class="celda">{{ optional($relationItem->category)->name }}</td>
    <td class="celda">{{ $decimal::represent($quantity) }}</td>
    <td class="celda">{{ $decimal::format($unitPrice, 2, '.', '') }}</td>
    <td class="celda">{{ $isComponent ? '(pck) ' : '' }}{{ $decimal::format($total, 2, '.', '') }}</td>
    <td class="celda">{{ $isComponent ? '' : $decimal::format($values['total_item_purchase'], 2, '.', '') }}</td>
    <td class="celda">{{ $isComponent ? '' : $decimal::format($values['utility_item'], 2, '.', '') }}</td>
</tr>
{{-- ######## FIN CALCULOS EXACTOS SAB ######## --}}
