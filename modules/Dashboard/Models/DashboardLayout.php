<?php

namespace Modules\Dashboard\Models;

use App\Models\Tenant\ModelTenant;

/**
 * Layout de widgets del dashboard por usuario.
 */
class DashboardLayout extends ModelTenant
{
    protected $table = 'dashboard_layouts';

    protected $fillable = [
        'user_id',
        'layout',
    ];

    protected $casts = [
        'layout' => 'array',
    ];
}
