<?php

namespace App\Services\Fiscal;

use Illuminate\Database\ConnectionInterface;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class FiscalOrderStockGuard
{
    public static function assertCanMutate(int $orderId, ?string $documentExternalId, ConnectionInterface $db): void
    {
        // The reservation is committed with the invoice, before the order link may be saved.
        $registered = $documentExternalId || $db->table('fiscal_number_reservations')
            ->where('operation_key', 'ecommerce-order-' . $orderId . '-invoice')
            ->whereNotNull('document_id')->exists();
        if ($registered) {
            throw new \DomainException('El pedido ya tiene factura registrada. Gestione la devolución o anulación desde el comprobante fiscal antes de modificar sus existencias.');
        }
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
