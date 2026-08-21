<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

/**
 *
 * @package App\Models\System
 * @mixin Model
 */
class BusinessTurn extends Model
{
    use UsesSystemConnection;

    public const CUSTOM_ID = 0;

    public const FULL_ID = 5;

    public const NRUS_ID = 6;

    public const RESERVED_IDS = [self::CUSTOM_ID, self::FULL_ID];

    protected $table = 'business_turns';

    protected $fillable = [
        'name',
        'value',
        'description',
        'modules',
        'levels',
        'apps',
        'app_levels',
        'active',
        'sort',
    ];

    protected $casts = [
        'modules' => 'array',
        'levels' => 'array',
        'apps' => 'array',
        'app_levels' => 'array',
        'locked' => 'boolean',
        'is_default' => 'boolean',
        'active' => 'boolean',
    ];

    /**
     * @param  \Illuminate\Database\Eloquent\Builder $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSorted($query)
    {
        return $query->orderBy('sort')->orderBy('id');
    }

    /**
     * Payload que consumen los formularios de cliente y de plan para armar los
     * radios de "Giro de negocio" y precargar los árboles de módulos/apps.
     *
     * @return \Illuminate\Support\Collection
     */
    public static function formOptions()
    {
        return self::sorted()
            ->get()
            ->map(function (self $row) {
                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'value' => $row->value,
                    'description' => $row->description,
                    'modules' => $row->modules ?? [],
                    'levels' => $row->levels ?? [],
                    'apps' => $row->apps ?? [],
                    'app_levels' => $row->app_levels ?? [],
                    'locked' => $row->locked,
                    'active' => $row->active,
                ];
            })
            ->values();
    }
}
