@php
    use Modules\Template\Helpers\TemplatePdf;

    $establishment = $document->establishment;
    $customer = $document->customer;
    $invoice = $document->invoice;
    $tittle = $document->series.'-'.str_pad($document->number, 8, '0', STR_PAD_LEFT);
    $payments = $document->payments;
    $accounts = (new TemplatePdf)->getBankAccountsForPdf($document->establishment_id);

    $logo = "storage/uploads/logos/{$company->logo}";
    if($establishment->logo) {
        $logo = "{$establishment->logo}";
    }
    $configurationInPdf= App\CoreFacturalo\Helpers\Template\TemplateHelper::getConfigurationInPdf();

    /* ------------------------------------------------------------------
     | modern-2027: variables propias del diseño del ticket
     ------------------------------------------------------------------ */
    $max_chars_description = config('tenant.enabled_template_tficket_80') ? 30 : 28;

    $m27_cashier = strtoupper(($document->seller_id != 0 && $document->seller) ? $document->seller->name : optional($document->user)->name ?? '');

    $m27_change_payment = $document->getChangePayment();
    $m27_total_change = ($m27_change_payment < 0) ? abs($m27_change_payment) : 0;
    $m27_total_received = (float) $payments->sum('payment') + (float) $payments->sum('change');

    $m27_igv_percentage = collect($document->items)
        ->map(function ($it) { return (float) ($it->percentage_igv ?? 0); })
        ->filter()
        ->max();
    $m27_igv_percentage = $m27_igv_percentage ? rtrim(rtrim(number_format($m27_igv_percentage, 2, '.', ''), '0'), '.') : null;

    $m27_total_products = rtrim(rtrim(number_format(collect($document->items)->sum(function ($item) {
        return (float) data_get($item, 'quantity', 0);
    }), 2, '.', ''), '0'), '.');

    $m27_barcode = preg_replace('/[^0-9A-Za-z\-]/', '', $tittle);

    $m27_thanks = '¡Gracias por su preferencia!';
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
        <td class="m27-head-line">RUC: {{ $company->number }}</td>
    </tr>
    <tr>
        <td class="m27-head-line" style="text-transform: uppercase;">
            {{ ($establishment->address !== '-')? $establishment->address : '' }}
            {{ ($establishment->district_id !== '-')? ', '.$establishment->district->description : '' }}
            {{ ($establishment->province_id !== '-')? ', '.$establishment->province->description : '' }}
            {{ ($establishment->department_id !== '-')? '- '.$establishment->department->description : '' }}
        </td>
    </tr>
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
    <tr>
        <td class="m27-gap"></td>
    </tr>
    <tr>
        <td class="m27-doc-type">Nota de venta</td>
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
    @if ($document->due_date)
        <tr>
            <td class="m27-label">F. Vencimiento:</td>
            <td class="m27-value">{{ $document->getFormatDueDate() }}</td>
        </tr>
    @endif
    <tr>
        <td class="m27-label align-top">Cliente:</td>
        <td class="m27-value">{{ $customer->name }}</td>
    </tr>
    <tr>
        <td class="m27-label">{{ $customer->identity_document_type->description }}:</td>
        <td class="m27-value">{{ $customer->number }}</td>
    </tr>
    <tr>
        <td class="m27-label">Cajero:</td>
        <td class="m27-value">{{ $m27_cashier }}</td>
    </tr>
    @if ($customer->address !== '')
        <tr>
            <td class="m27-label align-top">Dirección:</td>
            <td class="m27-value">
                {{ strtoupper($customer->address) }}
                {{ ($customer->district_id !== '-')? ', '.strtoupper($customer->district->description) : '' }}
                {{ ($customer->province_id !== '-')? ', '.strtoupper($customer->province->description) : '' }}
                {{ ($customer->department_id !== '-')? '- '.strtoupper($customer->department->description) : '' }}
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
            <td class="m27-label align-top">Consignado:</td>
            <td class="m27-value">
                {{ $consigned->number }} -
                {{ $consigned->name }} -
                {{ ($consigned_phone->phone)? $consigned_phone->phone : ' ' }} -
                {{ $document->consigned_address }}
                {{($district) ? ' , '.$district->description.' , '.$province->description.' , '.$department->description: ' '}}
            </td>
        </tr>
    @endif
    @if ($document->plate_number !== null)
        <tr>
            <td class="m27-label align-top">N° Placa:</td>
            <td class="m27-value">{{ $document->plate_number }}</td>
        </tr>
    @endif
    @if ($document->purchase_order)
        <tr>
            <td class="m27-label">Orden de compra:</td>
            <td class="m27-value">{{ $document->purchase_order }}</td>
        </tr>
    @endif
    @if ($document->observation)
        <tr>
            <td class="m27-label align-top">Observación:</td>
            <td class="m27-value">{{ $document->observation }}</td>
        </tr>
    @endif
    @if ($document->reference_data)
        <tr>
            <td class="m27-label align-top">D. Referencia:</td>
            <td class="m27-value">{{ $document->reference_data }}</td>
        </tr>
    @endif
    @if ($document->isPointSystem())
        <tr>
            <td class="m27-label">P. Acumulados:</td>
            <td class="m27-value">{{ $document->person->accumulated_points }}</td>
        </tr>
        <tr>
            <td class="m27-label">Puntos por la compra:</td>
            <td class="m27-value">{{ $document->getPointsBySale() }}</td>
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
    @php
        $fusionados = $document->items->filter(function($row) {return !empty($row->item->esFusionado);});
        $no_fusionados = $document->items->filter(function($row) {return empty($row->item->esFusionado);});
    @endphp
    @if($fusionados->count())
        @php
            $cantidad_fusionada = $fusionados->sum('quantity');
            $total_fusionado = $fusionados->sum('total');
            $cantidad_fusionada_format = ((int)$cantidad_fusionada != $cantidad_fusionada) ? $cantidad_fusionada : number_format($cantidad_fusionada, 0);
        @endphp
        <tr>
            <td class="m27-item text-left">{{ $cantidad_fusionada_format }}</td>
            <td class="m27-item text-left">Por consumo</td>
            <td class="m27-item text-right font-bold">{{ number_format($total_fusionado, 2) }}</td>
        </tr>
        <tr>
            <td></td>
            <td colspan="2" class="m27-item-sub">({{ $document->currency_type->symbol }} {{ number_format($total_fusionado, 2) }})</td>
        </tr>
    @endif
    @foreach($no_fusionados as $row)
        @inject('items', 'App\Models\Tenant\Item')
        @php
            $internal_id = isset($row->item->internal_id) ? $row->item->internal_id : $items->find($row->item_id)->internal_id;
            $show_item_code = !empty($internal_id)
                && mb_strpos(strip_tags((string) ($row->name_product_pdf ?: $row->item->description)), (string) $internal_id) === false;

            $item_quantity = ((int)$row->quantity != $row->quantity) ? $row->quantity : number_format($row->quantity, 0);

            $use_name_product_pdf = !empty($row->name_product_pdf);
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
                    {!! \App\CoreFacturalo\Helpers\Template\TemplateHelper::stripLeadingItemCode(\App\CoreFacturalo\Helpers\Template\TemplateHelper::formatNameProductPdfForTicket($row->name_product_pdf), $internal_id) !!}
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
                        @if(isset($dtos->factor) && !($dtos->from_global_distribution ?? false))
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
                @if($row->item->used_points_for_exchange ?? false)
                    <br>
                    <small>*** Canjeado por {{$row->item->used_points_for_exchange}} puntos ***</small>
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
            <td class="m27-item text-right font-bold">{{ number_format($row->total, 2) }}</td>
        </tr>
        <tr>
            <td></td>
            <td colspan="2" class="m27-item-sub text-left">
                @php
                    // Blade se come un "(...)" que siga a una directiva como @endif,
                    // asi que la linea secundaria se arma aqui y se imprime entera.
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
    @if($document->total_discount_with_igv > 0)
        <tr>
            <td colspan="2" class="m27-total-label">{{ ($document->total_prepayment > 0) ? 'Anticipo' : 'Descuento total' }}:</td>
            <td class="m27-total-value">{{ $document->currency_type->symbol }} {{ number_format($document->total_discount_with_igv, 2) }}</td>
        </tr>
    @endif
    @if($document->total_charge > 0 && $document->charges)
        <tr>
            <td colspan="2" class="m27-total-label">Cargos ({{ $document->getTotalFactor() }}%):</td>
            <td class="m27-total-value">{{ $document->currency_type->symbol }} {{ number_format($document->total_charge, 2) }}</td>
        </tr>
    @endif
    @if($document->total_igv > 0)
        <tr>
            <td colspan="2" class="m27-total-label">Subtotal:</td>
            <td class="m27-total-value">{{ $document->currency_type->symbol }} {{ number_format($document->total - $document->total_igv, 2) }}</td>
        </tr>
        <tr>
            <td colspan="2" class="m27-total-label">IGV{{ $m27_igv_percentage ? ' ('.$m27_igv_percentage.'%)' : '' }}:</td>
            <td class="m27-total-value">{{ $document->currency_type->symbol }} {{ number_format($document->total_igv, 2) }}</td>
        </tr>
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
        <td class="m27-item-sub align-bottom" style="white-space: nowrap;">{{ $m27_total_products }} und.</td>
        <td class="m27-grand-label">TOTAL:</td>
        <td class="m27-grand-value">{{ $document->currency_type->symbol }} {{ number_format($document->total, 2) }}</td>
    </tr>
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
                <td class="m27-value pt-2">Son: <span class="font-bold">{{ $row->value }} {{ $document->currency_type->description }}</span></td>
            @else
                <td class="m27-value pt-2">{{$row->code}}: {{ $row->value }}</td>
            @endif
        </tr>
    @endforeach
</table>
@include('pdf.modern-2027.partials.rule')
@endif
<table class="full-width">
    <tr>
        <td colspan="2" class="m27-gap"></td>
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
</table>

@if($payments->count())
    @php
        $payment = 0;
    @endphp
    <table class="full-width">
        <tr>
            <td class="m27-label pt-2">Pagos:</td>
        </tr>
        @foreach($payments as $row)
            <tr>
                <td class="m27-value">&#8226; {{ $row->date_of_payment->format('d/m/Y') }} - {{ $row->payment_method_type->description }} {{ $row->reference ? '- '.$row->reference.' ':'' }}- {{ $document->currency_type->symbol }} {{ number_format($row->payment + $row->change, 2) }}</td>
            </tr>
            @php
                $payment += (float) $row->payment;
            @endphp
        @endforeach
        <tr>
            <td class="m27-value"><span class="font-bold">SALDO:</span> {{ $document->currency_type->symbol }} {{ number_format($document->total - $payment, 2) }}</td>
        </tr>
    </table>
@endif

<table class="full-width">
    @if(isset($configurationInPdf) && $configurationInPdf->show_bank_accounts_in_pdf)
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
            Documento interno sin valor tributario.
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
</html>
