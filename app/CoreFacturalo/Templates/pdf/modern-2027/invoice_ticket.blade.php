@php
    use Modules\Template\Helpers\TemplatePdf;

    $establishment = $document->establishment;
    $customer = $document->customer;
    $invoice = $document->invoice;
    //$path_style = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.DIRECTORY_SEPARATOR.'pdf'.DIRECTORY_SEPARATOR.'style.css');
    $document_number = $document->series.'-'.str_pad($document->number, 8, '0', STR_PAD_LEFT);

    // $accounts = \App\Models\Tenant\BankAccount::where('show_in_documents', true)->get();
    $accounts = (new TemplatePdf)->getBankAccountsForPdf($document->establishment_id);

    $document_base = ($document->note) ? $document->note : null;
    $payments = $document->payments;

    if($document_base) {
        $affected_document_number = ($document_base->affected_document) ? $document_base->affected_document->series.'-'.str_pad($document_base->affected_document->number, 8, '0', STR_PAD_LEFT) : $document_base->data_affected_document->series.'-'.str_pad($document_base->data_affected_document->number, 8, '0', STR_PAD_LEFT);

    } else {
        $affected_document_number = null;
    }
    $document->load('reference_guides');

    $total_payment = $document->payments->sum('payment');
    $balance = ($document->total - $total_payment) - $document->payments->sum('change');


    $logo = "storage/uploads/logos/{$company->logo}";
    if($establishment->logo) {
        $logo = "{$establishment->logo}";
    }

    $configurationInPdf= App\CoreFacturalo\Helpers\Template\TemplateHelper::getConfigurationInPdf();


    /* ------------------------------------------------------------------
     | modern-2027: variables propias del diseño del ticket
     ------------------------------------------------------------------ */
    // La descripción ahora vive en su propia columna, más angosta que en "default"
    $max_chars_description = config('tenant.enabled_template_ticket_80') ? 30 : 28;

    // Cajero: quien emitió el comprobante
    $m27_cashier = strtoupper(optional($document->user)->name ?? '');

    // Recibido / Vuelto tomados de los pagos registrados
    $m27_total_change = (float) $document->payments->sum('change');
    $m27_total_received = (float) $document->payments->sum('payment') + $m27_total_change;
    if ($m27_total_change <= 0 && $balance < 0) {
        $m27_total_change = abs($balance);
    }

    // Porcentaje de IGV mostrado junto a la etiqueta (ej: "IGV (18%)")
    $m27_igv_percentage = collect($document->items)
        ->map(function ($it) { return (float) ($it->percentage_igv ?? 0); })
        ->filter()
        ->max();
    $m27_igv_percentage = $m27_igv_percentage ? rtrim(rtrim(number_format($m27_igv_percentage, 2, '.', ''), '0'), '.') : null;

    // Cantidad total de unidades vendidas
    $m27_total_products = rtrim(rtrim(number_format(collect($document->items)->sum(function ($item) {
        return (float) data_get($item, 'quantity', 0);
    }), 2, '.', ''), '0'), '.');

    // Código impreso como código de barras al pie
    $m27_barcode = preg_replace('/[^0-9A-Za-z\-]/', '', $document_number);

    $m27_thanks = '¡Gracias por su preferencia!';
@endphp
<html>
<head>
    {{--<title>{{ $document_number }}</title>--}}
    {{--<link href="{{ $path_style }}" rel="stylesheet" />--}}
</head>
<body class="ticket">

@if($company->logo)
    <table class="full-width">
        <tr>
            <td class="text-center pt-3 pb-2">
                <img
                    src="data:{{mime_content_type(public_path("{$logo}"))}};base64, {{base64_encode(file_get_contents(public_path("{$logo}")))}}"
                    alt="{{ \App\CoreFacturalo\Helpers\CompanyDocumentDisplay::logoAlt($company) }}" class="m27-logo contain">
            </td>
        </tr>
    </table>
@endif

@if($document->state_type->id == '11')
    <div class="company_logo_box" style="position: absolute; text-align: center; top:500px">
        <img
            src="data:{{mime_content_type(public_path("status_images".DIRECTORY_SEPARATOR."anulado.png"))}};base64, {{base64_encode(file_get_contents(public_path("status_images".DIRECTORY_SEPARATOR."anulado.png")))}}"
            alt="anulado" class="" style="opacity: 0.6;">
    </div>
@endif
@if($document->state_type->id == '09')
    <div style="position: absolute; width: 100%; text-align: center; top:30%; left: 0; right: 0; margin: auto;">
        <img
            src="data:{{mime_content_type(public_path("status_images".DIRECTORY_SEPARATOR."rechazado.png"))}};base64, {{base64_encode(file_get_contents(public_path("status_images".DIRECTORY_SEPARATOR."rechazado.png")))}}"
            alt="rechazado" class="" style="opacity: 0.6; width: 50%;">
    </div>
