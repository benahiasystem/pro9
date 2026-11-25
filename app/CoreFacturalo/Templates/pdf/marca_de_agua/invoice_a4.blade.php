{{-- ######## INICIO PERSISTENCIA FISCAL VENEZUELA ######## --}}
{{-- ######## INICIO MIGRACIÓN MONEDA VENEZUELA ######## --}}
@php
    use Modules\Template\Helpers\TemplatePdf;

    $accounts = (new TemplatePdf)->getBankAccountsForPdf($document->establishment_id);

    $establishment = $document->establishment;
    $customer = $document->customer;
    $invoice = $document->invoice;
    $document_base = ($document->note) ? $document->note : null;

    //$path_style = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.DIRECTORY_SEPARATOR.'pdf'.DIRECTORY_SEPARATOR.'style1.css');
    /* ######## INICIO NUMERACIÓN FISCAL VENEZUELA ######## */
$document_number = $document->number_full;
/* ######## FIN NUMERACIÓN FISCAL VENEZUELA ######## */
    $accounts = \App\Models\Tenant\BankAccount::where('show_in_documents', true)->get();

    if($document_base) {

        /* ######## INICIO NUMERACIÓN FISCAL VENEZUELA ######## */
$affected_document_number = $document_base->affected_document ? $document_base->affected_document->number_full : \App\Services\Fiscal\FiscalIdentity::numberFull($document_base->data_affected_document->series ?? '', $document_base->data_affected_document->number);
/* ######## FIN NUMERACIÓN FISCAL VENEZUELA ######## */

    } else {

        $affected_document_number = null;
    }

    $payments = $document->payments->filter(fn ($payment) => !data_get($payment, 'reversed_at'));

    $document->load('reference_guides');

    if (!$invoicePdf) {
    $total_payment = $document->payments->sum('payment');
    $balance = $document instanceof \App\Models\Tenant\Document ? $document->balance : (($document->total - $total_payment) - $document->payments->sum('change'));

    $totalProductos = count($document->items);
    $totalFilas = 6 + $totalProductos;

    $totalFilas += (int)$document->terms_condition;

    if (!empty($configurationInPdf->show_bank_accounts_in_pdf) &&
        ((string) $document->document_type->id === '01')) {
        $totalFilas += count($accounts);
    }

    if (\App\CoreFacturalo\Helpers\Template\TemplateHelper::getDocumentPaymentCondition($document)) {
        $totalFilas++;
    }

    if ($document->payment_method_type_id && $payments->isEmpty()) {
        $totalFilas++;
    }

    $totalFilas += $payments->count();
    if (in_array((string) $document->payment_condition_id, ['02', '03'], true)) $totalFilas += count($document->fee);

    $allowed = $totalFilas;
    if ($allowed>26){
        $allowed_items = 70 - $totalFilas;
    }elseif($totalProductos>=20){
        $allowed_items = 0;
    }
    elseif($allowed<=26){
        $allowed_items = 70 - $totalProductos;
    }
    $quantity_items = $document->items->count();
    $cycle_items = $allowed_items - ($quantity_items * 3);
    } else {
        $cycle_items = 0;
    }
    $total_weight = \App\Support\Exact\InvoicePdfSummary::netWeight($document->items);

    $logo = null;

    if ($company->logo) {
        $logo = "storage/uploads/logos/{$company->logo}";
    } else if($establishment->logo) {
        $logo = "{$establishment->logo}";
    }
    $configurationInPdf= App\CoreFacturalo\Helpers\Template\TemplateHelper::getConfigurationInPdf();
    $type = App\CoreFacturalo\Helpers\Template\TemplateHelper::getFiscalEnvironment();
    $total_pending_payment = $document->total_pending_payment;
    $safeLogo = \App\CoreFacturalo\Helpers\Template\TemplateHelper::printLogoDataUri($logo) ?: \App\CoreFacturalo\Helpers\Template\TemplateHelper::ticketLogoDataUri($company, $establishment ?? null);
@endphp
<html>
<head>
    {{--<title>{{ $document_number }}</title>--}}
    {{--<link href="{{ $path_style }}" rel="stylesheet" />--}}
</head>
<body>

@if($document->state_type->id == '11')
    <div class="company_logo_box" style="position: absolute; text-align: center; top: 30%; left: 0; right: 0;">
        <img
            src="data:{{ mime_content_type(public_path('status_images/anulado.png')) }};base64,{{ base64_encode(file_get_contents(public_path('status_images/anulado.png'))) }}"
            alt="anulado"
            style="opacity: 0.6; max-width: 300px; width: auto; height: auto;"
        >
    </div>
@else
    <div class="item_watermark" style="
        position: absolute;
        top: 35%;
        left: 10%;
        width: 80%;
        height: 300px;
        text-align: center;
    ">
    @if($safeLogo)
        <img
            src="{{ $safeLogo }}"
            alt="{{ \App\CoreFacturalo\Helpers\CompanyDocumentDisplay::logoAlt($company) }}"
            style="width: 100%; height: auto; object-fit: contain; opacity: 0.1;"
        >
    @endif
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
@if (($invoicePdf['environment'] ?? $type->fiscal_environment) === 'demo')
    <table class="full-width">
        <tr>
            <td style="width: 100%;text-align: center">
                <span style="color: red; font-weight: bold; font-size: 1.6rem;">Esta factura es solo de prueba</span>
            </td>
        </tr>
    </table>
@endif
<table class="full-width">

    <tr>
        @php $safePrintLogo = $safeLogo; @endphp
@if($safePrintLogo)
            <td width="20%">
                <div class="company_logo_box">
                    <img src="{{ $safePrintLogo }}" alt="{{ \App\CoreFacturalo\Helpers\CompanyDocumentDisplay::logoAlt($company) }}" class="company_logo" style="max-width: 250px;">
                </div>
            </td>
            <td width="40%" class="text-left">
                <div class="text-left">
                    @include('pdf.partials.company_document_header_names')
                    <h5>{{ 'R.I.F. '.($invoicePdf['rif'] ?? $company->number) }}</h5>
                    <h6 style="text-transform: uppercase;">
                        {{ ($establishment->address !== '-')? $establishment->address : '' }}
                        {{ (!empty($establishment->district->description))? ', '.$establishment->district->description : '' }}
                        {{ (!empty($establishment->province->description))? ', '.$establishment->province->description : '' }}
                        {{ (!empty($establishment->department->description))? '- '.$establishment->department->description : '' }}
                    </h6>

                    @isset($establishment->trade_address)
                        <h6>{{ ($establishment->trade_address !== '-')? 'D. Comercial: '.$establishment->trade_address : '' }}</h6>
                    @endisset

                    <h6>{{ ($establishment->telephone !== '-')? 'Central telefónica: '.$establishment->telephone : '' }}</h6>

                    <h6>{{ ($establishment->email !== '-')? 'Email: '.$establishment->email : '' }}</h6>

                    @isset($establishment->web_address)
                        <h6>{{ ($establishment->web_address !== '-')? 'Web: '.$establishment->web_address : '' }}</h6>
                    @endisset

                    @isset($establishment->aditional_information)
                        <h6>{{ ($establishment->aditional_information !== '-')? $establishment->aditional_information : '' }}</h6>
                    @endisset
                </div>
            </td>
            <td width="40%" class="border-box py-2 px-2 text-left" style="border: 0.5pt solid #888; padding: 9px 10px;">
@if($invoicePdf)
@include('pdf.partials.invoice_identity')
@else
                <h3 class="font-bold">{{ 'R.I.F. '.$company->number }}</h3>
                <h3 class="text-center font-bold">{{ $document->document_type->description }}</h3>
                <br>
                <h3 class="text-center font-bold">{{ $document_number }}</h3>
            @endif
</td>
        @else
            <td width="60%" class="pl-1">
                <div class="text-left">
                    @include('pdf.partials.company_document_header_names')
                    <h5>{{ 'R.I.F. '.($invoicePdf['rif'] ?? $company->number) }}</h5>
                    <h6 style="text-transform: uppercase;">
                        {{ ($establishment->address !== '-')? $establishment->address : '' }}
                        {{ (!empty($establishment->district->description))? ', '.$establishment->district->description : '' }}
                        {{ (!empty($establishment->province->description))? ', '.$establishment->province->description : '' }}
                        {{ (!empty($establishment->department->description))? '- '.$establishment->department->description : '' }}
                    </h6>

                    @isset($establishment->trade_address)
                        <h6>{{ ($establishment->trade_address !== '-')? 'D. Comercial: '.$establishment->trade_address : '' }}</h6>
                    @endisset

                    <h6>{{ ($establishment->telephone !== '-')? 'Central telefónica: '.$establishment->telephone : '' }}</h6>

                    <h6>{{ ($establishment->email !== '-')? 'Email: '.$establishment->email : '' }}</h6>

                    @isset($establishment->web_address)
                        <h6>{{ ($establishment->web_address !== '-')? 'Web: '.$establishment->web_address : '' }}</h6>
                    @endisset

                    @isset($establishment->aditional_information)
                        <h6>{{ ($establishment->aditional_information !== '-')? $establishment->aditional_information : '' }}</h6>
                    @endisset
                </div>
            </td>
            <td width="40%" class="border-box py-2 px-2 text-left" style="border: 0.5pt solid #888; padding: 9px 10px;">
@if($invoicePdf)
@include('pdf.partials.invoice_identity')
@else
                <h3 class="font-bold">{{ 'R.I.F. '.$company->number }}</h3>
                <h3 class="text-center font-bold">{{ $document->document_type->description }}</h3>
                <br>
                <h3 class="text-center font-bold">{{ $document_number }}</h3>
            @endif
</td>
        @endif
    </tr>
</table>
<!-- INVOICE_SPECIAL_IDENTITY -->
<table class="full-width mt-3">
    <tr>
        <td width="47%" class="border-box pl-3 align-top">
            <table class="full-width">
                <tr>
                    <td class="font-sm" width="80px">
                        <strong>Razón Social</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $customer->name }}
                    </td>
                </tr>
                <tr>
                    <td class="font-sm" width="80px">
                        <strong>{{$customer->identity_document_type->description}}</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{format_identity_document($customer->identity_document_type_id ?? null, $customer->number)}}
                    </td>
                </tr>
                @php
                    $addressParts = [];

                    if (!empty($customer->address)) {
                        $addressParts[] = $customer->address;
                    }

                    if (!empty($customer->district_id) && $customer->district_id !== '-' && isset($customer->district) && !empty($customer->district->description)) {
                        $addressParts[] = $customer->district->description;
                    }

                    if (!empty($customer->province_id) && $customer->province_id !== '-' && isset($customer->province) && !empty($customer->province->description)) {
                        $addressParts[] = $customer->province->description;
                    }

                    if (!empty($customer->department_id) && $customer->department_id !== '-' && isset($customer->department) && !empty($customer->department->description)) {
                        $addressParts[] = $customer->department->description;
                    }

                    $fullAddress = implode(', ', array_filter($addressParts, static fn($part) => trim((string)$part) !== ''));
                @endphp

                @if ($fullAddress)
                    <tr>
                        <td class="font-sm" width="80px">
                            <strong>Dirección</strong>
                        </td>
                        <td class="font-sm" width="8px">:</td>
                        <td class="font-sm">
                            {{ $fullAddress }}
                        </td>
                    </tr>
                @else
                    <tr>
                        <td class="font-sm" width="80px">
                            <strong>Dirección</strong>
                        </td>
                        <td class="font-sm" width="8px">:</td>
                        <td class="font-sm text-muted">
                            No disponible
                        </td>
                    </tr>
                @endif
                @if(!is_null($document_base))
                <tr>
                    <td class="font-sm font-bold" width="80px">Doc. Afectado</td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">{{ $affected_document_number }}</td>
                </tr>
                <tr>
                    <td class="font-sm font-bold" width="80px">Tipo de nota</td>
                    <td class="font-sm">:</td>
                    <td class="font-sm">{{ ($document_base->note_type === 'credit')?$document_base->note_credit_type->description:$document_base->note_debit_type->description}}</td>
                </tr>
                <tr>
                    <td class="font-sm font-bold" width="80px">Descripción</td>
                    <td class="font-sm">:</td>
                    <td class="font-sm">{{ $document_base->note_description }}</td>
                </tr>
                @endif
            </table>
        </td>
        <td width="3%"></td>
        <td width="50%" class="border-box pl-1 align-top">
            <table class="full-width">
                <tr>
                    <td class="font-sm" width="90px">
                        <strong>Fecha Emisión</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $document->date_of_issue->format('d-m-Y') }}
                    </td>
                    <td class="font-sm" width="70px">
                        <strong>H. Emisión</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm">
                        {{ $document->time_of_issue }}
                    </td>
                </tr>
                <tr>
                    @if($invoice)
                        <td class="font-sm" width="90px">
                            <strong>Fecha de Vcto</strong>
                        </td>
                        <td class="font-sm" width="8px">:</td>
                        <td class="font-sm">
                            {{$invoice->date_of_due->format('d-m-Y')}}
                        </td>
                    @endif

                    @if(!$invoicePdf)
