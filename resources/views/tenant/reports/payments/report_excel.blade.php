<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Content-Type" content="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Pagos</title>
    </head>
    <body>
        <div>
            <h3 align="center" class="title"><strong>Reporte Pagos</strong></h3>

        </div>
        <br>
        @if(!empty($records))
            <div class="">
                <div class=" ">
                    <table class="">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>RIF</th>
                                <th>Fecha</th>
                                <th>Factura</th>
                                <th class="">Nombre Comercial</th>

                                <th>Razon Social</th>
                                <th>Zona</th>
                                <th>Tipo de cliente</th>
                                <th>Departamento</th>
                                <th>Distrito</th>
                                {{-- ######## INICIO MONEDA DEL MEDIO DE COBRO ######## --}}
                                <th>Estado</th>
                                <th>Moneda del documento</th>
                                <th>Total del documento</th>
                                @for ($i = 1; $i <= $payment_count; $i++)
                                     <th>Pago aplicado {{$i}} (moneda del documento)</th>
                                @endfor
                                <th>Saldo (moneda del documento)</th>
                                @for ($i = 1; $i <= $payment_count; $i++)
                                     <th>Método {{$i}}</th>
                                     <th>Tipo {{$i}}</th>
                                     <th>Moneda recibida {{$i}}</th>
                                     <th>Importe recibido {{$i}}</th>
                                     <th>Tasa Bs./USD {{$i}}</th>
                                     <th>Referencia {{$i}}</th>
                                @endfor
                                {{-- ######## FIN MONEDA DEL MEDIO DE COBRO ######## --}}
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($records as $key => $value)
                            <tr>
                                <td class="celda" data-type="s">{{$loop->iteration}}</td>
                                <td class="celda" data-type="s">{{$value->ruc}}</td>
                                <td class="celda" data-type="s">
                                    {{$value->date}}
                                </td>
                                <td class="celda" data-type="s">{{$value->invoice }}</td>
                                <td class="celda" data-type="s">{{$value->comercial_name }}</td>


                                <td class="celda" data-type="s">{{$value->business_name }}</td>
                                <td class="celda" data-type="s">{{$value->zone }}</td>
                                <td class="celda" data-type="s">{{$value->person_type }}</td>
                                <td class="celda" data-type="s">{{$value->department }}</td>
                                <td class="celda" data-type="s">{{$value->district }}</td>
                                {{-- ######## INICIO MONEDA DEL MEDIO DE COBRO ######## --}}
                                <td class="celda" data-type="s">{{$value->status}}</td>
                                <td class="celda" data-type="s">{{$value->currency_type_id}}</td>
                                <td class="celda" data-type="s">{{$value->total}}</td>
                                @for ($i = 0; $i < $payment_count; $i++)
                                    <td class="celda" data-type="s">{{ isset($value->payments[$i]) ? \App\Support\Exact\Decimal::format($value->payments[$i]['applied_payment'], 2, '.', '') : '' }}</td>
                                @endfor
                                <td class="celda" data-type="s">{{$value->balance}}</td>
                                @for ($i = 0; $i < $payment_count; $i++)
                                    @php $payment = $value->payments[$i] ?? null; @endphp
                                    <td class="celda" data-type="s">{{ $payment['payment_method_type_description'] ?? '' }}</td>
                                    <td class="celda" data-type="s">{{ $payment['kind'] ?? '' }}</td>
                                    <td class="celda" data-type="s">{{ $payment['currency_type_id'] ?? '' }}</td>
                                    <td class="celda" data-type="s">{{ $payment && $payment['payment'] !== null ? \App\Support\Exact\Decimal::format($payment['payment'], 2, '.', '') : '' }}</td>
                                    <td class="celda" data-type="s">{{ $payment['exchange_rate'] ?? '' }}</td>
                                    <td class="celda" data-type="s">{{ $payment['reference'] ?? '' }}</td>
                                @endfor
                                {{-- ######## FIN MONEDA DEL MEDIO DE COBRO ######## --}}

                            </tr>
                            @endforeach
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
