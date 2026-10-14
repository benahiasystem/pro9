<?php
namespace App\Models\Tenant;

final class DispatchEmission extends ModelTenant
{
    protected $guarded = ['id'];
    protected $hidden = ['snapshot', 'payload', 'response'];
    protected $casts = ['snapshot'=>'array','payload'=>'array','response'=>'array'];
    public function dispatch() { return $this->belongsTo(Dispatch::class); }
}
