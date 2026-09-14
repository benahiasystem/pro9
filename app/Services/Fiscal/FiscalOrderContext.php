<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\User;
use App\Services\FiscalProfileService;
use Illuminate\Database\ConnectionInterface;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class FiscalOrderContext
{
    /** Internal attribution only: the caller must already have authorized the persisted order/payment. */
    public static function prepare(array $input, int $orderId, ?int $establishmentId, ConnectionInterface $db): array
    {
        if ($orderId < 1 || ($input['document_type_id'] ?? null) !== '01') throw new \DomainException('Pedido o tipo fiscal no admitido.');
        $input['operation_key'] = 'ecommerce-order-' . $orderId . '-invoice';
        $previous = $db->table('fiscal_number_reservations')->where('operation_key', $input['operation_key'])->first();
        $snapshot = $previous ? json_decode($previous->fiscal_snapshot, true, 512, JSON_THROW_ON_ERROR) : null;
        if ($snapshot) {
            if ($establishmentId && $establishmentId !== (int) $snapshot['establishment_id']) throw new \DomainException('La sucursal del pedido no coincide con su reserva.');
            $establishmentId = (int) $snapshot['establishment_id'];
            $profile = $snapshot['profile'];
        } else {
            if (!$establishmentId) {
                $establishments = $db->table('fiscal_profiles')->where('active', true)->where('channel', 'digital')->where('document_type_id', '01')->whereNull('device_group_id')->distinct()->pluck('establishment_id');
                if ($establishments->count() !== 1) throw new \DomainException('Configure una sucursal de emisión digital inequívoca para el pedido.');
                $establishmentId = (int) $establishments->first();
            }
            $profiles = new FiscalProfileService($db);
            $profile = $profiles->publicProfile($profiles->resolve($establishmentId, 'digital', '01', null));
        }
        $emitterId = $profile['configuration']['emitter_user_id'] ?? null;
        $emitter = $db->table('users')->where('id', $emitterId)->where('establishment_id', $establishmentId)->first();
        if (!$emitter || (!$previous && (!$emitter->active || !in_array($emitter->type, ['admin', 'integrator'], true)))) {
            throw new \DomainException('Configure un usuario emisor activo en el perfil digital del pedido.');
        }
        $actor = new User();
        $actor->setRawAttributes((array) $emitter);
        $input['establishment_id'] = $establishmentId;
        $input = FiscalWebDocumentContext::prepareFor($input, $actor, $db, null, 'digital', $orderId);
        $input['user_id'] = (int) $emitter->id;
        return $input;
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
