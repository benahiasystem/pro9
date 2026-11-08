<?php

namespace App\Services\Fiscal;

use App\CoreFacturalo\Facturalo;
use App\Models\Tenant\Document;

/** Render a current local copy without changing the earlier stored PDF. */
final class CurrentFreeFormPdf
{
    public static function bytesIfRegistered(Document $document, string $format='a4'): ?string
    {
        if ($document->fiscal_emission_mode!=='free_form' || !in_array($document->document_type_id,['01','07','08'],true)) return null;
        // A caller may still hold a model loaded before the control was recorded.
        $document=$document->fresh() ?? $document;
        $registration=$document->emission->response['free_form_control'] ?? null;
        $invoice=in_array($document->document_type_id,['07','08'],true) ? ($document->note->affected_document ?? null) : null;
        $invoiceRegistration=$invoice->emission->response['free_form_control'] ?? null;
        if (!$registration && !$invoiceRegistration) return null;
        if ($registration && (!$document->control_number || ($registration['control_number'] ?? null)!==$document->control_number))
            throw new \UnexpectedValueException('El control impreso requiere revisión antes de generar el PDF.');
        if ($invoiceRegistration && (!$invoice->control_number || ($invoiceRegistration['control_number'] ?? null)!==$invoice->control_number))
            throw new \UnexpectedValueException('El control de la factura afectada requiere revisión antes de generar el PDF.');
        $type=['01'=>'invoice','07'=>'credit','08'=>'debit'][$document->document_type_id];
        return (new Facturalo())->createPdf($document,$type,$format,'string');
    }

    public static function bytesForFilename(string $filename): ?string
    {
        $matches=Document::query()->where('filename',$filename)->where('fiscal_emission_mode','free_form')->whereIn('document_type_id',['01','07','08'])->get();
        if ($matches->count()>1) {
            foreach ($matches as $match) {
                $invoice=in_array($match->document_type_id,['07','08'],true) ? ($match->note->affected_document ?? null) : null;
                if (!empty($match->emission->response['free_form_control']) || !empty($invoice->emission->response['free_form_control']))
                    throw new \UnexpectedValueException('Nombre de PDF ambiguo.');
            }
            return null;
        }
        return $matches->isEmpty() ? null : self::bytesIfRegistered($matches->first());
    }
}
