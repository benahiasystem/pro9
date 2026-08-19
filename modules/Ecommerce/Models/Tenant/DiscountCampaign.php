<?php

namespace Modules\Ecommerce\Models\Tenant;

use App\Models\Tenant\ModelTenant;
use Hyn\Tenancy\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiscountCampaign extends ModelTenant
{
    use UsesTenantConnection, SoftDeletes;

    protected $table = 'discount_campaigns';

    protected $fillable = [
        'name',
        'discount_type',
        'value',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'value' => 'float',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public static function deactivateExpired(): int
    {
        return static::query()
            ->where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['is_active' => false]);
    }

    public function getIsValidAttribute(): bool
    {
        return $this->is_active
            && (! $this->starts_at || $this->starts_at->lte(now()))
            && (! $this->expires_at || $this->expires_at->gt(now()));
    }

    public function products()
    {
        return $this->belongsToMany(\App\Models\Tenant\Item::class, 'campaign_product', 'discount_campaign_id', 'item_id');
    }

    public function categories()
    {
        return $this->belongsToMany(\Modules\Item\Models\Category::class, 'campaign_category', 'discount_campaign_id', 'category_id');
    }

    public function appliesTo(int $itemId, ?int $categoryId): bool
    {
        return $this->products->contains('id', $itemId)
            || ($categoryId !== null && $this->categories->contains('id', $categoryId));
    }

    public function calculateDiscount(float $subtotal): float
    {
        if (! $this->is_valid) {
            return 0;
        }

        $discount = $subtotal * ($this->value / 100);

        return round(min($subtotal, max(0, $discount)), 2);
    }
}
