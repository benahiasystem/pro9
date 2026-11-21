<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
final class Company extends Model
{
    use HasUuids;
    use \App\Tenancy\Traits\UsesTenantConnection;
    protected $table = 'accounting_companies';
    protected $guarded = [];
    protected $casts = ['settings' => 'array', 'active' => 'boolean', 'offline_hours' => 'integer', 'workspace_days' => 'integer'];
}
