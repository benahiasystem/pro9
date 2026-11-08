{{-- ######## INICIO MIGRACIÓN MONEDA VENEZUELA ######## --}}
<?php
    $warehouse_description = \App\CoreFacturalo\Helpers\Template\ReportHelper::getWarehouseDescription($value, $purchase);

    $apply_conversion_to_pen = filter_var($request_apply_conversion_to_pen, FILTER_VALIDATE_BOOLEAN);

    $decimal = \App\Support\Exact\Decimal::class;
    $amounts = [];
    foreach (['unit_price','unit_value','total_value','total','total_igv','total_discount'] as $field) {
        $amounts[$field] = $decimal::readRequired($value, $field);
    }
    $quantity = $decimal::readRequired($value, 'quantity');
    $currency = $purchase->currency_type_id;
    $description_apply_conversion_to_pen = null;

    // aplicar conversión si es que esta habilitada la configuracion
    if($apply_conversion_to_pen && $currency === 'USD')
    {
        foreach ($amounts as $field=>$amount) $amounts[$field] = $decimal::convert($amount,'USD','VES',$purchase->exchange_rate_sale);
        $currency = 'VES';
        $description_apply_conversion_to_pen = '(Se aplicó conversión a bolívares)';
    }
    // aplicar conversión si es que esta habilitada la configuracion

?>
<tr>
    <td class="celda">{{ $loop->iteration }}</td>
    <td class="celda"> {{$purchase->date_of_issue->format('Y-m-d')}}</td>
    {{--
    @if($isSaleNote)
    <td class="celda">{{ $stablihsment['district'] }}</td>
    <td class="celda">{{ $stablihsment['department'] }}</td>
    <td class="celda">{{ $stablihsment['province'] }}</td>
    @endif
    --}}
    <td class="celda"> {{$purchase->document_type->description}}</td>
    <td class="celda"> {{$purchase->document_type_id}}</td>
    <td class="celda"> {{$purchase->series}}</td>
    <td class="celda"> {{$purchase->number}}</td>
    {{--
    <td class="celda">{{ $purchseOrder }}</td>
    <td class="celda">{{ $web_platform }}</td>
    --}}
    <td class="celda"> {{$purchase->state_type_id == '11' ? 'SI':'NO'}}</td>
    <td class="celda"> {{$purchase->supplier->identity_document_type->description}}</td>
    <td class="celda"> {{format_person_identity_document($purchase->supplier)}}</td>
    <td class="celda"> {{$purchase->supplier->name}}</td>
    {{--
    <td class="celda">
    @if($isSaleNote)
    {{ $document->seller_id == null ? $document->user->name : $document->seller->name }}
    @else
    {{$document->user->name}}
    @endif
    </td>
    --}}
    <td class="celda">{{$currency}}</td>
    {{-- <td class="celda"> {{$purchase->exchange_rate_sale}}</td> --}}
    <td class="celda"> {{$value->item->unit_type_id}}</td>
    <td class="celda"> {{$value->relation_item ? $value->relation_item->internal_id:''}}</td>
    <td class="celda"> {{$value->item->description}}</td>
    <td data-type="s" class="celda" style="mso-number-format:'\@'">{{$decimal::represent($quantity)}}</td>
    <td class="celda"></td>
    {{--
    <td class="celda">{{ $model }}</td>
    --}}
    <td class="celda"></td>
    <td data-type="s" class="celda" style="mso-number-format:'\@'">{{$decimal::represent($amounts['unit_value'])}}</td>
    <td data-type="s" class="celda" style="mso-number-format:'\@'">{{$decimal::represent($amounts['unit_price'])}}</td>
    <td data-type="s" class="celda" style="mso-number-format:'\@'">{{$decimal::represent($amounts['total_discount'])}}</td>
    <td data-type="s" class="celda" style="mso-number-format:'\@'">{{$decimal::represent($amounts['total_value'])}}</td>
    <td class="celda"> {{$value->affectation_igv_type_id}}</td>
    <td data-type="s" class="celda" style="mso-number-format:'\@'">{{$decimal::represent($amounts['total_igv'])}}</td>


    <td data-type="s" class="celda" style="mso-number-format:'\@'">{{$decimal::represent($amounts['total'])}}</td>

    <td class="celda">{{ $purchase->exchange_rate_sale }}</td>
    <td class="celda">{{ $warehouse_description }}</td>

    {{--
    <td class="celda">{{ $total_item_purchase }}</td>
    <td class="celda">{{ $utility_item }}</td>
    <td class="celda">{{ $brand }}</td>
    <td class="celda">{{ $category }}</td>
    --}}
</tr>

{{-- ######## FIN MIGRACIÓN MONEDA VENEZUELA ######## --}}
