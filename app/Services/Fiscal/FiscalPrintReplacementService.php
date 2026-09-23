<?php

namespace App\Services\Fiscal;

use App\Services\FiscalNumberingRepository;
use Illuminate\Database\ConnectionInterface;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class FiscalPrintReplacementService
{
    private ConnectionInterface $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    /** Replaces an inutilized preprinted form while preserving the single commercial sale. */
    public function replace(int $rootId, int $profileId, int $actorId, callable $validatePrint): object
    {
        return $this->db->transaction(function () use ($rootId, $profileId, $actorId, $validatePrint) {
            $company = $this->db->table('companies')->orderBy('id')->lockForUpdate()->first();
            $root = $this->db->table('fiscal_number_reservations')->where('id', $rootId)->lockForUpdate()->first();
            if (!$company || !$root || $root->parent_reservation_id) {
                throw new \DomainException('El reemplazo requiere la reserva original del documento.');
            }
            $current = FiscalReservation::effective($this->db, $root, true);
            $currentSnapshot = json_decode($current->fiscal_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $replacementData = $currentSnapshot['print_replacement'] ?? null;
            if ($current->status === 'awaiting_print' && $replacementData) {
                if ((int) $current->profile_id !== $profileId) {
                    throw new \DomainException('El control de reemplazo ya fue reservado con otro perfil.');
                }
                return $current;
            }
            if (($currentSnapshot['mode'] ?? null) !== 'free_form' || $current->status !== 'inutilized') {
                throw new \DomainException('Sólo puede reemplazar un control de forma libre previamente inutilizado.');
            }
            $actor = $this->db->table('users')->where('id', $actorId)->first();
            if (!$actor || $actor->type !== 'admin' || !$actor->active
                || (int) $actor->establishment_id !== (int) $currentSnapshot['establishment_id']) {
                throw new \DomainException('Solo un administrador activo de la sucursal puede reemplazar el control.');
            }
            $isDispatch = $currentSnapshot['document_type_id'] === '09';
            $subjectId = $isDispatch ? $root->dispatch_id : $root->document_id;
            $subject = $this->db->table($isDispatch ? 'dispatches' : 'documents')->where('id', $subjectId)->lockForUpdate()->first();
            if (!$subject || (int) $subject->establishment_id !== (int) $currentSnapshot['establishment_id']
                || $subject->document_type_id !== $currentSnapshot['document_type_id']
                || $subject->fiscal_environment !== $currentSnapshot['environment']
                || in_array($subject->state_type_id, ['09', '11'], true)) {
                throw new \DomainException('El documento comercial no admite reemplazo de impresión.');
            }
            $profile = $this->db->table('fiscal_profiles')->where('id', $profileId)->first();
            if (!$profile || $profile->mode !== 'free_form' || !$profile->active
                || (int) $profile->establishment_id !== (int) $currentSnapshot['establishment_id']
                || $profile->document_type_id !== $currentSnapshot['document_type_id']
                || $profile->channel !== ($currentSnapshot['channel'] ?? null)
                || $profile->device_group_id != ($currentSnapshot['profile']['device_group_id'] ?? null)
                || $company->fiscal_environment !== $currentSnapshot['environment']) {
                throw new \DomainException('Seleccione un perfil de forma libre compatible con el documento y punto de emisión.');
            }
            $fingerprint = hash('sha256', json_encode([$current->id, $profileId, $current->control_number,
                $current->invalidation_reason], JSON_THROW_ON_ERROR));
            $replacement = (new FiscalNumberingRepository($this->db))->reserveForProfile(
                $profileId, 'print-replacement-' . $current->id, $fingerprint,
                (int) $currentSnapshot['establishment_id'], $profile->channel,
                $profile->device_group_id ? (int) $profile->device_group_id : null
            );
            if ($replacement->document_id || $replacement->dispatch_id || $replacement->parent_reservation_id) {
                throw new \DomainException('La clave interna de reemplazo ya está ocupada.');
            }
            $physical = json_decode($replacement->fiscal_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $physical['issuer'] = $currentSnapshot['issuer'] ?? [];
            if (isset($currentSnapshot['affected_document'])) {
                $physical['affected_document'] = $currentSnapshot['affected_document'];
            }
            if (isset($currentSnapshot['contingency'])) {
                $physical['contingency'] = $currentSnapshot['contingency'];
            }
            $physical['print_replacement'] = [
                'replaced_reservation_id' => (int) $current->id,
                'replaced_series' => $currentSnapshot['series'],
                'replaced_document_number' => (string) $current->document_number,
                'replaced_control_number' => $current->control_number,
                'invalidation_reason' => $current->invalidation_reason,
                'actor_id' => $actorId,
                'replaced_at' => now()->toIso8601String(),
            ];
            $this->db->table('fiscal_number_reservations')->where('id', $replacement->id)->update([
                'parent_reservation_id' => $current->id, 'status' => 'awaiting_print',
                'fiscal_snapshot' => json_encode($physical, JSON_THROW_ON_ERROR), 'updated_at' => now(),
            ]);
            $replacement = $this->db->table('fiscal_number_reservations')->find($replacement->id);
            $rows = $this->db->table($isDispatch ? 'dispatch_items' : 'document_items')
                ->where($isDispatch ? 'dispatch_id' : 'document_id', $subjectId)->count();
            FiscalPdfData::assertItemCapacity($physical, $rows);
            $validatePrint($replacement, $subject);
            $this->db->table('fiscal_numbering_audits')->insert([
                'actor_id' => $actorId, 'action' => 'replace_print', 'entity_type' => 'reservation', 'entity_id' => $current->id,
                'changed_fields' => json_encode(['parent_reservation_id', 'document_number', 'control_number']), 'created_at' => now(),
            ]);
            return $replacement;
        });
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
