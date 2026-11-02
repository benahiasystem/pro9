<?php

namespace App\Services\Payments;

use App\Models\Tenant\{Quotation, SaleNote, SaleNotePayment};
use Modules\Sale\Models\QuotationPayment;

// ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
/** A conversion must carry every receipt already recorded on its quotation. */
final class QuotationReceiptsRequired
{
    public static function assertPresent($quotationId, array $payments): void
    {
        if (!$quotationId) return;
        if ((new Quotation())->getConnection()->transactionLevel() === 0) {
            throw new \LogicException('La conversión de cotización requiere una transacción.');
        }
        $present = [];
        $notePaymentIds = [];
        foreach ($payments as $row) {
            $source = $row['source_quotation_payment_id'] ?? (!empty($row['quotation_id']) ? ($row['id'] ?? null) : null);
            if ($source !== null) $present[(string) $source] = true;
            $noteSource = $row['source_sale_note_payment_id'] ?? (!empty($row['sale_note_id']) ? ($row['id'] ?? null) : null);
            if ($noteSource !== null) $notePaymentIds[] = $noteSource;
        }
        // Mantener el mismo orden que la edición de notas: propietario, cotización y cobros.
        if ($notePaymentIds) {
            $noteIds = SaleNotePayment::withoutEagerLoads()->whereIn('id', $notePaymentIds)->pluck('sale_note_id')->unique()->all();
            SaleNote::withoutEagerLoads()->whereIn('id', $noteIds)->orderBy('id')->lockForUpdate()->get(['id']);
        }
        $quotation = Quotation::withoutEagerLoads()->lockForUpdate()->findOrFail($quotationId);
        $required = QuotationPayment::withoutEagerLoads()->where('quotation_id', $quotation->id)
            ->orderBy('id')->lockForUpdate()->pluck('id')->map(fn ($id) => (string) $id)->all();
        if (!$required) return;
        if ($notePaymentIds) {
            $notePayments = SaleNotePayment::withoutEagerLoads()->whereIn('id', $notePaymentIds)
                ->orderBy('id')->lockForUpdate()->get(['id', 'payment_method_snapshot']);
            foreach ($notePayments as $payment) {
                $origin = $payment->payment_method_snapshot['receipt_origin'] ?? [];
                if (($origin['type'] ?? null) === QuotationPayment::class
                    && (string) ($origin['quotation_id'] ?? '') === (string) $quotation->id
                    && isset($origin['id'])) $present[(string) $origin['id']] = true;
            }
        }
        foreach ($required as $id) {
            if (!isset($present[$id])) {
                Classification::error('payments', 'La conversión debe conservar todos los cobros registrados de la cotización.');
            }
        }
    }
}
// ######## FIN CLASIFICACION DE COBROS Y CAJA ########
