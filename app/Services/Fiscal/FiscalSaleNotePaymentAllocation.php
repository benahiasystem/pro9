<?php
namespace App\Services\Fiscal;

use Illuminate\Database\ConnectionInterface;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class FiscalSaleNotePaymentAllocation
{
    /** Apply existing receipts to an invoice balance without creating cash/bank movements. */
    public static function apply(ConnectionInterface $db, int $sourceId, int $documentId): void
    {
        self::applyMany($db, [$sourceId], $documentId);
    }

    public static function applyMany(ConnectionInterface $db, array $sourceIds, int $documentId): void
    {
        if ($db->transactionLevel() < 1) throw new \LogicException('La aplicación de cobros requiere una transacción.');
        $db->table('companies')->orderBy('id')->lockForUpdate()->first();
        sort($sourceIds, SORT_NUMERIC);
        $sources = $db->table('sale_notes')->whereIn('id', $sourceIds)->orderBy('id')->lockForUpdate()->get();
        $document = $db->table('documents')->where('id', $documentId)->lockForUpdate()->first();
        if (!$document || $sources->count() !== count($sourceIds)
            || FiscalSaleNoteConversion::sourceIds((array) $document) !== $sourceIds) {
            throw new \DomainException('La factura no corresponde a las notas de venta cuyos cobros se aplican.');
        }
        $total = '0.00';
        foreach ($sources as $source) {
            if (($source->document_id && (int) $source->document_id !== $documentId)
                || $source->currency_type_id !== $document->currency_type_id
                || $source->fiscal_environment !== $document->fiscal_environment
                || (int) $source->establishment_id !== (int) $document->establishment_id
                || (int) $source->customer_id !== (int) $document->customer_id) {
                throw new \DomainException('La factura no corresponde a la nota de venta cuyos cobros se aplican.');
            }
            $total = bcadd($total, (string) $source->total, 2);
        }
        if (bccomp($total, (string) $document->total, 2) !== 0) throw new \DomainException('El importe de las notas no corresponde a la factura.');
        if ($db->table('document_payments')->where('document_id', $documentId)->whereNull('source_sale_note_payment_id')->exists()
            && !$db->table('document_payments')->where('document_id', $documentId)->whereNotNull('source_sale_note_payment_id')->exists()) {
            throw new \DomainException('No se pueden duplicar cobros existentes con pagos nuevos en esta conversión.');
        }
        $payments = $db->table('sale_note_payments')->whereIn('sale_note_id', $sourceIds)->orderBy('id')->lockForUpdate()->get();
        $totalPaid = '0.00';
        foreach ($payments as $payment) {
            $totalPaid = bcadd($totalPaid, (string) $payment->payment, 2);
            $existing = $db->table('document_payments')->where('source_sale_note_payment_id', $payment->id)->first();
            if ($existing) {
                if ((int) $existing->document_id !== $documentId || bccomp((string) $existing->payment, (string) $payment->payment, 2) !== 0) {
                    throw new \DomainException('El cobro ya tiene otra aplicación o su importe cambió.');
                }
                continue;
            }
            $attributes = array_intersect_key((array) $payment, array_flip(['date_of_payment', 'payment_method_type_id', 'has_card', 'card_brand_id', 'reference', 'change', 'payment']));
            $db->table('document_payments')->insert(array_replace($attributes, [
                'document_id' => $documentId, 'source_sale_note_payment_id' => $payment->id, 'payment_received' => true,
            ]));
        }
        $totalPaid = '0.00';
        foreach ($db->table('document_payments')->where('document_id', $documentId)->pluck('payment') as $amount) $totalPaid = bcadd($totalPaid, (string) $amount, 2);
        $db->table('documents')->where('id', $documentId)->update(['total_canceled' => bccomp($totalPaid, (string) $document->total, 2) >= 0]);
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
