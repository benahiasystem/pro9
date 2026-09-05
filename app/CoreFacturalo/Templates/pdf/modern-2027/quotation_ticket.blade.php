@php
    use Modules\Template\Helpers\TemplatePdf;

    $establishment = $document->establishment;
    $customer = $document->customer;
    $invoice = $document->invoice;
    $accounts = (new TemplatePdf)->getBankAccountsForPdf($document->establishment_id);

    $tittle = $document->pdf_title;

    $logo = "storage/uploads/logos/{$company->logo}";
    if($establishment->logo) {
        $logo = "{$establishment->logo}";
    }
    $configurationInPdf= App\CoreFacturalo\Helpers\Template\TemplateHelper::getConfigurationInPdf();

    /* ------------------------------------------------------------------
     | modern-2027: variables propias del diseño del ticket
     ------------------------------------------------------------------ */
    $max_chars_description = config('tenant.enabled_template_ticket_80') ? 30 : 28;

    $m27_seller = strtoupper(optional($document->seller)->name ?: optional($document->user)->name ?? '');

    $m27_igv_percentage = collect($document->items)
        ->map(function ($it) { return (float) ($it->percentage_igv ?? 0); })
        ->filter()
        ->max();
    $m27_igv_percentage = $m27_igv_percentage ? rtrim(rtrim(number_format($m27_igv_percentage, 2, '.', ''), '0'), '.') : null;

    $m27_total_products = rtrim(rtrim(number_format(collect($document->items)->sum(function ($item) {
        return (float) data_get($item, 'quantity', 0);
    }), 2, '.', ''), '0'), '.');

    $m27_barcode = preg_replace('/[^0-9A-Za-z\-]/', '', (string) $tittle);

    $m27_thanks = '¡Gracias por su preferencia!';

    $show_lot = $document->items->contains(function ($row) {
        return !empty($row->getSaleLotGroupCodeDescription());
    });
@endphp
<html>
<head></head>
<body class="ticket">

<table class="full-width">
    @if($company->logo)
        <tr>
            <td class="text-center pt-3 pb-2">
                <img src="data:{{mime_content_type(public_path("{$logo}"))}};base64, {{base64_encode(file_get_contents(public_path("{$logo}")))}}"
                     alt="{{ \App\CoreFacturalo\Helpers\CompanyDocumentDisplay::logoAlt($company) }}" class="m27-logo contain">
            </td>
        </tr>
    @endif
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
        <td class="m27-doc-type">Cotización</td>
    </tr>
    <tr>
        <td class="m27-gap"></td>
    </tr>
    <tr>
        <td class="m27-doc-number">{{ $tittle }}</td>
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
        <td width="38%" class="m27-label">Fecha:</td>
        <td class="m27-value">{{ $document->date_of_issue->format('d/m/Y') }} {{ $document->time_of_issue }}</td>
    </tr>
    @if($document->date_of_due)
        <tr>
            <td class="m27-label">T. Validez:</td>
            <td class="m27-value">{{ $document->date_of_due }}</td>
        </tr>
    @endif
    @if($document->delivery_date)
        <tr>
            <td class="m27-label">T. Entrega:</td>
            <td class="m27-value">{{ $document->delivery_date }}</td>
        </tr>
    @endif
    <tr>
        <td class="m27-label align-top">Cliente:</td>
        <td class="m27-value">{{ $customer->name }}</td>
    </tr>
    <tr>
        <td class="m27-label">{{ $customer->identity_document_type->description }}:</td>
        <td class="m27-value">{{ format_identity_document($customer->identity_document_type_id ?? null, $customer->number) }}</td>
    </tr>
    @if(isset($configurationInPdf) && $configurationInPdf->show_seller_in_pdf)
        <tr>
            <td class="m27-label">Vendedor:</td>
            <td class="m27-value">{{ $m27_seller }}</td>
        </tr>
    @endif
    @if ($customer->address !== '')
        <tr>
            <td class="m27-label align-top">Dirección:</td>
            <td class="m27-value">
                {{ $customer->address }}
                {{ ($customer->district_id !== '-')? ', '.$customer->district->description : '' }}
                {{ ($customer->province_id !== '-')? ', '.$customer->province->description : '' }}
                {{ ($customer->department_id !== '-')? '- '.$customer->department->description : '' }}
            </td>
        </tr>
    @endif
    @if ($document->shipping_address)
        <tr>
            <td class="m27-label align-top">Dir. envío:</td>
            <td class="m27-value">{{ $document->shipping_address }}</td>
        </tr>
    @endif
    @if ($customer->telephone)
        <tr>
            <td class="m27-label align-top">Teléfono:</td>
            <td class="m27-value">{{ $customer->telephone }}</td>
        </tr>
    @endif
    @if ($document->payment_method_type)
        <tr>
            <td class="m27-label align-top">T. Pago:</td>
            <td class="m27-value">{{ $document->payment_method_type->description }}</td>
        </tr>
    @endif
    @if ($document->account_number)
        <tr>
            <td class="m27-label align-top">N° Cuenta:</td>
            <td class="m27-value">{{ $document->account_number }}</td>
        </tr>
    @endif
    @if ($document->sale_opportunity)
        <tr>
            <td class="m27-label align-top">O. Venta:</td>
            <td class="m27-value">{{ $document->sale_opportunity->number_full }}</td>
        </tr>
    @endif
    @if ($document->description)
        <tr>
            <td class="m27-label align-top">Observación:</td>
            <td class="m27-value">{!! str_replace("\n", "<br/>", $document->description) !!}</td>
        </tr>
    @endif
    @if ($document->contact)
        <tr>
            <td class="m27-label align-top">Contacto:</td>
            <td class="m27-value">{{ $document->contact }}</td>
        </tr>
    @endif
    @if ($document->phone)
        <tr>
            <td class="m27-label align-top">Telf. contacto:</td>
            <td class="m27-value">{{ $document->phone }}</td>
        </tr>
    @endif
    @if ($document->purchase_order)
        <tr>
            <td class="m27-label">Orden de compra:</td>
            <td class="m27-value">{{ $document->purchase_order }}</td>
        </tr>
    @endif
    @if ($document->quotation_id)
        <tr>
            <td class="m27-label">Cotización:</td>
            <td class="m27-value">{{ $document->quotation->identifier }}</td>
        </tr>
    @endif
