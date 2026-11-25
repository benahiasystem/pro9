{{-- ######## INICIO DISEÑO COMPARTIDO DE FACTURA 80 MM ######## --}}
<table class="full-width mt-10 mb-10">
    <thead class="">
    <tr>
        <th colspan="2" class="border-top-bottom desc-9 text-left">DESCRIPCIÓN</th>
        <th class="border-top-bottom desc-9 text-right">IMPORTE</th>
    </tr>
    </thead>
    <tbody>
    @php
        $split = \App\Support\Exact\PdfInvoiceGroups::split($document);
        $fusionados = $split['grouped'];
        $no_fusionados = $split['individual'];
        $hay_fusionados = $fusionados->isNotEmpty();
        $cantidad_fusionada = \App\Support\Exact\Decimal::sum($fusionados->map(fn ($item) => \App\Support\Exact\Decimal::read($item, 'quantity')));
        $total_fusionado = \App\Support\Exact\Decimal::sum($fusionados->map(fn ($item) => \App\Support\Exact\Decimal::read($item, 'total')));
    @endphp
    @if($hay_fusionados)
        <tr>
            <td colspan="3" class="text-left desc-9 align-top pt-2">
                <span style="font-size: 7px;">001</span> Por consumo
            </td>
        </tr>
        <tr>
            <td colspan="2" class="text-left desc-9 pb-2">
                {{ \App\Support\Exact\Decimal::represent($cantidad_fusionada) }} UND x <span data-pdf-unit-price @if(\App\Support\Exact\PdfPrice::roundingChangesLine($cantidad_fusionada->isZero() ? 0 : $total_fusionado->dividedBy($cantidad_fusionada),$cantidad_fusionada,\App\Models\Tenant\Configuration::getDataDecimalQuantity())) data-pdf-group-rounded @endif>{{ \App\Support\Exact\PdfPrice::configured($cantidad_fusionada->isZero() ? 0 : $total_fusionado->dividedBy($cantidad_fusionada)) }}</span>
            </td>
            <td class="text-right desc-9 font-bold pb-2">{{ \App\Support\Exact\Decimal::format($total_fusionado) }}</td>
        </tr>
        <tr><td colspan="3" class="border-bottom"></td></tr>
    @endif
        @foreach($no_fusionados as $row)
            @php
                $item_code = $row->item->internal_id;
                $item_quantity = \App\Support\Exact\InvoicePdfSummary::quantity($row);
                $use_name_product_pdf = !empty($row->name_product_pdf);
                $item_description_html = null;

                if ($use_name_product_pdf) {
                    $item_description_html = \App\CoreFacturalo\Helpers\Template\TemplateHelper::formatNameProductPdfForTicket($row->name_product_pdf);
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
                }

                $show_item_code = $item_code !== '' && $item_code !== null
                    && mb_strpos(strip_tags((string) $item_description), (string) $item_code) === false;

            @endphp
            <tr>
                <td colspan="3" class="text-left desc-9 align-top pt-2">
                    @if($show_item_code)<span style="font-size: 7px;">{{ $item_code }}</span> @endif
                    @if($use_name_product_pdf)
                        {!! $item_description_html !!}
                    @else
                        {{ $item_description }}
                    @endif

                    {{-- ########## INICIO SIN DETRACCIONES E ISC --}}




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
                        @foreach(\App\Support\Exact\PdfAmounts::adjustments($row, 'discounts') as $dtos)
                            @if(!($dtos->from_global_distribution ?? false))
                                <br/><small>{{ ($dtos->is_amount ?? false) ? '' : \App\Support\Exact\PdfAmounts::adjustmentPercent($dtos).'%' }} {{$dtos->description ?? 'Descuento' }}</small>
                            @endif
                        @endforeach
                    @endif

                    @if($row->charges)
                        @foreach(\App\Support\Exact\PdfAmounts::adjustments($row, 'charges') as $charge)
                            <br/><small>{{ $document->currency_type->symbol}} {{ \App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::of($charge->amount_exact ?? $charge->amount_without_rounded ?? $charge->amount ?? 0)) }}
                                ({{ \App\Support\Exact\PdfAmounts::adjustmentPercent($charge) }}%) {{$charge->description ?? 'Cargo' }}</small>
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
                        $date_due = optional($row->item)->IdLoteSelected ? $itemLotGroup->getInvoiceLotDateOfDue($row->item->IdLoteSelected) : '';
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
                    {{ $item_quantity }} {{ $row->item->unit_type_id }} x <span data-pdf-unit-price>{{ $row->formatPdfUnitPrice() }}</span>
                </td>
                <td class="text-right desc-9 font-bold pb-2">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($row, 'total'), 2) }}</td>
            </tr>
            @if($loop->last)
                <tr>
                    <td colspan="3" class="border-bottom"></td>
                </tr>
            @endif
        @endforeach
    @if ($document->prepayments)
        @foreach($document->prepayments as $p)
            <tr>
                <td colspan="3" class="text-left desc-9 align-top pt-2">
                    ANTICIPO: FACTURA NRO. {{$p->number}}
                </td>
            </tr>
            <tr>
                <td colspan="2" class="text-left desc-9 pb-2">
                    1 UND x -{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($p, 'total'), 2) }}
                </td>
                <td class="text-right desc-9 font-bold pb-2">-{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($p, 'total'), 2) }}</td>
            </tr>
            <tr>
                <td colspan="3" class="border-bottom"></td>
            </tr>
        @endforeach
    @endif

    </tbody>
</table>
{{-- ######## FIN DISEÑO COMPARTIDO DE FACTURA 80 MM ######## --}}
