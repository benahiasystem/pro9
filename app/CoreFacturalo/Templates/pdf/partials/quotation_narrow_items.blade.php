{{-- A narrow receipt cannot fit the six-column layout without shrinking the text. --}}
<table class="full-width ticket">
    <tbody>
    @foreach($document->items as $row)
        <tr><td class="border-top-bottom desc font-bold">PARTIDA {{ $loop->iteration }}</td></tr>
        @if($show_codigo ?? true)
            <tr><td class="desc">Código: {{ optional($row->item)->internal_id }}</td></tr>
        @endif
        @if($show_descripcion ?? true)
            <tr><td class="desc">
                @if($row->item->name_product_pdf ?? false)
                    {!! \App\CoreFacturalo\Helpers\Template\TemplateHelper::formatNameProductPdfForTicket($row->item->name_product_pdf ?? '') !!}
                @else
                    {!! $row->item->description !!}
                @endif
                @if(!empty($row->item->presentation)) {!! $row->item->presentation->description !!} @endif
                @if(($show_marca ?? false) && !empty($row->item->brand))<br>Marca: {{ $row->item->brand }}@endif
                @if(($show_modelo ?? false) && !empty($row->item->model))<br>Modelo: {{ $row->item->model }}@endif
                @foreach((array) $row->attributes as $attr)<br>{!! $attr->description !!}: {{ $attr->value }}@endforeach
                @if(($show_descuento ?? true) && $row->discounts)
                    @foreach(\App\Support\Exact\PdfAmounts::adjustments($row, 'discounts') as $discount)
                        @if(!($discount->from_global_distribution ?? false))
                            <br>{{ ($discount->is_amount ?? false) ? '' : \App\Support\Exact\PdfAmounts::adjustmentPercent($discount).'%' }} {{ $discount->description }}
                        @endif
                    @endforeach
                @endif
                @foreach(\App\Support\Exact\PdfAmounts::adjustments($row, 'charges') as $charge)
                    <br>{{ $document->currency_type->symbol }} {{ \App\Support\Exact\PdfAmounts::adjustmentAmount($charge) }} ({{ \App\Support\Exact\PdfAmounts::adjustmentPercent($charge) }}%) {{ $charge->description }}
                @endforeach
                @if($row->item->is_set == 1)
                    @inject('itemSet', 'App\Services\ItemSetService')
                    @foreach($itemSet->getItemsSet($row->item_id) as $item)<br>{{ $item }}@endforeach
                @endif
                @if($row->item !== null && property_exists($row->item, 'extra_attr_value') && $row->item->extra_attr_value != '')
                    <br>{{ $row->item->extra_attr_name }}: {{ $row->item->extra_attr_value }}
                @endif
                @if(($show_lote ?? true) && ($show_lot ?? false))<br>Lote: {{ $row->getSaleLotGroupCodeDescription() }}@endif
                @if(($show_fecha_vencimiento ?? true) && ($show_due ?? false))
                    <br>FV: {{ isset($row->relation_item->date_of_due) ? $row->relation_item->date_of_due->format('Y-m-d') : '-' }}
                @endif
            </td></tr>
        @endif
        @if($show_cantidad ?? true)
            <tr><td class="desc">Cantidad: {{ \App\Support\Exact\PdfQuantities::line($row) }} @if($show_unidad ?? true){{ $row->item->unit_type_id }}@endif</td></tr>
        @elseif($show_unidad ?? true)
            <tr><td class="desc">Unidad: {{ $row->item->unit_type_id }}</td></tr>
        @endif
        @if($show_precio_unitario ?? true)<tr><td class="desc">P. unitario: {{ $row->formatPdfUnitPrice() }}</td></tr>@endif
        @if($show_total ?? true)<tr><td class="desc font-bold">Importe: {{ $document->currency_type->symbol }} {{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($row, 'total'), 2) }}</td></tr>@endif
    @endforeach
    </tbody>
</table>
<table class="full-width ticket">
    <tbody>
    @foreach(['total_exportation'=>'OP. EXPORTACIÓN', 'total_free'=>'OP. GRATUITAS', 'total_unaffected'=>'Total no sujeto', 'total_exonerated'=>'Total exento', 'total_taxed'=>'Base imponible'] as $field => $label)
        @if($document->$field > 0)
            <tr><td class="desc text-right font-bold">{{ $label }}: {{ $document->currency_type->symbol }} {{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document, $field), 2) }}</td></tr>
        @endif
    @endforeach
    @if($document->total_discount_with_igv > 0)
        <tr><td class="desc text-right font-bold">{{ $document->total_prepayment > 0 ? 'ANTICIPO' : 'DESCUENTO TOTAL' }}: {{ $document->currency_type->symbol }} {{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document, 'total_discount_with_igv'), 2) }}</td></tr>
    @endif
    <tr><td class="desc text-right font-bold">IVA: {{ $document->currency_type->symbol }} {{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document, 'total_igv'), 2) }}</td></tr>
    <tr><td class="desc text-right font-bold">TOTAL A PAGAR: {{ $document->currency_type->symbol }} {{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document, 'total'), 2) }}</td></tr>
    @if($show_nro_producto ?? false)<tr><td class="desc font-bold">N° DE PRODUCTOS: {{ $document->items->count() }}</td></tr>@endif
    @if(($show_peso_total ?? false) && ($show_weight_attribute ?? false))<tr><td class="desc font-bold">Peso estimado: {{ $quotationWeight['display'] }}</td></tr>@endif
    @if(($show_tipo_persona ?? false) && ($person_type ?? null) && $person_type->enabled_description_person_type)
        <tr><td class="desc"><span class="font-bold">{{ $person_type->description }}:</span> {{ $person_type->description_person_type }}</td></tr>
    @endif
    </tbody>
</table>