@endif
@if(isset($configuration['is_preview']) && $configuration['is_preview'])
    <div style="position: absolute; width: 100%; text-align: center; top:30%; left: 0; right: 0; margin: auto;">
        <img
            src="data:{{mime_content_type(public_path("status_images".DIRECTORY_SEPARATOR."vista_previa.png"))}};base64, {{base64_encode(file_get_contents(public_path("status_images".DIRECTORY_SEPARATOR."vista_previa.png")))}}"
            alt="vista previa" class="" style="opacity: 0.6; width: 50%;">
    </div>
@endif
<table class="full-width">
    <tr>
        <td class="m27-company">@include('pdf.partials.company_document_header_names', [
            'tagPrimary' => 'div',
            'tagLegal' => 'div',
            'primaryClass' => 'm27-company',
            'legalClass' => 'm27-company-legal',
            'legalLineStyle' => '',
        ])</td>
    </tr>
    <tr>
        <td class="m27-head-line">RIF: {{ $company->number }}</td>
    </tr>
    <tr>
        <td class="m27-head-line" style="text-transform: uppercase;">
            {{ ($establishment->address !== '-')? $establishment->address : '' }}
            {{ ($establishment->district_id !== '-')? ', '.$establishment->district->description : '' }}
            {{ ($establishment->province_id !== '-')? ', '.$establishment->province->description : '' }}
            {{ ($establishment->department_id !== '-')? '- '.$establishment->department->description : '' }}
        </td>
    </tr>
    @isset($establishment->trade_address)
        <tr>
            <td class="m27-head-line">{{ ($establishment->trade_address !== '-')? 'D. Comercial: '.$establishment->trade_address : '' }}</td>
        </tr>
    @endisset
    @if($establishment->telephone !== '-' && $establishment->telephone !== '')
        <tr>
            <td class="m27-head-line">Telf: {{ $establishment->telephone }}</td>
        </tr>
    @endif
    @if($establishment->email !== '-' && $establishment->email !== '')
        <tr>
            <td class="m27-head-line">{{ $establishment->email }}</td>
        </tr>
    @endif
    @isset($establishment->web_address)
        <tr>
            <td class="m27-head-line">{{ ($establishment->web_address !== '-')? $establishment->web_address : '' }}</td>
        </tr>
    @endisset
    @isset($establishment->aditional_information)
        <tr>
            <td class="m27-head-line">{{ ($establishment->aditional_information !== '-')? $establishment->aditional_information : '' }}</td>
        </tr>
    @endisset
    <tr>
        <td class="m27-gap"></td>
    </tr>
    <tr>
        <td class="m27-doc-type">{{ $document->document_type->description }}</td>
    </tr>
    <tr>
        <td class="m27-gap"></td>
    </tr>
    <tr>
        <td class="m27-doc-number">{{ $document_number }}</td>
    </tr>
    <tr>
        <td class="m27-gap"></td>
    </tr>
</table>
@include('pdf.modern-2027.partials.rule')
<table class="full-width">
    <tr>
        <td colspan="2" class="m27-gap"></td>
    </tr>
    <tr>
        <td width="38%" class="m27-label"><p class="m27-label">Fecha:</p></td>
        <td class="m27-value"><p class="m27-value">{{ $document->date_of_issue->format('d/m/Y') }} {{ $document->time_of_issue }}</p></td>
    </tr>
    @isset($invoice->date_of_due)
        <tr>
            <td class="m27-label"><p class="m27-label">F. Vencimiento:</p></td>
            <td class="m27-value"><p class="m27-value">{{ $invoice->date_of_due->format('d/m/Y') }}</p></td>
        </tr>
    @endisset

    <tr>
        <td class="align-top m27-label"><p class="m27-label">Cliente:</p></td>
        <td class="m27-value"><p class="m27-value">{{ $customer->name }}</p></td>
    </tr>
    <tr>
        <td class="m27-label"><p class="m27-label">{{ $customer->identity_document_type->description }}:</p></td>
        <td class="m27-value"><p class="m27-value">{{ format_identity_document($customer->identity_document_type_id ?? null, $customer->number) }}</p></td>
    </tr>
    <tr>
        <td class="m27-label"><p class="m27-label">Cajero:</p></td>
        <td class="m27-value"><p class="m27-value">{{ $m27_cashier }}</p></td>
    </tr>
    @if ($customer->address !== '')
        <tr>
            <td class="align-top m27-label"><p class="m27-label">Dirección:</p></td>
            <td>
                <p class="m27-value">
                    {{ $customer->address }}
                    {{ ($customer->district_id !== '-')? ', '.$customer->district->description : '' }}
                    {{ ($customer->province_id !== '-')? ', '.$customer->province->description : '' }}
                    {{ ($customer->department_id !== '-')? '- '.$customer->department->description : '' }}
                </p>
            </td>
        </tr>
    @endif
    @if ($document->consigned_id)
    @php
        $consigned = App\Models\Tenant\Consigned::where('id',$document->consigned_id)->first();
        $district = App\Models\Tenant\Catalogs\District::where('id', $document->consigned_ubigeo)->first();
        $consigned_phone = App\Models\Tenant\PersonAddress::where('person_id', $document->customer_id)
            ->where('address', $document->consigned_address)
            ->where('consigned_id', $document->consigned_id)
            ->first();

        if($district){
            $department = $district->province->department;
            $province = $district->province;
        }
    @endphp
    <tr>
        <td class="align-top m27-label"><p class="m27-label">Consignado:</p></td>
        <td>
            <p class="m27-value">
                {{ $consigned->number }} -
                {{ $consigned->name }} -
                {{ ($consigned_phone->phone)? $consigned_phone->phone : ' ' }} -
                {{ $document->consigned_address }}
                {{($district) ? ' , '.$district->description.' , '.$province->description.' , '.$department->description: ' '}}
            </p>
        </td>
    </tr>
