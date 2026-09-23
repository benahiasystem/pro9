<?php

namespace App\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class FiscalProfileService
{
    public const CHANNELS = ['presential' => 'Presencial', 'digital' => 'Digital', 'contingency' => 'Contingencia'];
    public const TYPES = ['01' => 'Factura', '07' => 'Nota de crédito', '08' => 'Nota de débito', '09' => 'Orden de entrega'];

    private ConnectionInterface $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    public static function supportedTypes(string $mode): array
    {
        return match ($mode) {
            'free_form', 'digital' => array_keys(self::TYPES),
            'fiscal_machine' => ['01', '07', '08'],
            default => [],
        };
    }

    /** Caller supplies the authenticated establishment and resolved device context. */
    public function forSelection(int $establishmentId, string $channel, ?int $groupId)
    {
        return $this->db->table('fiscal_profiles as p')
            ->join('fiscal_sequences as s', 's.id', '=', 'p.sequence_id')
            ->where('p.establishment_id', $establishmentId)->where('p.channel', $channel)
            ->where('p.active', true)->where('s.active', true)->where('p.device_group_id', $groupId)
            ->get(['p.id', 'p.name', 'p.mode', 'p.document_type_id', 's.series_code', 's.next_number']);
    }

    public function save(int $establishmentId, array $input, int $actorId): array
    {
        $data = Validator::make($input, [
            'id' => ['nullable', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:120'],
            'channel' => ['required', Rule::in(array_keys(self::CHANNELS))],
            'document_type_id' => ['required', Rule::in(array_keys(self::TYPES))],
            'mode' => ['required', Rule::in(array_keys(FiscalEmissionSettings::MODES))],
            'sequence_id' => ['required', 'integer', 'min:1'],
            'control_lot_id' => ['nullable', 'integer', 'min:1'],
            'device_group_id' => ['nullable', 'integer', 'min:1'],
            'provider' => ['required', 'string', 'max:80', 'regex:/\A[a-z0-9_-]+\z/'],
            'configuration' => ['present', 'array'],
            'credentials' => ['nullable', 'string', 'max:8192'],
            'clear_credentials' => ['sometimes', 'boolean'],
            'active' => ['required', 'boolean'],
        ])->validate();
        if (!in_array($data['document_type_id'], self::supportedTypes($data['mode']), true)) {
            $this->invalid('document_type_id', 'La modalidad no soporta este documento.');
        }
        if (($data['channel'] === 'digital' && !in_array($data['mode'], ['digital', 'free_form'], true))
            || ($data['channel'] === 'contingency' && $data['mode'] !== 'free_form')) {
            $this->invalid('mode', 'La modalidad no corresponde al canal.');
        }
        $allowed = match ($data['mode']) {
            'free_form' => ['page_capacity', 'emitter_user_id'],
            'digital' => ['authorization', 'authorization_date', 'emitter_user_id'],
            'fiscal_machine' => ['model', 'serial', 'port'],
        };
        $rules = ['configuration' => ['array:' . implode(',', $allowed)]];
        foreach ($allowed as $field) {
            $rules['configuration.' . $field] = match ($field) {
                'page_capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
                'emitter_user_id' => ['nullable', 'integer', 'min:1'],
                default => ['nullable', 'string', 'max:255'],
            };
        }
        Validator::make($data, $rules)->validate();
        if ($data['mode'] !== 'free_form' && !empty($data['control_lot_id'])) {
            $this->invalid('control_lot_id', 'Sólo forma libre consume lotes preimpresos.');
        }
        if ($data['mode'] === 'free_form' && ($data['provider'] !== 'none' || !empty($data['credentials']))) {
            $this->invalid('provider', 'Forma libre utiliza la imprenta del lote, sin credenciales de integración.');
        }
        if (!empty($data['clear_credentials']) && !empty($data['credentials'])) {
            $this->invalid('credentials', 'Elija reemplazar o eliminar las credenciales.');
        }
        return $this->db->transaction(function () use ($data, $establishmentId, $actorId) {
            $this->lockIssuer();
            if (!$this->db->table('establishments')->where('id', $establishmentId)->exists()) {
                $this->invalid('establishment_id', 'Sucursal inexistente.');
            }
            $sequence = $this->db->table('fiscal_sequences')->where('id', $data['sequence_id'])->first();
            if (!$sequence || $sequence->document_type_id !== $data['document_type_id'] || ($sequence->establishment_id !== null && (int) $sequence->establishment_id !== $establishmentId)) {
                $this->invalid('sequence_id', 'La numeración no pertenece a esta sucursal y tipo documental.');
            }
            foreach (['control_lot_id' => 'fiscal_control_lots', 'device_group_id' => 'series_device_groups'] as $field => $table) {
                if (!empty($data[$field]) && !$this->db->table($table)->where('id', $data[$field])->where('establishment_id', $establishmentId)->exists()) {
                    $this->invalid($field, 'El registro no pertenece a esta sucursal.');
                }
            }
            $id = $data['id'] ?? null;
            $existing = $id ? $this->db->table('fiscal_profiles')->where('id', $id)->where('establishment_id', $establishmentId)->first() : null;
            $oldEmitter = $existing ? (json_decode($existing->configuration, true)['emitter_user_id'] ?? null) : null;
            $newEmitter = $data['configuration']['emitter_user_id'] ?? null;
            if ($newEmitter && (!$existing || $newEmitter != $oldEmitter) && !$this->db->table('users')
                ->where('id', $newEmitter)->where('establishment_id', $establishmentId)
                ->where('active', true)->whereIn('type', ['admin', 'integrator'])->exists()) {
                $this->invalid('configuration.emitter_user_id', 'Seleccione un administrador o integrador activo de esta sucursal.');
            }
            if ($id && !$existing) {
                $this->invalid('id', 'Perfil inexistente en esta sucursal.');
            }
            $collision = $this->db->table('fiscal_profiles')->where('establishment_id', $establishmentId)->where('channel', $data['channel'])->where('document_type_id', $data['document_type_id'])->where('device_group_id', $data['device_group_id'] ?? null)->where('active', true);
            if ($id) {
                $collision->where('id', '!=', $id);
            }
            if ($data['active'] && $collision->exists()) {
                $this->invalid('channel', 'Ya existe un perfil activo para este canal, documento y grupo.');
            }
            $row = [
                'establishment_id' => $establishmentId, 'name' => $data['name'],
                'channel' => $data['channel'], 'document_type_id' => $data['document_type_id'],
                'mode' => $data['mode'], 'sequence_id' => $data['sequence_id'],
                'control_lot_id' => $data['control_lot_id'] ?? null, 'device_group_id' => $data['device_group_id'] ?? null,
                'provider' => $data['provider'], 'configuration' => json_encode($data['configuration'], JSON_THROW_ON_ERROR), 'active' => (bool) $data['active'],
            ];
            if ($existing && $this->db->table('fiscal_number_reservations')->where('profile_id', $id)->exists()) {
                foreach ($row as $field => $value) {
                    if (!in_array($field, ['name', 'active'], true) && ($field === 'configuration' ? json_decode($existing->$field, true) != $data['configuration'] : $existing->$field != $value)) {
                        $this->invalid($field, 'Perfil utilizado: archive el perfil y cree otro para cambiar su configuración fiscal.');
                    }
                }
            }
            $secret = $existing->credentials ?? null;
            if ($existing && ($existing->mode !== $data['mode'] || $existing->provider !== $data['provider'])) {
                $secret = null;
            }
            if (!empty($data['clear_credentials'])) {
                $secret = null;
            } elseif (!empty($data['credentials'])) {
                $secret = app('encrypter')->encryptString($data['credentials']);
            }
            $row['credentials'] = $secret;
            $row['updated_at'] = now();
            if ($existing) {
                $this->db->table('fiscal_profiles')->where('id', $id)->update($row);
            } else {
                $row['created_at'] = now();
                $id = $this->db->table('fiscal_profiles')->insertGetId($row);
            }
            $this->audit($actorId, $existing ? 'profile.updated' : 'profile.created', 'profile', $id, array_keys($row));
            return $this->publicProfile($this->db->table('fiscal_profiles')->where('id', $id)->first());
        });
    }

    /** Caller supplies a trusted origin and authorized group, not a payload override. */
    public function resolve(int $establishmentId, string $channel, string $type, ?int $groupId = null): object
    {
        if (!isset(self::CHANNELS[$channel], self::TYPES[$type])) {
            throw new \DomainException('Canal o documento fiscal no soportado.');
        }
        $profile = $this->db->table('fiscal_profiles')->where('establishment_id', $establishmentId)->where('channel', $channel)->where('document_type_id', $type)->where('device_group_id', $groupId)->where('active', true)->first();
        if (!$profile) {
            throw new \DomainException('No hay un perfil fiscal activo para este canal, documento y punto de emisión.');
        }
        return $profile;
    }

    public function publicProfile(object $profile): array
    {
        $row = (array) $profile;
        unset($row['credentials']);
        $row['credentials_configured'] = !empty($profile->credentials);
        $row['configuration'] = json_decode($profile->configuration, true, 512, JSON_THROW_ON_ERROR);
        $row['supported_document_types'] = self::supportedTypes($profile->mode);
        $row['integration_status'] = $profile->mode === 'free_form' ? 'manual_printing' : ($profile->provider === 'simulator' ? 'simulated' : 'not_integrated');
        $row['in_use'] = $this->db->table('fiscal_number_reservations')->where('profile_id', $profile->id)->exists();
        return $row;
    }

    /** Validate before reserving any number or running commercial side effects. */
    public function assertReady(object $profile, string $environment): void
    {
        if (!in_array($environment, ['demo', 'production'], true) || !$profile->active) {
            throw new \DomainException('Perfil o ambiente fiscal no disponible.');
        }
        $sequence = $this->db->table('fiscal_sequences')->where('id', $profile->sequence_id)->first();
        if (!$sequence || !$sequence->active) {
            throw new \DomainException('La numeración del perfil está archivada o no existe.');
        }
        if ($profile->mode === 'free_form') {
            $lot = $this->db->table('fiscal_control_lots')->where('id', $profile->control_lot_id)->first();
            if (!$lot || !$lot->active || $lot->next_ordinal > $lot->end_ordinal) {
                throw new \DomainException('Configure un lote de controles disponible antes de emitir.');
            }
            $configuration = json_decode($profile->configuration, true, 512, JSON_THROW_ON_ERROR);
            if (empty($configuration['page_capacity'])) {
                throw new \DomainException('Configure la capacidad del formato preimpreso antes de emitir.');
            }
            return;
        }
        if ($environment !== 'demo' || $profile->provider !== 'simulator') {
            throw new \DomainException('No hay un adaptador fiscal real verificado para este proveedor. Puede guardar un borrador.');
        }
    }

    public function audit(int $actorId, string $action, string $entity, int $entityId, array $fields): void
    {
        $this->db->table('fiscal_numbering_audits')->insert([
            'actor_id' => $actorId, 'action' => $action, 'entity_type' => $entity,
            'entity_id' => $entityId, 'changed_fields' => json_encode(array_values($fields), JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }

    private function lockIssuer(): void
    {
        if (!$this->db->table('companies')->orderBy('id')->lockForUpdate()->first()) {
            throw new \DomainException('Emisor no configurado.');
        }
    }

    private function invalid(string $field, string $message): void
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
