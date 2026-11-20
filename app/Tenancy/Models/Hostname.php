<?php

namespace App\Tenancy\Models;

use App\Tenancy\Abstracts\SystemModel;
use App\Tenancy\Contracts\CurrentHostname;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hostname extends SystemModel implements CurrentHostname
{
    use SoftDeletes;

    protected $casts = [
        'force_https' => 'boolean',
        'under_maintenance_since' => 'datetime',
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
