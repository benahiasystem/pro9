@php
    use Modules\Template\Helpers\TemplatePdf;

    $establishment = $document->establishment;
    $customer = $document->customer;
    $invoice = $document->invoice;
    //$path_style = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.DIRECTORY_SEPARATOR.'pdf'.DIRECTORY_SEPARATOR.'style.css');
    $tittle = $document->series.'-'.str_pad($document->number, 8, '0', STR_PAD_LEFT);
    $payments = $document->payments;
    // $accounts = \App\Models\Tenant\BankAccount::all();
    $accounts = (new TemplatePdf)->getBankAccountsForPdf($document->establishment_id);

    $logo = "storage/uploads/logos/{$company->logo}";
    if($establishment->logo) {
        $logo = "{$establishment->logo}";
    }
    $configurationInPdf= App\CoreFacturalo\Helpers\Template\TemplateHelper::getConfigurationInPdf();

    $max_chars_description = config('tenant.enabled_template_ticket_80') ? 46 : 47;

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
        <td class="text-center"><h5>{{ 'RUC '.$company->number }}</h5></td>
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
        <td><p class="desc">{{ $customer->number }}</p></td>
    </tr>
    @if ($customer->address !== '')
        <tr>
            <td class="align-top"><p class="desc">Dirección:</p></td>
            <td>
                <p class="desc">
                    {{ strtoupper($customer->address) }}
                    {{ ($customer->district_id !== '-')? ', '.strtoupper($customer->district->description) : '' }}
                    {{ ($customer->province_id !== '-')? ', '.strtoupper($customer->province->description) : '' }}
                    {{ ($customer->department_id !== '-')? '- '.strtoupper($customer->department->description) : '' }}
                </p>
            </td>
        </tr>
    @endif
    @if ($document->consigned_id)
    @php
        $consigned =App\Models\Tenant\Consigned::where('id',$document->consigned_id)->first();
        $district = App\Models\Tenant\Catalogs\District::where('id', $document->consigned_ubigeo)->first();
        //La dirección del consignado es el número asignado a la dirección
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
        <td class="align-top">Consignado:</td>
        <td colspan="3">
            {{ $consigned->number }} -
            {{ $consigned->name }} -
            {{ ($consigned_phone->phone)? $consigned_phone->phone : ' ' }} -
            {{ $document->consigned_address }}
            {{($district) ? ' , '.$district->description.' , '.$province->description.' , '.$department->description: ' '}}
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
        <th colspan="2" class="border-top-bottom desc-9 text-left">DESCRIPCIÓN</th>
        <th class="border-top-bottom desc-9 text-right">IMPORTE</th>
    </tr>
    </thead>
    <tbody>
    @php
        $fusionados = $document->items->filter(function($row) {return!empty($row->item->esFusionado);});
        $no_fusionados = $document->items->filter(function($row) {return empty($row->item->esFusionado);});
    @endphp
    {{-- Mostrar solo un producto fusionado --}}
    @if($fusionados->count())
        @php
            $cantidad_fusionada = $fusionados->sum('quantity');
            $total_fusionado = $fusionados->sum('total');
            $cantidad_fusionada_format = ((int)$cantidad_fusionada != $cantidad_fusionada) ? $cantidad_fusionada : number_format($cantidad_fusionada, 0);
        @endphp
        <tr>
            <td colspan="3" class="text-left desc-9 align-top pt-3">
                <span style="font-size: 7px;">001</span> Por consumo
            </td>
        </tr>
        <tr>
            <td colspan="2" class="text-left desc-9 pb-3">
                {{ $cantidad_fusionada_format }} NIU x {{ number_format($total_fusionado, 2) }}
            </td>
            <td class="text-right desc-9 font-bold pb-3">{{ number_format($total_fusionado, 2) }}</td>
        </tr>
        @if($no_fusionados->count() === 0)
            <tr><td colspan="3" class="border-bottom"></td></tr>
        @endif
    @endif
    {{-- Mostrar productos no fusionados --}}
    @foreach($no_fusionados as $row)
        @inject('items', 'App\Models\Tenant\Item')
        @php
            $internal_id = isset($row->item->internal_id) ? $row->item->internal_id : $items->find($row->item_id)->internal_id;
        @endphp
        @php
            $item_code = !empty($row->item->esFusionado) ? '001' : $internal_id;
            $item_unit = !empty($row->item->esFusionado) ? 'NIU' : $row->item->unit_type_id;
            $item_quantity = ((int)$row->quantity != $row->quantity) ? $row->quantity : number_format($row->quantity, 0);

            if(!empty($row->item->esFusionado)) {
                $item_description = 'Por consumo';
            } else {
                $item_description = $row->name_product_pdf ? $row->name_product_pdf : $row->item->description;
            }
            $show_item_code = $item_code !== '' && $item_code !== null
                && mb_strpos(strip_tags((string) $item_description), (string) $item_code) === false;

            if (!empty($row->item->presentation)) {
                $item_description .= ' '.$row->item->presentation->description;
            }
            $item_description = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $item_description))));
            $available_chars = $max_chars_description;
            if ($show_item_code) {
                $available_chars -= (int) ceil(mb_strlen((string) $item_code) * 7 / 9) + 1;
            }
            if (mb_strlen($item_description) > $available_chars) {
                $item_description = rtrim(mb_substr($item_description, 0, max($available_chars - 1, 1))).'.';
            }
        @endphp
        <tr>
            <td colspan="3" class="text-left desc-9 align-top pt-2">
                @if($show_item_code)<span style="font-size: 7px;">{{ $item_code }}</span> @endif{{ $item_description }}
                @if($row->attributes)
                    @foreach($row->attributes as $attr)
                        <br/>{!! $attr->description !!} : {{ $attr->value }}
                    @endforeach
                @endif
                @if($row->discounts)
                    @foreach($row->discounts as $dtos)
                        @if(isset($dtos->factor))
                            <br/><small>{{ $dtos->factor * 100 }}% {{$dtos->description }}</small>
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
                @inject('itemLotGroup', 'App\Services\ItemLotsGroupService')
                @php
                    $lot = $itemLotGroup->getLote($row->item->IdLoteSelected);
                    $date_due = $itemLotGroup->getLotDateOfDue($row->item->IdLoteSelected);
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
        </tr>
        <tr>
            <td colspan="2" class="text-left desc-9 pb-2">
                {{ $item_quantity }} {{ $item_unit }} x {{ number_format($row->unit_price, 2) }}
            </td>
            <td class="text-right desc-9 font-bold pb-2">{{ number_format($row->total, 2) }}</td>
        </tr>
        @if($loop->last)
            <tr>
                <td colspan="3" class="border-bottom"></td>
            </tr>
        @endif
    @endforeach
        @if($document->total_exportation > 0)
            <tr>
                <td colspan="2" class="text-right font-bold desc">OP. EXPORTACIÓN: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold desc">{{ number_format($document->total_exportation, 2) }}</td>
            </tr>
        @endif
        @if($document->total_free > 0)
            <tr>
                <td colspan="2" class="text-right font-bold desc">OP. GRATUITAS: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold desc">{{ number_format($document->total_free, 2) }}</td>
            </tr>
        @endif
        @if($document->total_unaffected > 0)
            <tr>
                <td colspan="2" class="text-right font-bold desc">OP. INAFECTAS: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold desc">{{ number_format($document->total_unaffected, 2) }}</td>
            </tr>
        @endif
        @if($document->total_exonerated > 0)
            <tr>
                <td colspan="2" class="text-right font-bold desc">OP. EXONERADAS: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold desc">{{ number_format($document->total_exonerated, 2) }}</td>
            </tr>
        @endif
        {{-- @if($document->total_taxed > 0)
            <tr>
                <td colspan="2" class="text-right font-bold desc">OP. GRAVADAS: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold desc">{{ number_format($document->total_taxed, 2) }}</td>
            </tr>
        @endif --}}
         @if($document->total_discount_with_igv > 0)
            <tr>
                <td colspan="2" class="text-right font-bold desc">{{(($document->total_prepayment > 0) ? 'ANTICIPO':'DESCUENTO TOTAL')}}: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold desc">{{ number_format($document->total_discount_with_igv, 2) }}</td>
            </tr>
        @endif
        {{--<tr>
            <td colspan="5" class="text-right font-bold desc">IGV: {{ $document->currency_type->symbol }}</td>
            <td class="text-right font-bold desc">{{ number_format($document->total_igv, 2) }}</td>
        </tr>--}}

        @if($document->total_charge > 0 && $document->charges)
            <tr>
                <td colspan="2" class="text-right font-bold desc">CARGOS ({{$document->getTotalFactor()}}%): {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold desc">{{ number_format($document->total_charge, 2) }}</td>
            </tr>
        @endif

        <tr>
            <td class="text-left font-bold desc" style="white-space: nowrap;">Productos: {{ rtrim(rtrim(number_format(collect($document->items)->sum(function ($item) { return (float) data_get($item, 'quantity', 0); }), 2, '.', ''), '0'), '.') }}</td>
            <td class="text-right font-bold desc" style="white-space: nowrap;">TOTAL A PAGAR: {{ $document->currency_type->symbol }}</td>
            <td class="text-right font-bold desc">{{ number_format($document->total, 2) }}</td>
        </tr>

        @php
            $change_payment = $document->getChangePayment();
        @endphp

        @if($change_payment < 0)
            <tr>
                <td colspan="2" class="text-right font-bold desc">VUELTO: {{ $document->currency_type->symbol }}</td>
                <td class="text-right font-bold desc">{{ number_format(abs($change_payment),2, ".", "") }}</td>
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

    @if(isset($configurationInPdf) && $configurationInPdf->show_bank_accounts_in_pdf)
        <tr>
            <td class="desc pt-3">
                <br>
                @foreach($accounts as $account)
                    <span class="font-bold">{{$account->bank->description}}</span> {{$account->currency_type->description}}
                    <br>
                    <span class="font-bold">N°:</span> {{$account->number}}
                    @if($account->cci)
                    - <span class="font-bold">CCI:</span> {{$account->cci}}
                    @endif
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
        <tr><td>- {{ $row->date_of_payment->format('d/m/Y') }} - {{ $row->payment_method_type->description }} - {{ $row->reference ? $row->reference.' - ':'' }} {{ $document->currency_type->symbol }} {{ $row->payment + $row->change }}</td></tr>
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
