<?php

namespace App\Models\Tenant;

class DocumentIvaRetentionOperation extends ModelTenant
{
    protected $table = 'document_iva_retention_operations';
    protected $guarded = ['id'];
    protected $hidden = ['payload', 'context', 'attempt_id'];
    protected $casts = ['payload' => 'array', 'context' => 'array', 'next_attempt_at' => 'datetime', 'started_at' => 'datetime'];
    public function retention() { return $this->belongsTo(DocumentReceivedRetention::class, 'retention_id'); }
}