</table>

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
    @foreach($document->items as $row)
        @php
            $internal_id = optional($row->item)->internal_id;
            $item_quantity = ((int)$row->quantity != $row->quantity) ? $row->quantity : number_format($row->quantity, 0);
            $use_name_product_pdf = !empty($row->item->name_product_pdf ?? null);
            $item_description = null;
            if (!$use_name_product_pdf) {
                $item_description = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $row->item->description))));
                if (!empty($row->item->presentation)) {
                    $item_description .= ' '.strip_tags((string) $row->item->presentation->description);
                }
                $item_description = \App\CoreFacturalo\Helpers\Template\TemplateHelper::stripLeadingItemCode($item_description, $internal_id);
                if (mb_strlen($item_description) > $max_chars_description) {
                    $item_description = rtrim(mb_substr($item_description, 0, max($max_chars_description - 1, 1))).'.';
                }
            }
        @endphp
        <tr>
            <td class="m27-item text-left">{{ $item_quantity }}</td>
            <td class="m27-item text-left">
                @if($use_name_product_pdf)
                    {!! \App\CoreFacturalo\Helpers\Template\TemplateHelper::stripLeadingItemCode(\App\CoreFacturalo\Helpers\Template\TemplateHelper::formatNameProductPdfForTicket($row->item->name_product_pdf ?? ''), $internal_id) !!}
                    @if (!empty($row->item->presentation)) {!!$row->item->presentation->description!!} @endif
                @else
                    {{ $item_description }}
                @endif
                @if($row->attributes)
                    @foreach($row->attributes as $attr)
                        <br/>{!! $attr->description !!} : {{ $attr->value }}
                    @endforeach
                @endif
                @if($row->discounts)
                    @foreach($row->discounts as $dtos)
                        @if(!($dtos->from_global_distribution ?? false))
                            <br/><small>{{ ($dtos->is_amount ?? false) ? '' : ($dtos->factor * 100).'%' }} {{$dtos->description }}</small>
                        @endif
                    @endforeach
                @endif
                @if($row->item->is_set == 1)
                    <br>
                    @inject('itemSet', 'App\Services\ItemSetService')
                    @foreach ($itemSet->getItemsSet($row->item_id) as $item)
                        {{$item}}<br>
                    @endforeach
                @endif
                @if($row->item !== null && property_exists($row->item,'extra_attr_value') && $row->item->extra_attr_value != '')
                    <br/><span style="font-size: 8px">{{$row->item->extra_attr_name}}: {{ $row->item->extra_attr_value }}</span>
                @endif
                @if($show_lot)
                    <small style="display:block; font-weight: normal; font-size: 7px;">
                        Lote: {{ $row->getSaleLotGroupCodeDescription() }}
                        <br>
                        FV:
                        @if(isset($row->relation_item->date_of_due))
                            {{ $row->relation_item->date_of_due->format('Y-m-d') }}
                        @else
                            -
                        @endif
                        <br>
                    </small>
                @endif
            </td>
            <td class="m27-item text-right font-bold">{{ number_format($row->total, 2) }}</td>
        </tr>
        <tr>
            <td></td>
            <td colspan="2" class="m27-item-sub text-left">
                @php
                    $item_unit_price = $row->unit_price;
                    $item_unit_label = $row->item->unit_type_id !== 'NIU' ? $row->item->unit_type_id.' x ' : '';
                    $item_code_label = trim((string) $internal_id);
                    $item_sub_line = ($item_code_label !== '' ? $item_code_label.' ' : '')
                        .'('.$item_unit_label.$document->currency_type->symbol.' '.number_format($item_unit_price, 2).')';
                @endphp
                {{ $item_sub_line }}
            </td>
        </tr>
    @endforeach

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
    @if($document->total_taxed > 0)
        <tr>
            <td colspan="2" class="m27-total-label">Op. gravadas:</td>
            <td class="m27-total-value">{{ $document->currency_type->symbol }} {{ number_format($document->total_taxed, 2) }}</td>
        </tr>
    @endif
    @if($document->total_discount_with_igv > 0)
        <tr>
            <td colspan="2" class="m27-total-label">{{ ($document->total_prepayment > 0) ? 'Anticipo' : 'Descuento total' }}:</td>
            <td class="m27-total-value">{{ $document->currency_type->symbol }} {{ number_format($document->total_discount_with_igv, 2) }}</td>
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
    <tr>
        <td class="m27-item-sub align-bottom" style="white-space: nowrap;">{{ $m27_total_products }} und.</td>
        <td class="m27-grand-label">TOTAL:</td>
        <td class="m27-grand-value">{{ $document->currency_type->symbol }} {{ number_format($document->total, 2) }}</td>
    </tr>
    </tbody>
