<?php

namespace Modules\Marketplace\Models;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use UsesSystemConnection;

    protected $table = 'marketplace_categories';

    protected $fillable = [
        'name',
        'slug',
        'items_count',
        'is_visible',
        'position',
    ];

    protected $casts = [
        'items_count' => 'integer',
        'is_visible' => 'boolean',
        'position' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(Item::class, 'category_id');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }
}
