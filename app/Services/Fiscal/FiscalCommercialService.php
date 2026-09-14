<?php

namespace App\Services\Fiscal;

use App\Services\FiscalNumberingRepository;
use Illuminate\Database\ConnectionInterface;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
/** Coordinates the reservation and ALL commercial database effects on one tenant connection. */
final class FiscalCommercialService
{
    private ConnectionInterface $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    /**
     * The writer receives server-assigned identifiers and returns the persisted subject ID.
     * It must write items/payments/inventory on this connection and defer external effects.
     * Channel and establishment are resolved by the authorized caller, not by request overrides.
     */
    public function register(int $profileId, string $operationKey, string $fingerprint, int $establishmentId, string $channel, callable $writer, ?int $deviceGroupId = null, ?callable $beforeCommit = null): object
    {
        return $this->db->transaction(function () use ($profileId, $operationKey, $fingerprint, $establishmentId, $channel, $writer, $deviceGroupId, $beforeCommit) {
            $reservation = (new FiscalNumberingRepository($this->db))->reserveForProfile(
                $profileId, $operationKey, $fingerprint, $establishmentId, $channel, $deviceGroupId
            );
            $snapshot = json_decode($reservation->fiscal_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $isDispatch = $snapshot['document_type_id'] === '09';
            $table = $isDispatch ? 'dispatches' : 'documents';
            $foreignKey = $isDispatch ? 'dispatch_id' : 'document_id';
            $otherKey = $isDispatch ? 'document_id' : 'dispatch_id';
            if ($reservation->{$otherKey}) {
                throw new \DomainException('La reserva está asociada a otro tipo de documento.');
            }
            if ($reservation->{$foreignKey}) {
                $this->assertSubject($table, (int) $reservation->{$foreignKey}, $reservation, $snapshot);
                return $reservation;
            }
            if ($reservation->status !== 'reserved') {
                throw new \DomainException('La reserva no admite una nueva operación comercial.');
            }
            $subjectId = $writer([
                'series' => $snapshot['series'], 'number' => (int) $reservation->document_number,
                'document_type_id' => $snapshot['document_type_id'], 'establishment_id' => $establishmentId,
                'fiscal_environment' => $snapshot['environment'], 'fiscal_emission_mode' => $snapshot['mode'],
            ], (int) $reservation->id);
            if (!is_int($subjectId) || $subjectId < 1) {
                throw new \DomainException('El registro comercial debe devolver el ID del documento persistido.');
            }
            $this->assertSubject($table, $subjectId, $reservation, $snapshot);
            $this->db->table('fiscal_number_reservations')->where('id', $reservation->id)->update([
                $foreignKey => $subjectId, 'updated_at' => now(),
            ]);
            $reservation = $this->db->table('fiscal_number_reservations')->find($reservation->id);
            // Render/validate the linked draft without publishing files or external effects.
            // A validation failure rolls back identifiers and every commercial database write.
            if ($beforeCommit) $beforeCommit($reservation);
            return $reservation;
        });
    }

    private function assertSubject(string $table, int $id, object $reservation, array $snapshot): void
    {
        $subject = $this->db->table($table)->where('id', $id)->first();
        if (!$subject || (int) $subject->establishment_id !== (int) $snapshot['establishment_id']
            || $subject->document_type_id !== $snapshot['document_type_id']
            || $subject->series !== $snapshot['series'] || (int) $subject->number !== (int) $reservation->document_number
            || $subject->fiscal_environment !== $snapshot['environment']
            || ($table === 'documents' && $subject->fiscal_emission_mode !== $snapshot['mode'])) {
            throw new \DomainException('El documento comercial no coincide con la reserva fiscal.');
        }
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
