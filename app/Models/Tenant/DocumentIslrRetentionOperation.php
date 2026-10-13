<?php

namespace App\Models\Tenant;

class DocumentIslrRetentionOperation extends DocumentIvaRetentionOperation
{
    protected $table = 'document_islr_retention_operations';
    protected $hidden = ['payload','context','attempt_id','result'];
    protected $casts = ['payload'=>'array','context'=>'array','result'=>'array','next_attempt_at'=>'datetime','started_at'=>'datetime'];
}