@endif


    @if ($document->reference_data)
        <tr>
            <td class="align-top m27-label"><p class="m27-label">D. Referencia:</p></td>
            <td>
                <p class="m27-value">
                    {{ $document->reference_data }}
                </p>
            </td>
        </tr>
    @endif

    @if ($document->isPointSystem())
        <tr>
            <td><p class="m27-label">P. Acumulados:</p></td>
            <td><p class="m27-value">{{ $document->person->accumulated_points }}</p></td>
        </tr>
        <tr>
            <td><p class="m27-label">Puntos por la compra:</p></td>
            <td><p class="m27-value">{{ $document->getPointsBySale() }}</p></td>
        </tr>
    @endif



    @if ($document->retention)
        <br>
        <tr>
            <td colspan="2">
                <p class="m27-value"><strong>Información de la retención</strong></p>
            </td>
        </tr>
        <tr>
            <td><p class="m27-value">Base imponible de la retención: </p></td>
            <td><p class="m27-value">Bs. {{ $document->getRetentionTaxBase() }} </p></td>
        </tr>
        <tr>
            <td><p class="m27-label">Porcentaje de la retención:</p></td>
            <td><p class="m27-value">{{ $document->retention->percentage * 100 }}%</p></td>
        </tr>
        <tr>
            <td><p class="m27-label">Monto de la retención:</p></td>
            <td><p class="m27-value">Bs. {{ $document->retention->amount_pen }}</p></td>
        </tr>
    @endif


    @if ($document->prepayments)
        @foreach($document->prepayments as $p)
            <tr>
                <td><p class="m27-label">Anticipo :</p></td>
                <td><p class="m27-value">{{$p->number}}</p></td>
            </tr>
        @endforeach
    @endif
    @if ($document->purchase_order)
        <tr>
            <td><p class="m27-label">Orden de Compra:</p></td>
            <td><p class="m27-value">{{ $document->purchase_order }}</p></td>
        </tr>
    @endif
    @if ($document->quotation_id)
        <tr>
            <td><p class="m27-label">Cotización:</p></td>
            <td><p class="m27-value">{{ $document->quotation->identifier }}</p></td>
        </tr>
    @endif
    @isset($document->quotation->delivery_date)
        <tr>
            <td><p class="m27-value">F. Entrega</p></td>
            <td>
                <p class="m27-value">{{ $document->date_of_issue->addDays($document->quotation->delivery_date)->format('d-m-Y') }}</p>
            </td>
        </tr>
    @endisset
    @isset($document->quotation->sale_opportunity)
        <tr>
            <td><p class="m27-value">O. Venta</p></td>
            <td><p class="m27-value">{{ $document->quotation->sale_opportunity->number_full}}</p></td>
        </tr>
    @endisset
    @if ($document->plate_number !== null)
    <tr>
        <td ><p class="m27-label">N° Placa:</p></td>
        <td ><p class="m27-value">{{ $document->plate_number }}</p></td>
    </tr>
    @endif
</table>

@if ($document->guides)
    {{--<strong>Órdenes de entrega:</strong>--}}
    <table>
        @foreach($document->guides as $guide)
            <tr>
                @if(isset($guide->document_type_description))
                    <td class="m27-value">{{ $guide->document_type_description }}</td>
                @else
                    <td class="m27-value">{{ $guide->document_type_id }}</td>
                @endif
                <td class="m27-value">:</td>
                <td class="m27-value">{{ $guide->number }}</td>
            </tr>
        @endforeach
    </table>
@endif


