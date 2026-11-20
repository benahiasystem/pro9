<?php

namespace Modules\Inventory\Models;

use App\Models\Tenant\Item;
use Hyn\Tenancy\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;

class GuideItem extends Model
{
    // ########## INICIO CAMBIO POSTGRESQL 17
    use \App\Database\HasPostgresIdentity;
    // ######### FIN CAMBIO POSTGRESQL 17

    use UsesTenantConnection;
    use \App\Support\Exact\HasExactValues;
    use \App\Support\Exact\PersistsExactValues;

    public $timestamps = false;
    protected $fillable = [
        'guide_id',
        'item_id',
        'item_name',
        'quantity',
        'unit_cost',
        'total',
    ];

    protected $casts = [
        'quantity' => 'string',
        'unit_cost' => 'string',
        'total' => 'string',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function guide()
    {
        return $this->belongsTo(Guide::class);
    }
}
