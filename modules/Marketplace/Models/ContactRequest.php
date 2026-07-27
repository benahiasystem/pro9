<?php

namespace Modules\Marketplace\Models;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

/**
 * Una solicitud de contacto: el rastro que deja cada enlace de WhatsApp
 * generado por la puerta de contacto. Ver la migración para el porqué.
 */
class ContactRequest extends Model
{
    use UsesSystemConnection;

    public const TYPE_STORE = 'store';
    public const TYPE_PRODUCT = 'product';
    public const TYPE_CART = 'cart';

    protected $table = 'marketplace_contact_requests';

    // Solo created_at: una solicitud no se edita jamás.
    public const UPDATED_AT = null;

    protected $fillable = [
        'store_id',
        'item_id',
        'type',
        'items',
        'buyer_name',
        'buyer_whatsapp',
        'visitor_id',
        'ip',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'item_id' => 'integer',
        'items' => 'array',
        'created_at' => 'datetime',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /**
     * Código público de la solicitud, el que ve el comprador («Solicitud
     * registrada como REQ-0104»). Derivado del id: no se guarda aparte.
     */
    public function code(): string
    {
        return 'REQ-' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }
}
