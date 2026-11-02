{{-- ######## INICIO MIGRACIÓN MONEDA VENEZUELA ######## --}}
<?php
// ######## INICIO MONEDA DEL MEDIO DE COBRO ########
$currencyTotals = [];
$unknownCurrency = false;
// ######## FIN MONEDA DEL MEDIO DE COBRO ########
$i = 0
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
    {{-- <title>Movimientos de ingresos y egresos</title> --}}
</head>
<body>
<div>
    <h3 align="center"
        class="title"><strong>Movimientos de ingresos y egresos</strong></h3>
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
    </table>
</div>
<br>
{{-- ######## INICIO CLASIFICACION DE COBROS Y CAJA ######## --}}
<p>El saldo se acumula por moneda en orden de registro dentro del período consultado.</p>
{{-- ######## FIN CLASIFICACION DE COBROS Y CAJA ######## --}}
@if(!empty($records))
    <div class="">
        <div class=" ">
            <table class="">
                <thead>
                <tr>
                    <!--                    <th class="">#</th>-->
                    {{-- ######## INICIO CALCULOS EXACTOS PRO9 ######## --}}
                    <th class="">ID movimiento</th>
                    {{-- ######## FIN CALCULOS EXACTOS PRO9 ######## --}}
                    <th class="">Fecha</th>
                    <th class="">Adquiriente</th>
                    <th class="">N° Doc. Identidad</th>
                    <th class="">Tipo documento</th>
                    <th class="">Documento/Transacción</th>
                    <th class="">Detalle</th>
                    <th class="">Moneda</th>
                    <th class="">Referencia</th>
                    <th class="">Destino</th>
                    <th class="">Cod.</th>
                    <th class="">Tipo</th>
                    <th class="">Ingresos</th>
                    <th class="">Gastos</th>
                    <th class="">Saldo</th>
                </tr>
                </thead>
                <tbody>
                @foreach($records as $key => $value)
                    <tr>
                    <?php
                    // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
                    $i++;
                    $document_type = $value['document_type'] ?? '';
                    $destination_array = $value['destination_array'] ?? [];
                    $destination_name = $value['destination_name'] ?? '';
                    $reference = $value['reference'] ?? '';
                    $items = $value['items'] ?? [];
                    $currency = $value['currency_type_id'];
                    $symbol = $currency === 'VES' ? 'Bs.' : ($currency === 'USD' ? '$' : '');
                    $type_movement = $value['type_movement'];
                    $fraction = $value['calculation_snapshot']['values']['amount'] ?? ($value['payments'] ?? null);
                    $amount = $fraction === null ? null : \App\Support\Exact\Decimal::of($fraction);
                    if ($amount !== null && in_array($currency, ['VES', 'USD'], true)) {
                        if (!isset($currencyTotals[$currency])) $currencyTotals[$currency] = ['input' => \App\Support\Exact\Decimal::of(0), 'output' => \App\Support\Exact\Decimal::of(0)];
                        $currencyTotals[$currency][$type_movement] = $currencyTotals[$currency][$type_movement]->plus($amount);
                    } else { $unknownCurrency = true; }
                    $formatted = $amount === null ? '-' : \App\Support\Exact\Decimal::format($amount, 2, '.', '');
                    // ######## FIN MONEDA DEL MEDIO DE COBRO ########
                    ?>


                    <!--                        <td class="celda">{{$i}}</td>-->
                        {{-- ######## INICIO CALCULOS EXACTOS PRO9 ######## --}}
                        <td class="celda">{{$value['id'] ?? '-'}}</td>
                        {{-- ######## FIN CALCULOS EXACTOS PRO9 ######## --}}
                        <td class="celda">{{$value['date_of_payment']}}</td>
                        <td class="celda">{{$value['person_name']}}</td>
                        <td class="celda">{{$value['person_number']}}</td>
                        <td class="celda">{{ $document_type }}</td>
                        <td class="celda">{{$value['number_full']}}</td>
                        <td class="celda">
                            @foreach ($items as $item)
                                <p>- {{ $item['description'] }}</p>
                            @endforeach
                        </td>
                        <td class="celda">{{ $value['currency_type_id'] ?: 'Sin moneda' }}</td>
                        <td class="celda">{{ $reference }}</td>
                        <td class="celda">{{ $destination_name }}</td>
                        <td class="celda"> {{$destination_array['cci'] . " "}} &nbsp;</td>
                        <td class="celda">{{$value['instance_type_description']}}</td>

                        <td class="celda">{{ $type_movement === 'input' ? $symbol.$formatted : '-' }}</td>
                        <td class="celda">{{ $type_movement === 'output' ? $symbol.$formatted : '-' }}</td>
                        <td class="celda">{{ $symbol.$value['balance'] }}</td>
                    </tr>


                @endforeach
                </tbody>
                <tfoot>
                @foreach($currencyTotals as $currency => $totals)
                @php $symbol = $currency === 'VES' ? 'Bs.' : '$'; @endphp
                <tr>
                    <td colspan="12" class="celda">Totales {{ $currency }}</td>
                    <td class="celda">{{ $symbol.\App\Support\Exact\Decimal::format($totals['input'], 2, '.', '') }}</td>
                    <td class="celda">{{ $symbol.\App\Support\Exact\Decimal::format($totals['output'], 2, '.', '') }}</td>
                    <td class="celda">{{ $symbol.\App\Support\Exact\Decimal::format($totals['input']->minus($totals['output']), 2, '.', '') }}</td>
                </tr>
                @endforeach
                @if($unknownCurrency)
                <tr><td colspan="15" class="celda">Los movimientos sin moneda no se incluyen en los totales.</td></tr>
                @endif
                </tfoot>
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
