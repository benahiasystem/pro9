<?php

namespace Modules\Marketplace\Models;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use UsesSystemConnection;

    public const STATUS_OPEN = 'open';
    public const STATUS_DISMISSED = 'dismissed';

    protected $table = 'marketplace_reports';

    // La tabla no tiene updated_at.
    public const UPDATED_AT = null;

    protected $fillable = [
        'store_id',
        'item_id',
        'reason',
        'ip',
        'status',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'item_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function item()
    {
        // Sin el scope de publicación: el admin necesita ver el ítem denunciado
        // aunque ya esté bloqueado o inactivo.
        return $this->belongsTo(Item::class, 'item_id')->withoutGlobalScopes();
    }
}
