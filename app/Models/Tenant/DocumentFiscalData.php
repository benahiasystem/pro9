<?php
namespace App\Models\Tenant;
// ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
class DocumentFiscalData extends ModelTenant
{
    protected $table = 'document_fiscal_data';
    protected $guarded = ['id'];
    protected $casts = ['catalog_snapshot' => 'array', 'third_party' => 'array', 'conditional_data' => 'array'];
    public function document() { return $this->belongsTo(Document::class); }
}
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
