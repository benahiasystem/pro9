<?php

namespace App\Models\Tenant;

class ReceivedRetentionUpload extends ModelTenant
{
    protected $table = 'received_retention_uploads';
    protected $guarded = ['id'];
    protected $hidden = ['path'];
    protected $casts = ['expires_at' => 'datetime'];
}
