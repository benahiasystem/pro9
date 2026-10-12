<?php
namespace App\Models\Tenant;
// ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
class DocumentReceivedRetention extends ModelTenant
{
    protected $table = 'document_received_retentions';
    protected $guarded = ['id'];
    protected $hidden = ['input_snapshot','input_hash','active_iva_document_id','active_voucher_number'];
    protected $casts = ['fiscal_context' => 'array','agent' => 'array', 'voucher_date' => 'date','input_snapshot'=>'array','reversed_at'=>'datetime'];
    public function operations() { return $this->hasMany(DocumentIvaRetentionOperation::class, 'retention_id'); }
    public function scopeApplied($query) { return \App\Services\Fiscal\IvaRetentionService::available() ? $query->where('application_status','applied') : $query; }
    public function document() { return $this->belongsTo(Document::class); }
}
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
