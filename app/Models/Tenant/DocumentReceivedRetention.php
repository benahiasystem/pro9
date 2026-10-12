<?php
namespace App\Models\Tenant;
// ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
class DocumentReceivedRetention extends ModelTenant
{
    protected $table = 'document_received_retentions';
    protected $guarded = ['id'];
    protected $casts = ['fiscal_context' => 'array','agent' => 'array', 'voucher_date' => 'date'];
    public function document() { return $this->belongsTo(Document::class); }
}
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
