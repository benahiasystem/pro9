@php
    $establishment = $document->establishment;
    $customer = $document->customer;
    /* ######## INICIO NUMERACIÓN FISCAL VENEZUELA ######## */
$document_number = $document->number_full;
/* ######## FIN NUMERACIÓN FISCAL VENEZUELA ######## */
@endphp
<html>
<head>
    {{--<title>{{ $document_number }}</title>--}}
    {{--<link href="{{ $path_style }}" rel="stylesheet" />--}}
</head>
<body>
@include('pdf.marca_de_agua.partials.document_header_a4')
@if($document->transfer_reason_type_id === '04')
    <table class="full-width border-box mt-10 mb-10">
        <thead>
        <tr>
            <th class="border-bottom text-left">DESTINATARIO</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>Razón Social: {{ $company->name }}</td>
        </tr>
        <tr>
            <td>RIF: {{ $company->number }}
            </td>
        </tr>
        </tbody>
    </table>
@else
    <table class="full-width border-box mt-10 mb-10">
        <thead>
        <tr>
            <th class="border-bottom text-left">DESTINATARIO</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>Razón Social: {{ $customer->name }}</td>
        </tr>
        <tr>
            <td>{{ $customer->identity_document_type->description }}: {{ format_identity_document($customer->identity_document_type_id ?? null, $customer->number) }}
            </td>
        </tr>
        <tr>
            @php
                $ubigeo = App\Models\Tenant\Catalogs\District::find($customer->district_id);
            @endphp
            @if(false)
                <td>Dirección: {{ $customer->address }} - {{ $customer->country->description }}
                </td>
            @else
                <td>Dirección: {{ $customer->address }}
                    @if ($ubigeo)
                        {{ ($customer->district_id !== '-')? ', '.$ubigeo->description : '' }}
                        {{ ($customer->province_id !== '-')? ', '.$ubigeo->province->description : '' }}
                        {{ ($customer->department_id !== '-')? '- '.$ubigeo->province->department->description : '' }}
                    @endif
                </td>
            @endif
        </tr>
        @if ($customer->telephone)
            <tr>
                <td>Teléfono:{{ $customer->telephone }}</td>
            </tr>
        @endif
        <tr>
            <td>Vendedor: {{ $document->user->name }}</td>
        </tr>
        </tbody>
    </table>

@endif

@if (false)
    @php
        $buyer = $document->buyer;
        $identify_description = App\Models\Tenant\Catalogs\IdentityDocumentType::find($buyer->identity_document_type_id)->description;
    @endphp
    <table class="full-width border-box mt-10 mb-10">
        <thead>
        <tr>
            <th class="border-bottom text-left">COMPRADOR</th>
        </tr>
        </thead>
    <tbody>
    <tr>
        <td>Razón Social: {{ $buyer->name }}</td>
    </tr>
    <tr>
        <td>{{ $identify_description }}: {{ $buyer->number }}
        </td>
    </tr>
    <tr>
        <td>Dirección: {{ $buyer->address }}
        </td>
    </tr>
    </tbody>

    </table>

