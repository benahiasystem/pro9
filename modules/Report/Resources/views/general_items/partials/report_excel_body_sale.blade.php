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
    $catalogCost = \Modules\Report\Services\GeneralItemAmounts::catalogPrice($relationItem, 'purchase_unit_price', $document, $currency);
    $unitPrice = $isComponent
        ? \Modules\Report\Services\GeneralItemAmounts::catalogPrice($relationItem, 'sale_unit_price', $document, $currency)
        : $values['unit_price'];
    $total = $isComponent ? $catalogCost->multipliedBy($quantity) : $values['total'];
    $isDocument = (string)$document_type_id !== '80' && $type === 'sale';
    $data = \Modules\Report\Http\Resources\GeneralItemCollection::getDocument($value);
    $observation = $isDocument ? (($data['additional_information'][0] ?? '')) : ($data['observation'] ?? '');
    $stablihsment = $stablihsment ?? ['district'=>'','department'=>'','province'=>''];
    $seller = $document->seller_id ? $document->seller : $document->user;
    $payments = \App\CoreFacturalo\Helpers\Template\TemplateHelper::getDetailedPayment($document);
    $warehouse = \App\CoreFacturalo\Helpers\Template\ReportHelper::getWarehouseDescription($value, $document);
    $lotService = new \App\Services\ItemLotsGroupService();
    $lotDescription = $lotService->getItemLotGroupLineBreak($value->item->IdLoteSelected ?? []);
@endphp
<tr>
    <td class="celda">{{ $loop->iteration }}</td>
    <td class="celda">{{ $document->date_of_issue->format('Y-m-d') }}</td>
    <td class="celda">{{ optional($seller)->name }}</td>
    @if($isDocument)
        <td class="celda">{{ $stablihsment['district'] }}</td>
        <td class="celda">{{ $stablihsment['department'] }}</td>
        <td class="celda">{{ $stablihsment['province'] }}</td>
    @endif
    <td class="celda">{{ $isDocument ? $document->document_type->description : 'NOTA DE VENTA' }}</td>
    <td class="celda">{{ $isDocument ? $document->document_type_id : '80' }}</td>
    <td class="celda">{{ $document->series }}</td>
    <td class="celda">{{ $document->number }}</td>
    <td class="celda">{{ $document->purchase_order }}</td>
    <td class="celda">{{ $platform ?? '' }}</td>
    <td class="celda">{{ $document->state_type_id === '11' ? 'SI' : 'NO' }}</td>
    <td class="celda">{{ $document->customer->identity_document_type->description }}</td>
    <td class="celda">{{ format_person_identity_document($document->customer) }}</td>
    <td class="celda">{{ $document->customer->name }}</td>
    <td class="celda">{{ optional($seller)->name }}</td>
    <td class="celda">{{ $observation }}</td>
    <td class="celda">{{ $currency }}</td>
    <td class="celda">{{ $rowItem->unit_type_id ?? '' }}</td>
    <td class="celda">{{ $rowItem->internal_id ?? $relationItem->internal_id ?? '' }}</td>
    <td class="celda">{{ $document->reference_data }}</td>
    <td class="celda">{{ $isComponent ? '(Item de pack) - ' : '' }}{{ $rowItem->description ?? '' }}</td>
    <td data-type="s" class="celda" style="mso-number-format:'\@'">{{ $decimal::represent($quantity) }}</td>
    <td>@foreach ($payments as $payment) @foreach ($payment as $pay) {{ $pay['display'] ?? $pay['description'] }} @if (!$loop->last)<br>@endif @endforeach @if (!$loop->last)<br>@endif @endforeach</td>
    <td class="celda">{{ $series }}</td>
    <td class="celda">{!! $lotDescription !!}</td>
    <td class="celda">{{ $model }}</td>
    <td data-type="s" class="celda" style="mso-number-format:'\@'">{{ $decimal::represent($catalogCost) }}</td>
    <td data-type="s" class="celda" style="mso-number-format:'\@'">{{ $isComponent ? '' : $decimal::represent($values['unit_value']) }}</td>
    <td data-type="s" class="celda" style="mso-number-format:'\@'">{{ $decimal::represent($unitPrice) }}</td>
    <td data-type="s" class="celda" style="mso-number-format:'\@'">{{ $isComponent ? '' : $decimal::represent($values['total_discount']) }}</td>
    <td data-type="s" class="celda" style="mso-number-format:'\@'">{{ $isComponent ? '' : $decimal::represent($values['total_value']) }}</td>
    <td class="celda">{{ $value->affectation_igv_type_id }}</td>
    <td data-type="s" class="celda" style="mso-number-format:'\@'">{{ $isComponent ? '' : $decimal::represent($values['total_igv']) }}</td>
    <td data-type="s" class="celda" style="mso-number-format:'\@'">{{ $decimal::represent($total) }}</td>
    <td data-type="s" class="celda" style="mso-number-format:'\@'">{{ $isComponent ? '' : $decimal::represent($values['total_item_purchase']) }}</td>
    <td data-type="s" class="celda" style="mso-number-format:'\@'">{{ $isComponent ? '' : $decimal::represent($values['utility_item']) }}</td>
    <td class="celda">{{ optional($relationItem->brand)->name }}</td>
    <td class="celda">{{ optional($relationItem->category)->name }}</td>
    <td data-type="s" class="celda" style="mso-number-format:'\@'">{{ $document->exchange_rate_sale }}</td>
    <td class="celda">{{ $warehouse }}</td>
</tr>
{{-- ######## FIN CALCULOS EXACTOS SAB ######## --}}
