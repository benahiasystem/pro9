<?php

namespace App\Services\Payments;

use App\Models\Tenant\{Document, DocumentPayment, SaleNotePayment};
use App\Support\Exact\Decimal as D;

// ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
/** Documentary reuse of an existing receipt does not create new money. */
final class ConvertedReceipt
{
    public static function resolve(Document $document, array $input): ?array
    {
        $id=$input['source_sale_note_payment_id'] ?? (!empty($input['sale_note_id']) ? ($input['id'] ?? null) : null);
        if ($id === null) return null;
        $source=SaleNotePayment::query()->withoutEagerLoads()->lockForUpdate()->find($id);
        $raw=$document->getAttributes()['sale_notes_relateds'] ?? null;
        $related=$raw ? json_decode($raw,true,512,JSON_THROW_ON_ERROR) : [];
        if (!is_array($related) || !array_is_list($related)) Classification::error('source_sale_note_payment_id','La relación de notas de venta no es válida.');
        $allowed=array_map(fn($row)=>(string)(is_array($row) ? ($row['id'] ?? '') : $row),$related);
        $allowed[]=(string)$document->sale_note_id;
        $note=$source?->sale_note;
        if (!$note || !in_array((string)$note->id,$allowed,true) || (string)$note->customer_id !== (string)$document->customer_id
            || (string)$note->establishment_id !== (string)$document->establishment_id
            || $note->currency_type_id !== $document->currency_type_id || $note->isVoidedOrRejected()) Classification::error('source_sale_note_payment_id','El cobro no pertenece a una nota de venta válida del comprobante.');
        if ((string)$source->payment_method_type_id !== (string)($input['payment_method_type_id'] ?? '')
            || !D::readRequired($source,'payment')->isEqualTo(D::of($input['payment'] ?? '0'))
            || $source->date_of_payment->format('Y-m-d') !== ($input['date_of_payment'] ?? '')
            || ($input['currency_type_id'] ?? $note->currency_type_id) !== $note->currency_type_id) Classification::error('source_sale_note_payment_id','El cobro de origen no coincide con el medio, importe, fecha o moneda.');
        $used=DocumentPayment::query()->where('payment_method_snapshot->receipt_origin->id',(string)$source->id)->exists();
        if ($used) Classification::error('source_sale_note_payment_id','El cobro de origen ya fue aplicado a un comprobante.');
        return ['type'=>SaleNotePayment::class,'id'=>(string)$source->id,'sale_note_id'=>(string)$note->id,
            'method_snapshot'=>$source->global_payment?->report_context['method'] ?? null];
    }
}
// ######## FIN CLASIFICACION DE COBROS Y CAJA ########