@if ($document->transport)
    <p class="m27-label">Transporte de pasajeros</p>

    @php
        $transport = $document->transport;
        $origin_district_id = (array)$transport->origin_district_id;
        $destinatation_district_id = (array)$transport->destinatation_district_id;
        $origin_district = Modules\Order\Services\AddressFullService::getDescription($origin_district_id[2]);
        $destinatation_district = Modules\Order\Services\AddressFullService::getDescription($destinatation_district_id[2]);
    @endphp


    <table class="full-width mt-3">
        <tr>
            <td><p class="m27-label">{{ $transport->identity_document_type->description }}:</p></td>
            <td><p class="m27-value">{{ $transport->number_identity_document }}</p></td>
        </tr>
        <tr>
            <td><p class="m27-label">Nombre:</p></td>
            <td><p class="m27-value">{{ $transport->passenger_fullname }}</p></td>
        </tr>


        <tr>
            <td><p class="m27-label">N° Asiento:</p></td>
            <td><p class="m27-value">{{ $transport->seat_number }}</p></td>
        </tr>
        <tr>
            <td><p class="m27-label">M. Pasajero:</p></td>
            <td><p class="m27-value">{{ $transport->passenger_manifest }}</p></td>
        </tr>

        <tr>
            <td><p class="m27-label">F. Inicio:</p></td>
            <td><p class="m27-value">{{ $transport->start_date }}</p></td>
        </tr>
        <tr>
            <td><p class="m27-label">H. Inicio:</p></td>
            <td><p class="m27-value">{{ $transport->start_time }}</p></td>
        </tr>


        <tr>
            <td><p class="m27-label">U. Origen:</p></td>
            <td><p class="m27-value">{{ $origin_district }}</p></td>
        </tr>
        <tr>
            <td><p class="m27-label">D. Origen:</p></td>
            <td><p class="m27-value">{{ $transport->origin_address }}</p></td>
        </tr>

        <tr>
            <td><p class="m27-label">U. Destino:</p></td>
            <td><p class="m27-value">{{ $destinatation_district }}</p></td>
        </tr>
        <tr>
            <td><p class="m27-label">D. Destino:</p></td>
            <td><p class="m27-value">{{ $transport->destinatation_address }}</p></td>
        </tr>

    </table>
@endif


@if ($document->dispatch)
    <br/>
    <strong>Órdenes de entrega</strong>
    <table>
        <tr>
            <td>{{ $document->dispatch->number_full }}</td>
        </tr>
    </table>

@elseif (count($document->reference_guides) > 0)
    <br/>
    <strong>Órdenes de entrega</strong>
    <table>
        @foreach($document->reference_guides as $guide)
            <tr>
                <td>{{ $guide->series }}</td>
                <td>-</td>
                <td>{{ $guide->number }}</td>
            </tr>
        @endforeach
    </table>
@endif

@if(!is_null($document_base))
    <table>
        <tr>
            <td class="m27-label">Documento afectado:</td>
            <td class="m27-value">{{ $affected_document_number }}</td>
        </tr>
        <tr>
            <td class="m27-label">Tipo de nota:</td>
            <td class="m27-value">{{ ($document_base->note_type === 'credit')?$document_base->note_credit_type->description:$document_base->note_debit_type->description}}</td>
        </tr>
        <tr>
            <td class="align-top m27-label">Descripción:</td>
            <td class="text-left m27-value">{{ $document_base->note_description }}</td>
        </tr>
    </table>
@endif

