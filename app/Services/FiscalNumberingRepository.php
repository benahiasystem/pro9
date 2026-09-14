<?php

namespace App\Services;

use Illuminate\Database\ConnectionInterface;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
/** All callers must pass the current tenant connection, never the system connection. */
final class FiscalNumberingRepository
{
    private ConnectionInterface $db;

    public function __construct(ConnectionInterface $connection)
    {
        $this->db = $connection;
    }

    public function createSequence(string $documentType, string $seriesCode, int $initialNumber, ?int $establishmentId): int
    {
        if (!in_array($documentType, ['01', '07', '08', '09'], true)) {
            throw new \InvalidArgumentException('Tipo documental sin soporte de numeración fiscal.');
        }
        if (!preg_match('/\A[A-Z0-9-]{0,32}\z/', $seriesCode) || $initialNumber < 1 || $initialNumber >= PHP_INT_MAX) {
            throw new \InvalidArgumentException('Serie o número inicial inválido.');
        }
        return $this->db->transaction(function () use ($documentType, $seriesCode, $initialNumber, $establishmentId) {
            $this->lockIssuer();
            if ($establishmentId !== null) {
                $this->assertEstablishment($establishmentId);
            }
            return $this->db->table('fiscal_sequences')->insertGetId([
                'document_type_id' => $documentType, 'series_code' => $seriesCode,
                'establishment_id' => $establishmentId, 'initial_number' => $initialNumber,
                'next_number' => $initialNumber, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function changeInitialNumber(int $sequenceId, int $number): void
    {
        if ($number < 1 || $number >= PHP_INT_MAX) {
            throw new \InvalidArgumentException('El número inicial debe ser un entero positivo dentro del rango admitido.');
        }
        $this->db->transaction(function () use ($sequenceId, $number) {
            $this->lockIssuer();
            $sequence = $this->sequence($sequenceId);
            if ($sequence->in_use) {
                throw new \DomainException('La numeración ya fue utilizada y no puede modificarse.');
            }
            $this->db->table('fiscal_sequences')->where('id', $sequenceId)->update([
                'initial_number' => $number, 'next_number' => $number, 'updated_at' => now(),
            ]);
        });
    }

    public function createLot(array $data): int
    {
        $start = new FiscalControlNumber($data['start'] ?? '');
        $end = new FiscalControlNumber($data['end'] ?? '');
        if ($start->ordinal() > $end->ordinal()) {
            throw new \InvalidArgumentException('Rango de controles invertido.');
        }
        foreach (['printer_name', 'printer_rif', 'authorization', 'authorization_date', 'prepared_at'] as $field) {
            if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '') {
                throw new \InvalidArgumentException('Falta el dato de imprenta: ' . $field);
            }
        }
        foreach (['authorization_date', 'prepared_at'] as $field) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $data[$field]);
            if (!$date || $date->format('Y-m-d') !== $data[$field]) {
                throw new \InvalidArgumentException('Fecha de imprenta inválida: ' . $field);
            }
        }
        return $this->db->transaction(function () use ($data, $start, $end) {
            $this->lockIssuer();
            $this->assertEstablishment((int) ($data['establishment_id'] ?? 0));
            if ($this->db->table('fiscal_control_lots')->where('start_ordinal', '<=', $end->ordinal())->where('end_ordinal', '>=', $start->ordinal())->exists()) {
                throw new \DomainException('El rango se superpone con controles ya registrados para este emisor.');
            }
            return $this->db->table('fiscal_control_lots')->insertGetId([
                'establishment_id' => $data['establishment_id'],
                'printer_name' => $data['printer_name'], 'printer_rif' => $data['printer_rif'],
                'authorization' => $data['authorization'], 'authorization_date' => $data['authorization_date'],
                'prepared_at' => $data['prepared_at'], 'start_ordinal' => $start->ordinal(),
                'end_ordinal' => $end->ordinal(), 'next_ordinal' => $start->ordinal(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    /** Reservation is durable; subsequent provider/printing failures must not delete it. */
    public function reserve(int $sequenceId, string $operationKey, string $fingerprint, int $establishmentId, ?int $lotId = null): object
    {
        if (!preg_match('/\A[a-zA-Z0-9_-]{1,128}\z/', $operationKey) || !preg_match('/\A[a-f0-9]{64}\z/', $fingerprint)) {
            throw new \InvalidArgumentException('Clave de operación o huella inválida.');
        }
        return $this->db->transaction(function () use ($sequenceId, $operationKey, $fingerprint, $establishmentId, $lotId) {
            $company = $this->lockIssuer();
            $this->assertEstablishment($establishmentId);
            $existing = $this->db->table('fiscal_number_reservations')->where('operation_key', $operationKey)->first();
            if ($existing) {
                $snapshot = json_decode($existing->fiscal_snapshot, true, 512, JSON_THROW_ON_ERROR);
                if ((int) $existing->sequence_id !== $sequenceId || $existing->payload_fingerprint !== $fingerprint || $existing->control_lot_id != $lotId || (int) $snapshot['establishment_id'] !== $establishmentId) {
                    throw new \DomainException('La clave de operación ya corresponde a otro documento.');
                }
                return $existing;
            }
            $sequence = $this->sequence($sequenceId);
            if (!$sequence->active || ($sequence->establishment_id !== null && (int) $sequence->establishment_id !== $establishmentId)) {
                throw new \DomainException('La numeración no está disponible para esta sucursal.');
            }
            if ((int) $sequence->next_number >= PHP_INT_MAX) {
                throw new \DomainException('Numeración documental agotada.');
            }
            $snapshot = ['establishment_id' => $establishmentId, 'series' => $sequence->series_code, 'document_type_id' => $sequence->document_type_id, 'environment' => $company->fiscal_environment];
            $snapshot['issuer'] = array_intersect_key((array) $company, array_flip(['name', 'trade_name', 'number']));
            $control = null;
            if ($lotId !== null) {
                $lot = $this->db->table('fiscal_control_lots')->where('id', $lotId)->lockForUpdate()->first();
                if (!$lot || !$lot->active || (int) $lot->establishment_id !== $establishmentId || $lot->next_ordinal > $lot->end_ordinal) {
                    throw new \DomainException('Lote no disponible o agotado para esta sucursal.');
                }
                $ordinal = (int) $lot->next_ordinal;
                $control = (string) new FiscalControlNumber(sprintf('%02d-%d', intdiv($ordinal, 100000000), $ordinal % 100000000));
                $next = $ordinal + 1;
                if ($next % 100000000 === 0) {
                    $next++;
                }
                $this->db->table('fiscal_control_lots')->where('id', $lotId)->update(['next_ordinal' => $next, 'updated_at' => now()]);
                $snapshot['lot'] = (array) $lot;
            }
            $id = $this->db->table('fiscal_number_reservations')->insertGetId([
                'operation_key' => $operationKey, 'payload_fingerprint' => $fingerprint,
                'sequence_id' => $sequenceId, 'document_number' => $sequence->next_number,
                'control_lot_id' => $lotId, 'control_number' => $control,
                'fiscal_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->db->table('fiscal_sequences')->where('id', $sequenceId)->update(['next_number' => (int) $sequence->next_number + 1, 'in_use' => true, 'updated_at' => now()]);
            $this->db->table('companies')->where('id', $company->id)->update(['fiscal_environment_locked' => true]);
            return $this->db->table('fiscal_number_reservations')->where('id', $id)->first();
        });
    }

    private function sequence(int $id): object
    {
        $sequence = $this->db->table('fiscal_sequences')->where('id', $id)->lockForUpdate()->first();
        if (!$sequence) {
            throw new \DomainException('Numeración inexistente.');
        }
        return $sequence;
    }

    public function reserveForProfile(int $profileId, string $operationKey, string $fingerprint, int $establishmentId, string $channel, ?int $groupId = null): object
    {
        return $this->db->transaction(function () use ($profileId, $operationKey, $fingerprint, $establishmentId, $channel, $groupId) {
            $company = $this->lockIssuer();
            $profile = $this->db->table('fiscal_profiles')->where('id', $profileId)->where('establishment_id', $establishmentId)->first();
            if (!$profile || $profile->channel !== $channel || $profile->device_group_id != $groupId) {
                throw new \DomainException('El perfil no corresponde al canal y punto de emisión autorizados.');
            }
            $previous = $this->db->table('fiscal_number_reservations')->where('operation_key', $operationKey)->first();
            if ($previous) {
                if ((int) $previous->profile_id !== $profileId || $previous->payload_fingerprint !== $fingerprint) {
                    throw new \DomainException('La operación ya fue reservada con otro perfil o contenido.');
                }
                return $previous;
            }
            $profiles = new FiscalProfileService($this->db);
            $profiles->assertReady($profile, $company->fiscal_environment);
            $reservation = $this->reserve((int) $profile->sequence_id, $operationKey, $fingerprint, $establishmentId, $profile->control_lot_id ? (int) $profile->control_lot_id : null);
            $snapshot = json_decode($reservation->fiscal_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $snapshot['profile'] = $profiles->publicProfile($profile);
            unset($snapshot['profile']['credentials_configured']);
            $snapshot['mode'] = $profile->mode;
            $snapshot['channel'] = $channel;
            $this->db->table('fiscal_number_reservations')->where('id', $reservation->id)->update([
                'profile_id' => $profileId, 'fiscal_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            ]);
            return $this->db->table('fiscal_number_reservations')->where('id', $reservation->id)->first();
        });
    }

    private function lockIssuer(): object
    {
        $company = $this->db->table('companies')->orderBy('id')->lockForUpdate()->first();
        if (!$company) {
            throw new \DomainException('El emisor no está configurado.');
        }
        return $company;
    }

    private function assertEstablishment(int $id): void
    {
        if (!$this->db->table('establishments')->where('id', $id)->exists()) {
            throw new \DomainException('Sucursal inexistente.');
        }
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
