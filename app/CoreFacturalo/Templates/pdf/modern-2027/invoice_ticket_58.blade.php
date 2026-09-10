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
    $m27_cashier = strtoupper(optional($document->user)->name ?? '');

    $m27_total_change = (float) $document->payments->sum('change');
    $m27_total_received = (float) $document->payments->sum('payment') + $m27_total_change;
    if ($m27_total_change <= 0 && $balance < 0) {
        $m27_total_change = abs($balance);
    }

    $m27_igv_percentage = collect($document->items)
        ->map(function ($it) { return (float) ($it->percentage_igv ?? 0); })
        ->filter()
        ->max();
    $m27_igv_percentage = $m27_igv_percentage ? rtrim(rtrim(number_format($m27_igv_percentage, 2, '.', ''), '0'), '.') : null;

    $m27_total_products = rtrim(rtrim(number_format(collect($document->items)->sum(function ($item) {
        return (float) data_get($item, 'quantity', 0);
    }), 2, '.', ''), '0'), '.');

    $m27_barcode = preg_replace('/[^0-9A-Za-z\-]/', '', $document_number);

    $m27_thanks = '¡Gracias por su preferencia!';
@endphp
<html>
<head></head>
<body class="ticket-58">
    @if($document->state_type->id == '11') 
    <div class="company_logo_box" style="position: absolute; text-align: center; top:30%;">
        <img
            src="data:{{mime_content_type(public_path("status_images".DIRECTORY_SEPARATOR."anulado.png"))}};base64, {{base64_encode(file_get_contents(public_path("status_images".DIRECTORY_SEPARATOR."anulado.png")))}}"
            alt="anulado" class="" style="opacity: 0.6;">
    </div>
    @endif
    @if($document->state_type->id == '09')
    <div class="company_logo_box" style="position: absolute; text-align: center; top:30%;">
        <img
            src="data:{{mime_content_type(public_path("status_images".DIRECTORY_SEPARATOR."rechazado.png"))}};base64, {{base64_encode(file_get_contents(public_path("status_images".DIRECTORY_SEPARATOR."rechazado.png")))}}"
            alt="rechazado" class="" style="opacity: 0.6;">
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
    @if($company->logo)
        <tr>
            <td class="text-center pt-2 pb-2">
                <img
                    src="data:{{mime_content_type(public_path("{$logo}"))}};base64, {{base64_encode(file_get_contents(public_path("{$logo}")))}}"
                    alt="{{ \App\CoreFacturalo\Helpers\CompanyDocumentDisplay::logoAlt($company) }}" class="m27-logo m27-logo-sm contain">
            </td>
        </tr>
    @endif
    <tr>
        <td class="m27-company m27-company-sm">@include('pdf.partials.company_document_header_names', [
            'tagPrimary' => 'div',
            'tagLegal' => 'div',
            'primaryClass' => 'm27-company m27-company-sm',
            'legalClass' => 'm27-company-legal m27-company-legal-sm',
            'legalLineStyle' => '',
        ])</td>
    </tr>
    <tr>
        <td class="m27-head-line m27-head-line-sm">RIF: {{ $company->number }}</td>
    </tr>
    <tr>
        <td class="m27-head-line m27-head-line-sm" style="text-transform: uppercase;">
            {{ ($establishment->address !== '-')? $establishment->address : '' }}
            {{ ($establishment->district_id !== '-')? ', '.$establishment->district->description : '' }}
            {{ ($establishment->province_id !== '-')? ', '.$establishment->province->description : '' }}
            {{ ($establishment->department_id !== '-')? '- '.$establishment->department->description : '' }}
        </td>
    </tr>
    @isset($establishment->trade_address)
        <tr>
            <td class="m27-head-line m27-head-line-sm">{{ ($establishment->trade_address !== '-')? 'D. Comercial: '.$establishment->trade_address : '' }}</td>
        </tr>
    @endisset
    @if($establishment->telephone !== '-' && $establishment->telephone !== '')
        <tr>
            <td class="m27-head-line m27-head-line-sm">Telf: {{ $establishment->telephone }}</td>
        </tr>
    @endif
    @if($establishment->email !== '-' && $establishment->email !== '')
        <tr>
            <td class="m27-head-line m27-head-line-sm">{{ $establishment->email }}</td>
        </tr>
    @endif
    @isset($establishment->web_address)
        <tr>
            <td class="m27-head-line m27-head-line-sm">{{ ($establishment->web_address !== '-')? $establishment->web_address : '' }}</td>
        </tr>
    @endisset
    @isset($establishment->aditional_information)
        <tr>
            <td class="m27-head-line m27-head-line-sm">{{ ($establishment->aditional_information !== '-')? $establishment->aditional_information : '' }}</td>
        </tr>
    @endisset
    <tr>
        <td class="m27-gap"></td>
    </tr>
    <tr>
        <td class="m27-doc-type m27-doc-type-sm">{{ $document->document_type->description }}</td>
    </tr>
    <tr>
        <td class="m27-gap"></td>
    </tr>
    <tr>
        <td class="m27-doc-number m27-doc-number-sm">{{ $document_number }}</td>
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
        <td width="42%" class="m27-label m27-label-sm">Fecha:</td>
        <td class="m27-value m27-value-sm">{{ $document->date_of_issue->format('d/m/Y') }} {{ $document->time_of_issue }}</td>
    </tr>
    @isset($invoice->date_of_due)
        <tr>
            <td class="m27-label m27-label-sm">F. Vencimiento:</td>
            <td class="m27-value m27-value-sm">{{ $invoice->date_of_due->format('d/m/Y') }}</td>
        </tr>
    @endisset
    <tr>
        <td class="m27-label m27-label-sm align-top">Cliente:</td>
        <td class="m27-value m27-value-sm">{{ $customer->name }}</td>
    </tr>
    <tr>
        <td class="m27-label m27-label-sm">{{ $customer->identity_document_type->description }}:</td>
        <td class="m27-value m27-value-sm">{{ format_identity_document($customer->identity_document_type_id ?? null, $customer->number) }}</td>
    </tr>
    <tr>
        <td class="m27-label m27-label-sm">Cajero:</td>
        <td class="m27-value m27-value-sm">{{ $m27_cashier }}</td>
    </tr>
    @if($customer->address !== '' && $customer->address !== '-')
        <tr>
            <td class="m27-label m27-label-sm align-top">Dirección:</td>
            <td class="m27-value m27-value-sm">
                {{ $customer->address }}
                {{ ($customer->district_id !== '-')? ', '.$customer->district->description : '' }}
                {{ ($customer->province_id !== '-')? ', '.$customer->province->description : '' }}
                {{ ($customer->department_id !== '-')? '- '.$customer->department->description : '' }}
            </td>
        </tr>
    @endif

    @if ($document->reference_data)
        <tr>
            <td class="align-top"><p class="m27-label m27-label-sm">D. Referencia:</p></td>
            <td>
                <p class="m27-value m27-value-sm">
                    {{ $document->reference_data }}
                </p>
            </td>
        </tr>
    @endif


    @if ($document->retention)
        <br>
        <tr>
            <td colspan="2">
                <p class="m27-value m27-value-sm"><span>Información de la retención</span></p>
            </td>
        </tr>
        <tr>
            <td><p class="m27-value m27-value-sm">Base imponible de la retención: </p></td>
            <td>
                <p class="m27-value m27-value-sm">Bs. {{ $document->getRetentionTaxBase() }} </p>
            </td>
        </tr>
        <tr>
            <td><p class="m27-label m27-label-sm">Porcentaje de la retención:</p></td>
            <td><p class="m27-value m27-value-sm">{{ $document->retention->percentage * 100 }}%</p></td>
        </tr>
        <tr>
            <td><p class="m27-label m27-label-sm">Monto de la retención:</p></td>
            <td>
                <p class="m27-value m27-value-sm">Bs. {{ $document->retention->amount_pen }}</p>
            </td>
        </tr>
    @endif

    @if ($document->purchase_order)
        <tr>
            <td><p class="m27-label m27-label-sm">Orden de Compra:</p></td>
            <td><p class="m27-value m27-value-sm">{{ $document->purchase_order }}</p></td>
        </tr>
    @endif
    @if ($document->quotation_id)
        <tr>
            <td><p class="m27-label m27-label-sm">Cotización:</p></td>
            <td><p class="m27-value m27-value-sm">{{ $document->quotation->identifier }}</p></td>
        </tr>
    @endif
    @isset($document->quotation->delivery_date)
        <tr>
            <td><p class="m27-value m27-value-sm">F. Entrega</p></td>
            <td>
                <p class="m27-value m27-value-sm">{{ $document->date_of_issue->addDays($document->quotation->delivery_date)->format('d-m-Y') }}</p>
            </td>
        </tr>
    @endisset
    @isset($document->quotation->sale_opportunity)
        <tr>
            <td><p class="m27-value m27-value-sm">O. Venta</p></td>
            <td><p class="m27-value m27-value-sm">{{ $document->quotation->sale_opportunity->number_full}}</p></td>
        </tr>
    @endisset
