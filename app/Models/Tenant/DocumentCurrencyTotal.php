<?php
namespace App\Models\Tenant;
// ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
class DocumentCurrencyTotal extends ModelTenant
{
    protected $table = 'document_currency_totals';
    protected $guarded = ['id'];
    protected $casts = ['tax_breakdown' => 'array'];
    public function document() { return $this->belongsTo(Document::class); }
}
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
