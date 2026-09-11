<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Content-Type" content="application/pdf; charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Productos por Agotarse</title>
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
                font-size: 13px;
            }

            thead {
                font-weight: bold;
                background: #0088cc;
                color: white;
                text-align: center;
            }

            .badge-danger {
                background: #dc3545;
                color: white;
                padding: 2px 8px;
                border-radius: 4px;
                font-size: 10px;
            }

            .badge-warning {
                background: #ffc107;
                color: #333;
                padding: 2px 8px;
                border-radius: 4px;
                font-size: 10px;
            }
        </style>
    </head>
    <body>
        <div>
            <p align="center" class="title"><strong>Reporte Productos por Agotarse</strong></p>
        </div>
        <div style="margin-top:20px; margin-bottom:20px;">
            <table>
                <tr>
                    <td>
                        <p><strong>Empresa: </strong>{{$company->name}}</p>
                    </td>
                    <td>
                        <p><strong>Fecha: </strong>{{date('Y-m-d')}}</p>
                    </td>
                </tr>
                <tr>
                    <td>
                        <p><strong>Ruc: </strong>{{$company->number}}</p>
                    </td>
                    <td>
                        <p><strong>Establecimiento: </strong>{{$establishment->address}} - {{$establishment->department->description}} - {{$establishment->district->description}}</p>
                    </td>
                </tr>
            </table>
        </div>
        @if(!empty($records) && count($records) > 0)
            <div class="">
                <div class="">
                    <table class="">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Cód. Interno</th>
                                <th>Producto</th>
                                <th>Stock</th>
                                <th>Estado</th>
                                <th>Almacén</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($records as $key => $value)
                                <tr>
                                    <td class="celda">{{$loop->iteration}}</td>
                                    <td class="celda">{{ optional($value->item)->internal_id }}</td>
                                    <td class="celda">{{ optional($value->item)->description }}</td>
                                    <td class="celda">{{ number_format($value->stock, 2) }}</td>
                                    <td class="celda">
                                        @if($value->stock <= 0)
                                            <span class="badge-danger">Agotado</span>
                                        @else
                                            <span class="badge-warning">Pocas unidades</span>
                                        @endif
                                    </td>
                                    <td class="celda">{{ optional($value->warehouse)->description }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <br>
                    <table>
                        <tr>
                            <td><strong>Total de productos:</strong> {{ count($records) }}</td>
                            <td><strong>Agotados:</strong> {{ $records->where('stock', '<=', 0)->count() }}</td>
                            <td><strong>Pocas unidades:</strong> {{ $records->where('stock', '>', 0)->count() }}</td>
                        </tr>
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