<td class="font-sm align-top" width="70px">
                        <strong>Moneda</strong>
                    </td>
                    <td class="font-sm align-top" width="8px">:</td>
                    <td class="font-sm">
                        {{ $document->currency_type->description }}
                    </td>
@endif
                </tr>
                <tr>
                    @if($document->purchase_order)
                        <td class="font-sm" width="90px">
                            <strong>Orden de Compra</strong>
                        </td>
                        <td class="font-sm" width="8px">:</td>
                        <td class="font-sm">
                            {{ $document->purchase_order }}
                        </td>
                    @endif

                    @if(!$invoicePdf)
{{-- ######## INICIO MONEDA DEL MEDIO DE COBRO ######## --}}
                    @if($payments->count() > 0)
                        <td class="font-sm align-top" width="70px">
                            <strong>F. Pago</strong>
                        </td>
                        <td class="font-sm align-top" width="8px">:</td>
                        <td class="font-sm">
                            @include('pdf.partials.invoice_receipt', ['row' => $payments->first(), 'document' => $document])
                        </td>
                    @endif
                    {{-- ######## FIN MONEDA DEL MEDIO DE COBRO ######## --}}
@endif
                </tr>
                @if($document->guides)
                    <tr>
                        <td class="font-sm" width="100px">
                            {{-- ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES --}}
                            <strong>Orden de entrega</strong>
                            {{-- ######### FIN CAMBIO CATÁLOGOS DE NOMBRES --}}
                        </td>
                        <td class="font-sm" width="8px">:</td>
                        <td class="font-sm" colspan="4">
                            @foreach ($document->guides as $item)
                            {{ $item->document_type_description }}:  {{ $item->number }}<br>
                            @endforeach
                        </td>
                    </tr>
                @endif
                @if ($document->dispatch)
                <tr>
                    <td class="font-sm" width="100px">
                        <strong>Órdenes de entrega</strong>
                    </td>
                    <td class="font-sm" width="8px">:</td>
                    <td class="font-sm" colspan="4">
                        {{ $document->dispatch->number_full }}
                    </td>
                </tr>
                @elseif ($document->reference_guides)
                    @if (count($document->reference_guides) > 0)
                        <tr>
                            <td class="font-sm" width="100px">
                                <strong>Órdenes de entrega</strong>
                            </td>
                            <td class="font-sm" width="8px">:</td>
                            <td class="font-sm" colspan="4">
                                @foreach($document->reference_guides as $guide)
                                    {{ $guide->series }} - {{ $guide->number }}<br>
                                @endforeach
                            </td>
                        </tr>
                    @endif
                @endif
            </table>
        </td>
        {{-- <td width="5%" class="p-0 m-0">
        </td> --}}
    </tr>
</table>
<table class="full-width my-2 text-center" border="0">
    <tr>
        <td class="desc"></td>
    </tr>
</table>

@php
    $showBrandColumn = false;
    $showModelColumn = false;

    foreach ($document->items as $row) {
        if (!empty($row->item->model)) {
            $showModelColumn = true;
        }

        if (!empty($row->m_item->brand->name ?? null)) {
            $showBrandColumn = true;
        }

        if ($showModelColumn && $showBrandColumn) break;
    }
@endphp

<table class="full-width mt-0 mb-0" >
    <thead >
        <tr class="">
            <th class="border-top-bottom text-center py-1 desc" class="cell-solid"  width="8%">CÓDIGO</th>
            <th class="border-top-bottom text-center py-1 desc" class="cell-solid"  width="6%">CANT.</th>
            <th class="border-top-bottom text-center py-1 desc" class="cell-solid"  width="9%">U.M.</th>
            <th class="border-top-bottom text-center py-1 desc" class="cell-solid">DESCRIPCIÓN</th>
            @php
                $showSerieColumn = false;
                $showLoteColumn = false;
                foreach ($document->items as $row) {
                    if (!empty($row->item->lots)) {
                        $showSerieColumn = true;
                        break;
                    }
                }

                foreach ($document->items as $row) {
                    if (isset($row->item->IdLoteSelected)) {
                        $showLoteColumn = true;
                        break;
                    }
                }
            @endphp
            @empty($showSerieColumn) @else <th class="border-top-bottom text-center py-1 desc" class="cell-solid">SERIE</th> @endempty
            @if($showModelColumn)
                <th class="border-top-bottom text-center py-1 desc" class="cell-solid">MODELO</th>
            @endif
            @if($showBrandColumn)
                <th class="border-top-bottom text-center py-1 desc" class="cell-solid">MARCA</th>
            @endif
            @if($showLoteColumn) <th class="border-top-bottom text-center py-1 desc" class="cell-solid">
                LOTE
            </th> @endif
            @if($showLoteColumn) <th class="border-top-bottom text-center py-1 desc" class="cell-solid"> F. VENC. </th> @endif
            <th class="border-top-bottom text-center py-1 desc col-total" class="cell-solid">P.UNIT</th>
            {{-- ########## INICIO CAMBIO IGV A IVA --}}
            <th class="border-top-bottom text-center py-1 desc" class="cell-solid"  width="8%">SIN IVA</th>
            {{-- ######### FIN CAMBIO IGV A IVA --}}
            <th class="border-top-bottom text-center py-1 desc col-total" class="cell-solid">TOTAL</th>
        </tr>
    </thead>
    @php
        $colspan_base = 2;
        $colspan_total = $colspan_base;

        if($showSerieColumn) $colspan_total++;
        if($showModelColumn) $colspan_total++;
        if($showBrandColumn) $colspan_total++;
        if($showLoteColumn) {
            $colspan_total++;
            $colspan_total++;
        }
    @endphp
    <tbody class="">
        @foreach($document->items as $row)
            <tr>
                <td class="p-1 text-center align-top desc cell-solid-rl">{{ $row->item->internal_id }}</td>
                <td class="p-1 text-center align-top desc cell-solid-rl">
                    {{ \App\Support\Exact\PdfQuantities::line($row) }}
                </td>
                <td class="p-1 text-center align-top desc cell-solid-rl" style="white-space:nowrap;">{{ $row->item->unit_type_id === 'UND' ? 'UNIDADES' : $row->item->unit_type_id }}</td>
                <td class="p-1 text-left align-top desc text-upp cell-solid-rl">
                    @if($row->name_product_pdf)
                        <div style="white-space:pre-line;line-height:1.35;">{!! \App\CoreFacturalo\Helpers\Template\TemplateHelper::formatNameProductPdfForTicket($row->name_product_pdf) !!}</div>
                    @else
                        {!!$row->item->description!!}
                    @endif

                    @if (!empty($row->item->presentation)) {!!$row->item->presentation->description!!} @endif

                    @if($row->attributes)
                        @foreach($row->attributes as $attr)
                            @if($attr->attribute_type_id === '5032')

                            @endif
                            <br/><span style="font-size: 9px">{!! $attr->description !!} : {{ $attr->value }}</span>
                        @endforeach
                    @endif
                    {{-- @if($row->discounts)
                        @foreach(\App\Support\Exact\PdfAmounts::adjustments($row, 'discounts') as $dtos)
                            @if(!($dtos->from_global_distribution ?? false))
                                <br/><span style="font-size: 9px">{{ ($dtos->is_amount ?? false) ? '' : \App\Support\Exact\PdfAmounts::adjustmentPercent($dtos).'%' }} {{$dtos->description }}</span>
                            @endif
                        @endforeach
                    @endif --}}

                    @if($row->item->is_set == 1)
                     <br>
                     @inject('itemSet', 'App\Services\ItemSetService')
                        @foreach ($itemSet->getItemsSet($row->item_id) as $item)
                            {{ $item }}<br>
                        @endforeach
                    @endif
                </td>
                @empty($showSerieColumn) @else
                <td class="text-center align-top desc cell-solid-rl p-1">
                    @isset($row->item->lots)
                        @foreach($row->item->lots as $lot)
                            @if( isset($lot->has_sale) && $lot->has_sale)
                                <span style="font-size: 9px">{{ $lot->series }}</span><br>
                            @endif
                        @endforeach
                    @endisset
                </td>
                @endempty
                @if($showModelColumn)
                    <td class="text-center align-top desc cell-solid-rl p-1">{{ $row->item->model ?? '' }}</td>
                @endif

                @if($showBrandColumn)
                    <td class="text-center align-top desc cell-solid-rl p-1">
                        {{ $row->m_item->brand->name ?? '' }}
                    </td>
                @endif
                @inject('itemLotGroup', 'App\Services\ItemLotsGroupService')
                @php
                    $lot = optional($row->item)->IdLoteSelected ? $itemLotGroup->getLote($row->item->IdLoteSelected) : '';
                    $date_due = optional($row->item)->IdLoteSelected ? $itemLotGroup->getInvoiceLotDateOfDue($row->item->IdLoteSelected) : '';
                @endphp

                @if($showLoteColumn)
                    <td class="text-center align-top desc cell-solid-rl p-1">
                        @if($lot)
                            @foreach(explode('/', $lot) as $code)
                                @if(trim($code) !== '')
                                    {{ trim($code) }}<br>
                                @endif
                            @endforeach
                        @endif
                    </td>
                @endif
                @if($showLoteColumn)
                    <td class="text-center align-top desc cell-solid-rl p-1">
                        @php
                            $cleanedDate = $date_due != ''
                                ? ltrim($date_due, '/')
                                : ($row->relation_item->date_of_due ? $row->relation_item->date_of_due->format('Y-m-d') : '');
                        @endphp

                        {{ $cleanedDate }}
                    </td>
                @endif
                <td class="p-1 text-center align-top desc cell-solid-rl"><span data-pdf-unit-price>{{ $row->formatPdfUnitPrice() }}</span></td>
                <td class="p-1 text-center align-top desc cell-solid-rl">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($row, 'unit_value'), 2) }}</td>
                <td class="p-1 text-center align-top desc cell-solid-rl">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($row, 'total'), 2) }}</td>
            </tr>

        @endforeach

        {{-- Mantener un área de ítems amplia en facturas cortas; no añadir espacio a contenido extenso. --}}
        @php
            $itemCount = $document->items->count();
            $simpleItems = $document->items->every(function ($line) {
                $description = strip_tags((string) ($line->name_product_pdf ?: $line->item->description));
                return mb_strlen($description) <= 100 && substr_count($description, "\n") < 2
                    && empty($line->attributes) && empty($line->item->presentation)
                    && empty($line->item->is_set) && empty($line->item->IdLoteSelected)
                    && empty($line->item->lots);
            });
            $extraFiscal = !empty($invoicePdf['igtf']) || !empty($invoicePdf['retentions'])
                || !empty($invoicePdf['forecasts']) || !empty($invoicePdf['guarantee'])
                || !empty($invoicePdf['ves_totals']) || !empty($invoicePdf['later_igtf'])
                || ($invoicePdf['balance'] ?? null) !== null;
            $paddingRows = $invoicePdf && $itemCount > 0 && $itemCount <= 8 && $simpleItems
                && !$extraFiscal && $payments->count() <= 1 && collect($document->fee)->isEmpty()
                && empty($document->terms_condition)
                ? max(0, 19 - $itemCount) : 0;
        @endphp
        @for($i = 0; $i < $paddingRows; $i++)
            <tr data-item-padding>
                @for($column = 0; $column < 7 + (int) $showSerieColumn + (int) $showModelColumn + (int) $showBrandColumn + 2 * (int) $showLoteColumn; $column++)
                    <td class="cell-solid-rl" style="height:5mm">&nbsp;</td>
                @endfor
            </tr>
        @endfor

        @for($i = 0; $i < $cycle_items; $i++)
        <tr>
            <td class="p-1 text-center align-top desc cell-solid-rl"></td>
            <td class="p-1 text-center align-top desc cell-solid-rl">
            </td>
            <td class="p-1 text-center align-top desc cell-solid-rl"></td>
            <td class="p-1 text-left align-top desc text-upp cell-solid-rl">
            </td>
            <td class="p-1 text-right align-top desc cell-solid-rl"></td>
            <td class="p-1 text-right align-top desc cell-solid-rl">
            </td>
            <td class="p-1 text-right align-top desc cell-solid-rl"></td>

            @empty($showSerieColumn) @else
            <td class="p-1 text-right align-top desc cell-solid-rl"></td>
            @endempty
            @if($showModelColumn)
            <td class="p-1 text-right align-top desc cell-solid-rl"></td>
            @endif
            @if($showBrandColumn)
            <td class="p-1 text-right align-top desc cell-solid-rl"></td>
            @endif
            @if($showLoteColumn) <td class="p-1 text-right align-top desc cell-solid-rl"></td> @endif
            @if($showLoteColumn) <td class="p-1 text-right align-top desc cell-solid-rl"></td> @endif

        </tr>
        @endfor

        <tr>
            @if(isset($configurationInPdf) && $configurationInPdf->show_seller_in_pdf)
                <td class="p-1 text-left align-top desc cell-solid" colspan="3"><strong> VENDEDOR:</strong> {{ $document->user->name }}</td>

            @else
                <td class="p-1 text-left align-top desc cell-solid" colspan="3"></td>
            @endif
            <td class="p-1 text-left align-top desc cell-solid font-bold">
                SON:
                @foreach(array_reverse( (array) $document->legends) as $row)
                    @if ($row->code == "1000")
                        {{ $row->value }} {{ $document->currency_type->description }}
                    @else
                        {{$row->code}}: {{ $row->value }}
                    @endif
                @endforeach
            </td>
            <td class="p-1 text-right align-top desc cell-solid font-bold" colspan="{{ $colspan_total }}">
                Base imponible {{$document->currency_type->symbol}}
            </td>
            <td class="p-1 text-right align-top desc cell-solid font-bold">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document, 'total_taxed'), 2) }}</td>
        </tr>

        <tr>
            <td class="p-1 text-left align-top desc cell-solid" colspan="3" rowspan="{{ 6 + count($invoicePdf['igtf'] ?? []) }}">
                @php
                    // Solo contar productos (no servicios) para total bultos
                    $total_packages = \App\Support\Exact\InvoicePdfSummary::packages($document->items);
                @endphp
                @if($total_packages->isGreaterThan(0))
                    <strong>Total bultos:</strong>
                    {{ \App\Support\Exact\InvoicePdfSummary::packageText($total_packages) }}
                    <br>
                @endif
                @if($total_weight->isGreaterThan(0))
                    <strong>Total Peso:</strong> {{ \App\Support\Exact\Decimal::represent($total_weight) }} KG
                    <br>
                @endif
                @foreach($document->additional_information as $information)
                    @if ($information)
                        @if($loop->first)
                            <strong> Observación:</strong>
                        @endif
                        {{ $information }} <br>
                    @endif
                @endforeach


            <td class="p-1 text-center align-top desc cell-solid " rowspan="{{ 6 + count($invoicePdf['igtf'] ?? []) }}">
            </td>
            <td class="p-1 text-right align-top desc cell-solid font-bold" colspan="{{ $colspan_total }}">
                Total no sujeto {{$document->currency_type->symbol}}
            </td>
            <td class="p-1 text-right align-top desc cell-solid font-bold">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document, 'total_unaffected'), 2) }}</td>
        </tr>
        <tr>
            <td class="p-1 text-right align-top desc cell-solid font-bold" colspan="{{ $colspan_total }}">
                Total exento {{$document->currency_type->symbol}}
            </td>
            <td class="p-1 text-right align-top desc cell-solid font-bold">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document, 'total_exonerated'), 2) }}</td>
        </tr>
        <tr>
            <td class="p-1 text-right align-top desc cell-solid font-bold" colspan="{{ $colspan_total }}">
                OP. GRATUITAS {{$document->currency_type->symbol}}
            </td>
            <td class="p-1 text-right align-top desc cell-solid font-bold">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document, 'total_free'), 2) }}</td>
        </tr>
        <tr>
            <td class="p-1 text-right align-top desc cell-solid font-bold" colspan="{{ $colspan_total }}">
                TOTAL DCTOS. {{$document->currency_type->symbol}}
            </td>
            <td class="p-1 text-right align-top desc cell-solid font-bold">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document, 'total_discount_with_igv'), 2) }}</td>
        </tr>
        <tr>

            <td class="p-1 text-right align-top desc cell-solid font-bold" colspan="{{ $colspan_total }}">
                IVA ({{ \App\CoreFacturalo\Helpers\Template\TemplateHelper::getDocumentIgvPercentage($document) }}%). {{$document->currency_type->symbol}}
            </td>
            <td class="p-1 text-right align-top desc cell-solid font-bold">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document, 'total_igv'), 2) }}</td>
        </tr>
        @include('pdf.partials.invoice_igtf')
