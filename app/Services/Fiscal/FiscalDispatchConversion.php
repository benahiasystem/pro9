<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\User;
use Illuminate\Database\ConnectionInterface;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class FiscalDispatchConversion
{
    /** Runs inside the reservation transaction, so a second operation cannot bill the same dispatch. */
    public static function register(ConnectionInterface $db, array $context, User $actor, callable $writer): int
    {
        if ($db->transactionLevel() < 1) {
            throw new \LogicException('La conversión requiere la transacción de reserva fiscal.');
        }
        $dispatch = $db->table('dispatches')->where('id', $context['dispatch_id'] ?? null)->lockForUpdate()->first();
        if (!$dispatch || ($context['document_type_id'] ?? null) !== '01'
            || !in_array($actor->type, ['admin', 'seller'], true)
            || (int) $actor->establishment_id !== (int) $dispatch->establishment_id
            || (int) $context['establishment_id'] !== (int) $dispatch->establishment_id
            || (int) $context['customer_id'] !== (int) $dispatch->customer_id
            || $context['fiscal_environment'] !== $dispatch->fiscal_environment
            || $dispatch->state_type_id === '11'
            || ($actor->type === 'seller' && (int) $actor->id !== (int) $dispatch->user_id)) {
            throw new \DomainException('La orden de entrega no corresponde al cliente, ambiente, sucursal o usuario de la factura.');
        }
        if ($dispatch->document_id || $dispatch->reference_document_id
            || $db->table('documents')->where('dispatch_id', $dispatch->id)->exists()) {
            throw new \DomainException('La orden de entrega ya está vinculada a una factura.');
        }
        $documentId = $writer();
        $document = $db->table('documents')->where('id', $documentId)->first();
        if (!$document || (int) $document->dispatch_id !== (int) $dispatch->id) {
            throw new \DomainException('La factura debe conservar la referencia de la orden de entrega.');
        }
        $db->table('dispatches')->where('id', $dispatch->id)->update(['document_id' => $documentId]);
        return (int) $documentId;
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
