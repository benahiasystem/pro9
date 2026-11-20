{{-- The totals use the same frozen amounts and display budget as A4. --}}
<table class="full-width invoice-a5-summary" data-invoice-a5-summary>
    <tr>
        <td width="62%" style="vertical-align: top;">
            <p style="margin: 2px 0;"><strong>Productos: {{ \App\Support\Exact\InvoicePdfSummary::quantities($document->items) }}</strong></p>
            @if(!isset($showColumns) || ($showColumns['nro_producto'] ?? false))
            <p style="margin: 2px 0;"><strong>N° DE PRODUCTOS: {{ $document->items->count() }}</strong></p>
            @endif
            @php $personType = $document->person?->person_type; @endphp
            @if($personType?->enabled_description_person_type && (!isset($showColumns) || ($showColumns['tipo_persona'] ?? false)))
            <p style="margin: 2px 0;"><strong>{{ $personType->description }}:</strong> {{ $personType->description_person_type }}</p>
            @endif
            @foreach(array_reverse((array) $document->legends) as $legend)
                @if($legend->code == '1000')
                <p style="text-transform: uppercase; margin: 2px 0;"><strong>SON:</strong> {{ $legend->value }} {{ $document->currency_type->description }}</p>
                @else
                <p style="margin: 2px 0;">{{ $legend->code }}: {{ $legend->value }}</p>
                @endif
            @endforeach
        </td>
        <td width="38%" style="vertical-align: top;">
            <table class="full-width invoice-a5-totals">
                @foreach(['total_exportation'=>'OP. EXPORTACIÓN', 'total_unaffected'=>'OP. INAFECTAS', 'total_exonerated'=>'OP. EXONERADAS', 'total_free'=>'OP. GRATUITAS', 'total_taxed'=>'OP. GRAVADAS'] as $field=>$label)
                    @if(\App\Support\Exact\PdfAmounts::read($document, $field)->isPositive())
                    <tr><td class="text-right" style="white-space: nowrap; padding: 0 6px 0 0;"><strong>{{ $label }}: {{ $document->currency_type->symbol }}</strong></td><td class="text-right">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document, $field), 2) }}</td></tr>
                    @endif
                @endforeach
                <tr><td class="text-right" style="white-space: nowrap; padding: 0 6px 0 0;"><strong>IVA: {{ $document->currency_type->symbol }}</strong></td><td class="text-right">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document, 'total_igv'), 2) }}</td></tr>
                @if(\App\Support\Exact\PdfAmounts::read($document, 'subtotal')->isPositive())
                    @php
                        $hasDiscount = \App\Support\Exact\PdfAmounts::read($document, 'total_discount_with_igv')->isPositive();
                        $subtotal = \App\Support\Exact\PdfAmounts::read($document, 'subtotal');
                        if ($hasDiscount) $subtotal = $subtotal->plus(\App\Support\Exact\Decimal::of(\App\Support\Exact\Decimal::represent(\App\Support\Exact\PdfAmounts::read($document, 'total_discount_with_igv'), 2)));
                    @endphp
                    <tr><td class="text-right" style="white-space: nowrap; padding: 0 6px 0 0;"><strong>{{ $hasDiscount ? 'SUMA DE IMPORTES' : 'SUBTOTAL' }}: {{ $document->currency_type->symbol }}</strong></td><td class="text-right"><strong>{{ \App\Support\Exact\Decimal::format($subtotal, 2) }}</strong></td></tr>
                @endif
                @if(\App\Support\Exact\PdfAmounts::read($document, 'total_discount_with_igv')->isPositive())
                <tr><td class="text-right" style="white-space: nowrap; padding: 0 6px 0 0;"><strong>{{ \App\Support\Exact\PdfAmounts::read($document, 'total_prepayment')->isPositive() ? 'ANTICIPO' : 'DESCUENTO TOTAL' }}: {{ $document->currency_type->symbol }}</strong></td><td class="text-right">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document, 'total_discount_with_igv'), 2) }}</td></tr>
                @endif
                @if(\App\Support\Exact\PdfAmounts::read($document, 'total_charge')->isPositive())
                <tr><td class="text-right" style="white-space: nowrap; padding: 0 6px 0 0;"><strong>CARGOS: {{ $document->currency_type->symbol }}</strong></td><td class="text-right">{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document, 'total_charge'), 2) }}</td></tr>
                @endif
                @include('pdf.partials.invoice_igtf', ['invoiceTaxColspan'=>1])
                <tr><td class="text-right" style="white-space: nowrap; padding: 0 6px 0 0;"><strong>TOTAL A PAGAR: {{ $document->currency_type->symbol }}</strong></td><td class="text-right"><strong>{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\PdfAmounts::read($document, 'total'), 2) }}</strong></td></tr>
            </table>
        </td>
    </tr>
