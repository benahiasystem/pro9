<?php
namespace App\Models\Tenant;
// ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
class DocumentGuaranteeFund extends ModelTenant
{
    protected $table = 'document_guarantee_funds';
    protected $guarded = ['id'];
    protected $casts = [];
    public function document() { return $this->belongsTo(Document::class); }
}
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