<tr>
            <td class="p-1 text-right align-top desc cell-solid font-bold" colspan="{{ $colspan_total }}">
                TOTAL A PAGAR. {{$document->currency_type->symbol}}
            </td>
            <td class="p-1 text-right align-top desc cell-solid font-bold">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document, 'total'), 2) }}</td>
        </tr>

    </tbody>

</table>

@if ($document->terms_condition)
    <table class="full-width">
        <tr>
            <td>
                <h6 style="font-size: 12px; font-weight: bold;">Términos y condiciones del servicio</h6>
                {!! $document->terms_condition !!}
            </td>
        </tr>
    </table>
@endif
@if(isset($configurationInPdf) && $configurationInPdf->show_bank_accounts_in_pdf)
    @if(((string) $document->document_type->id === '01'))
        @foreach($accounts as $account)
            <div>
                <span
                    class="font-bold">{{$account->bank->description}}</span> {{$account->currency_type->description}}
                <span class="font-bold">N°:</span> {{$account->number}}
            </div>
        @endforeach
    @endif
@endif


@if($invoicePdf)
@include('pdf.partials.invoice_fiscal')
@endif
@php
    $paymentCondition = \App\CoreFacturalo\Helpers\Template\TemplateHelper::getDocumentPaymentCondition($document);
