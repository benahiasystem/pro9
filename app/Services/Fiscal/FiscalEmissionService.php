<?php

namespace App\Services\Fiscal;

use Illuminate\Database\ConnectionInterface;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
/** Executes committed reservations. Controllers must authorize the tenant and subject first. */
final class FiscalEmissionService
{
    private ConnectionInterface $db;
    private FiscalAdapter $simulator;

    public function __construct(ConnectionInterface $db, ?FiscalAdapter $simulator = null)
    {
        $this->db = $db;
        $this->simulator = $simulator ?? new SimulatedFiscalAdapter($db);
    }

    /** Reservations are the durable emission queue; each entry is visited once per sweep. */
    public function recoverPending(): array
    {
        if ($this->db->transactionLevel() !== 0) {
            throw new \DomainException('La recuperación requiere operaciones comerciales confirmadas.');
        }
        $result = ['visited' => 0, 'states' => [], 'failed' => 0, 'failed_ids' => []];
        $lastId = (int) $this->db->table('fiscal_number_reservations')->max('id');
        $this->db->table('fiscal_number_reservations')
            ->where('id', '<=', $lastId)
            ->whereIn('status', ['reserved', 'processing', 'uncertain'])
            ->where(fn ($query) => $query->whereNotNull('document_id')->orWhereNotNull('dispatch_id'))
            ->select('id')->chunkById(100, function ($rows) use (&$result) {
                foreach ($rows as $row) {
                    $result['visited']++;
                    try {
                        // process claims under lock, then consults/emits outside the transaction.
                        $status = $this->process((int) $row->id)->status;
                        $result['states'][$status] = ($result['states'][$status] ?? 0) + 1;
                    } catch (\Throwable $exception) {
                        // Continue past damaged entries; never expose provider payloads or secrets.
                        $result['failed']++;
                        if (count($result['failed_ids']) < 100) $result['failed_ids'][] = (int) $row->id;
                    }
                }
            });
        return $result;
    }

    public function process(int $id): object
    {
        if ($this->db->transactionLevel() !== 0) {
            throw new \DomainException('La operación comercial debe estar confirmada antes de emitir.');
        }
        $claim = $this->db->transaction(function () use ($id) {
            $reservation = $this->reservation($id);
            if (in_array($reservation->status, ['issued', 'rejected', 'inutilized', 'awaiting_print', 'contingency'], true)) {
                return null;
            }
            if (!$reservation->document_id && !$reservation->dispatch_id) {
                throw new \DomainException('La reserva todavía no tiene un documento comercial.');
            }
            $snapshot = json_decode($reservation->fiscal_snapshot, true, 512, JSON_THROW_ON_ERROR);
            if (($snapshot['mode'] ?? null) === 'free_form') {
                $this->db->table('fiscal_number_reservations')->where('id', $id)->update(['status' => 'awaiting_print', 'updated_at' => now()]);
                return null;
            }
            if (($snapshot['environment'] ?? null) !== 'demo' || ($snapshot['profile']['provider'] ?? null) !== 'simulator') {
                throw new \DomainException('No existe un adaptador fiscal de producción integrado.');
            }
            $action = 'emit';
            if ($reservation->status === 'processing') {
                $previous = $this->db->table('fiscal_emission_attempts')->find($reservation->current_attempt_id);
                if ($previous && \Carbon\Carbon::parse($previous->created_at)->gt(now()->subSeconds(120))) {
                    return null;
                }
                $action = 'lookup';
            } elseif ($reservation->status === 'uncertain') {
                $action = 'lookup';
            } elseif ($reservation->status !== 'reserved') {
                throw new \DomainException('Estado fiscal no procesable.');
            }
            $attempt = $this->db->table('fiscal_emission_attempts')->insertGetId([
                'reservation_id' => $id, 'action' => $action, 'status' => 'processing', 'created_at' => now(),
            ]);
            $this->db->table('fiscal_number_reservations')->where('id', $id)->update([
                'status' => 'processing', 'current_attempt_id' => $attempt, 'updated_at' => now(),
            ]);
            return compact('reservation', 'snapshot', 'action', 'attempt');
        });
        if (!$claim) {
            return FiscalReservation::effective($this->db, $this->db->table('fiscal_number_reservations')->find($id));
        }
        try {
            $result = $this->simulator->{$claim['action']}($claim['reservation'], $claim['snapshot']);
            $result = $this->validatedResult($result, $claim['action']);
        } catch (\Throwable $exception) {
            // Provider exceptions may contain credentials or customer payloads.
            $result = ['status' => 'uncertain', 'reason' => 'Respuesta no confirmada; requiere consulta.'];
        }
        return $this->db->transaction(function () use ($id, $claim, $result) {
            $reservation = $this->reservation($id);
            $this->db->table('fiscal_emission_attempts')->where('id', $claim['attempt'])->update([
                'status' => $result['status'], 'result' => json_encode($result, JSON_THROW_ON_ERROR), 'finished_at' => now(),
            ]);
            // A late response cannot overwrite a newer reconciliation attempt.
            if ((int) $reservation->current_attempt_id === $claim['attempt']) {
                $values = [
                    'status' => $result['status'] === 'not_found' ? 'reserved' : $result['status'],
                    'provider_result' => json_encode($result, JSON_THROW_ON_ERROR), 'updated_at' => now(),
                ];
                if ($result['status'] === 'issued') {
                    $values['issued_at'] = now();
                }
                $this->db->table('fiscal_number_reservations')->where('id', $id)->update($values);
            }
            return $this->db->table('fiscal_number_reservations')->find($id);
        });
    }

