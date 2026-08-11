<?php

namespace App\Models\Tenant;

use Carbon\Carbon;
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
            $campaigns = Cache::remember('ecommerce_campaigns_active', 60, function () {
                // Solo una campaña global.
                return static::query()
                    ->where('status', true)
                    ->orderByDesc('id')
                    ->limit(1)
                    ->get();
            });

            // Evergreen: si el fin ya pasó, sumar +1 día (misma hora) hasta quedar en el futuro.
            foreach ($campaigns as $campaign) {
                $campaign->rollForwardCountdownIfNeeded();
            }

            return $campaigns;
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /**
     * La única campaña activa (global, aplica a todos los productos).
     */
    public static function current(): ?self
    {
        return static::activeCampaigns()->first();
    }

    public static function forgetActiveCache(): void
    {
        Cache::forget('ecommerce_campaigns_active');
    }

    /**
     * Normaliza fecha/hora del date-picker a string local (sin shift UTC).
     */
    public static function normalizeDateTime($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
        }

        $value = trim((string) $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
            return $value;
        }

        return Carbon::parse($value)
            ->timezone(config('app.timezone'))
            ->format('Y-m-d H:i:s');
    }

    /**
     * Payload admin: fechas en formato local para el date-picker.
     */
    public function toAdminArray(): array
    {
        $this->rollForwardCountdownIfNeeded();

        $data = $this->toArray();
        $data['start_date'] = $this->start_date
            ? $this->start_date->timezone(config('app.timezone'))->format('Y-m-d H:i:s')
            : null;
        $data['end_date'] = $this->end_date
            ? $this->end_date->timezone(config('app.timezone'))->format('Y-m-d H:i:s')
            : null;

        return $data;
    }

    /**
     * Cuenta regresiva evergreen: al vencer la hora, suma un día más (misma hora)
     * y así sucesivamente. Persiste en BD para todos los visitantes.
     */
    public function rollForwardCountdownIfNeeded(?Carbon $at = null): bool
    {
        if (! $this->sp_countdown || ! $this->end_date) {
            return false;
        }

        $at = $at ?: now();
        if ($this->end_date->gt($at)) {
            return false;
        }

        $end = $this->end_date->copy();
        // Por si la pestaña/servidor estuvo offline varios días.
        while ($end->lte($at)) {
            $end->addDay();
        }

        $this->end_date = $end;
        $this->save();
        static::forgetActiveCache();

        return true;
    }

    /**
     * Vigencia por calendario (tras aplicar roll evergreen si corresponde).
     */
    public function isWithinSchedule(?Carbon $at = null): bool
    {
        $at = $at ?: now();
        $this->rollForwardCountdownIfNeeded($at);

        if ($this->start_date && $this->start_date->gt($at)) {
            return false;
        }

        // Con countdown evergreen el end_date ya se adelantó; sin countdown sí corta.
        if (! $this->sp_countdown && $this->end_date && $this->end_date->lte($at)) {
            return false;
        }

        return true;
    }

    public function hasActiveDiscount(?Carbon $at = null): bool
    {
        return $this->sp_discount_price && $this->isWithinSchedule($at);
    }

    public function hasActiveCountdown(?Carbon $at = null): bool
    {
        $at = $at ?: now();
        $this->rollForwardCountdownIfNeeded($at);

        return $this->sp_countdown
            && $this->end_date
            && $this->end_date->gt($at)
            && (! $this->start_date || $this->start_date->lte($at));
    }

    /**
     * Unix timestamp del fin (estable para JS). Null si no hay countdown vigente.
     */
    public function countdownEndsAtTimestamp(?Carbon $at = null): ?int
    {
        if (! $this->hasActiveCountdown($at)) {
            return null;
        }

        return $this->end_date->getTimestamp();
    }

    /**
     * Campaña global activa (aplica a cualquier producto).
     */
    public static function forProduct(int $itemId): ?self
    {
        return static::current();
    }

    public function appliesToProduct(int $itemId): bool
    {
        return true;
    }

    public function discountedPrice(float $basePrice): float
    {
        if (! $this->hasActiveDiscount()) {
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
