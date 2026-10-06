<?php
namespace App\Models\Tenant;
// ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
class DocumentEmission extends ModelTenant
{
    protected $table = 'document_emissions';
    protected $guarded = ['id'];
    protected $casts = ['payload' => 'array', 'response' => 'array', 'assigned_at' => 'datetime', 'control_assigned_at' => 'datetime'];
    public function document() { return $this->belongsTo(Document::class); }
}
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
