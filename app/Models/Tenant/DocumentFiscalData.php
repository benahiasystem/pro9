<?php
namespace App\Models\Tenant;
// ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
class DocumentFiscalData extends ModelTenant
{
    protected $table = 'document_fiscal_data';
    protected $guarded = ['id'];
    protected $casts = ['catalog_snapshot' => 'array', 'third_party' => 'array', 'conditional_data' => 'array', 'iva_retention_prevision' => 'array', 'islr_retention_prevision' => 'array'];
    public function document() { return $this->belongsTo(Document::class); }
    public function third_party_person() { return $this->belongsTo(Person::class, 'third_party_person_id'); }
}
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
