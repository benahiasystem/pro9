<?php

namespace App\Services\Payments;

use App\Models\Tenant\{Cash, Document, DocumentPayment, SaleNote, SaleNotePayment};
use App\Support\Exact\Decimal as D;

// ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
/** Documentary reuse of an existing receipt does not create new money. */
final class ConvertedReceipt
{
    public static function resolve(Document $document, array $input): ?array
    {
        $sources = (int)(!empty($input['source_quotation_payment_id']) || (!empty($input['quotation_id']) && !empty($input['id'])))
            + (int)(!empty($input['source_technical_service_payment_id']) || (!empty($input['technical_service_id']) && !empty($input['id'])))
            + (int)(!empty($input['source_sale_note_payment_id']) || (!empty($input['sale_note_id']) && !empty($input['id'])));
        if ($sources > 1) Classification::error('payments', 'Seleccione un único cobro de origen.');
        $quotation = QuotationReceipt::resolve($document, $input);
        if ($quotation) return $quotation;
        $technical = CommercialSourceReceipt::resolve($document, $input, 'technical_service');
        if ($technical) return $technical;
        $id=$input['source_sale_note_payment_id'] ?? (!empty($input['sale_note_id']) ? ($input['id'] ?? null) : null);
        if ($id === null) return null;
        $noteId=SaleNotePayment::query()->withoutEagerLoads()->whereKey($id)->value('sale_note_id');
        $note=SaleNote::query()->withoutEagerLoads()->lockForUpdate()->find($noteId);
        $source=SaleNotePayment::query()->withoutEagerLoads()->where('sale_note_id',$note?->id)->lockForUpdate()->find($id);
        $raw=$document->getAttributes()['sale_notes_relateds'] ?? null;
        $related=$raw ? json_decode($raw,true,512,JSON_THROW_ON_ERROR) : [];
        if (!is_array($related) || !array_is_list($related)) Classification::error('source_sale_note_payment_id','La relación de notas de venta no es válida.');
        $allowed=array_map(fn($row)=>(string)(is_array($row) ? ($row['id'] ?? '') : $row),$related);
        $allowed[]=(string)$document->sale_note_id;
        if (!$note || !$source || !in_array((string)$note->id,$allowed,true) || (string)$note->customer_id !== (string)$document->customer_id
            || (string)$note->establishment_id !== (string)$document->establishment_id
            || $note->currency_type_id !== $document->currency_type_id || $note->isVoidedOrRejected() || $note->state_type_id === '13') Classification::error('source_sale_note_payment_id','El cobro no pertenece a una nota de venta válida del comprobante.');
        // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
        $sourceCurrency = $source->currency_type_id ?? $note->currency_type_id;
        if ((string)$source->payment_method_type_id !== (string)($input['payment_method_type_id'] ?? '')
            || !D::readRequired($source,'payment')->isEqualTo(D::of(D::input($input['payment'] ?? null, 'source_sale_note_payment_id')))
            || $source->date_of_payment->format('Y-m-d') !== ($input['date_of_payment'] ?? '')
            || ($input['currency_type_id'] ?? $note->currency_type_id) !== $sourceCurrency) Classification::error('source_sale_note_payment_id','El cobro de origen no coincide con el medio, importe, fecha o moneda.');
        $original = $source->original_amount === null ? D::readRequired($source, 'payment') : D::readRequired($source, 'original_amount');
        $inputOriginal = $input['original_amount'] ?? ($sourceCurrency === $note->currency_type_id ? ($input['payment'] ?? null) : null);
        $sourceRate = $source->exchange_rate ?? $note->exchange_rate_sale;
        $inputRate = $input['exchange_rate'] ?? ($sourceCurrency === $note->currency_type_id ? $note->exchange_rate_sale : null);
        if ($inputOriginal === null || $inputRate === null || !$original->isEqualTo(D::of(D::input($inputOriginal, 'source_sale_note_payment_id')))
            || \App\Services\ExchangeRates\ExchangeRateMath::rate($sourceRate) !== \App\Services\ExchangeRates\ExchangeRateMath::rate($inputRate)) {
            Classification::error('source_sale_note_payment_id','El importe recibido o la tasa no coinciden con el cobro original.');
        }
        $global=$source->global_payment;
        $previousOrigin=$source->payment_method_snapshot['receipt_origin'] ?? [];
        $destination=$global ? ($global->destination_type === Cash::class ? 'cash' : (string)$global->destination_id) : ($previousOrigin['destination_id'] ?? null);
        $destinationLabel=$global?->report_context['destination_label'] ?? ($previousOrigin['destination_label'] ?? null);
        if ($destination !== null && isset($input['payment_destination_id']) && (string)$input['payment_destination_id'] !== (string)$destination) {
            Classification::error('source_sale_note_payment_id','El destino no coincide con el cobro original.');
        }
        // ######## FIN MONEDA DEL MEDIO DE COBRO ########
        $used=DocumentPayment::query()->where('payment_method_snapshot->receipt_origin->type',SaleNotePayment::class)
            ->where('payment_method_snapshot->receipt_origin->id',(string)$source->id)->whereNull('reversed_at')->lockForUpdate()->exists();
        if ($used) Classification::error('source_sale_note_payment_id','El cobro de origen ya fue aplicado a un comprobante.');
        return ['type'=>SaleNotePayment::class,'id'=>(string)$source->id,'sale_note_id'=>(string)$note->id,
            'reference'=>$source->reference,
            'destination_id'=>$destination, 'destination_label'=>$destinationLabel,
            'exchange_rate_source'=>$source->exchange_rate_source,
            'exchange_rate_date'=>$source->exchange_rate_date?->format('Y-m-d'),
            'method_snapshot'=>$source->payment_method_snapshot ?? $source->global_payment?->report_context['method'] ?? null];
    }
}
// ######## FIN CLASIFICACION DE COBROS Y CAJA ########
