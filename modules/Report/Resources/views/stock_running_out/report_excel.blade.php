<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Content-Type" content="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Productos por Agotarse</title>
    </head>
    <body>
        <div>
            <h3 align="center" class="title"><strong>Reporte Productos por Agotarse</strong></h3>
        </div>
        <br>
        <div style="margin-top:20px; margin-bottom:15px;">
            <table>
                <tr>
                    <td>
                        <p><b>Empresa: </b></p>
                    </td>
                    <td align="center">
                        <p><strong>{{$company->name}}</strong></p>
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
                        <p><strong>Ruc: </strong></p>
                    </td>
                    <td align="center">{{$company->number}}</td>
                    <td>
                        <p><strong>Establecimiento: </strong></p>
                    </td>
                    <td align="center">{{$establishment->address}} - {{$establishment->department->description}} - {{$establishment->district->description}}</td>
                </tr>
            </table>
        </div>
        <br>
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
                                            Agotado
                                        @else
                                            Pocas unidades
                                        @endif
                                    </td>
                                    <td class="celda">{{ optional($value->warehouse)->description }}</td>
                                </tr>
                            @endforeach

                            <tr>
                                <td colspan="3"></td>
                                <td><strong>Total productos: {{ count($records) }}</strong></td>
                                <td><strong>Agotados: {{ $records->where('stock', '<=', 0)->count() }}</strong></td>
                                <td><strong>Pocas und.: {{ $records->where('stock', '>', 0)->count() }}</strong></td>
                            </tr>
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