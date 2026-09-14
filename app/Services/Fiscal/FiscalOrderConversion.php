<?php

namespace App\Services\Fiscal;

use Illuminate\Database\ConnectionInterface;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class FiscalOrderConversion
{
    /** Trusted source context is passed by the order service, never read from invoice payloads. */
    public static function register(ConnectionInterface $db, array $source, string $operationKey, callable $writer): int
    {
        if ($db->transactionLevel() < 1) throw new \LogicException('La conversión requiere una transacción fiscal.');
        $orderId = (int) ($source['id'] ?? 0);
        if ($orderId < 1 || $operationKey !== 'ecommerce-order-' . $orderId . '-invoice') {
            throw new \DomainException('La operación fiscal no corresponde al pedido.');
        }
        $order = $db->table('orders')->where('id', $orderId)->whereNull('deleted_at')->lockForUpdate()->first();
        if (!$order || $order->document_external_id || $db->table('sale_notes')->where('order_id', $orderId)->exists()) {
            throw new \DomainException('El pedido no está disponible o ya tiene comprobante.');
        }
        $purchase = json_decode($order->purchase, true, 512, JSON_THROW_ON_ERROR);
        if (!hash_equals($source['purchase_fingerprint'], self::fingerprint($purchase))) {
            throw new \DomainException('El pedido cambió durante la generación. Recargue antes de facturar.');
        }
        FiscalOrderStockReservation::releaseLocked($db, $order, (int) ($source['establishment_id'] ?? 0));
        $documentId = $writer($order);
        $document = $db->table('documents')->find($documentId);
        if (!$document || $document->document_type_id !== '01' || !$document->external_id) {
            throw new \DomainException('La conversión debe generar una factura identificada.');
        }
        $db->table('orders')->where('id', $orderId)->update([
            'document_external_id' => $document->external_id,
            'number_document' => ($document->series !== null && $document->series !== '' ? $document->series . '-' : '') . $document->number,
            'updated_at' => now(),
        ]);
        return (int) $documentId;
    }

    public static function fingerprint(array $purchase): string
    {
        // Nest the source so document transport-field exclusions cannot omit source data.
        return FiscalOperationFingerprint::forWebDocument(['purchase' => $purchase], 0);
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
