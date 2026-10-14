<?php
namespace App\Services\Fiscal;

/** Frozen delivery snapshot -> HKA 04. A dispatch does not create invoice payments or taxes. */
final class HkaDispatchPayloadBuilder
{
    public function build(array $s,string $operation): array
    {
        $buyer=$s['customer'] ?: $s['issuer'];
        $code=self::identity($buyer);
        $id=['tipoDocumento'=>'04','serie'=>$s['series'],'numeroDocumento'=>(string)$s['number'],
            'fechaEmision'=>\Carbon\Carbon::parse($s['date_of_issue'])->format('d/m/Y'),'horaEmision'=>\Carbon\Carbon::parse($s['time_of_issue'])->format('h:i:s a'),
            'tipoTransaccion'=>'01','tipoDeVenta'=>'Interna','moneda'=>'VES','sucursal'=>(string)($s['establishment']['code'] ?? ''),'transaccionId'=>HkaPayloadBuilder::transactionId($operation)];
        $guide=['esGuiaDespacho'=>'1','motivoTraslado'=>$s['reason_description'],'pesoOVolumenTotal'=>number_format($s['total_weight'],2,'.','').' '.$s['unit_type_id']];
        $data=$s['hka_data'] ?? [];
        if ($s['transfer_reason_type_id']==='21') {
            if (!trim($data['service_description'] ?? '')) FiscalAmounts::error('hka_data.service_description','Describa el servicio de reparación o perfeccionamiento.');
            $guide['descripcionServicio']=$data['service_description'];
        }
        foreach (['product_type'=>'tipoProducto','product_origin'=>'origenProducto','taxation_product'=>'destinoProducto'] as $key=>$field) if (!empty($s['catalogs'][$key])) $guide[$field]=$s['catalogs'][$key];
        if ($d=$s['driver'] ?? null) $guide['conductor']=['nombreCompleto'=>$d['name'],'tipoIdentificacion'=>self::identity($d),'numeroIdentificacion'=>self::number($d),'tipoLicencia'=>$d['license'],'infoContacto'=>$d['telephone']];
        if ($v=$s['transport_data'] ?? null) $guide['vehiculo']=['tipoVehiculo'=>trim($v['brand'].' '.$v['model']),'numeroTransporte'=>$v['tuc'] ?: $v['plate_number'],'numeroPlaca'=>$v['plate_number']];
        $carrier=$s['dispatcher'] ?: $s['issuer'];
        $guide['transportista']=['razonSocial'=>$carrier['name'],'numeroIdentificacion'=>self::identity($carrier).self::number($carrier),'domicilioFiscal'=>$carrier['address'] ?? $s['issuer']['address']];
        $buyerAddress=trim($buyer['address'] ?? '');
        $header=['identificacionDocumento'=>$id,'comprador'=>['tipoIdentificacion'=>$code,'numeroIdentificacion'=>self::number($buyer),'razonSocial'=>$buyer['name'],'direccion'=>$buyerAddress && $buyerAddress!=='-' ? $buyerAddress : $s['delivery']['address'],'pais'=>'VE','notificar'=>'No']];
        if (!empty($s['invoice_identity'])) $header['facturaGuia']=[$s['invoice_identity']];
        $rows=[];
        foreach ($s['items'] as $index=>$row) {
            $unit=$row['item']['hka_unit_code'] ?? null;
            if (!$unit) FiscalAmounts::error('items','Falta la equivalencia de unidad HKA conservada.');
            // Real DEMO 2026-10-13: quantity-only guides accept 0 unit price and no Totales node.
            $line=['numeroLinea'=>(string)($index+1),'indicadorBienoServicio'=>'1','descripcion'=>$row['name_product_pdf'] ?: $row['item']['description'],
                'cantidad'=>number_format($row['quantity'],4,'.',''),'unidadMedida'=>$unit,'precioUnitario'=>'0.0000','precioItem'=>'0.00'];
            if ((float)($row['item']['unit_price'] ?? 0)>0) {
                $valuation=$row['item']['valuation'] ?? null;
                if (!$valuation || !in_array($valuation['iva_rate']['code'] ?? null,['G','R','A','E'],true)) FiscalAmounts::error('items','Falta la valorización conservada de la orden.');
                $line['precioUnitario']=number_format($valuation['unit_value'],4,'.','');
                $line['precioItem']=number_format($valuation['total_value'],2,'.','');
                $line+=['codigoImpuesto'=>$valuation['iva_rate']['code'],'tasaIVA'=>number_format($valuation['percentage_igv'],2,'.',''),
                    'valorIVA'=>number_format($valuation['total_igv'],2,'.',''),'valorTotalItem'=>number_format($valuation['total'],2,'.','')];
            }
            $rows[]=$line;
        }
        $document=['encabezado'=>$header,'guiaDespacho'=>$guide,'transporte'=>['tipo'=>$s['transport_mode_type_id']==='02' ? 'Privado':'Público','origen'=>$s['origin']['address'],'destino'=>$s['delivery']['address'],'fechaSalida'=>\Carbon\Carbon::parse($s['date_of_shipping'])->format('d/m/Y')],'detallesItems'=>$rows];
        $additional=[];
        if (!empty($s['transfer_reason_description'])) $additional[]=['campo'=>'Detalle del motivo','valor'=>$s['transfer_reason_description']];
        foreach (['secondary_drivers'=>'Conductor adicional','secondary_transports'=>'Vehículo adicional'] as $key=>$label) foreach ($s[$key] ?? [] as $index=>$extra) $additional[]=['campo'=>$label.' '.($index+1),'valor'=>$key==='secondary_drivers' ? implode(' / ',array_filter([$extra['name'],self::identity($extra).'-'.self::number($extra),$extra['license'],$extra['telephone']])) : implode(' / ',array_filter([$extra['plate_number'],$extra['brand'],$extra['model'],$extra['tuc']]))];
        if ($additional) $document['infoAdicional']=$additional;
        $payload=['documentoElectronico'=>$document];(new HkaSchemaValidator)->validate($payload);return $payload;
    }

    public static function identity(array $person): string
    {
        foreach (\App\Support\Venezuela\IdentityDocument::TYPES as $type) if ($type['id']===($person['identity_document_type_id'] ?? null) && $type['code']) return $type['code'];
        if (preg_match('/^([VJEGCRP])/', $person['number'] ?? '',$match)) return $match[1];
        FiscalAmounts::error('identity','La identidad no tiene equivalencia HKA para la orden.');
    }
    public static function number(array $person): string { return preg_replace('/[^0-9]/','',$person['number'] ?? ''); }
}
