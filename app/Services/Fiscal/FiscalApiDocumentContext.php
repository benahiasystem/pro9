<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\User;
use Illuminate\Database\ConnectionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class FiscalApiDocumentContext
{
    /** Integrator tokens represent the digital channel; staff mobile sessions use their physical point. */
    public static function prepareFor(array $input, ?User $actor, ConnectionInterface $db, ?int $groupId): array
    {
        if (!$actor || !in_array($actor->type, ['admin', 'seller', 'integrator'], true) || !$actor->establishment_id) {
            throw new AccessDeniedHttpException('La API fiscal requiere un usuario autorizado con sucursal.');
        }
        if (!empty($input['establishment_id']) && (int) $input['establishment_id'] !== (int) $actor->establishment_id) {
            throw new AccessDeniedHttpException('La sucursal no corresponde al usuario de la API.');
        }
        $input['establishment_id'] = (int) $actor->establishment_id;
        $channel = $actor->type === 'integrator' ? 'digital' : 'presential';
        return FiscalWebDocumentContext::prepareFor($input, $actor, $db, $groupId, $channel);
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
