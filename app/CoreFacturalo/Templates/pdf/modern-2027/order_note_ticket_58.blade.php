@php
    $establishment = $document->establishment;
    $customer = $document->customer;
    $invoice = $document->invoice;
    $accounts = \App\Models\Tenant\BankAccount::all();
    $tittle = $document->prefix.'-'.str_pad($document->id, 8, '0', STR_PAD_LEFT);

    /* ------------------------------------------------------------------
     | modern-2027: variables propias del diseño del ticket
     ------------------------------------------------------------------ */
    $max_chars_description = 28;

    $m27_seller = strtoupper(optional($document->user)->name ?? '');

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
@endphp
<html>
<head></head>
<body class="ticket-58">

<table class="full-width">
    @if($company->logo)
        <tr>
            <td class="text-center pt-2 pb-2">
                <img src="data:{{mime_content_type(public_path("storage/uploads/logos/{$company->logo}"))}};base64, {{base64_encode(file_get_contents(public_path("storage/uploads/logos/{$company->logo}")))}}"
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
        <td class="m27-doc-type m27-doc-type-sm">Pedido</td>
    </tr>
    <tr>
        <td class="m27-gap"></td>
    </tr>
    <tr>
        <td class="m27-doc-number m27-doc-number-sm">{{ $tittle }}</td>
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
    @if($document->date_of_due)
        <tr>
            <td class="m27-label m27-label-sm">F. Vencimiento:</td>
            <td class="m27-value m27-value-sm">{{ $document->date_of_due->format('d/m/Y') }}</td>
        </tr>
    @endif
    @if($document->delivery_date)
        <tr>
            <td class="m27-label m27-label-sm">F. Entrega:</td>
            <td class="m27-value m27-value-sm">{{ $document->delivery_date->format('d/m/Y') }}</td>
        </tr>
    @endif
    <tr>
        <td class="m27-label m27-label-sm align-top">Cliente:</td>
        <td class="m27-value m27-value-sm">{{ $customer->name }}</td>
    </tr>
    <tr>
        <td class="m27-label m27-label-sm">{{ $customer->identity_document_type->description }}:</td>
        <td class="m27-value m27-value-sm">{{ format_identity_document($customer->identity_document_type_id ?? null, $customer->number) }}</td>
    </tr>
    <tr>
        <td class="m27-label m27-label-sm">Vendedor:</td>
        <td class="m27-value m27-value-sm">{{ $m27_seller }}</td>
    </tr>
    @if ($customer->address !== '')
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
    @if ($document->shipping_address)
        <tr>
            <td class="m27-label m27-label-sm align-top">Dir. envío:</td>
            <td class="m27-value m27-value-sm">{{ $document->shipping_address }}</td>
        </tr>
    @endif
    @if ($customer->telephone)
        <tr>
            <td class="m27-label m27-label-sm align-top">Teléfono:</td>
            <td class="m27-value m27-value-sm">{{ $customer->telephone }}</td>
        </tr>
    @endif
    @if ($document->payment_method_type)
        <tr>
            <td class="m27-label m27-label-sm align-top">T. Pago:</td>
            <td class="m27-value m27-value-sm">{{ $document->payment_method_type->description }}</td>
        </tr>
    @endif
    @if ($document->observation)
        <tr>
            <td class="m27-label m27-label-sm align-top">Observación:</td>
            <td class="m27-value m27-value-sm">{{ $document->observation }}</td>
        </tr>
    @endif
    @if ($document->purchase_order)
        <tr>
            <td class="m27-label m27-label-sm">Orden de compra:</td>
            <td class="m27-value m27-value-sm">{{ $document->purchase_order }}</td>
        </tr>
    @endif
</table>

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
            $item_quantity = ((int)$row->quantity != $row->quantity) ? $row->quantity : number_format($row->quantity, 0);
        @endphp
        <tr>
            <td class="m27-item m27-item-sm text-left">{{ $item_quantity }}</td>
            <td class="m27-item m27-item-sm text-left">
                {!! \App\CoreFacturalo\Helpers\Template\TemplateHelper::stripLeadingItemCode($row->getTemplateDescription(), $item_code) !!} @if (!empty($row->item->presentation)) {!!$row->item->presentation->description!!} @endif
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
            </td>
            <td class="m27-item m27-item-sm text-right font-bold">{{ number_format($row->total, 2) }}</td>
        </tr>
        <tr>
            <td></td>
            <td colspan="2" class="m27-item-sub m27-item-sub-sm text-left">
                @php
                    // Blade se come un "(...)" que siga a una directiva como @endif,
                    // asi que la linea secundaria se arma aqui y se imprime entera.
                    $item_unit_price = $row->unit_price;
                    $item_unit_label = $row->item->unit_type_id !== 'UND' ? $row->item->unit_type_id.' x ' : '';
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
    @if($document->total_discount_with_igv > 0)
        <tr>
            <td colspan="2" class="m27-total-label m27-total-label-sm">Descuento total:</td>
            <td class="m27-total-value m27-total-value-sm">{{ $document->currency_type->symbol }} {{ number_format($document->total_discount_with_igv, 2) }}</td>
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
    </tbody>
</table>
@include('pdf.modern-2027.partials.rule')

{{-- El bloque cierra con su propio separador: si no hay leyendas ni cuentas
     no se imprime nada y no quedan dos separadores seguidos. --}}
@if(count((array) $document->legends) || count($accounts))
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
</table>
@include('pdf.modern-2027.partials.rule')
@endif
<table class="full-width">
    <tr>
        <td class="m27-note m27-note-sm pt-2">Este documento es un pedido y no constituye comprobante de pago.</td>
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
</html>
