{{-- ######## INICIO NUMERACIÓN FISCAL VENEZUELA ######## --}}
@php \App\Services\Fiscal\FiscalIdentity::preload($records); @endphp
{{-- ######## FIN NUMERACIÓN FISCAL VENEZUELA ######## --}}
{{-- ######## INICIO MIGRACIÓN MONEDA VENEZUELA ######## --}}
<?php
    use App\Models\Tenant\Document;
    use App\CoreFacturalo\Helpers\Template\TemplateHelper;
    use App\Models\Tenant\SaleNote;

    $enabled_sales_agents = App\Models\Tenant\Configuration::getRecordIndividualColumn('enabled_sales_agents');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type"
          content="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8"/>
    <meta http-equiv="X-UA-Compatible"
          content="ie=edge">
    <title>Document</title>
</head>
<body>
<div>
    <h3 align="center"
        class="title"><strong>Reporte Documentos</strong></h3>
</div>
<br>
<div style="margin-top:20px; margin-bottom:15px;">
    <table>
        <tr>
            <td>
                <p><b>Empresa: </b></p>
            </td>
            <td align="center">
                <p>@include('partials.report_company_header', ['showEmpresaLabel' => false])</p>
            </td>
            <td>
                <p><strong>Fecha: </strong></p>
            </td>
            <td align="center">
                <p><strong>{{date('Y-m-d')}}</strong></p>
            </td>
        </tr>
        <tr>
            <td>
                <p><strong>RIF: </strong></p>
            </td>
            <td align="center">{{$company->number}}</td>
            <td>
                <p><strong>Establecimiento: </strong></p>
            </td>
            <td align="center">{{$establishment->address}} - {{$establishment->department->description}}
                                                           - {{$establishment->district->description}}</td>
        </tr>
        @inject('reportService', 'Modules\Report\Services\ReportService')
        <tr>
            @if($filters['seller_id'])
                <td>
                    <p><strong>Usuario: </strong></p>
                </td>
                <td align="center">
                    {{$reportService->getUserName($filters['seller_id'])}}
                </td>
            @endif
            @if($filters['person_id'])
                <td>
                    <p><strong>Cliente: </strong></p>
                </td>
                <td align="center">
                    {{$reportService->getPersonName($filters['person_id'])}}
                </td>
            @endif
        </tr>
    </table>
</div>
<br>
<p>Los importes por categoría corresponden a partidas, sin ajustes globales de cabecera. Las cantidades se agrupan por unidad.</p>
@if($records->isNotEmpty())
    <div class="">
        <div class=" ">
            @php
                $currencyTotals = \Modules\Report\Services\DocumentCurrencyTotals::empty();
                        $displayTotals = \Modules\Report\Services\DocumentCurrencyTotals::empty();
                $serie_affec = '';
            @endphp
            <table class="">
                <thead>
                <tr>
                    <th>#</th>
                    <th class="">Usuario/Vendedor</th>
                    <th>Tipo Doc</th>
                    <th>Número</th>
                    <th>Fecha emisión</th>
                    <th>Fecha Vencimiento</th>
                    <th>Doc. Afectado</th>
                    <th># Orden de entrega</th>
                    <th>Cotización</th>
                    <th>Caso</th>

                    <th>DIST</th>
                    <th>DPTO</th>
                    <th>PROV</th>

                    <th>Direccion de cliente</th>
                    <th>Tipo de Cliente</th>
                    <th>Cliente</th>
                    <th>RIF</th>
                    <th>Estado</th>
                    <th class="">Moneda</th>
                    <th>Plataforma</th>
                    <th>Orden de compra</th>

                    <th>Nota de venta</th>
                    <th>Fecha N. Venta</th>

                    <th class="">Forma de pago</th>
                    <th> MÉTODO DE PAGO </th>
                    <th>TC</th>
                    <th>Total Cargos</th>
                    <th>Total exento</th>


                    <th>Base imponible</th>
                    <th>Descuento total</th>
                    {{-- ########## INICIO CAMBIO IGV A IVA --}}
                    <th>Total IVA</th>
                    {{-- ######### FIN CAMBIO IGV A IVA --}}
                    {{-- ########## INICIO SIN DETRACCIONES E ISC --}}

                    {{-- ######### FIN SIN DETRACCIONES E ISC --}}
                    <th>Total</th>
                    <th>Cantidades por unidad</th>

                    @foreach ($categories as $category)
                        <th>{{$category->name}} (importe de partidas)</th>
                    @endforeach

                    @foreach ($categories_services as $category)
                        <th>{{$category->name}} (cantidad de servicios)</th>
                    @endforeach

                    @if ($enabled_sales_agents)
                        <th>Agente</th>
                        <th>Datos de referencia</th>
                    @endif

                    <th>Placa</th>

                </tr>
                </thead>
                <tbody>
                @foreach($records as $key => $value)
                    <?php
                    /** @var \App\Models\Tenant\Document|App\Models\Tenant\SaleNote  $value */
                    $iteration = $loop->iteration;
                                    $userCreator = $value->user->name;
                    $document_type = $value->getDocumentType();
                    $seller = \App\CoreFacturalo\Helpers\Template\ReportHelper::getSellerData($value);
                    try{
                        $user = $seller->name;
                    }catch (ErrorException $e){
                        $user = '';
                    }

                    ?>

                    ?>
                    <tr>
                        <td class="celda">{{$iteration}}</td>
                        <td class="celda">
                            @if($filters['user_type']==='CREADOR')
                                {{$userCreator}}
                            @else
                                {{$user}}
                            @endif
                        </td>
                        <td class="celda">{{$document_type->id}}</td>
                        <td class="celda">{{$value->number_full}}@include('partials.fiscal_report_control', ['value' => $value])</td>
                        <td class="celda">{{$value->date_of_issue->format('Y-m-d')}}</td>
                        <td class="celda">{{isset($value->invoice) ? $value->invoice->date_of_due->format('Y-m-d'):''}}</td>
                        @if(in_array($document_type->id,["07","08"]) && $value->note)

                            @php
                                $serie = ($value->note->affected_document) ? $value->note->affected_document->series : $value->note->data_affected_document->series;
                                $number =  ($value->note->affected_document) ? $value->note->affected_document->number : $value->note->data_affected_document->number;
                                $serie_affec = $serie.' - '.$number;

                            @endphp


                        @endif
                        <td class="celda">{{$serie_affec }} </td>
                        <td class="celda">
                            @if(!empty($value->guides))
                                @foreach($value->guides as $guide)
                                    {{ $guide->number }}<br>
                                @endforeach
                            @endif
                        </td>
                        <td class="celda">{{ ($value->quotation) ? $value->quotation->number_full : '' }}</td>
                        <td class="celda">{{ isset($value->quotation->sale_opportunity) ? $value->quotation->sale_opportunity->number_full : '' }}</td>

                        <?php $stablihsment = \App\CoreFacturalo\Helpers\Template\ReportHelper::getLocationData($value); ?>
                        <td class="celda">{{$stablihsment['district']}}</td>
                        <td class="celda">{{$stablihsment['department']}}</td>
                        <td class="celda">{{$stablihsment['province']}}</td>

                        <td class="celda">{{$value->customer->address}}</td>
                        @php
                            $customer = \App\Models\Tenant\Person::find($value->customer_id);
                        @endphp
                        <td class="celda">{{ optional($customer->person_type)->description}}</td>
                        <td class="celda">{{$value->customer->name}}</td>
                        <td class="celda">{{format_person_identity_document($value->customer)}}</td>
                        <td class="celda">{{$value->state_type->description}}</td>

                        @php
                            $signal = $document_type->id;
                            $state = $value->state_type_id;
                        @endphp

                        <td class="celda">{{$value->currency_type_id}}</td>
                        <td class="celda">
                            @foreach ($value->getPlatformThroughItems() as $platform)
                                <label class="d-block">{{$platform->name}}</label>
                            @endforeach
                        </td>
                        <td class="celda">{{$value->purchase_order}}</td>

                        @if($value->sale_note)
                            <td class="celda">{{ $value->sale_note->number_full }}</td>
                            <td class="celda">{{ $value->sale_note->date_of_issue->format('Y-m-d') }}</td>
                        @else
                            <td class="celda"></td>
                            <td class="celda"></td>
                        @endif

                        <td class="celda">
                            {{ \App\Services\Payments\ReceiptPresentation::firstMethod($value->payments)}}
                        </td>
                        <td class="celda">
                            @php
                            $payments= [];
                            if(
                                get_class($value) == Document::class ||
                                get_class($value) == SaleNote::class
                            ){
                                $payments = TemplateHelper::getDetailedPayment($value);
                            }
                            @endphp

                            @foreach ($payments as $payment)
                                @foreach ($payment as $pay)
                                    {{ $pay['display'] ?? $pay['description'] }}
                                    @if ($loop->count > 1 && !$loop->last)
                                        <br>
                                    @endif
                                @endforeach
                            @if (!$loop->last)<br>@endif
@endforeach

                        </td>
                        <td>{{ $value->exchange_rate_sale }}</td>



                        <td class="celda" data-type="s" style="mso-number-format:'\@'">{{ \App\Support\Exact\Decimal::format(\Modules\Report\Services\DocumentReportLines::monetary($value, 'total_charge'),2,'.','') }}</td>
                        <td class="celda" data-type="s" style="mso-number-format:'\@'">{{ \App\Support\Exact\Decimal::format(\Modules\Report\Services\DocumentReportLines::monetary($value, 'total_exonerated'),2,'.','') }}</td>
                        <td class="celda" data-type="s" style="mso-number-format:'\@'">{{ \App\Support\Exact\Decimal::format(\Modules\Report\Services\DocumentReportLines::monetary($value, 'total_taxed'),2,'.','') }}</td>
                        <td class="celda" data-type="s" style="mso-number-format:'\@'">{{ \App\Support\Exact\Decimal::format(\Modules\Report\Services\DocumentReportLines::monetary($value, 'total_discount'),2,'.','') }}</td>
                        <td class="celda" data-type="s" style="mso-number-format:'\@'">{{ \App\Support\Exact\Decimal::format(\Modules\Report\Services\DocumentReportLines::monetary($value, 'total_igv'),2,'.','') }}</td>
                        <td class="celda" data-type="s" style="mso-number-format:'\@'">{{ \App\Support\Exact\Decimal::format(\Modules\Report\Services\DocumentReportLines::monetary($value, 'total'),2,'.','') }}</td>

                        <td data-type="s">{{ \Modules\Report\Services\DocumentReportLines::quantityText($value) }}</td>
                        @foreach ($categories as $category)
                            <td data-type="s" style="mso-number-format:'\@'">{{ \App\Support\Exact\Decimal::format(\Modules\Report\Services\DocumentReportLines::categoryAmount($value, $category->id),2,'.','') }}</td>
                        @endforeach
                        @foreach ($categories_services as $category)
                            <td data-type="s" style="mso-number-format:'\@'">{{ \App\Support\Exact\Decimal::represent(\Modules\Report\Services\DocumentReportLines::serviceQuantity($value, $category->id)) }}</td>
                        @endforeach
                        @php $serie_affec = ''; @endphp

                        @if ($enabled_sales_agents)
                            <td>{{optional($value->agent)->search_description}}</td>
                            <td>{{$value->reference_data}}</td>
                        @endif

                        <td>{{$value->getPlateNumberSaleReport() }}</td>

                    </tr>
                    @php
                        $currencyTotals = \Modules\Report\Services\DocumentCurrencyTotals::add($currencyTotals, $value);
                            $displayTotals = \Modules\Report\Services\DocumentCurrencyTotals::addDisplayed($displayTotals, $value);
                        @endphp
                @endforeach
                @foreach (['VES','USD'] as $currency)
                    @if (\Modules\Report\Services\DocumentCurrencyTotals::hasAdjustment($currencyTotals,$displayTotals,$currency,['total_charge','total_exonerated','total_taxed','total_igv','total']))
                    <tr><td colspan="23"></td><td></td><td colspan="2">Ajuste por redondeo {{ $currency }}</td><td data-type="s" style="mso-number-format:'\@'">{{ \Modules\Report\Services\DocumentCurrencyTotals::adjustment($currencyTotals,$displayTotals,$currency,'total_charge') }}</td> <td data-type="s" style="mso-number-format:'\@'">{{ \Modules\Report\Services\DocumentCurrencyTotals::adjustment($currencyTotals,$displayTotals,$currency,'total_exonerated') }}</td> <td data-type="s" style="mso-number-format:'\@'">{{ \Modules\Report\Services\DocumentCurrencyTotals::adjustment($currencyTotals,$displayTotals,$currency,'total_taxed') }}</td> <td></td> <td data-type="s" style="mso-number-format:'\@'">{{ \Modules\Report\Services\DocumentCurrencyTotals::adjustment($currencyTotals,$displayTotals,$currency,'total_igv') }}</td> <td data-type="s" style="mso-number-format:'\@'">{{ \Modules\Report\Services\DocumentCurrencyTotals::adjustment($currencyTotals,$displayTotals,$currency,'total') }}</td></tr>
                    @endif
                @endforeach
                <tr>
                    <td colspan="23"></td>
                    <td></td>
                    <td colspan="2">Totales VES</td>
                    <td data-type="s" style="mso-number-format:'\@'">{{\App\Support\Exact\Decimal::format($currencyTotals['VES']['total_charge'])}}</td>
                    <td data-type="s" style="mso-number-format:'\@'">{{\App\Support\Exact\Decimal::format($currencyTotals['VES']['total_exonerated'])}}</td>


                    <td data-type="s" style="mso-number-format:'\@'">{{\App\Support\Exact\Decimal::format($currencyTotals['VES']['total_taxed'])}}</td>
                    <td></td>
                    <td data-type="s" style="mso-number-format:'\@'">{{\App\Support\Exact\Decimal::format($currencyTotals['VES']['total_igv'])}}</td>
                    <td data-type="s" style="mso-number-format:'\@'">{{\App\Support\Exact\Decimal::format($currencyTotals['VES']['total'])}}</td>
                </tr>
                @unless(\App\Services\SalesDocumentCurrency::usesBolivars())
                <tr>
                    <td colspan="23"></td>
                    <td></td>
                    <td colspan="2">Totales USD</td>
                    <td data-type="s" style="mso-number-format:'\@'">{{\App\Support\Exact\Decimal::format($currencyTotals['USD']['total_charge'])}}</td>
                    <td data-type="s" style="mso-number-format:'\@'">{{\App\Support\Exact\Decimal::format($currencyTotals['USD']['total_exonerated'])}}</td>
                    <td data-type="s" style="mso-number-format:'\@'">{{\App\Support\Exact\Decimal::format($currencyTotals['USD']['total_taxed'])}}</td>
                    <td></td>
                    <td data-type="s" style="mso-number-format:'\@'">{{\App\Support\Exact\Decimal::format($currencyTotals['USD']['total_igv'])}}</td>
                    <td data-type="s" style="mso-number-format:'\@'">{{\App\Support\Exact\Decimal::format($currencyTotals['USD']['total'])}}</td>
                </tr>
                @endunless

                </tbody>
            </table>
        </div>
    </div>
@else
    <div>
        <p>No se encontraron registros.</p>
    </div>
@endif
</body>
</html>

{{-- ######## FIN MIGRACIÓN MONEDA VENEZUELA ######## --}}
