<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

class MassiveInvoice extends ModelSystem
{
    protected $fillable = [
        'fecha_emision',
        'fecha_vencimiento',
        'ruc_emisor',
        'tipo_comprobante',
        'serie_comprobante',
        'ruc',
        'correo',
        'moneda',
        'forma_pago',
        'observacion',
        'orden_compra',
        'incluye_igv',
        'item',
        'descripcion_producto',
        'cantidad',
        'unidad_medida',
        'tipo_afectacion',
        'precio',
        'status',
        'nota',
        'external_id',
        'pdf_link',
        'estado_emision',
        'mensaje_emision',
        'total_gravado',
        'total_igv', 
        'total_venta'
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
        'incluye_igv' => 'boolean',
        'cantidad' => 'string',
        'precio' => 'string',
        'total_gravado' => 'string',
        'total_igv' => 'string',
        'total_venta' => 'string'
    ];
}
