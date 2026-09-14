<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\Company;
use App\Models\Tenant\User;
use App\Services\FiscalProfileService;
use App\Services\SeriesResolver;
use Illuminate\Validation\ValidationException;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class FiscalWebDocumentContext
{
    public static function prepare(array $input): array
    {
        $user = auth()->user();
        if (!$user instanceof User || (int) $user->establishment_id !== (int) ($input['establishment_id'] ?? 0)) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('La sucursal no corresponde al usuario autenticado.');
        }
        return self::prepareFor($input, $user, Company::active()->getConnection(), app(SeriesResolver::class)->activeGroupId());
    }

    public static function prepareFor(array $input, User $user, \Illuminate\Database\ConnectionInterface $db, ?int $groupId, string $channel = 'presential', ?int $internalOrderId = null): array
    {
        if (!array_key_exists($channel, FiscalProfileService::CHANNELS)) throw new \DomainException('Canal fiscal no admitido.');
        if ((int) $user->establishment_id !== (int) ($input['establishment_id'] ?? 0)) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('La sucursal no corresponde al usuario autenticado.');
        }
        if (!is_string($input['operation_key'] ?? null) || !preg_match('/\A[a-zA-Z0-9_-]{1,128}\z/', $input['operation_key'])) {
            throw ValidationException::withMessages(['operation_key' => 'Se requiere una clave de operación válida para evitar duplicados.']);
        }
        if (preg_match('/\Acontingency-/i', $input['operation_key'])) {
            throw ValidationException::withMessages(['operation_key' => 'Esta clave está reservada al flujo interno de contingencia.']);
        }
        if (($internalOrderId === null && preg_match('/\Aecommerce-order-/i', $input['operation_key']))
            || ($internalOrderId !== null && ($internalOrderId < 1 || $input['operation_key'] !== 'ecommerce-order-' . $internalOrderId . '-invoice' || $channel !== 'digital' || $input['document_type_id'] !== '01'))) {
            throw ValidationException::withMessages(['operation_key' => 'Esta clave está reservada al flujo interno de pedidos.']);
        }
        $fingerprint = FiscalOperationFingerprint::forWebDocument($input, (int) $user->id);
        $previous = $db->table('fiscal_number_reservations')->where('operation_key', $input['operation_key'])->first();
        if ($previous) {
            $snapshot = json_decode($previous->fiscal_snapshot, true, 512, JSON_THROW_ON_ERROR);
            if ($previous->payload_fingerprint !== $fingerprint || (int) $snapshot['establishment_id'] !== (int) $user->establishment_id
                || ($snapshot['channel'] ?? null) !== $channel || ($snapshot['profile']['device_group_id'] ?? null) != $groupId) {
                throw ValidationException::withMessages(['operation_key' => 'Esta operación ya tiene otro contenido o contexto fiscal.']);
            }
            $profile = $db->table('fiscal_profiles')->find($previous->profile_id);
        } else {
            $profile = (new FiscalProfileService($db))->resolve((int) $user->establishment_id, $channel, (string) $input['document_type_id'], $groupId);
        }
        if (!$profile) {
            throw new \DomainException('No se encontró el perfil fiscal de la operación.');
        }
        $sequence = $db->table('fiscal_sequences')->find($profile->sequence_id);
        $input['fiscal_fingerprint'] = $fingerprint;
        $input['fiscal_profile_id'] = (int) $profile->id;
        $input['fiscal_channel'] = $channel;
        $input['fiscal_group_id'] = $groupId;
        $input['series'] = $sequence->series_code;
        $input['number'] = '#';
        unset($input['series_id']);
        return $input;
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
