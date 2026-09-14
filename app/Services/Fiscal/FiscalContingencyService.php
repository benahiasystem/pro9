<?php

namespace App\Services\Fiscal;

use App\Services\FiscalNumberingRepository;
use Illuminate\Database\ConnectionInterface;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class FiscalContingencyService
{
    private ConnectionInterface $db;

    public function __construct(ConnectionInterface $db) { $this->db = $db; }

    /** The caller authorizes the subject; this service also verifies the persisted administrator. */
    public function start(int $originalId, int $profileId, string $reason, int $actorId, callable $validatePrint): object
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 255) {
            throw new \DomainException('Indique la causa de contingencia, hasta 255 caracteres.');
        }
        return $this->db->transaction(function () use ($originalId, $profileId, $reason, $actorId, $validatePrint) {
            // Same lock order as commercial registration and configuration; no provider call here.
            $company = $this->db->table('companies')->orderBy('id')->lockForUpdate()->first();
            $original = $this->db->table('fiscal_number_reservations')->where('id', $originalId)->lockForUpdate()->first();
            if (!$company || !$original || $original->parent_reservation_id) {
                throw new \DomainException('La contingencia requiere una reserva original existente.');
            }
            $snapshot = json_decode($original->fiscal_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $actor = $this->db->table('users')->where('id', $actorId)->first();
            if (!$actor || $actor->type !== 'admin' || !$actor->active
                || (int) $actor->establishment_id !== (int) $snapshot['establishment_id']) {
                throw new \DomainException('Solo un administrador activo de la sucursal puede iniciar la contingencia.');
            }
            $fingerprint = hash('sha256', json_encode([$originalId, $profileId, $reason], JSON_THROW_ON_ERROR));
            $previous = $this->db->table('fiscal_number_reservations')->where('parent_reservation_id', $originalId)->first();
            if ($previous) {
                if ($previous->payload_fingerprint !== $fingerprint || $original->status !== 'contingency') {
                    throw new \DomainException('La reserva ya tiene una contingencia con otra causa o perfil.');
                }
                return $previous;
            }
            if (!in_array($snapshot['mode'] ?? null, ['digital', 'fiscal_machine'], true)
                || !in_array($original->status, ['reserved', 'rejected'], true)) {
                throw new \DomainException('Conciliar la emisión pendiente antes de iniciar contingencia; una emisión confirmada no puede sustituirse.');
            }
            $result = json_decode($original->provider_result ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            $attempted = $this->db->table('fiscal_emission_attempts')->where('reservation_id', $originalId)->exists();
            if (($original->status === 'reserved' && $attempted && ($result['status'] ?? null) !== 'not_found')
                || ($original->status === 'rejected' && ($result['status'] ?? null) !== 'rejected')) {
                throw new \DomainException('Falta un resultado conciliado que descarte la emisión original.');
            }
            $isDispatch = $snapshot['document_type_id'] === '09';
            $subjectId = $isDispatch ? $original->dispatch_id : $original->document_id;
            $subject = $this->db->table($isDispatch ? 'dispatches' : 'documents')->where('id', $subjectId)->lockForUpdate()->first();
            if (!$subject || (int) $subject->establishment_id !== (int) $snapshot['establishment_id']
                || $subject->document_type_id !== $snapshot['document_type_id']
                || $subject->fiscal_environment !== $snapshot['environment']
                || in_array($subject->state_type_id, ['09', '11'], true)) {
                throw new \DomainException('El documento comercial no admite contingencia.');
            }
            $profile = $this->db->table('fiscal_profiles')->where('id', $profileId)->first();
            if (!$profile || $profile->mode !== 'free_form' || $profile->channel !== 'contingency'
                || $profile->document_type_id !== $snapshot['document_type_id']
                || $profile->device_group_id != ($snapshot['profile']['device_group_id'] ?? null)
                || $company->fiscal_environment !== $snapshot['environment']) {
                throw new \DomainException('Seleccione un perfil de contingencia compatible con el documento y punto de emisión.');
            }
            $replacement = (new FiscalNumberingRepository($this->db))->reserveForProfile(
                $profileId, 'contingency-' . $originalId, $fingerprint, (int) $snapshot['establishment_id'], 'contingency',
                $profile->device_group_id ? (int) $profile->device_group_id : null
            );
            if ($replacement->document_id || $replacement->dispatch_id || $replacement->parent_reservation_id) {
                throw new \DomainException('La clave interna de contingencia ya está ocupada.');
            }
            $physical = json_decode($replacement->fiscal_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $physical['issuer'] = $snapshot['issuer'] ?? [];
            if (isset($snapshot['affected_document'])) $physical['affected_document'] = $snapshot['affected_document'];
            $physical['contingency'] = ['original_reservation_id' => $originalId, 'original_series' => $snapshot['series'],
                'original_document_number' => (string) $original->document_number, 'original_mode' => $snapshot['mode'],
                'reason' => $reason, 'actor_id' => $actorId, 'started_at' => now()->toIso8601String()];
            $this->db->table('fiscal_number_reservations')->where('id', $replacement->id)->update([
                'parent_reservation_id' => $originalId, 'status' => 'awaiting_print',
                'fiscal_snapshot' => json_encode($physical, JSON_THROW_ON_ERROR), 'updated_at' => now(),
            ]);
            $this->db->table('fiscal_number_reservations')->where('id', $originalId)->update([
                'status' => 'contingency', 'current_attempt_id' => null, 'updated_at' => now(),
            ]);
            $replacement = $this->db->table('fiscal_number_reservations')->find($replacement->id);
            // Must render the effective physical document without publishing it. Failure rolls back all numbers.
            $rows = $this->db->table($isDispatch ? 'dispatch_items' : 'document_items')
                ->where($isDispatch ? 'dispatch_id' : 'document_id', $subjectId)->count();
            FiscalPdfData::assertItemCapacity($physical, $rows);
            $validatePrint($replacement, $subject);
            $this->db->table('fiscal_numbering_audits')->insert([
                'actor_id' => $actorId, 'action' => 'start_contingency', 'entity_type' => 'reservation', 'entity_id' => $originalId,
                'changed_fields' => json_encode(['status', 'parent_reservation_id', 'contingency']), 'created_at' => now(),
            ]);
            return $replacement;
        });
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