@endif
@if($document['reference_documents'])
    <table class="full-width border-box mt-10 mb-10">
        <thead>
        <tr>
            <th class="border-bottom text-left" colspan="2">DOCUMENTOS RELACIONADOS</th>
        </tr>
        </thead>
        <tbody>
        @foreach($document['reference_documents'] as $row)
            <tr>
                <td>{{ $row['document_type']['description'] }}: {{ $row['number'] }}</td>
            </tr>
            <tr>
                <td>PROVEEDOR {{ $row['name'] }}</td>
                <td>RIF: {{ $row['customer'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif
<table class="full-width border-box mt-10 mb-10">
    <thead>
    <tr>
        <th class="border-bottom text-left" colspan="2">ENVIO</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td>Fecha Emisión: {{ $document->date_of_issue->format('Y-m-d') }}</td>
        <td>Fecha Inicio de Traslado: {{ $document->date_of_shipping->format('Y-m-d') }}</td>
    </tr>
    <tr>
        <td>Motivo de traslado: {{ $document->transfer_reason_label }}</td>
        <td>Modalidad de Transporte: {{ $document->transport_mode_type->description }}</td>
    </tr>


    @if($document->related)
        <tr>
            <td>Número de documento (DAM): {{ $document->related->number }}</td>
            <td>Tipo documento relacionado: {{ $document->getRelatedDocumentTypeDescription() }}</td>
        </tr>
    @endif

    <tr>
        <td>Peso Bruto Total({{ $document->unit_type_id }}): {{ $document->total_weight }}</td>
        @if($document->packages_number)
            <td>Número de Bultos: {{ $document->packages_number }}</td>
        @endif
    </tr>
    <tr>
        <td colspan="2">P.Partida: {{ $document->origin_address_label }}</td>
    </tr>
    <tr>
        <td colspan="2">P.Llegada: {{ $document->delivery_address_label }}</td>
    </tr>
    @if($document->order_form_external)
        <tr>
            <td>Orden de pedido: {{ $document->order_form_external }}</td>
            <td></td>
        </tr>
    @endif
    </tbody>
</table>
<table class="full-width border-box mt-10 mb-10">
    <thead>
    <tr>
        <th class="border-bottom text-left" colspan="2">TRANSPORTE</th>
    </tr>
    </thead>
    <tbody>
    {{-- ########## INICIO RETIRO TRASLADO M1/L1 ########## --}}
    @if($document->transport_mode_type_id === '01')
        @php
            $document_type_dispatcher = App\Models\Tenant\Catalogs\IdentityDocumentType::findOrFail($document->dispatcher->identity_document_type_id);
        @endphp
        <tr>
            <td>Nombre y/o razón social: {{ $document->dispatcher->name }}</td>
            <td>{{ $document_type_dispatcher->description }}: {{ $document->dispatcher->number }}</td>
        </tr>
    @else
        <tr>
            @if($document->transport_data)
                <td>Número de placa del vehículo Principal: {{ $document->transport_data['plate_number'] }}</td>
            @endif
            @if(isset($document->transport_data['tuc']) && $document->transport_data['tuc'])
                <td>Certificado de habilitación vehicular: {{ $document->transport_data['tuc'] }}</td>
            @endif
        </tr>
        <tr>
            @if($document->driver->number)
                <td>Conductor Principal: {{$document->driver->name}}</td>
            @endif
            @if($document->driver->number)
                <td>Documento de conductor: {{ $document->driver->number }}</td>
            @endif
        </tr>
        <tr>
        {{-- ########## RETIRO DATOS VEHICULARES SECUNDARIOS ########## --}}
            @if($document->driver->license)
                <td>Licencia del conductor: {{ $document->driver->license }}</td>
            @endif
        </tr>
    @endif
    {{-- ######### FIN RETIRO TRASLADO M1/L1 ######### --}}
    </tbody>
</table>

@if($document->secondary_transports)
    <table class="full-width border-box mt-10 mb-10">
        <thead>
        <tr>
            <th class="border-bottom text-left" colspan="2">Vehículos Secundarios</th>
        </tr>
        </thead>
        <tbody>
        @foreach($document->secondary_transports as $row)
        <tr>
            @if($row["plate_number"])
                <td>Número de placa del vehículo: {{ $row["plate_number"] }}</td>
            @endif
            @if($row['tuc'])
                <td>Certificado de habilitación vehicular: {{ $row['tuc'] }}</td>
            @endif
        </tr>
        @endforeach
        </tbody>
    </table>
@endif
@if($document->secondary_drivers)
    <table class="full-width border-box mt-10 mb-10">
        <thead>
        <tr>
            <th class="border-bottom text-left" colspan="3">Conductores Secundarios</th>
        </tr>
        </thead>
        <tbody>
        @foreach($document->secondary_drivers as $row)
        <tr>
            @if($row['name'])
                <td>Conductor: {{$row['name']}}</td>
            @endif
            @if($row['number'])
                <td>Documento: {{ $row['number'] }}</td>
            @endif
            @if($row['license'])
                <td>Licencia: {{ $row['license'] }}</td>
            @endif
        </tr>
        @endforeach
        </tbody>
    </table>
@endif

@inject('unitTypeService', 'App\Services\UnitTypeService')
<table class="full-width border-box mt-10 mb-10">
    <thead class="">
    <tr>
        <th class="border-top-bottom text-center" width="6%">ITEM</th>
        <th class="border-top-bottom text-center" width="12%">CÓDIGO</th>
        <th class="border-top-bottom text-center" width="10%">CANTIDAD</th>
        <th class="border-top-bottom text-center" width="10%">U.M</th>
        <th class="border-top-bottom text-left">DESCRIPCIÓN</th>
        <th class="border-top-bottom text-center" width="10%">PESO</th>
    </tr>
    </thead>
    <tbody>
    @foreach($document->items as $row)
        @php
            $unitTypeId = $row->item->unit_type_id ?? null;
            $unitMeasure = ($unitTypeId === 'UND')
                ? 'UNIDAD'
                : ($unitTypeService->getDescription($unitTypeId) ?: $unitTypeId);
            $itemWeight = $row->item->weight
                ?? optional($row->relation_item)->weight
                ?? '';
        @endphp
        <tr>
            <td class="text-center">{{ $loop->iteration }}</td>
            <td class="text-center">{{ $row->item->internal_id }}</td>
            <td class="text-center">
                @if(((int)$row->quantity != $row->quantity))
                    {{ $row->quantity }}
                @else
                    {{ number_format($row->quantity, 0) }}
                @endif
            </td>
            <td class="text-center">{{ $unitMeasure }}</td>
            <td class="text-left">
                @if($row->name_product_pdf)
                    {!!$row->name_product_pdf!!}
                @else
                    {!!$row->item->description!!}
                @endif

                @if (!empty($row->item->presentation)) {!!$row->item->presentation->description!!} @endif

                @if($row->attributes)
                    @foreach($row->attributes as $attr)
                        <br/><span style="font-size: 9px">{!! $attr->description !!} : {{ $attr->value }}</span>
                    @endforeach
                @endif
                @if($row->discounts)
                    @foreach($row->discounts as $dtos)
                        @if(!($dtos->from_global_distribution ?? false))
                            <br/><span style="font-size: 9px">{{ ($dtos->is_amount ?? false) ? '' : ($dtos->factor * 100).'%' }} {{$dtos->description }}</span>
                        @endif
                    @endforeach
                @endif
                @if(optional($row->relation_item)->is_set == 1)
                    <br>
                    @inject('itemSet', 'App\Services\ItemSetService')
                    @foreach ($itemSet->getItemsSet($row->item_id) as $item)
                        {{$item}}<br>
                    @endforeach
                @endif

                @if($document->has_prepayment)
                    <br>
                    *** Pago Anticipado ***
                @endif
            </td>
            <td class="text-center">{{ $itemWeight }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

@if($document->observations)
    <table class="full-width border-box mt-10 mb-10">
        <tr>
            <td class="text-bold border-bottom font-bold">OBSERVACIONES</td>
        </tr>
        <tr>
            <td>{{ $document->observations }}</td>
        </tr>
    </table>
@endif

@if ($document->reference_document)
    <table class="full-width border-box">
        @if($document->reference_document)
            <tr>
                <td class="text-bold border-bottom font-bold">{{$document->reference_document->document_type->description}}</td>
            </tr>
            <tr>
                <td>{{ ($document->reference_document) ? $document->reference_document->number_full : "" }}</td>
            </tr>
        @endif
    </table>
@endif
@if ($document->data_affected_document)
    @php
        $document_data_affected_document = $document->data_affected_document;

    $number = (property_exists($document_data_affected_document,'number'))?$document_data_affected_document->number:null;
    $series = (property_exists($document_data_affected_document,'series'))?$document_data_affected_document->series:null;
    $document_type_id = (property_exists($document_data_affected_document,'document_type_id'))?$document_data_affected_document->document_type_id:null;

    @endphp
    @if($number !== null && $series !== null && $document_type_id !== null)

        @php
            $documentType  = App\Models\Tenant\Catalogs\DocumentType::find($document_type_id);
            $textDocumentType = $documentType->getDescription();
        @endphp
        <table class="full-width border-box">
            <tr>
                <td class="text-bold border-bottom font-bold">{{$textDocumentType}}</td>
            </tr>
            <tr>
                <td>{{$series }}-{{$number}}</td>
            </tr>
        </table>
    @endif
@endif
@if ($document->reference_order_form_id)
    <table class="full-width border-box">
        @if($document->order_form)
            <tr>
                <td class="text-bold border-bottom font-bold">ORDEN DE PEDIDO</td>
            </tr>
            <tr>
                <td>{{ ($document->order_form) ? $document->order_form->number_full : "" }}</td>
            </tr>
        @endif
    </table>

@elseif ($document->order_form_external)
    <table class="full-width border-box">
        <tr>
            <td class="text-bold border-bottom font-bold">ORDEN DE PEDIDO</td>
        </tr>
        <tr>
            <td>{{ $document->order_form_external }}</td>
        </tr>
    </table>

@endif


@if ($document->reference_sale_note_id)
    <table class="full-width border-box">
        @if($document->sale_note)
            <tr>
                <td class="text-bold border-bottom font-bold">NOTA DE VENTA</td>
            </tr>
            <tr>
                <td>{{ ($document->sale_note) ? $document->sale_note->number_full : "" }}</td>
            </tr>
        @endif
    </table>
@endif
@if($document->qr)
<table class="full-width">
    <tr>
        <td class="text-left">
            <img src="data:image/png;base64, {{ $document->qr }}" style="margin-right: -10px;"/>
        </td>
    </tr>
</table>
@endif
@if ($document->terms_condition)
    <br>
    <table class="full-width">
        <tr>
            <td>
                <h6 style="font-size: 12px; font-weight: bold;">Términos y condiciones del servicio</h6>
                {!! $document->terms_condition !!}
            </td>
        </tr>
    </table>
@endif

</body>
</html>
