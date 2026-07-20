<?php

namespace Modules\Marketplace\Models;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;
use Modules\Marketplace\Scopes\PublishedScope;

class Item extends Model
{
    use UsesSystemConnection;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_BLOCKED = 'blocked';

    protected $table = 'marketplace_items';

    protected $fillable = [
        'store_id',
        'external_id',
        'name',
        'name_normalized',
        'internal_code',
        'barcode',
        'category_id',
        'source_category',
        'price',
        'description',
        'image_path',
        'image_hash',
        'status',
        'blocked_at',
        'blocked_reason',
        'reports_count',
        'recommendations_count',
        'last_synced_at',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'category_id' => 'integer',
        'price' => 'decimal:2',
        'reports_count' => 'integer',
        'recommendations_count' => 'integer',
        'blocked_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Solo se publica lo que está activo y pertenece a una tienda aprobada.
     * Es un global scope justamente para que sea imposible olvidarlo en el
     * front público. El sync y el admin lo desactivan explícitamente con
     * Item::unscoped() — ver ese helper.
     */
    protected static function booted()
    {
        static::addGlobalScope(new PublishedScope());
    }

    /**
     * Query sin el filtro de publicación. Úsalo SOLO en sync y admin, donde hay
     * que ver ítems inactivos y bloqueados.
     */
    public static function unscoped()
    {
        return static::withoutGlobalScope(PublishedScope::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function isBlocked(): bool
    {
        return $this->status === self::STATUS_BLOCKED;
    }
}