</table>
@include('pdf.modern-2027.partials.rule')

{{-- Cada bloque opcional cierra con su propio separador. Asi nunca salen dos
     separadores seguidos cuando el bloque de en medio no imprime nada. --}}
@if(count((array) $document->legends))
    <table class="full-width">
        @foreach(array_reverse((array) $document->legends) as $row)
            <tr>
                @if ($row->code == "1000")
                    <td class="m27-value pt-2">Son: <span class="font-bold">{{ $row->value }} {{ $document->currency_type->description }}</span></td>
                @else
                    <td class="m27-value pt-2">{{$row->code}}: {{ $row->value }}</td>
                @endif
            </tr>
        @endforeach
    </table>
    @include('pdf.modern-2027.partials.rule')
@endif

@if($document->payments->count())
    @php
        $payment = 0;
    @endphp
    <table class="full-width">
        <tr>
            <td class="m27-label pt-2">Pagos:</td>
        </tr>
        @foreach($document->payments as $row)
            <tr>
                <td class="m27-value">&#8226; {{ $row->payment_method_type->description }} {{ $row->reference ? '- '.$row->reference.' ':'' }}- {{ $document->currency_type->symbol }} {{ number_format($row->payment, 2) }}</td>
            </tr>
            @php
                $payment += (float) $row->payment;
            @endphp
        @endforeach
        <tr>
            <td class="m27-value"><span class="font-bold">SALDO:</span> {{ $document->currency_type->symbol }} {{ number_format($document->total - $payment, 2) }}</td>
        </tr>
    </table>
    @include('pdf.modern-2027.partials.rule')
@endif

@php
    $m27_show_accounts = isset($configurationInPdf) && $configurationInPdf->show_bank_accounts_in_pdf && count($accounts);
@endphp
@if($m27_show_accounts || $document->terms_condition)
    <table class="full-width">
        @if($m27_show_accounts)
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
        @if($document->terms_condition)
            <tr>
                <td class="m27-item-note pt-2">
                    <span class="font-bold">TÉRMINOS Y CONDICIONES DEL SERVICIO</span>
                    <div style="font-size: 8px;">
                        {!!$document->terms_condition!!}
                    </div>
                </td>
            </tr>
        @endif
    </table>
    @include('pdf.modern-2027.partials.rule')
@endif

<table class="full-width">
    <tr>
        <td class="m27-note pt-2">Este documento es una cotización y no constituye comprobante de pago.</td>
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
</html>