</table>

@if ($document->guides)
    <table>
        @foreach($document->guides as $guide)
            <tr>
                @if(isset($guide->document_type_description))
                    <td>{{ $guide->document_type_description }}</td>
                @else
                    <td>{{ $guide->document_type_id }}</td>
                @endif
                <td>:</td>
                <td>{{ $guide->number }}</td>
            </tr>
        @endforeach
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
    <span>Órdenes de entrega</span>
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
            <td class="m27-label m27-label-sm">Documento Afectado:</td>
            <td class="m27-value m27-value-sm">{{ $affected_document_number }}</td>
        </tr>
        <tr>
            <td class="m27-label m27-label-sm">Tipo de nota:</td>
            <td class="m27-value m27-value-sm">{{ ($document_base->note_type === 'credit')?$document_base->note_credit_type->description:$document_base->note_debit_type->description}}</td>
        </tr>
        <tr>
            <td class="align-top m27-label m27-label-sm">Descripción:</td>
            <td class="text-left m27-value m27-value-sm">{{ $document_base->note_description }}</td>
        </tr>
    </table>
@endif

@include('pdf.modern-2027.partials.rule')
<table class="full-width mt-10 mb-10">
    <thead>
    <tr>
        <th class="m27-th m27-th-sm m27-th-line text-left" width="14%">Cant</th>
        <th class="m27-th m27-th-sm m27-th-line text-left" width="57%">Descripción</th>
        <th class="m27-th m27-th-sm m27-th-line text-right" width="29%">Total</th>
    </tr>
    </thead>
    <tbody>
    @foreach($document->items as $row)
        @php
            // El codigo interno se imprime en la linea secundaria del item
            $item_code = trim((string) optional($row->item)->internal_id);
        @endphp
        <tr>
            <td class="m27-item m27-item-sm text-left">@if(((int)$row->quantity != $row->quantity)){{ $row->quantity }}@else{{ number_format($row->quantity, 0) }}@endif</td>
            <td class="m27-item m27-item-sm text-left text-uppercase">
                @if($row->name_product_pdf)
                    {!! \App\CoreFacturalo\Helpers\Template\TemplateHelper::stripLeadingItemCode(\App\CoreFacturalo\Helpers\Template\TemplateHelper::formatNameProductPdfForTicket($row->name_product_pdf), $item_code) !!}
                @else
                    {!! \App\CoreFacturalo\Helpers\Template\TemplateHelper::stripLeadingItemCode($row->item->description, $item_code) !!}
                @endif

                {{-- ########## INICIO SIN DETRACCIONES E ISC --}}
                @if(\App\Services\LocalFiscalDocumentPolicy::showIsc() && ($row->total_isc > 0))
                {{-- ######### FIN SIN DETRACCIONES E ISC --}}
                    <br/>ISC : {{ $row->total_isc }} ({{ $row->percentage_isc }}%)
                @endif

                @if (!empty($row->item->presentation)) {!!$row->item->presentation->description!!} @endif

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
            <td class="m27-item m27-item-sm text-right">{{ number_format($row->total, 2) }}</td>
        </tr>
        <tr>
            <td></td>
            <td colspan="2" class="m27-item-sub m27-item-sub-sm text-left">
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
            <td colspan="2" class="m27-total-label m27-total-label-sm">Op. exportación:</td>
            <td class="m27-total-value m27-total-value-sm">{{ $document->currency_type->symbol }} {{ number_format($document->total_exportation, 2) }}</td>
        </tr>
    @endif
    @if($document->total_free > 0)
        <tr>
            <td colspan="2" class="m27-total-label m27-total-label-sm">Op. gratuitas:</td>
            <td class="m27-total-value m27-total-value-sm">{{ $document->currency_type->symbol }} {{ number_format($document->total_free, 2) }}</td>
        </tr>
    @endif
    @if($document->total_unaffected > 0)
        <tr>
            <td colspan="2" class="m27-total-label m27-total-label-sm">Op. inafectas:</td>
            <td class="m27-total-value m27-total-value-sm">{{ $document->currency_type->symbol }} {{ number_format($document->total_unaffected, 2) }}</td>
        </tr>
    @endif
    @if($document->total_exonerated > 0)
        <tr>
            <td colspan="2" class="m27-total-label m27-total-label-sm">Op. exoneradas:</td>
            <td class="m27-total-value m27-total-value-sm">{{ $document->currency_type->symbol }} {{ number_format($document->total_exonerated, 2) }}</td>
        </tr>
    @endif
    @if($document->total_taxed > 0)
        <tr>
            <td colspan="2" class="m27-total-label m27-total-label-sm">Op. gravadas:</td>
            <td class="m27-total-value m27-total-value-sm">{{ $document->currency_type->symbol }} {{ number_format($document->total_taxed, 2) }}</td>
        </tr>
    @endif

    @if($document->subtotal > 0)
        @php
            $labelSubtotal = $document->total_discount_with_igv > 0 ? 'Suma de importes' : 'Subtotal';
            $subtotal = $document->total_discount_with_igv > 0 ? $document->subtotal + $document->total_discount_with_igv : $document->subtotal;
        @endphp
        <tr>
            <td colspan="2" class="m27-total-label m27-total-label-sm">{{ $labelSubtotal }}:</td>
            <td class="m27-total-value m27-total-value-sm">{{ $document->currency_type->symbol }} {{ number_format($subtotal, 2) }}</td>
        </tr>
    @endif

    @if($document->total_discount_with_igv > 0)
        <tr>
            <td colspan="2" class="m27-total-label m27-total-label-sm">Descuento total:</td>
            <td class="m27-total-value m27-total-value-sm">- {{ $document->currency_type->symbol }} {{ number_format($document->total_discount_with_igv, 2) }}</td>
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
                <td colspan="2" class="m27-total-label m27-total-label-sm">Cargos ({{ $total_factor }}%):</td>
                <td class="m27-total-value m27-total-value-sm">{{ $document->currency_type->symbol }} {{ number_format($document->total_charge, 2) }}</td>
            </tr>
        @else
            <tr>
                <td colspan="2" class="m27-total-label m27-total-label-sm">Cargos:</td>
                <td class="m27-total-value m27-total-value-sm">{{ $document->currency_type->symbol }} {{ number_format($document->total_charge, 2) }}</td>
            </tr>
        @endif
    @endif

    @if($document->total_plastic_bag_taxes > 0)
        <tr>
            <td colspan="2" class="m27-total-label m27-total-label-sm">ICBPER:</td>
            <td class="m27-total-value m27-total-value-sm">{{ $document->currency_type->symbol }} {{ number_format($document->total_plastic_bag_taxes, 2) }}</td>
        </tr>
    @endif

    {{-- ########## INICIO SIN DETRACCIONES E ISC --}}
    @if(\App\Services\LocalFiscalDocumentPolicy::showIsc() && ($document->total_isc > 0))
    {{-- ######### FIN SIN DETRACCIONES E ISC --}}
        <tr>
            <td colspan="2" class="m27-total-label m27-total-label-sm">ISC:</td>
            <td class="m27-total-value m27-total-value-sm">{{ $document->currency_type->symbol }} {{ number_format($document->total_isc, 2) }}</td>
        </tr>
    @endif

    <tr>
        <td colspan="2" class="m27-total-label m27-total-label-sm">IGV{{ $m27_igv_percentage ? ' ('.$m27_igv_percentage.'%)' : '' }}:</td>
        <td class="m27-total-value m27-total-value-sm">{{ $document->currency_type->symbol }} {{ number_format($document->total_igv, 2) }}</td>
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
        <td class="m27-item-sub m27-item-sub-sm align-bottom" style="white-space: nowrap;">{{ $m27_total_products }} und.</td>
        <td class="m27-grand-label m27-grand-label-sm">TOTAL:</td>
        <td class="m27-grand-value m27-grand-value-sm">{{ $document->currency_type->symbol }} {{ number_format($document->total, 2) }}</td>
    </tr>

    @if(($document->retention) && $document->total_pending_payment > 0)
        <tr>
            <td colspan="2" class="m27-total-label m27-total-label-sm">M. pendiente:</td>
            <td class="m27-total-value m27-total-value-sm">{{ $document->currency_type->symbol }} {{ number_format($document->total_pending_payment, 2) }}</td>
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
                <td class="m27-value m27-value-sm pt-2">Son: <span class="font-bold">{{ $row->value }} {{ $document->currency_type->description }}</span></td>
            @else
                <td class="m27-value m27-value-sm pt-2">{{$row->code}}: {{ $row->value }}</td>
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
        <td colspan="2" class="m27-gap"></td>
    </tr>
    @if($document->payment_method_type_id)
        <tr>
            <td class="m27-label m27-label-sm" width="48%">Método de pago:</td>
            <td class="m27-value m27-value-sm">{{ $document->payment_method_type->description }}</td>
        </tr>
    @endif
    @if($m27_total_received > 0)
        <tr>
            <td class="m27-label m27-label-sm">Recibido:</td>
            <td class="m27-value m27-value-sm">{{ $document->currency_type->symbol }} {{ number_format($m27_total_received, 2) }}</td>
        </tr>
    @endif
    @if($m27_total_change > 0)
        <tr>
            <td class="m27-label m27-label-sm">Vuelto:</td>
            <td class="m27-value m27-value-sm">{{ $document->currency_type->symbol }} {{ number_format($m27_total_change, 2) }}</td>
        </tr>
    @endif
    <tr>
        <td class="m27-label m27-label-sm" width="48%">Condición de pago:</td>
        <td class="m27-value m27-value-sm">{{ $paymentCondition }}</td>
    </tr>
</table>

@if ($document->payment_condition_id === '01')
    @if($payments->count())
        <table class="full-width">
            <tr>
                <td class="m27-label m27-label-sm pt-2">Pagos:</td>
            </tr>
            @foreach($payments as $row)
                <tr>
                    <td class="m27-value m27-value-sm">&#8226; {{ $row->payment_method_type->description }} {{ $row->reference ? '- '.$row->reference.' ':'' }}- {{ $document->currency_type->symbol }} {{ number_format($row->payment + $row->change, 2) }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@else
    @if(count($document->fee) > 0)
        <table class="full-width">
            <tr>
                <td class="m27-label m27-label-sm pt-2">Cuotas:</td>
            </tr>
            @foreach($document->fee as $key => $quote)
                <tr>
                    <td class="m27-value m27-value-sm">&#8226; {{ (empty($quote->getStringPaymentMethodType()) ? 'Cuota #'.( $key + 1) : $quote->getStringPaymentMethodType()) }} / {{ $quote->date->format('d-m-Y') }} / {{ $quote->currency_type->symbol }} {{ $quote->amount }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endif

<table class="full-width">
    @if(isset($configurationInPdf) && $configurationInPdf->show_seller_in_pdf)
        <tr>
            <td class="m27-value m27-value-sm pt-2"><span class="font-bold">VENDEDOR:</span> {{ $document->seller ? $document->seller->name : $document->user->name }}</td>
        </tr>
    @endif
    @foreach($document->additional_information as $information)
        @if ($information)
            @if ($loop->first)
                <tr>
                    <td class="m27-label m27-label-sm pt-2">Información adicional:</td>
                </tr>
            @endif
            <tr>
                <td class="m27-value m27-value-sm">{{ $information }}</td>
            </tr>
        @endif
    @endforeach
    @if(isset($configurationInPdf) && $configurationInPdf->show_bank_accounts_in_pdf)
        @if(in_array($document->document_type->id,['01','03']))
            @foreach($accounts as $account)
                @if($loop->first)
                    <tr>
                        <td class="m27-label m27-label-sm pt-2">Cuentas bancarias:</td>
                    </tr>
                @endif
                <tr>
                    <td class="m27-item-note m27-item-sub-sm">
                        <span class="font-bold">{{$account->bank->description}}</span> {{$account->currency_type->description}}
                        N°: {{$account->number}}@if($account->cci) / CCI: {{$account->cci}}@endif
                    </td>
                </tr>
            @endforeach
        @endif
    @endif
    @if ($document->terms_condition)
        <tr>
            <td class="m27-item-note m27-item-sub-sm pt-2">
                <span class="font-bold">TÉRMINOS Y CONDICIONES DEL SERVICIO</span>
                <div style="font-size: 7px;">
                    {!! $document->terms_condition !!}
                </div>
            </td>
        </tr>
    @endif
    <tr>
        <td class="m27-item-note m27-item-sub-sm pt-2">CÓDIGO HASH: {{ $document->hash }}</td>
    </tr>
    @if($document->qr)
        <tr>
            <td class="text-center pt-2">
                <img src="data:image/png;base64, {{ $document->qr }}" style="max-width: 70px"/>
            </td>
        </tr>
    @endif
</table>
@include('pdf.modern-2027.partials.rule')
<table class="full-width">
    <tr>
        <td class="m27-note m27-note-sm pt-2">
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
        <td class="m27-thanks m27-thanks-sm pt-2">{{ $m27_thanks }}</td>
    </tr>
    @if($m27_barcode)
        <tr>
            <td class="m27-barcode pt-2">
                <barcode code="{{ $m27_barcode }}" type="C128B" size="0.7" height="0.7"/>
            </td>
        </tr>
        <tr>
            <td class="m27-barcode-text m27-barcode-text-sm">{{ $m27_barcode }}</td>
        </tr>
    @endif
</table>
</body>
