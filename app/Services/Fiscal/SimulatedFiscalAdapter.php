<?php

namespace App\Services\Fiscal;

use Illuminate\Database\ConnectionInterface;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
/** Durable demo receipts allow a lookup after loss of the application's response. */
final class SimulatedFiscalAdapter implements FiscalAdapter
{
    private ConnectionInterface $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    public function emit(object $reservation, array $snapshot): array
    {
        $this->assertDemo($snapshot);
        return $this->db->transaction(function () use ($reservation, $snapshot) {
            $this->db->table('companies')->orderBy('id')->lockForUpdate()->first();
            $existing = $this->lookup($reservation, $snapshot);
            if ($existing['status'] !== 'not_found') {
                return $existing;
            }
            $receipt = [
                'status' => 'issued', 'simulated' => true,
                'provider_reference' => 'DEMO-' . $reservation->id,
                'document_number' => (string) $reservation->document_number,
                'issued_at' => now()->toIso8601String(),
            ];
            if ($snapshot['mode'] === 'digital') {
                $receipt['simulated_control'] = 'SIM-' . $reservation->id;
            } else {
                $receipt['device_serial'] = 'DEMO-MACHINE';
            }
            $this->db->table('fiscal_demo_receipts')->insert([
                'operation_key' => $reservation->operation_key,
                'payload_fingerprint' => $reservation->payload_fingerprint,
                'receipt' => json_encode($receipt, JSON_THROW_ON_ERROR), 'created_at' => now(),
            ]);
            return $receipt;
        });
    }

    public function lookup(object $reservation, array $snapshot): array
    {
        $this->assertDemo($snapshot);
        $record = $this->db->table('fiscal_demo_receipts')->where('operation_key', $reservation->operation_key)->first();
        if (!$record) {
            return ['status' => 'not_found', 'simulated' => true];
        }
        if ($record->payload_fingerprint !== $reservation->payload_fingerprint) {
            throw new \DomainException('La operación simulada ya tiene otro contenido.');
        }
        return json_decode($record->receipt, true, 512, JSON_THROW_ON_ERROR);
    }

    private function assertDemo(array $snapshot): void
    {
        if (($snapshot['environment'] ?? null) !== 'demo') {
            throw new \DomainException('El simulador fiscal está prohibido en producción.');
        }
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
