<?php

namespace App\Models\System;

use Hyn\Tenancy\Models\Website;
use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

/**
 * Programacion de la limpieza automatica de archivos de una empresa.
 *
 * Ver App\Traits\StorageManagementTrait, que resuelve las rutas y hace el borrado.
 *
 * @property int $website_id
 * @property array $packages
 * @property string $frequency
 * @property int|null $day_of_week
 * @property int|null $day_of_month
 * @property string $time
 * @property bool $active
 * @property \Illuminate\Support\Carbon|null $last_run_at
 */
class StorageCleanupConfiguration extends Model
{
    use UsesSystemConnection;

    const FREQUENCY_DAILY = 'daily';
    const FREQUENCY_WEEKLY = 'weekly';
    const FREQUENCY_MONTHLY = 'monthly';

    const FREQUENCIES = [
        self::FREQUENCY_DAILY,
        self::FREQUENCY_WEEKLY,
        self::FREQUENCY_MONTHLY,
    ];

    protected $fillable = [
        'website_id',
        'packages',
        'frequency',
        'day_of_week',
        'day_of_month',
        'time',
        'active',
        'last_run_at',
    ];

    protected $casts = [
        // lista de carpetas, se guarda como json
        'packages' => 'array',
        'day_of_week' => 'integer',
        'day_of_month' => 'integer',
        'active' => 'boolean',
        'last_run_at' => 'datetime',
        // time queda sin cast: la columna es TIME y castearla a datetime le
        // agregaria una fecha que no existe
    ];

    public function website()
    {
        return $this->belongsTo(Website::class);
    }

    /**
     * Solo las programaciones habilitadas
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Ruta de la carpeta del tenant al que pertenece esta programacion
     */
    public function getTenantUuidAttribute()
    {
        return optional($this->website)->uuid;
    }
}
