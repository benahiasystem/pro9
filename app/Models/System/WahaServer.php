<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WahaServer extends Model
{
    use UsesSystemConnection;

    protected $fillable = [
        'key',
        'name',
        'url',
        'api_key',
        'engine',
        'is_default',
        'active',
        'notes',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'active' => 'boolean',
    ];

    public static function generateUniqueKey(string $name): string
    {
        $base = Str::slug($name) ?: 'waha-server';
        $key = $base;
        $suffix = 1;

        while (self::where('key', $key)->exists()) {
            $suffix++;
            $key = "{$base}-{$suffix}";
        }

        return $key;
    }
}
