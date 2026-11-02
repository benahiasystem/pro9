{{-- ######## INICIO MIGRACIÓN MONEDA VENEZUELA ######## --}}
<?php
// ######## INICIO MONEDA DEL MEDIO DE COBRO ########
$currencyTotals = [];
$unknownCurrency = false;
// ######## FIN MONEDA DEL MEDIO DE COBRO ########
$i = 0
?>
    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type"
          content="application/pdf; charset=utf-8"/>
    <meta http-equiv="X-UA-Compatible"
          content="ie=edge">
    <title>Movimientos de ingresos y egresos</title>
    <style>
        html {
            font-family: sans-serif;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-spacing: 0;
            border: 1px solid black;
        }

        .celda {
            text-align: center;
            padding: 5px;
            border: 0.1px solid black;
        }

        th {
            padding: 5px;
            text-align: center;
            border-color: #0088cc;
            border: 0.1px solid black;
        }

        .title {
            font-weight: bold;
            padding: 5px;
            font-size: 20px !important;
            text-decoration: underline;
        }

        p > strong {
            margin-left: 5px;
            font-size: 13px;
        }

        thead {
            font-weight: bold;
            background: #0088cc;
            color: white;
            text-align: center;
        }
    </style>
</head>
<body>
<div>
    <p align="center"
       class="title"><strong>Movimientos de ingresos y egresos</strong></p>
</div>
<div style="margin-top:20px; margin-bottom:20px;">
    <table>
        <tr>
            <td>
                <p>@include('partials.report_company_header')</p>
            </td>
            <td>
                <p><strong>Fecha: </strong>{{date('Y-m-d')}}</p>
            </td>
        </tr>
        <tr>
            <td>
                <p><strong>RIF: </strong>{{$company->number}}</p>
            </td>
            <td>
                <p><strong>Establecimiento: </strong>{{$establishment->address}}
                    - {{$establishment->department->description}} - {{$establishment->district->description}}</p>
            </td>
        </tr>
    </table>
</div>
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
                    <th class="">Fecha</th>
                    <th class="">Adquiriente</th>
                    <th class="">N° Doc. Identidad</th>
                    <th class="">Tipo documento</th>
                    <th class="">Documento/Transacción</th>
                    <th class="">Detalle</th>
                    <th class="">Moneda</th>
                    <th class="">Destino</th>
                    <th class="">Cod.</th>
                    <th class="">Tipo</th>
                    <th class="">Ingresos</th>
                    <th class="">Gastos</th>
                    <th class="">Saldo</th>
                </tr>
                </thead>
                <tbody>
                @foreach($records as $value)
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
                    <td colspan="10" class="celda">Totales {{ $currency }}</td>
                    <td class="celda">{{ $symbol.\App\Support\Exact\Decimal::format($totals['input'], 2, '.', '') }}</td>
                    <td class="celda">{{ $symbol.\App\Support\Exact\Decimal::format($totals['output'], 2, '.', '') }}</td>
                    <td class="celda">{{ $symbol.\App\Support\Exact\Decimal::format($totals['input']->minus($totals['output']), 2, '.', '') }}</td>
                </tr>
                @endforeach
                @if($unknownCurrency)
                <tr><td colspan="13" class="celda">Los movimientos sin moneda no se incluyen en los totales.</td></tr>
                @endif
                </tfoot>
            </table>
        </div>
    </div>
@else
    <div class="callout callout-info">
        <p>No se encontraron registros.</p>
    </div>
@endif
</body>
</html>

{{-- ######## FIN MIGRACIÓN MONEDA VENEZUELA ######## --}}
