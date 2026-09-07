<?php

namespace Modules\Sync\Models;

use App\Models\Tenant\Establishment;
use App\Models\Tenant\ModelTenant;
use App\Models\Tenant\SeriesDeviceGroup;
use App\Models\Tenant\User;
use Hyn\Tenancy\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Builder;

/**
 * Máquina VendeYa enrolada. El token solo existe hasheado (sha256):
 * el valor plano viaja una única vez en la respuesta del enrolamiento.
 */
class OfflineMachine extends ModelTenant
{
    use UsesTenantConnection;

    protected $table = 'offline_machines';

    protected $fillable = [
        'uuid',
        'name',
        'token_hash',
        'user_id',
        'establishment_id',
        'series_device_group_id',
        'status',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function establishment()
    {
        return $this->belongsTo(Establishment::class);
    }

    public function device_group()
    {
        return $this->belongsTo(SeriesDeviceGroup::class, 'series_device_group_id');
    }

    public function enrolled_by()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public static function findByPlainToken(?string $plainToken): ?self
    {
        if (empty($plainToken)) {
            return null;
        }

        return self::active()->where('token_hash', self::hashToken($plainToken))->first();
    }

    public function touchSeen(): void
    {
        $this->forceFill(['last_seen_at' => now()])->saveQuietly();
    }
}
