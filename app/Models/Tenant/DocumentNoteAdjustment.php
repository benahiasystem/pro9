<?php
namespace App\Models\Tenant;

/** Durable coordination of a note's non-cash/fiscal corrections. */
class DocumentNoteAdjustment extends ModelTenant
{
    protected $hidden = ['request','request_hash','context'];
    protected $guarded = ['id'];
    protected $casts = ['context'=>'array', 'request'=>'array', 'reversed_at'=>'datetime'];
    public function document() { return $this->belongsTo(Document::class); }
    public function affected_document() { return $this->belongsTo(Document::class, 'affected_document_id'); }
}
