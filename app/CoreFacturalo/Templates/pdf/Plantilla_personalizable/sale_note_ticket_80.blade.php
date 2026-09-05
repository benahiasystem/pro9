@php
    $establishment = $document->establishment;
    $customer = $document->customer;
    $invoice = $document->invoice;
    //$path_style = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.DIRECTORY_SEPARATOR.'pdf'.DIRECTORY_SEPARATOR.'style.css');
    $left =  ($document->series) ? $document->series : $document->prefix;
    $tittle = $left.'-'.str_pad($document->number, 8, '0', STR_PAD_LEFT);
    $payments = $document->payments;

    $logo = "storage/uploads/logos/{$company->logo}";
    if($establishment->logo) {
        $logo = "{$establishment->logo}";
    }

    $configurationInPdf= App\CoreFacturalo\Helpers\Template\TemplateHelper::getConfigurationInPdf();

    extract(\App\CoreFacturalo\Helpers\Template\TemplateHelper::getPersonalizableTicketShowColumns(
        $document->establishment_id,
        ['codigo', 'cantidad', 'unidad', 'descripcion', 'precio_unitario', 'total'],
        ['codigo' => false]
    ));

    $person_type = $document->person?->person_type;

@endphp
<html>
<head>
    {{--<title>{{ $tittle }}</title>--}}
    {{--<link href="{{ $path_style }}" rel="stylesheet" />--}}
</head>
<body>

@if($company->logo)
    <div class="text-center company_logo_box pt-5">
        <img src="data:{{mime_content_type(public_path("{$logo}"))}};base64, {{base64_encode(file_get_contents(public_path("{$logo}")))}}" alt="{{ \App\CoreFacturalo\Helpers\CompanyDocumentDisplay::logoAlt($company) }}" class="company_logo_ticket contain">
    </div>
{{--@else--}}
    {{--<div class="text-center company_logo_box pt-5">--}}
        {{--<img src="{{ asset('logo/logo.jpg') }}" class="company_logo_ticket contain">--}}
    {{--</div>--}}
@endif
<table class="full-width">
    <tr>
        <td class="text-center">@include('pdf.partials.company_document_header_names')</td>
    </tr>
    <tr>
        <td class="text-center"><h5>{{ 'RIF '.$company->number }}</h5></td>
    </tr>
    <tr>
        <td class="text-center" style="text-transform: uppercase;">
            {{ ($establishment->address !== '-')? $establishment->address : '' }}
            {{ ($establishment->district_id !== '-')? ', '.$establishment->district->description : '' }}
            {{ ($establishment->province_id !== '-')? ', '.$establishment->province->description : '' }}
            {{ ($establishment->department_id !== '-')? '- '.$establishment->department->description : '' }}
        </td>
    </tr>
    <tr>
        <td class="text-center">{{ ($establishment->email !== '-')? $establishment->email : '' }}</td>
    </tr>
    <tr>
        <td class="text-center pb-3">{{ ($establishment->telephone !== '-')? $establishment->telephone : '' }}</td>
    </tr>
    <tr>
        <td class="text-center pt-3 border-top"><h4>COTIZACIÓN</h4></td>
    </tr>
    <tr>
        <td class="text-center pb-3 border-bottom"><h3>{{ $tittle }}</h3></td>
    </tr>
</table>
<table class="full-width">
    <tr>
        <td width="" class="pt-3"><p class="desc">F. Emisión:</p></td>
        <td width="" class="pt-3"><p class="desc">{{ $document->date_of_issue->format('Y-m-d') }}</p></td>
    </tr>


    <tr>
        <td class="align-top"><p class="desc">Cliente:</p></td>
        <td><p class="desc">{{ $customer->name }}</p></td>
    </tr>
    <tr>
        <td><p class="desc">{{ $customer->identity_document_type->description }}:</p></td>
        <td><p class="desc">{{ format_identity_document($customer->identity_document_type_id ?? null, $customer->number) }}</p></td>
    </tr>
    @if ($customer->address !== '')
        <tr>
            <td class="align-top"><p class="desc">Dirección:</p></td>
            <td>
                <p class="desc">
                    {{ $customer->address }}
                    {{ ($customer->district_id !== '-')? ', '.$customer->district->description : '' }}
                    {{ ($customer->province_id !== '-')? ', '.$customer->province->description : '' }}
                    {{ ($customer->department_id !== '-')? '- '.$customer->department->description : '' }}
                </p>
            </td>
        </tr>
    @endif
    @if(isset($configurationInPdf) && $configurationInPdf->show_seller_in_pdf)
        <tr>
            <td>Vendedor:</td>
            <td> @if($document->seller_id != 0){{$document->seller->name }} @else {{ $document->user->name }} @endif</td>
        </tr>
    @endif
    @if ($document->purchase_order)
        <tr>
            <td><p class="desc">Orden de Compra:</p></td>
            <td><p class="desc">{{ $document->purchase_order }}</p></td>
        </tr>
    @endif
    @if ($document->quotation_id)
        <tr>
            <td><p class="desc">Cotización:</p></td>
            <td><p class="desc">{{ $document->quotation->identifier }}</p></td>
        </tr>
    @endif
