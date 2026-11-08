<?php
$document_type_id = $document_type_id ?? null;
$type = $type ?? 'sale';
$plus = $plus ?? 4;
?>
<th style="width:{{$plus+2}}%;">FECHA DE EMISIÓN</th>
<th style="width:{{$plus+2}}%;">USUARIO /<br>VENDEDOR</th>
@if($document_type_id != '80' && $type == 'sale')
    <th style="width:2%;">PARRO-<br>QUIA</th>
    <th style="width:2%;">ESTA-<br>DO</th>
    <th style="width:2%;">MUNI-<br>CIPIO</th>
@endif
<th style="width:4%;">SERIE</th>
<th style="width:4%;">NÚMERO</th>
@if( $type == 'sale')
    <th style="width:4%;">ORDEN DE COMPRA</th>
    <th style="width:{{$plus+1}}%;">PLATA-<br>FORMA</th>
@endif
<th style="width:{{$plus+2}}%;">DOC ENTIDAD TIPO DNI RIF</th>
<th style="width:{{$plus+4}}%;">DOC ENTIDAD NÚMERO</th>
<th style="width:10%;">DENOMINACIÓN ENTIDAD</th>
<th style="width:{{$plus+1}}%;">MONE-<br>DA</th>
<th style="width:{{$plus+1}}%;">UNIDAD DE MEDIDA</th>
<th style="width:{{$plus+1}}%;">MARCA</th>
<th style="width:{{$plus+7}}%;">DESCRIPCIÓN</th>
{{-- @if($type == 'sale')
    se comenta porque genera inconsistencia en columnas
    <th style="width:{{$plus+1}}%;">MODELO</th>
@endif --}}
<th style="width:{{$plus+1}}%;">CATE-<br>GORÍA</th>
<th style="width:{{$plus+5}}%;">CANTI-<br>DAD</th>
<th style="width:5%;">PRECIO UNITARIO</th>



<th style="width:5%;">TOTAL</th>
@if($type == 'sale')
    <th style="width:6%;">COSTO ESTIMADO</th>
    <th style="width:7%;">GANANCIA ESTIMADA</th>
@endif
