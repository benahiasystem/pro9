<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\Document;

/** A later registered invoice control is shown as current, without rewriting the note snapshot. */
final class NoteReferenceControl
{
    public static function forDocument(Document $document): ?array
    {
        if (!$document->exists || $document->fiscal_emission_mode!=='free_form' || !in_array($document->document_type_id,['07','08'],true)) return null;
        $note=$document->note;
        if (!$note) return null;
        $snapshot=(array)($note->data_affected_document ?? []);
        if (!empty($snapshot['control_number'])) return ['label'=>'Control de factura afectada','number'=>$snapshot['control_number']];
        $number=$note->affected_document->control_number ?? null;
        return $number ? ['label'=>'Control actual de factura afectada','number'=>$number] : null;
    }
}
