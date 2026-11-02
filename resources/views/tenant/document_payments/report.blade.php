<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Content-Type" content="application/pdf; charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Reporte de Pagos</title>
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

            p>strong {
                margin-left: 5px;
                font-size: 12px;
            }

            thead {
                font-weight: bold;
                background: #0088cc;
                color: white;
                text-align: center;
            }
            .td-custom { line-height: 0.1em; }
        </style>
    </head>
    <body>
        <div>
            <p align="center" class="title"><strong>Reporte de Pagos</strong></p>
        </div>
        <div style="margin-top:20px; margin-bottom:20px;">

        </div>
        @if($records->count())
            <div class="">
                <div class=" ">
                    <table class="">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Cliente</th>
                                <th>Tipo Comprobante</th>
                                <th>Método de pago</th>
                                <th>Destino</th>
                                <th>Referencia</th>
                                {{-- ######## INICIO MONEDA DEL MEDIO DE COBRO ######## --}}
                                <th>Tipo de aplicación</th>
                                <th>Estado</th>
                                <th>Vuelto</th>
                                <th>Moneda recibida</th><th>Importe recibido</th><th>Tasa Bs./USD</th>
                                <th>Moneda del documento</th><th>Importe aplicado</th>
                                <th>Total del documento</th>
                                {{-- ######## FIN MONEDA DEL MEDIO DE COBRO ######## --}}
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>

                            @foreach($records as $item)
                                <tr>
                                    <td class="celda">{{ $loop->iteration }}</td>
                                    <td class="celda">{{ $item['customer'] }}</td>
                                    <td class="celda">{{ $item['number'] }}</td>
                                    <td class="celda">{{ $item['payment_method_type_description'] }}</td>
                                    <td class="celda">{{ $item['destination_description'] }}</td>
                                    <td class="celda">{{ $item['reference'] }}</td>
                                    {{-- ######## INICIO MONEDA DEL MEDIO DE COBRO ######## --}}
                                    <td class="celda">{{ $item['kind'] }}</td>
                                    <td class="celda">{{ $item['status'] }}</td>
                                    <td class="celda">{{ $item['change'] ?? 'No disponible' }}</td>
                                    <td class="celda">{{ $item['currency_type_id'] ?? 'No disponible' }}</td><td class="celda">{{ $item['payment'] ?? 'No disponible' }}</td><td class="celda">{{ $item['exchange_rate'] ?? 'No disponible' }}</td><td class="celda">{{ $item['document_currency_type_id'] }}</td><td class="celda">{{ $item['applied_payment'] }}</td>
                                    <td class="celda">{{ $item['total'] }}</td>
                                    {{-- ######## FIN MONEDA DEL MEDIO DE COBRO ######## --}}
                                    <td class="celda">{{ $item['date_of_payment'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
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
