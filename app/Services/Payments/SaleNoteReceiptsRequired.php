<?php

namespace App\Services\Payments;

use App\Models\Tenant\{SaleNote, SaleNotePayment};

// ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
/** Lock source notes and require their existing receipts in an invoice. */
final class SaleNoteReceiptsRequired
{
    public static function lockOwners(array $input): array
    {
        if ((new SaleNote())->getConnection()->transactionLevel() === 0) {
            throw new \LogicException('La conversión de notas requiere una transacción.');
        }
        $ids = [];
        if (!empty($input['sale_note_id'])) $ids[] = $input['sale_note_id'];
        $related = $input['sale_notes_relateds'] ?? [];
        if (!is_array($related) || !array_is_list($related)) Classification::error('sale_notes_relateds', 'La relación de notas de venta no es válida.');
        foreach ($related as $row) {
            $id = is_array($row) ? ($row['id'] ?? null) : $row;
            if (!$id || !ctype_digit((string)$id)) Classification::error('sale_notes_relateds', 'Identifique las notas de venta de origen.');
            $ids[] = $id;
        }
        $paymentIds = [];
        foreach ($input['payments'] ?? [] as $row) {
            $id = $row['source_sale_note_payment_id'] ?? (!empty($row['sale_note_id']) ? ($row['id'] ?? null) : null);
            if ($id !== null) $paymentIds[] = $id;
        }
        // Lock every owner mentioned by a receipt first, including invalid inputs,
        // so QuotationReceiptsRequired and ConvertedReceipt use the same order.
        $ownerIds = $ids;
        if ($paymentIds) $ownerIds = array_merge($ownerIds, SaleNotePayment::withoutEagerLoads()->whereIn('id', $paymentIds)->pluck('sale_note_id')->all());
        $noteIds = array_values(array_unique(array_map('strval', $ids)));
        $owners = $ownerIds ? SaleNote::withoutEagerLoads()->whereIn('id', $ownerIds)->orderBy('id')->lockForUpdate()->get() : collect();
        $sources = $owners->filter(fn($note) => in_array((string)$note->id, $noteIds, true));
        if ($sources->count() !== count($noteIds)) Classification::error('sale_notes_relateds', 'Una nota de venta de origen ya no está disponible.');
        $sourceRate = null;
        foreach ($sources as $note) {
            if ($note->document_id || $note->isVoidedOrRejected() || $note->state_type_id === '13'
                || (string)$note->customer_id !== (string)($input['customer_id'] ?? '')
                || (string)$note->establishment_id !== (string)($input['establishment_id'] ?? '')
                || $note->currency_type_id !== ($input['currency_type_id'] ?? null)) {
                Classification::error('sale_notes_relateds', 'Las notas deben estar vigentes, sin factura y conservar el cliente, sucursal y moneda.');
            }
            if (count($noteIds) > 1) {
                $rate = \App\Services\ExchangeRates\ExchangeRateMath::rate($note->exchange_rate_sale, 'sale_notes_relateds');
                if ($sourceRate !== null && $sourceRate !== $rate) Classification::error('sale_notes_relateds', 'Las notas deben conservar la misma tasa.');
                $sourceRate = $rate;
            }
        }
        return $noteIds;
    }

    /** Owners are locked until the enclosing invoice transaction commits. */
    public static function attach(array $noteIds, int $documentId): void
    {
        if ($noteIds) SaleNote::withoutEagerLoads()->whereIn('id', $noteIds)->update(['document_id'=>$documentId]);
    }

    public static function assertPresent(array $noteIds, array $payments): void
    {
        if (!$noteIds) return;
        $present = [];
        foreach ($payments as $row) {
            $id = $row['source_sale_note_payment_id'] ?? (!empty($row['sale_note_id']) ? ($row['id'] ?? null) : null);
            if ($id !== null) $present[(string)$id] = true;
        }
        $required = SaleNotePayment::withoutEagerLoads()->whereIn('sale_note_id', $noteIds)
            ->orderBy('id')->lockForUpdate()->pluck('id');
        foreach ($required as $id) {
            if (!isset($present[(string)$id])) Classification::error('payments', 'La conversión debe conservar todos los cobros registrados de las notas de venta.');
        }
    }
}
// ######## FIN CLASIFICACION DE COBROS Y CAJA ########
