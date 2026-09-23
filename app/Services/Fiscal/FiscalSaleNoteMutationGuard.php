<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class FiscalSaleNoteMutationGuard
{
    public static function lockEditable(ConnectionInterface $db, int $id, ?User $actor): object
    {
        if ($db->transactionLevel() < 1) throw new \LogicException('La modificación de la nota requiere una transacción.');
        $db->table('companies')->orderBy('id')->lockForUpdate()->first();
        $source = $db->table('sale_notes')->where('id', $id)->lockForUpdate()->first();
        if (!$actor || !$source || !in_array($actor->type, ['admin', 'seller', 'integrator'], true)
            || (int) $actor->establishment_id !== (int) $source->establishment_id
            || ($actor->type !== 'admin' && (int) $actor->id !== (int) $source->user_id)) {
            throw new AccessDeniedHttpException('No puede modificar esta nota de venta.');
        }
        if ($source->document_id || $source->changed || $db->table('documents')->where('sale_note_id', $id)->exists()) {
            throw ValidationException::withMessages(['sale_note_id' => 'La nota de venta ya fue convertida; su origen y vínculos están bloqueados.']);
        }
        if (in_array($source->state_type_id ?? null, ['09', '11'], true)) {
            throw ValidationException::withMessages(['sale_note_id' => 'La nota está anulada o rechazada y no admite esta modificación.']);
        }
        return $source;
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