@include('pdf.modern-2027.partials.rule')
<table class="full-width mt-10 mb-10">
    <thead>
    <tr>
        <th class="m27-th m27-th-line text-left" width="13%">Cant</th>
        <th class="m27-th m27-th-line text-left" width="59%">Descripción</th>
        <th class="m27-th m27-th-line text-right" width="28%">Total</th>
    </tr>
    </thead>
    <tbody>
    @php
        $data_json_items = collect($document->data_json->items ?? []);
        $fusionados = $data_json_items->filter(function($item) {return !empty($item->esFusionado);});
        $hay_fusionados = $fusionados->isNotEmpty();
        $cantidad_fusionada = $fusionados->reduce(function($carry, $item) {
            return $carry + (float) ($item->cantidad ?? 0);
        }, 0);

        $total_fusionado = $fusionados->reduce(function($carry, $item) {
            return $carry + (float) ($item->total_item ?? 0);
        }, 0);
    @endphp
    @if($hay_fusionados)
        <tr>
            <td class="m27-item text-left">{{ number_format($cantidad_fusionada, 0) }}</td>
            <td class="m27-item text-left">Por consumo</td>
            <td class="m27-item text-right font-bold">{{ number_format($total_fusionado, 2) }}</td>
        </tr>
        <tr>
            <td></td>
            <td colspan="2" class="m27-item-sub">001 ({{ $document->currency_type->symbol }} {{ number_format($total_fusionado, 2) }})</td>
        </tr>
    @else
        @foreach($document->items as $row)
            @php
                $item_code = $row->item->internal_id;
                $item_quantity = ((int)$row->quantity != $row->quantity) ? $row->quantity : number_format($row->quantity, 0);
                $use_name_product_pdf = !empty($row->name_product_pdf);
                $item_description_html = null;

                if ($use_name_product_pdf) {
                    $item_description_html = \App\CoreFacturalo\Helpers\Template\TemplateHelper::formatNameProductPdfForTicket($row->name_product_pdf);
                    $item_description_html = \App\CoreFacturalo\Helpers\Template\TemplateHelper::stripLeadingItemCode($item_description_html, $item_code);
                    if (!empty($row->item->presentation)) {
                        $item_description_html .= '<br/>'.$row->item->presentation->description;
                    }
                    $item_description = strip_tags($item_description_html);
                } else {
                    $item_description = $row->item->description;
                    if (!empty($row->item->presentation)) {
                        $item_description .= ' '.$row->item->presentation->description;
                    }
                    $item_description = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $item_description))));
                    $item_description = \App\CoreFacturalo\Helpers\Template\TemplateHelper::stripLeadingItemCode($item_description, $item_code);
                    if (mb_strlen($item_description) > $max_chars_description) {
                        $item_description = rtrim(mb_substr($item_description, 0, max($max_chars_description - 1, 1))).'.';
                    }
                }

                // El código va en la línea secundaria del ítem, no en la descripción
                $show_item_code = trim((string) $item_code) !== '';
            @endphp
            <tr>
                <td class="m27-item text-left">{{ $item_quantity }}</td>
                <td class="m27-item text-left">
                    @if($use_name_product_pdf)
                        {!! $item_description_html !!}
                    @else
                        {{ $item_description }}
                    @endif

                    {{-- ########## INICIO SIN DETRACCIONES E ISC --}}
                    @if(\App\Services\LocalFiscalDocumentPolicy::showIsc() && ($row->total_isc > 0))
                    {{-- ######### FIN SIN DETRACCIONES E ISC --}}
                        <br/>ISC : {{ $row->total_isc }} ({{ $row->percentage_isc }}%)
                    @endif

                    @if($row->total_plastic_bag_taxes > 0)
                        <br/>ICBPER : {{ $row->total_plastic_bag_taxes }}
                    @endif

                    @foreach($row->additional_information as $information)
                        @if ($information)
                            <br/>{{ $information }}
                        @endif
                    @endforeach

                    @if($row->attributes)
                        @foreach($row->attributes as $attr)
                            {{-- Excluir atributos de placa (diferentes variaciones de texto) --}}
                            @if(!in_array(strtoupper(trim($attr->description)), [
                                'PLACA', 
                                'NRO PLACA', 
                                'NUMERO DE PLACA', 
                                'NÚMERO DE PLACA', 
                                'N° PLACA',
                                'NUMERO PLACA',
                                'NRO DE PLACA'
                            ]))
                                <br/>{!! $attr->description !!} : {{ $attr->value }}
                            @endif
                        @endforeach
                    @endif
                    @if($row->discounts)
                        @foreach($row->discounts as $dtos)
                            @if(!($dtos->from_global_distribution ?? false))
                                <br/><small>{{ ($dtos->is_amount ?? false) ? '' : ($dtos->factor * 100).'%' }} {{$dtos->description }}</small>
                            @endif
                        @endforeach
                    @endif

                    @if($row->charges)
                        @foreach($row->charges as $charge)
                            <br/><small>{{ $document->currency_type->symbol}} {{ $charge->amount}}
                                ({{ $charge->factor * 100 }}%) {{$charge->description }}</small>
                        @endforeach
                    @endif

                    @if($row->item->is_set == 1)

                        <br>
                        @inject('itemSet', 'App\Services\ItemSetService')
                        @foreach ($itemSet->getItemsSet($row->item_id) as $item)
                            {{$item}}<br>
                        @endforeach
                        {{-- {{join( "-", $itemSet->getItemsSet($row->item_id) )}} --}}
                    @endif

                    @if($row->item->used_points_for_exchange ?? false)
                        <br>
                        <small>*** Canjeado por {{$row->item->used_points_for_exchange}} puntos ***</small>
                    @endif

                    @if($document->has_prepayment)
                        <br>
                        *** Pago Anticipado ***
                    @endif
                    @inject('itemLotGroup', 'App\Services\ItemLotsGroupService')
                    @php
                        $lot = optional($row->item)->IdLoteSelected ? $itemLotGroup->getLote($row->item->IdLoteSelected) : '';
                        $date_due = optional($row->item)->IdLoteSelected ? $itemLotGroup->getLotDateOfDue($row->item->IdLoteSelected) : '';
                    @endphp
                    @if($lot)
                        <small style="display:block; font-weight: normal; font-size: 7px;">
                            Lote: {{ ltrim($lot, '/') }}  
                            <br>
                            FV: 
                            @if($date_due != '')
                                {{ ltrim($date_due, '/') }}
                            @elseif($row->relation_item->date_of_due)
                                {{ $row->relation_item->date_of_due->format('y-m-d') }}
                            @endif 
                            <br>
                        </small>
                    @endif
                    <small style="display:block; font-weight: normal; font-size: 7px;">
                        @isset($row->item->lots)
                            @foreach($row->item->lots as $lot)
                                @if( isset($lot->has_sale) && $lot->has_sale)
                                    <span> Serie: {{ $lot->series }}</span><br>
                                @endif
                            @endforeach
                        @endisset
                    </small>
                </td>
                <td class="m27-item text-right">{{ number_format($row->total, 2) }}</td>
            </tr>
            <tr>
                <td></td>
                <td colspan="2" class="m27-item-sub text-left">
                    @php
                        // Blade se come un "(...)" que siga a una directiva como @endif,
                        // asi que la linea secundaria se arma aqui y se imprime entera.
                        $item_unit_price = optional($row->item)->unit_price ? $row->item->unit_price : $row->unit_price;
                        $item_unit_label = $row->item->unit_type_id !== 'NIU' ? $row->item->unit_type_id.' x ' : '';
                        $item_code_label = trim((string) $item_code);
                        $item_sub_line = ($item_code_label !== '' ? $item_code_label.' ' : '')
                            .'('.$item_unit_label.$document->currency_type->symbol.' '.number_format($item_unit_price, 2).')';
                    @endphp
                    {{ $item_sub_line }}
                </td>
            </tr>
        @endforeach
    @endif
    @if ($document->prepayments)
        @foreach($document->prepayments as $p)
            <tr>
                <td class="m27-item">1</td>
                <td class="m27-item">ANTICIPO: {{($p->document_type_id == '02')? 'FACTURA':'BOLETA'}} NRO. {{$p->number}}</td>
                <td class="m27-item text-right font-bold">-{{ number_format($p->total, 2) }}</td>
            </tr>
            <tr>
                <td></td>
                <td colspan="2" class="m27-item-sub">({{ $document->currency_type->symbol }} -{{ number_format($p->total, 2) }})</td>
            </tr>
        @endforeach
    @endif
    </tbody>
</table>
@include('pdf.modern-2027.partials.rule')
<table class="full-width">
    <tbody>
    <tr>
        <td class="m27-colspec" width="16%"></td>
        <td class="m27-colspec" width="56%"></td>
        <td class="m27-colspec" width="28%"></td>
    </tr>
    <tr>
        <td colspan="3" class="m27-gap"></td>
    </tr>

    @if($document->total_exportation > 0)
        <tr>
            <td colspan="2" class="m27-total-label">Op. exportación:</td>
            <td class="m27-total-value">{{ $document->currency_type->symbol }} {{ number_format($document->total_exportation, 2) }}</td>
        </tr>
    @endif
    @if($document->total_free > 0)
        <tr>
            <td colspan="2" class="m27-total-label">Op. gratuitas:</td>
            <td class="m27-total-value">{{ $document->currency_type->symbol }} {{ number_format($document->total_free, 2) }}</td>
        </tr>
    @endif
    @if($document->total_unaffected > 0)
        <tr>
            <td colspan="2" class="m27-total-label">Op. inafectas:</td>
            <td class="m27-total-value">{{ $document->currency_type->symbol }} {{ number_format($document->total_unaffected, 2) }}</td>
        </tr>
    @endif
    @if($document->total_exonerated > 0)
        <tr>
            <td colspan="2" class="m27-total-label">Op. exoneradas:</td>
            <td class="m27-total-value">{{ $document->currency_type->symbol }} {{ number_format($document->total_exonerated, 2) }}</td>
        </tr>
    @endif
    @if ($document->document_type_id === '07')
        @if($document->total_taxed >= 0)
            <tr>
                <td colspan="2" class="m27-total-label">Op. gravadas:</td>
                <td class="m27-total-value">{{ $document->currency_type->symbol }} {{ number_format($document->total_taxed, 2) }}</td>
            </tr>
        @endif
    @elseif($document->total_taxed > 0)
        <tr>
            <td colspan="2" class="m27-total-label">Op. gravadas:</td>
            <td class="m27-total-value">{{ $document->currency_type->symbol }} {{ number_format($document->total_taxed, 2) }}</td>
        </tr>
    @endif

    @if($document->subtotal > 0)
        @php
            $labelSubtotal = $document->total_discount_with_igv > 0 ? 'Suma de importes' : 'Subtotal';
            $subtotal = $document->total_discount_with_igv > 0 ? $document->subtotal + $document->total_discount_with_igv : $document->subtotal;
        @endphp
        <tr>
            <td colspan="2" class="m27-total-label">{{ $labelSubtotal }}:</td>
            <td class="m27-total-value">{{ $document->currency_type->symbol }} {{ number_format($subtotal, 2) }}</td>
        </tr>
    @endif

    @if($document->total_discount_with_igv > 0)
        <tr>
            <td colspan="2" class="m27-total-label">Descuento total:</td>
            <td class="m27-total-value">- {{ $document->currency_type->symbol }} {{ number_format($document->total_discount_with_igv, 2) }}</td>
        </tr>
    @endif

    @if($document->total_charge > 0)
        @if($document->charges)
            @php
                $total_factor = 0;
                foreach($document->charges as $charge) {
                    $total_factor = ($total_factor + $charge->factor) * 100;
                }
            @endphp
            <tr>
                <td colspan="2" class="m27-total-label">Cargos ({{ $total_factor }}%):</td>
                <td class="m27-total-value">{{ $document->currency_type->symbol }} {{ number_format($document->total_charge, 2) }}</td>
            </tr>
        @else
            <tr>
                <td colspan="2" class="m27-total-label">Cargos:</td>
                <td class="m27-total-value">{{ $document->currency_type->symbol }} {{ number_format($document->total_charge, 2) }}</td>
            </tr>
        @endif
    @endif

    @if($document->total_plastic_bag_taxes > 0)
        <tr>
            <td colspan="2" class="m27-total-label">ICBPER:</td>
            <td class="m27-total-value">{{ $document->currency_type->symbol }} {{ number_format($document->total_plastic_bag_taxes, 2) }}</td>
        </tr>
    @endif

    {{-- ########## INICIO SIN DETRACCIONES E ISC --}}
    @if(\App\Services\LocalFiscalDocumentPolicy::showIsc() && ($document->total_isc > 0))
    {{-- ######### FIN SIN DETRACCIONES E ISC --}}
        <tr>
            <td colspan="2" class="m27-total-label">ISC:</td>
            <td class="m27-total-value">{{ $document->currency_type->symbol }} {{ number_format($document->total_isc, 2) }}</td>
        </tr>
    @endif

    <tr>
        <td colspan="2" class="m27-total-label">IGV{{ $m27_igv_percentage ? ' ('.$m27_igv_percentage.'%)' : '' }}:</td>
        <td class="m27-total-value">{{ $document->currency_type->symbol }} {{ number_format($document->total_igv, 2) }}</td>
    </tr>

    </tbody>
</table>
@include('pdf.modern-2027.partials.rule')
<table class="full-width">
    <tbody>
    <tr>
        <td class="m27-colspec" width="16%"></td>
        <td class="m27-colspec" width="56%"></td>
        <td class="m27-colspec" width="28%"></td>
    </tr>

    @if ($document->retention)
        <tr>
            <td colspan="2" class="m27-grand-label">IMPORTE TOTAL:</td>
            <td class="m27-grand-value">{{ $document->currency_type->symbol }} {{ number_format($document->total, 2) }}</td>
        </tr>
        <tr>
            <td colspan="2" class="m27-total-label">Importe neto:</td>
            <td class="m27-total-value">{{ $document->currency_type->symbol }} {{ number_format($document->total - $document->retention->amount_pen, 2) }}</td>
        </tr>
    @else
        <tr>
            <td class="m27-item-sub align-bottom" style="white-space: nowrap;">{{ $m27_total_products }} und.</td>
            <td class="m27-grand-label">TOTAL:</td>
            <td class="m27-grand-value">{{ $document->currency_type->symbol }} {{ number_format($document->total, 2) }}</td>
        </tr>
    @endif

    @if(($document->retention) && $document->total_pending_payment > 0)
        <tr>
            <td colspan="2" class="m27-total-label">M. pendiente:</td>
            <td class="m27-total-value">{{ $document->currency_type->symbol }} {{ number_format($document->total_pending_payment, 2) }}</td>
        </tr>
    @endif
    </tbody>
</table>
@include('pdf.modern-2027.partials.rule')
{{-- El bloque cierra con su propio separador: si no hay leyendas no se
     imprime nada y no quedan dos separadores seguidos. --}}
@if(count((array) $document->legends))
<table class="full-width">
    @foreach(array_reverse((array) $document->legends) as $row)
        <tr>
            @if ($row->code == "1000")
                <td class="m27-value pt-2" colspan="2">Son: <span class="font-bold">{{ $row->value }} {{ $document->currency_type->description }}</span></td>
            @else
                <td class="m27-value pt-2" colspan="2">{{$row->code}}: {{ $row->value }}</td>
            @endif
        </tr>
    @endforeach
</table>
@include('pdf.modern-2027.partials.rule')
@endif

@php
    $paymentCondition = \App\CoreFacturalo\Helpers\Template\TemplateHelper::getDocumentPaymentCondition($document);
@endphp
<table class="full-width">
    <tr>
        <td class="m27-gap" colspan="2"></td>
    </tr>
    @if($document->payment_method_type_id)
        <tr>
            <td class="m27-label" width="45%">Método de pago:</td>
            <td class="m27-value">{{ $document->payment_method_type->description }}</td>
        </tr>
    @endif
    @if($m27_total_received > 0)
        <tr>
            <td class="m27-label">Recibido:</td>
            <td class="m27-value">{{ $document->currency_type->symbol }} {{ number_format($m27_total_received, 2) }}</td>
        </tr>
    @endif
    @if($m27_total_change > 0)
        <tr>
            <td class="m27-label">Vuelto:</td>
            <td class="m27-value">{{ $document->currency_type->symbol }} {{ number_format($m27_total_change, 2) }}</td>
        </tr>
    @endif
    <tr>
        <td class="m27-label" width="45%">Condición de pago:</td>
        <td class="m27-value">{{ $paymentCondition }}</td>
    </tr>
</table>

@if ($document->payment_condition_id === '01')
    @if($payments->count())
        <table class="full-width">
            <tr>
                <td class="m27-label pt-2">Pagos:</td>
            </tr>
            @foreach($payments as $row)
                <tr>
                    <td class="m27-value">&#8226; {{ $row->payment_method_type->description }} {{ $row->reference ? '- '.$row->reference.' ':'' }}- {{ $document->currency_type->symbol }} {{ number_format($row->payment + $row->change, 2) }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@else
    @if(count($document->fee) > 0)
        <table class="full-width">
            <tr>
                <td class="m27-label pt-2">Cuotas:</td>
            </tr>
            @foreach($document->fee as $key => $quote)
                <tr>
                    <td class="m27-value">&#8226; {{ (empty($quote->getStringPaymentMethodType()) ? 'Cuota #'.( $key + 1) : $quote->getStringPaymentMethodType()) }} / {{ $quote->date->format('d-m-Y') }} / {{ $quote->currency_type->symbol }} {{ $quote->amount }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endif

<table class="full-width">
    @foreach($document->additional_information as $information)
        @if ($information)
            @if ($loop->first)
                <tr>
                    <td class="m27-label pt-2">Información adicional:</td>
                </tr>
            @endif
            <tr>
                <td class="m27-value">{{ $information }}</td>
            </tr>
        @endif
    @endforeach

    @if(isset($configurationInPdf) && $configurationInPdf->show_bank_accounts_in_pdf)
        @if(in_array($document->document_type->id,['01','03']))
            @foreach($accounts as $account)
                @if($loop->first)
                    <tr>
                        <td class="m27-label pt-2">Cuentas bancarias:</td>
                    </tr>
                @endif
                <tr>
                    <td class="m27-item-note">
                        <span class="font-bold">{{$account->bank->description}}</span> {{$account->currency_type->description}}
                        N°: {{$account->number}}@if($account->cci) / CCI: {{$account->cci}}@endif
                    </td>
                </tr>
            @endforeach
        @endif
    @endif

    @if(isset($configurationInPdf) && $configurationInPdf->show_seller_in_pdf)
        <tr>
            <td class="m27-value pt-2"><span class="font-bold">VENDEDOR:</span> {{ $document->seller ? $document->seller->name : $document->user->name }}</td>
        </tr>
    @endif

    <tr>
        <td class="m27-item-note pt-2">CÓDIGO HASH: {{ $document->hash }}</td>
    </tr>
    @if($document->qr)
        <tr>
            <td class="text-center pt-2">
                <img class="m27-qr" src="data:image/png;base64, {{ $document->qr }}" />
            </td>
        </tr>
    @endif
</table>
<table class="full-width">
    @if ($customer->department_id == 16)
        <tr>
            <td class="m27-note pt-3">
                Representación impresa del Comprobante de Pago Electrónico.
                <br/>Esta puede ser consultada en:
                <br/><span class="font-bold">{!! url('/buscar') !!}</span>
                <br/>"Bienes transferidos en la Amazonía
                <br/>para ser consumidos en la misma"
            </td>
        </tr>
    @endif
    @if ($document->terms_condition)
        <tr>
            <td class="m27-item-note pt-2">
                <span class="font-bold">TÉRMINOS Y CONDICIONES DEL SERVICIO</span>
                <div style="font-size: 8px;">
                    {!! $document->terms_condition !!}
                </div>
            </td>
        </tr>
    @endif
</table>
@include('pdf.modern-2027.partials.rule')
<table class="full-width">
    <tr>
        <td class="m27-note pt-2">
            @if($configurationInPdf->legend_footer_sale)
                {!! $configurationInPdf->legend_footer_sale !!}<br/>
            @endif
            @if($document->document_type)
                Representación impresa de la <span style="text-transform: capitalize;">{{ $document->document_type->description }}</span>.<br/>
            @endif
            @if(!in_array($document->document_type_id, ['09']))
                Consúltela en {!! url('/buscar') !!}
            @endif
        </td>
    </tr>
</table>
<table class="full-width">
    <tr>
        <td class="m27-thanks pt-2">{{ $m27_thanks }}</td>
    </tr>
    @if($m27_barcode)
        <tr>
            <td class="m27-barcode pt-3">
                <barcode code="{{ $m27_barcode }}" type="C128B" size="0.9" height="0.9"/>
            </td>
        </tr>
        <tr>
            <td class="m27-barcode-text">{{ $m27_barcode }}</td>
        </tr>
    @endif
</table>
</body>
