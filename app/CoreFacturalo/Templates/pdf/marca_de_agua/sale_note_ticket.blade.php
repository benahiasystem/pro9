@php
    use Modules\Template\Helpers\TemplatePdf;

    $establishment = $document->establishment;
    $customer = $document->customer;
    $invoice = $document->invoice;
    //$path_style = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.DIRECTORY_SEPARATOR.'pdf'.DIRECTORY_SEPARATOR.'style.css');
    $tittle = \App\Services\Fiscal\FiscalIdentity::numberFull($document->series, $document->number);
    $payments = $document->payments;
    // $accounts = \App\Models\Tenant\BankAccount::all();
    $accounts = (new TemplatePdf)->getBankAccountsForPdf($document->establishment_id);

    $ticketLogo = \App\CoreFacturalo\Helpers\Template\TemplateHelper::ticketLogoDataUri($company, $establishment);
    $configurationInPdf= App\CoreFacturalo\Helpers\Template\TemplateHelper::getConfigurationInPdf();

@endphp
<html>
<head>
    {{--<title>{{ $tittle }}</title>--}}
    {{--<link href="{{ $path_style }}" rel="stylesheet" />--}}
</head>
<body>

@if($ticketLogo)
    <div class="text-center company_logo_box pt-5">
        <img src="{{ $ticketLogo }}" alt="{{ \App\CoreFacturalo\Helpers\CompanyDocumentDisplay::logoAlt($company) }}" class="company_logo_ticket contain">
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
        <td class="text-center pt-3 border-top"><h4>NOTA DE VENTA</h4></td>
    </tr>
    <tr>
        <td class="text-center pb-3 border-bottom"><h3>{{ $tittle }}</h3></td>
    </tr>
</table>
<table class="full-width">
    <tr>
        <td width="" class="pt-3"><p class="desc">F. Emisión:</p></td>
        <td width="" class="pt-3"><p class="desc">{{ $document->date_of_issue->format('Y-m-d') }} / {{ $document->time_of_issue }}</p></td>
    </tr>

    @if ($document->due_date)
        <tr>
            <td width="" class="pt-3"><p class="desc">F. Vencimiento:</p></td>
            <td width="" class="pt-3"><p class="desc">{{ $document->getFormatDueDate() }}</p></td>
        </tr>
    @endif

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
                    {{ strtoupper($customer->address) }}
                    {{ (!empty($customer->district->description))? ', '.strtoupper($customer->district->description) : '' }}
                    {{ (!empty($customer->province->description))? ', '.strtoupper($customer->province->description) : '' }}
                    {{ (!empty($customer->department->description))? '- '.strtoupper($customer->department->description) : '' }}
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
    @if ($document->plate_number !== null)
    <tr>
        <td class="align-top"><p class="desc">N° Placa:</p></td>
        <td><p class="desc">{{ $document->plate_number }}</p></td>
    </tr>
    @endif
    @if ($document->purchase_order)
        <tr>
            <td><p class="desc">Orden de Compra:</p></td>
            <td><p class="desc">{{ $document->purchase_order }}</p></td>
        </tr>
    @endif
    @if ($document->observation)
        <tr>
            <td><p class="desc">Observación:</p></td>
            <td><p class="desc">{{ $document->observation }}</p></td>
        </tr>
    @endif
    @if ($document->reference_data)
        <tr>
            <td class="align-top"><p class="desc">D. Referencia:</p></td>
            <td>
                <p class="desc">
                    {{ $document->reference_data }}
                </p>
            </td>
        </tr>
    @endif

    @if ($document->isPointSystem())
        <tr>
            <td><p class="desc">P. Acumulados:</p></td>
            <td><p class="desc">{{ $document->person->accumulated_points }}</p></td>
        </tr>
        <tr>
            <td><p class="desc">Puntos por la compra:</p></td>
            <td><p class="desc">{{ $document->getPointsBySale() }}</p></td>
        </tr>
    @endif

</table>