@endphp
{{-- Condicion de pago  Crédito / Contado --}}
<table class="full-width">
    <tr>
        <td>
            <strong>CONDICIÓN DE PAGO: {{ $paymentCondition }} </strong>
        </td>
    </tr>
</table>

@if($document->payment_method_type_id && $payments->isEmpty())
    <table class="full-width">
        <tr>
            <td>
                <strong>MÉTODO DE PAGO: </strong>{{ $document->payment_method_type->description }}
            </td>
        </tr>
    </table>
@endif

@if($invoicePdf)
@include('pdf.partials.invoice_payments')
@else
@if (in_array((string) $document->payment_condition_id, ['01', '02', '03'], true))
    @if($payments->count())
        <table class="full-width">
            <tr>
                <td><strong>PAGOS:</strong></td>
            </tr>
            @php $payment = 0; @endphp
            @foreach($payments as $row)
                <tr>
                    <td>&#8226; {{-- ######## INICIO MONEDA DEL MEDIO DE COBRO ######## --}}@include('pdf.partials.invoice_receipt', ['row' => $row, 'document' => $document]){{-- ######## FIN MONEDA DEL MEDIO DE COBRO ######## --}}</td>
                </tr>
                @endforeach
                </tr>
        </table>
    @endif
@endif
                @endif
@if (in_array((string) $document->payment_condition_id, ['02', '03'], true))
    <table class="full-width">
        @foreach($document->fee as $key => $quote)
            <tr>
                <td>
                    &#8226; {{ (empty($quote->getStringPaymentMethodType()) ? 'Cuota #'.( $key + 1) : $quote->getStringPaymentMethodType()) }}
                    / Fecha: {{ $quote->date->format('d-m-Y') }} /
                    Monto: {{ $quote->currency_type->symbol }}{{ $quote->amount }}</td>
            </tr>
            @endforeach
            </tr>
    </table>
@endif
</body>
</html>
{{-- ######## FIN MIGRACIÓN MONEDA VENEZUELA ######## --}}

{{-- ######## FIN PERSISTENCIA FISCAL VENEZUELA ######## --}}