</table>

<table class="full-width mt-10 mb-10">
    <thead class="">
    <tr>
        @if($show_codigo) <th class="border-top-bottom desc-9 text-left">COD.</th> @endif
        @if($show_cantidad) <th class="border-top-bottom desc-9 text-left">CANT.</th> @endif
        @if($show_unidad) <th class="border-top-bottom desc-9 text-left">UNIDAD</th> @endif
        @if($show_descripcion) <th class="border-top-bottom desc-9 text-left">DESCRIPCIÓN</th> @endif
        @if($show_precio_unitario) <th class="border-top-bottom desc-9 text-left">P.UNIT</th> @endif
        @if($show_total) <th class="border-top-bottom desc-9 text-left">TOTAL</th> @endif
    </tr>
    </thead>
    <tbody>
    @foreach($document->items as $row)
        @inject('items', 'App\Models\Tenant\Item')
        @php
            $internal_id = isset($row->item->internal_id) ? $row->item->internal_id : optional($items->find($row->item_id))->internal_id;
        @endphp
        <tr>
            @if($show_codigo) <td class="text-center desc-9 align-top">{{ $internal_id }}</td> @endif
            @if($show_cantidad)
            <td class="text-center desc-9 align-top">
                @if(((int)$row->quantity != $row->quantity))
                    {{ $row->quantity }}
                @else
                    {{ number_format($row->quantity, 0) }}
                @endif
            </td>
            @endif
            @if($show_unidad) <td class="text-center desc-9 align-top">{{ $row->item->unit_type_id }}</td> @endif
            @if($show_descripcion)
            <td class="text-left desc-9 align-top">
                @if($row->name_product_pdf)
                    {!! \App\CoreFacturalo\Helpers\Template\TemplateHelper::formatNameProductPdfForTicket($row->name_product_pdf) !!}
                @else
                    {!!$row->item->description!!}
                @endif @if (!empty($row->item->presentation)) {!!$row->item->presentation->description!!} @endif
                @if($show_marca && !empty($row->relation_item->brand->name ?? null))
                    <br/><small>Marca: {{ $row->relation_item->brand->name }}</small>
                @endif
                @if($show_modelo && !empty($row->item->model ?? null))
                    <br/><small>Modelo: {{ $row->item->model }}</small>
                @endif
                @if($row->attributes)
                    @foreach($row->attributes as $attr)
                        <br/>{!! $attr->description !!} : {{ $attr->value }}
                    @endforeach
                @endif
                @if($show_descuento && $row->discounts)
                    @foreach($row->discounts as $dtos)
                        @if(!($dtos->from_global_distribution ?? false))
                            <br/><small>{{ ($dtos->is_amount ?? false) ? '' : ($dtos->factor * 100).'%' }} {{$dtos->description }}</small>
                        @endif
                    @endforeach
                @endif
                @if($show_serie)
                    @isset($row->item->lots)
                        @foreach($row->item->lots as $lot)
                            @if(isset($lot->has_sale) && $lot->has_sale)
                                <br/><small>Serie: {{ $lot->series }}</small>
                            @endif
                        @endforeach
                    @endisset
                @endif
            </td>
            @endif
            @if($show_precio_unitario) <td class="text-right desc-9 align-top">{{ number_format($row->unit_price, 2) }}</td> @endif
            @if($show_total) <td class="text-right desc-9 align-top">{{ number_format($row->total, 2) }}</td> @endif
        </tr>
        <tr>
            <td colspan="{{ $colspan_total }}" class="border-bottom"></td>
        </tr>
    @endforeach
        @if($document->total_exportation > 0)
            <tr>
                <td colspan="{{ $colspan_label }}" class="text-right font-bold desc">OP. EXPORTACIÓN: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold desc">{{ number_format($document->total_exportation, 2) }}</td>
            </tr>
        @endif
        @if($document->total_free > 0)
            <tr>
                <td colspan="{{ $colspan_label }}" class="text-right font-bold desc">OP. GRATUITAS: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold desc">{{ number_format($document->total_free, 2) }}</td>
            </tr>
        @endif
        @if($document->total_unaffected > 0)
            <tr>
                <td colspan="{{ $colspan_label }}" class="text-right font-bold desc">OP. INAFECTAS: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold desc">{{ number_format($document->total_unaffected, 2) }}</td>
            </tr>
        @endif
        @if($document->total_exonerated > 0)
            <tr>
                <td colspan="{{ $colspan_label }}" class="text-right font-bold desc">OP. EXONERADAS: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold desc">{{ number_format($document->total_exonerated, 2) }}</td>
            </tr>
        @endif
        {{-- @if($document->total_taxed > 0)
            <tr>
                <td colspan="{{ $colspan_label }}" class="text-right font-bold desc">OP. GRAVADAS: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold desc">{{ number_format($document->total_taxed, 2) }}</td>
            </tr>
        @endif --}}
        @if($document->total_discount_with_igv > 0)
            <tr>
                <td colspan="{{ $colspan_label }}" class="text-right font-bold">{{(($document->total_prepayment > 0) ? 'ANTICIPO':'DESCUENTO TOTAL')}}: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold">{{ number_format($document->total_discount_with_igv, 2) }}</td>
            </tr>
        @endif
        {{--<tr>
            {{-- ########## INICIO CAMBIO IGV A IVA --}}
            <td colspan="{{ $colspan_label }}" class="text-right font-bold desc">IVA: {{ $document->currency_type->symbol }}</td>
            {{-- ######### FIN CAMBIO IGV A IVA --}}
            <td class="text-right font-bold desc">{{ number_format($document->total_igv, 2) }}</td>
        </tr>--}}
        @if($show_nro_producto)
        <tr>
            <td colspan="{{ $colspan_label }}" class="text-left font-bold desc" style="white-space: nowrap;">Productos: {{ rtrim(rtrim(number_format(collect($document->items)->sum(function ($item) { return (float) data_get($item, 'quantity', 0); }), 2, '.', ''), '0'), '.') }}</td>
            <td class="text-right font-bold desc"></td>
        </tr>
        @endif
        <tr>
            <td colspan="{{ $colspan_label }}" class="text-right font-bold desc">TOTAL A PAGAR: {{ $document->currency_type->symbol }}</td>
            <td class="text-right font-bold desc">{{ number_format($document->total, 2) }}</td>
        </tr>
        @if($show_tipo_persona && $person_type && $person_type->enabled_description_person_type)
            <tr>
                <td colspan="{{ $colspan_total }}" class="text-left desc">
                    <span class="font-bold">{{ $person_type->description }}:</span> {{ $person_type->description_person_type }}
                </td>
            </tr>
        @endif
    </tbody>
</table>
<table class="full-width">
    <tr>

        @foreach(array_reverse((array) $document->legends) as $row)
            <tr>
                @if ($row->code == "1000")
                    <td class="desc pt-3" style="text-transform: uppercase;">Son: <span class="font-bold">{{ $row->value }} {{ $document->currency_type->description }}</span></td>
                    @if (count((array) $document->legends)>1)
                    <tr><td class="desc pt-3"><span class="font-bold">Leyendas</span></td></tr>
                    @endif
                @else
                    <td class="desc pt-3">{{$row->code}}: {{ $row->value }}</td>
                @endif
            </tr>
        @endforeach
    </tr>


</table>

@php
    $paymentCondition = \App\CoreFacturalo\Helpers\Template\TemplateHelper::getDocumentPaymentCondition($document);
@endphp
<table class="full-width">
    <tr>
        <td class="desc pt-5">
            <strong>CONDICIÓN DE PAGO: {{ $paymentCondition }} </strong>
        </td>
    </tr>
    @if($document->payment_method_type_id)
    <tr>
        <td class="desc pt-5">
            <strong>MÉTODO DE PAGO: </strong>{{ $document->payment_method_type->description }}
        </td>
    </tr>
    @endif
</table>

@if ($document->payment_condition_id === '01')
@if($payments->count())
<table class="full-width">
    <tr><td><strong>PAGOS:</strong> </td></tr>
    @php
        $payment = 0;
    @endphp
    @foreach($payments as $row)
        <tr><td>- {{ $row->date_of_payment->format('d/m/Y') }} - {{ $row->payment_method_type->description }} - {{ $row->reference ? $row->reference.' - ':'' }} {{ $document->currency_type->symbol }} {{ $row->payment }}</td></tr>
        @php
            $payment += (float) $row->payment;
        @endphp
    @endforeach
    <tr><td class="pb-10"><strong>SALDO:</strong> {{ $document->currency_type->symbol }} {{ number_format($document->total - $payment, 2) }}</td></tr>
</table>
@endif
@else
<table class="full-width">
    @foreach($document->fee as $key => $quote)
    <tr>
        <td class="desc pt-5">
            &#8226; {{ (empty($quote->getStringPaymentMethodType()) ? 'Cuota #'.( $key + 1) : $quote->getStringPaymentMethodType()) }} / Fecha: {{ $quote->date->format('d-m-Y') }} / Monto: {{ $quote->currency_type->symbol }}{{ $quote->amount }}
        </td>
    </tr>
    @endforeach
</table>
@endif
@if ($document->terms_condition)
    <br>
    <table class="full-width">
        <tr>
            <td>
                <h6 style="font-size: 10px; font-weight: bold;">Términos y condiciones del servicio</h6>
                {!! $document->terms_condition !!}
            </td>
        </tr>
    </table>
@endif
</body>
</html>
