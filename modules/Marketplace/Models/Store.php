<?php

namespace Modules\Marketplace\Models;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use UsesSystemConnection;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_DISABLED = 'disabled';

    protected $table = 'marketplace_stores';

    protected $fillable = [
        'external_uuid',
        'name',
        'name_normalized',
        'slug',
        'tax_id',
        'email',
        'whatsapp',
        'logo_path',
        'logo_hash',
        'description',
        'address',
        'show_address',
        'address_zone',
        'show_prices',
        'status',
        'status_reason',
        'items_count',
        'reports_count',
        'recommendations_count',
        'terms_accepted_at',
        'approved_at',
        'rejected_at',
        'disabled_at',
        'last_synced_at',
        'last_sync_items',
        'last_sync_ip',
        'app_version',
    ];

    protected $casts = [
        'show_prices' => 'boolean',
        'show_address' => 'boolean',
        'hidden_at' => 'datetime',
        'items_count' => 'integer',
        'reports_count' => 'integer',
        'recommendations_count' => 'integer',
        'last_sync_items' => 'integer',
        'terms_accepted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'disabled_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(Item::class, 'store_id');
    }

    public function reports()
    {
        return $this->hasMany(Report::class, 'store_id');
    }

    public function recommendations()
    {
        return $this->hasMany(Recommendation::class, 'store_id');
    }

    public function contactRequests()
    {
        return $this->hasMany(ContactRequest::class, 'store_id');
    }

    public function securityAlerts()
    {
        return $this->hasMany(SecurityAlert::class, 'store_id');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * ¿La ocultó la propia tienda? («Ocultar mi tienda» desde la app.) Es un
     * estado paralelo a status: sigue approved, pero no es pública. El
     * siguiente sync la republica.
     */
    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }

    /**
     * Visible al público = aprobada por el admin Y no auto-ocultada.
     */
    public function isPubliclyVisible(): bool
    {
        return $this->isApproved() && ! $this->isHidden();
    }

    /**
     * URL pública compartible. Null mientras la tienda no esté aprobada:
     * la app no debe mostrar un link que devolvería 410. Una tienda oculta
     * conserva su URL (el enlace mostrará el 410 amable hasta republicar).
     */
    public function publicUrl(): ?string
    {
        if (! $this->isApproved()) {
            return null;
        }

        return url(config('marketplace.route_prefix') . '/tienda/' . $this->slug);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /** Lo que puede ver el público: aprobada y no oculta. */
    public function scopeVisible($query)
    {
        return $query->approved()->whereNull('hidden_at');
    }
}
