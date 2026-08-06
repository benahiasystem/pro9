<?php

namespace App\Models\Tenant;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class EcommerceCampaign extends ModelTenant
{
    protected $table = 'ecommerce_campaigns';

    protected $fillable = [
        'title',
        'discount_type',
        'discount_value',
        'start_date',
        'end_date',
        'sp_product_ids',
        'status',
        'sp_countdown',
        'sp_discount_price',
        'sp_purchase_count',
        'sp_views_count',
        'sp_stock_alert',
        'sp_rating',
        'sp_stock_threshold',
        'sp_views_min',
        'sp_views_max',
        'sp_purchase_min',
        'sp_purchase_max',
    ];

    protected $casts = [
        'discount_value' => 'float',
        'status' => 'boolean',
        'sp_countdown' => 'boolean',
        'sp_discount_price' => 'boolean',
        'sp_purchase_count' => 'boolean',
        'sp_views_count' => 'boolean',
        'sp_stock_alert' => 'boolean',
        'sp_rating' => 'boolean',
        'sp_product_ids' => 'array',
        'sp_stock_threshold' => 'integer',
        'sp_views_min' => 'integer',
        'sp_views_max' => 'integer',
        'sp_purchase_min' => 'integer',
        'sp_purchase_max' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public static function tableExists(): bool
    {
        try {
            // En tenancy la conexión correcta es la del tenant, no la default (system).
            return Schema::connection('tenant')->hasTable((new static)->getTable());
        } catch (\Throwable $e) {
            try {
                static::query()->limit(1)->exists();

                return true;
            } catch (\Throwable $e2) {
                return false;
            }
        }
    }

    public static function activeCampaigns()
    {
        if (! static::tableExists()) {
            return collect();
        }

        try {
            return Cache::remember('ecommerce_campaigns_active', 120, function () {
                return static::query()
                    ->where('status', true)
                    ->orderByDesc('id')
                    ->get();
            });
        } catch (\Throwable $e) {
            return collect();
        }
    }

    public static function forgetActiveCache(): void
    {
        Cache::forget('ecommerce_campaigns_active');
    }

    /**
     * Primera campaña activa que incluye el producto.
     */
    public static function forProduct(int $itemId): ?self
    {
        $itemId = (int) $itemId;
        if ($itemId <= 0) {
            return null;
        }

        foreach (static::activeCampaigns() as $campaign) {
            $ids = collect($campaign->sp_product_ids ?? [])
                ->map(fn ($id) => (int) $id)
                ->all();

            if (in_array($itemId, $ids, true)) {
                return $campaign;
            }
        }

        return null;
    }

    public function appliesToProduct(int $itemId): bool
    {
        $ids = collect($this->sp_product_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->all();

        return in_array((int) $itemId, $ids, true);
    }

    public function discountedPrice(float $basePrice): float
    {
        if (! $this->sp_discount_price) {
            return $basePrice;
        }

        if ($this->discount_type === 'percentage') {
            $price = $basePrice - ($basePrice * ((float) $this->discount_value / 100));
        } else {
            $price = $basePrice - (float) $this->discount_value;
        }

        return max(0, round($price, 2));
    }

    public function stockThreshold(): int
    {
        return max(1, (int) ($this->sp_stock_threshold ?: 10));
    }
}
