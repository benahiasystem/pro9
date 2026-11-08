<?php

    $apply_conversion_to_pen = filter_var($request_apply_conversion_to_pen, FILTER_VALIDATE_BOOLEAN);

    $decimal = \App\Support\Exact\Decimal::class;
    $unit_price = $decimal::readRequired($value, 'unit_price');
    $total = $decimal::readRequired($value, 'total');
    $quantity = $decimal::readRequired($value, 'quantity');
    $currency = $document->currency_type_id;
    $description_apply_conversion_to_pen = null;

    // aplicar conversión si es que esta habilitada la configuracion
    if($apply_conversion_to_pen && $currency === 'USD')
    {
        $total = $decimal::convert($total,'USD','VES',$document->exchange_rate_sale);
        $unit_price = $decimal::convert($unit_price,'USD','VES',$document->exchange_rate_sale);
        $currency = 'VES';
        $description_apply_conversion_to_pen = '(Conv.)';
    }
    // aplicar conversión si es que esta habilitada la configuracion


?>
<tr>
    <td class="celda">{{$document->date_of_issue->format('Y-m-d')}}</td>
    <td class="celda">{{ optional($document->user)->name}}</td>

    {{--
    @if($isSaleNote)
        <td class="celda">{{ $stablihsment['district'] }}</td>
        <td class="celda">{{ $stablihsment['department'] }}</td>
        <td class="celda">{{ $stablihsment['province'] }}</td>
    @endif
    --}}
    <td class="celda">{{$document->series}}</td>
    <td class="celda">{{$document->number}}</td>
    {{--
    @if( $type == 'sale')
        <td class="celda">{{ $purchseOrder }} </td>
    @endif
    --}}
    <td class="celda">{{$document->supplier->identity_document_type_id}}</td>
    <td class="celda">{{format_person_identity_document($document->supplier)}}</td>
    <td class="celda">{{$document->supplier->name}}</td>
    <td class="celda">{{$currency}} {{ $description_apply_conversion_to_pen ?? ''}}</td>
    {{-- <td class="celda">{{$document->exchange_rate_sale}}</td> --}}
    <td class="celda">{{$value->item->unit_type_id}}</td>

    {{-- <td class="celda">{{$value->relation_item ? $value->relation_item->internal_id:''}}</td> --}}
    <td class="celda">{{$value->relation_item->brand->name}}</td>
    {{--
    @if($type == 'sale')
        <td class="celda">{{ $model }}</td>
    @endif
    --}}
    <td class="celda">{{$value->item->description}}</td>
    <td class="celda">{{$value->relation_item->category->name}}</td>
    <td class="celda">{{$decimal::represent($quantity)}}</td>

    <td class="celda">{{$decimal::format($unit_price, 6, '.', '')}}</td>


    <td class="celda">{{$decimal::format($total, 2, '.', '')}}</td>
    {{-- <td class="celda"></td> --}}

</tr>
