<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\User;
use Illuminate\Database\ConnectionInterface;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class FiscalTechnicalServiceConversion
{
    public static function assertAvailable(ConnectionInterface $db, array $input, User $actor): void
    {
        if ($db->transactionLevel() < 1) throw new \LogicException('La conversión requiere una transacción.');
        $source = $db->table('technical_services')->where('id', $input['technical_service_id'] ?? null)->lockForUpdate()->first();
        if (!$source || !in_array($actor->type, ['admin', 'seller'], true)
            || (int) $actor->establishment_id !== (int) $source->establishment_id
            || (int) $input['establishment_id'] !== (int) $source->establishment_id
            || $input['fiscal_environment'] !== $source->fiscal_environment
            || ($actor->type === 'seller' && (int) $source->user_id !== (int) $actor->id)) {
            throw new \DomainException('El servicio técnico no corresponde al usuario, sucursal o ambiente de emisión.');
        }
        if ($db->table('documents')->where('technical_service_id', $source->id)->exists()
            || $db->table('sale_notes')->where('technical_service_id', $source->id)->exists()) {
            throw new \DomainException('El servicio técnico ya está vinculado a un comprobante.');
        }
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
