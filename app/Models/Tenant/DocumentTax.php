<?php
namespace App\Models\Tenant;
// ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
class DocumentTax extends ModelTenant
{
    protected $table = 'document_taxes';
    protected $guarded = ['id'];
    protected $casts = [];
    public function document() { return $this->belongsTo(Document::class); }
}
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