</table>
@include('pdf.partials.invoice_fiscal')
@php $compactInvoicePayments = !empty($invoicePdf['retentions']) || !empty($invoicePdf['forecasts']); @endphp
<table class="full-width" style="margin-top: 0;">
    <tr><td width="62%"><strong>CONDICIÓN DE PAGO:</strong> {{ \App\CoreFacturalo\Helpers\Template\TemplateHelper::getDocumentPaymentCondition($document) }}</td>
        @if($compactInvoicePayments)<td width="38%">Tasa de Cambio: {{ $invoicePdf['rate'] ? 'Bs. '.$invoicePdf['rate'] : 'No disponible' }}</td>@endif
    </tr>
</table>
@if($document->payment_method_type_id && $payments->isEmpty())
<table class="full-width"><tr><td><strong>MÉTODO DE PAGO:</strong> {{ $document->payment_method_type->description }}</td></tr></table>
@endif
@include('pdf.partials.invoice_payments', ['invoiceShowRate'=>!$compactInvoicePayments, 'invoiceSideInfo'=>$compactInvoicePayments])
@if((!$compactInvoicePayments || !$invoicePdf['receipts']) && isset($configurationInPdf) && $configurationInPdf->show_seller_in_pdf)
<table class="full-width"><tr><td><strong>Vendedor:</strong> {{ $document->seller ? $document->seller->name : $document->user->name }}</td></tr></table>
@endif
@if(in_array((string) $document->payment_condition_id, ['02', '03'], true))
<table class="full-width">
    @foreach($document->fee as $key=>$quote)
    <tr><td>&#8226; {{ $quote->getStringPaymentMethodType() ?: 'Cuota #'.($key + 1) }} / Fecha: {{ $quote->date->format('d-m-Y') }} / Monto: {{ $quote->currency_type->symbol }}{{ \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::read($quote, 'amount'), 2) }}</td></tr>
    @endforeach
</table>
@endif
<div class="invoice-a5-extras">
    @foreach($document->additional_information as $information)
        @if($information)
            @if($loop->first)<strong>INFORMACIÓN ADICIONAL</strong>@endif
            <p style="margin: 2px 0;">@if(\App\CoreFacturalo\Helpers\Template\TemplateHelper::canShowNewLineOnObservation()){!! \App\CoreFacturalo\Helpers\Template\TemplateHelper::SetHtmlTag($information) !!}@else{{ $information }}@endif</p>
        @endif
    @endforeach
    @if(isset($configurationInPdf) && $configurationInPdf->show_bank_accounts_in_pdf)
        @foreach($accounts as $account)
        <p style="margin: 2px 0;">{{ $account->bank->description }} {{ $account->currency_type->description }} N°: {{ $account->number }}</p>
        @endforeach
    @endif
    @if($document->terms_condition)
    <strong>TÉRMINOS Y CONDICIONES DEL SERVICIO</strong>
    {!! $document->terms_condition !!}
    @endif
</div>