    public function confirmPrinted(int $id, int $actorId): object
    {
        return $this->finishPrint($id, $actorId, null);
    }

    public function invalidatePrint(int $id, int $actorId, string $reason): object
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 255) {
            throw new \DomainException('Indique el motivo de inutilización, hasta 255 caracteres.');
        }
        return $this->finishPrint($id, $actorId, $reason);
    }

    private function finishPrint(int $id, int $actorId, ?string $reason): object
    {
        return $this->db->transaction(function () use ($id, $actorId, $reason) {
            $reservation = FiscalReservation::effective($this->db, $this->reservation($id), true);
            $id = (int) $reservation->id;
            $snapshot = json_decode($reservation->fiscal_snapshot, true, 512, JSON_THROW_ON_ERROR);
            if (($snapshot['mode'] ?? null) !== 'free_form' || !$reservation->control_number) {
                throw new \DomainException('La operación requiere un control preimpreso reservado.');
            }
            $target = $reason === null ? 'issued' : 'inutilized';
            if ($reservation->status === $target) {
                return $reservation;
            }
            if ($reservation->status !== 'awaiting_print') {
                throw new \DomainException('El documento no está pendiente de confirmación de impresión.');
            }
            $values = ['status' => $target, 'confirmed_by' => $actorId, 'updated_at' => now()];
            if ($reason === null) {
                $values['issued_at'] = now();
            } else {
                $values['invalidation_reason'] = $reason;
            }
            $this->db->table('fiscal_number_reservations')->where('id', $id)->update($values);
            $this->db->table('fiscal_numbering_audits')->insert([
                'actor_id' => $actorId, 'action' => $reason === null ? 'confirm_print' : 'invalidate_print',
                'entity_type' => 'reservation', 'entity_id' => $id,
                'changed_fields' => json_encode(array_keys($values)), 'created_at' => now(),
            ]);
            return $this->db->table('fiscal_number_reservations')->find($id);
        });
    }

    private function reservation(int $id): object
    {
        $record = $this->db->table('fiscal_number_reservations')->where('id', $id)->lockForUpdate()->first();
        if (!$record) {
            throw new \DomainException('Reserva fiscal inexistente.');
        }
        return $record;
    }

    private function validatedResult(array $result, string $action): array
    {
        if (($result['simulated'] ?? null) !== true || !in_array($result['status'] ?? null, ['issued', 'rejected', 'uncertain', 'not_found'], true)
            || ($result['status'] === 'not_found' && $action !== 'lookup')) {
            throw new \DomainException('Respuesta inválida del simulador.');
        }
        if ($result['status'] === 'issued' && empty($result['provider_reference'])) {
            throw new \DomainException('Falta identificación del resultado.');
        }
        return array_intersect_key($result, array_flip(['status', 'simulated', 'provider_reference', 'document_number', 'issued_at', 'simulated_control', 'device_serial']));
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
