<?php

namespace Modules\Ecommerce\Models\Tenant;

use App\Models\Tenant\ModelTenant;
use Hyn\Tenancy\Traits\UsesTenantConnection;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo para cupones de descuento del ecommerce.
 * Soporta tipo porcentaje o monto fijo, límites de compra,
 * límites de uso, vigencia y envío gratis.
 */
class DiscountCoupon extends ModelTenant
{
    use UsesTenantConnection, SoftDeletes;

    protected $table = 'discount_coupons';

    protected $fillable = [
        'code',
        'type',
        'amount',
        'has_purchase_limits',
        'min_amount',
        'max_amount',
        'has_usage_limits',
        'max_total_uses',
        'uses_count',
        'max_uses_per_customer',
        'expires_at',
        'free_shipping',
        'active',
    ];

    protected $casts = [
        'amount'              => 'float',
        'min_amount'          => 'float',
        'max_amount'          => 'float',
        'has_purchase_limits' => 'boolean',
        'has_usage_limits'    => 'boolean',
        'free_shipping'       => 'boolean',
        'active'              => 'boolean',
        'max_total_uses'      => 'integer',
        'uses_count'          => 'integer',
        'max_uses_per_customer' => 'integer',
        'expires_at'          => 'datetime',
    ];

    public function setCodeAttribute($value): void
    {
        $this->attributes['code'] = mb_strtoupper(trim((string) $value));
    }

    /**
     * Determina si el cupón está vencido según su fecha de expiración.
     */
    public function getIsExpiredAttribute(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return now()->greaterThan($this->expires_at);
    }

    public function getIsValidAttribute(): bool
    {
        if (! $this->active || $this->is_expired) {
            return false;
        }

        return ! ($this->has_usage_limits
            && $this->max_total_uses !== null
            && $this->totalUses() >= $this->max_total_uses);
    }

    /**
     * Retorna el arreglo de datos para colecciones y recursos API.
     */
    public function getCollectionData(): array
    {
        return [
            'id'                   => $this->id,
            'code'                 => $this->code,
            'type'                 => $this->type,
            'type_label'           => $this->type === 'percentage' ? '% Porcentaje' : 'S/ Fijo',
            'amount'               => $this->amount,
            'amount_formatted'     => $this->type === 'percentage'
                ? number_format($this->amount, 0) . '%'
                : 'S/ ' . number_format($this->amount, 2),
            'has_purchase_limits'  => $this->has_purchase_limits,
            'min_amount'           => $this->min_amount,
            'max_amount'           => $this->max_amount,
            'has_usage_limits'     => $this->has_usage_limits,
            'max_total_uses'       => $this->max_total_uses,
            'max_uses_per_customer'=> $this->max_uses_per_customer,
            'expires_at'           => $this->expires_at ? $this->expires_at->format('Y-m-d H:i:s') : null,
            'expires_at_formatted' => $this->expires_at ? $this->expires_at->format('d/m/Y H:i') : null,
            'free_shipping'        => $this->free_shipping,
            'active'               => $this->active,
            'is_expired'           => $this->is_expired,
            'uses_count'           => $this->totalUses(),
            'total_uses'           => $this->totalUses(),
            'is_valid'             => $this->is_valid,
            'created_at'           => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Usos registrados del cupón
     */
    public function usages()
    {
        return $this->hasMany(DiscountCouponUsage::class, 'discount_coupon_id');
    }

    /**
     * Cuenta de usos totales
     */
    public function totalUses(): int
    {
        return max((int) $this->uses_count, $this->usages()->count());
    }

    /**
     * Cuenta de usos por cliente (person_id)
     */
    public function usesByPerson($person_id): int
    {
        if (!$person_id) return 0;
        return $this->usages()->where('person_id', $person_id)->count();
    }

    /**
     * Determina si el coupon puede ser usado por el cliente dado
     */
    public function canBeUsedBy($person_id, $order_total = 0): bool
    {
        return $this->validationError($person_id, $order_total) === null;
    }

    public function validationError($person_id, $order_total = 0): ?string
    {
        if (! $this->active) return 'El cupón está inactivo.';
        if ($this->is_expired) return 'El cupón ha vencido.';

        if ($this->has_usage_limits && $this->max_total_uses !== null) {
            if ($this->totalUses() >= $this->max_total_uses) return 'El cupón alcanzó su límite total de usos.';
        }

        if ($this->has_usage_limits && $this->max_uses_per_customer !== null) {
            if ($person_id && $this->usesByPerson($person_id) >= $this->max_uses_per_customer) {
                return 'Ya alcanzaste el límite de usos permitido para este cupón.';
            }
        }

        if ($this->has_purchase_limits) {
            if ($this->min_amount !== null && $order_total < $this->min_amount) {
                return 'La compra mínima para este cupón es S/ '.number_format($this->min_amount, 2).'.';
            }
            if ($this->max_amount !== null && $order_total > $this->max_amount) {
                return 'El cupón aplica hasta compras de S/ '.number_format($this->max_amount, 2).'.';
            }
        }

        return null;
    }

    /**
     * Calcula el monto de descuento sobre un total dado
     */
    public function calculateDiscountAmount($total): float
    {
        if ($this->type === 'percentage') {
            $discount = ($this->amount / 100) * $total;
        } else {
            $discount = (float) $this->amount;
        }

        // Si existe un tope (max_amount), usarlo como tope del descuento
        if ($this->max_amount !== null && $this->max_amount > 0) {
            $discount = min($discount, $this->max_amount);
        }

        return round(min($discount, max(0, (float) $total)), 2);
    }
}
