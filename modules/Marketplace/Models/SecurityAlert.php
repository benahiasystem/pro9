<?php

namespace Modules\Marketplace\Models;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

/**
 * Aviso de seguridad para el admin. Ver la migración para los tipos.
 */
class SecurityAlert extends Model
{
    use UsesSystemConnection;

    public const TYPE_WHATSAPP_CHANGED = 'whatsapp_changed';
    public const TYPE_FEED_SWEEP = 'feed_sweep';
    public const TYPE_SECRET_RESET = 'secret_reset';

    protected $table = 'marketplace_security_alerts';

    public const UPDATED_AT = null;

    protected $fillable = [
        'store_id',
        'type',
        'message',
        'meta',
        'read_at',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'meta' => 'array',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }
}