<table class="full-width mt-10 mb-10">
    <thead class="">
    <tr>
        <th class="border-top-bottom desc-9 text-left">COD.</th>
        <th class="border-top-bottom desc-9 text-left">CANT.</th>
        <th class="border-top-bottom desc-9 text-left">UNIDAD</th>
        <th class="border-top-bottom desc-9 text-left">DESCRIPCIÓN</th>
        <th class="border-top-bottom desc-9 text-left">P.UNIT</th>
        <th class="border-top-bottom desc-9 text-left">TOTAL</th>
    </tr>
    </thead>
    <tbody>
    @php
        // Separa los ítems fusionados de los normales
        $fusionados = [];
        $no_fusionados = [];
        foreach($document->items as $item) {
            if(!empty($item->item->esFusionado)) {
                $fusionados[] = $item;
            } else {
                $no_fusionados[] = $item;
            }
        }
    @endphp
    {{-- Si existen productos fusionados, mostrar solo una fila resumen --}}
    @if(count($fusionados) > 0)
        @php
            $cantidad_total = \App\Support\Exact\Decimal::sum(collect($fusionados)->map(fn($f)=>\App\Support\Exact\Decimal::read($f,'quantity')));
            $importe_total = \App\Support\Exact\Decimal::sum(collect($fusionados)->map(fn($f)=>\App\Support\Exact\Decimal::read($f,'total')));
        @endphp
        @if(strlen(\App\Support\Exact\Decimal::represent($cantidad_total)) > 7)
            <tr><td colspan="6" class="text-left desc-9">Cantidad: {{ \App\Support\Exact\Decimal::represent($cantidad_total) }}</td></tr>
        @endif
        <tr>
            <td class="text-center align-top">001</td>
            <td class="text-center desc-9 align-top">
                {{ strlen(\App\Support\Exact\Decimal::represent($cantidad_total)) > 7 ? '' : \App\Support\Exact\Decimal::represent($cantidad_total) }}
            </td>
            <td class="text-center desc-9 align-top">UND</td>
            <td class="text-left desc-9 align-top">Por consumo</td>
            <td class="text-right desc-9 align-top"><span data-pdf-unit-price @if(\App\Support\Exact\PdfPrice::roundingChangesLine($cantidad_total->isZero() ? 0 : $importe_total->dividedBy($cantidad_total),$cantidad_total,\App\Models\Tenant\Configuration::getDataDecimalQuantity())) data-pdf-group-rounded @endif>{{ \App\Support\Exact\PdfPrice::configured($cantidad_total->isZero() ? 0 : $importe_total->dividedBy($cantidad_total)) }}</span></td>
            <td class="text-right desc-9 align-top">{{ \App\Support\Exact\Decimal::format($importe_total, 2) }}</td>
        </tr>
        <tr><td colspan="6" class="border-bottom"></td></tr>
    @endif
    @foreach($no_fusionados as $row)
        @php $visible_quantity = \App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::read($row, 'quantity')); @endphp
        @if(strlen($visible_quantity) > 7)
            <tr><td colspan="6" class="text-left desc-9">Cantidad: {{ $visible_quantity }}</td></tr>
        @endif
        <tr>
            <td class="text-center desc-9 align-top font-bold">{{ $row->item->internal_id }}</td>
            <td class="text-center desc-9 align-top">
                {{ strlen($visible_quantity) > 7 ? '' : $visible_quantity }}
            </td>
            <td class="text-center desc-9 align-top">{{ $row->item->unit_type_id }}</td>
            <td class="text-left desc-9 align-top">

                @if($row->name_product_pdf)
                    {!! \App\CoreFacturalo\Helpers\Template\TemplateHelper::formatNameProductPdfForTicket($row->name_product_pdf) !!}
                @else
                    {!!$row->item->description!!}
                @endif
                    @if (!empty($row->item->presentation)) {!!$row->item->presentation->description!!} @endif
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
                
                @if($row->item->used_points_for_exchange ?? false)
                    <br>
                    <small>*** Canjeado por {{$row->item->used_points_for_exchange}}  puntos ***</small>
                @endif
                
            </td>
            <td class="text-right desc-9 align-top"><span data-pdf-unit-price>{{ $row->formatPdfUnitPrice() }}</span></td>
            <td class="text-right desc-9 align-top">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($row, 'total'), 2) }}</td>
        </tr>
        <tr>
            <td colspan="6" class="border-bottom"></td>
        </tr>
    @endforeach
        @if($document->total_exportation > 0)
            <tr>
                <td colspan="5" class="text-right font-bold desc">OP. EXPORTACIÓN: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold desc">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document, 'total_exportation'), 2) }}</td>
            </tr>
        @endif
        @if($document->total_free > 0)
            <tr>
                <td colspan="5" class="text-right font-bold desc">OP. GRATUITAS: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold desc">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document, 'total_free'), 2) }}</td>
            </tr>
        @endif
        @if($document->total_unaffected > 0)
            <tr>
                <td colspan="5" class="text-right font-bold desc">Total no sujeto: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold desc">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document, 'total_unaffected'), 2) }}</td>
            </tr>
        @endif
        @if($document->total_exonerated > 0)
            <tr>
                <td colspan="5" class="text-right font-bold desc">Total exento: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold desc">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document, 'total_exonerated'), 2) }}</td>
            </tr>
        @endif
        {{-- @if($document->total_taxed > 0)
            <tr>
                <td colspan="5" class="text-right font-bold desc">Base imponible: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold desc">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document, 'total_taxed'), 2) }}</td>
            </tr>
        @endif --}}
         @if($document->total_discount_with_igv > 0)
            <tr>
                <td colspan="5" class="text-right font-bold desc">{{(($document->total_prepayment > 0) ? 'ANTICIPO':'DESCUENTO TOTAL')}}: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold desc">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document, 'total_discount_with_igv'), 2) }}</td>
            </tr>
        @endif
        <tr><td colspan="6" class="text-right font-bold desc" style="white-space: nowrap;">IVA: {{ $document->currency_type->symbol }} {{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document, 'total_igv'), 2) }}</td></tr>
        
        @if($document->total_charge > 0 && $document->charges)
            <tr>
                <td colspan="5" class="text-right font-bold desc">CARGOS ({{$document->getTotalFactor()}}%): {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold desc">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document, 'total_charge'), 2) }}</td>
            </tr>
        @endif
        
        <tr>
            <td colspan="5" class="text-right font-bold desc">TOTAL A PAGAR: {{ $document->currency_type->symbol }}</td>
            <td class="text-right font-bold desc">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document, 'total'), 2) }}</td>
        </tr>


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
    @if(isset($configurationInPdf) && $configurationInPdf->show_bank_accounts_in_pdf)
    <tr>
        <td class="desc pt-3">
            <br>
            @foreach($accounts as $account)
                <span class="font-bold">{{$account->bank->description}}</span> {{$account->currency_type->description}}
                <br>
                <span class="font-bold">N°:</span> {{$account->number}}
                <br>
            @endforeach

        </td>
    </tr>
    @endif

</table>

@if($document->payment_method_type_id && $payments->count() == 0)
<table class="full-width">
    <tr>
    <td class="desc pt-5">
        <strong>PAGO: </strong>{{ $document->payment_method_type->description }}
    </td>
</tr>
</table>
@endif

@if($payments->count())
<table class="full-width">
    <tr><td><strong>PAGOS:</strong> </td></tr>
    @php
        $payment = 0;
    @endphp
    @foreach($payments as $row)
        <tr><td>- {{ $row->date_of_payment->format('d/m/Y') }} - @include('pdf.partials.sale_note_receipt', ['row' => $row, 'document' => $document])</td></tr>
        @php
            $payment += (float) $row->payment;
        @endphp
    @endforeach
    <tr><td class="pb-10"><strong>SALDO:</strong> {{ $document->currency_type->symbol }} {{ number_format($document->total - $payment, 2) }}</td></tr>
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
<br>
</body>
</html>
