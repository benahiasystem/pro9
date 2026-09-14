<?php

namespace Tests\Unit;

use App\Services\FiscalNumberingRepository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalNumberingRepositoryTest extends \Tests\Support\FiscalDatabaseTestCase
{
    private function lot(string $start = '00-1', string $end = '00-2', int $establishment = 1): int
    {
        return $this->repository->createLot([
            'establishment_id' => $establishment, 'start' => $start, 'end' => $end,
            'printer_name' => 'Imprenta de prueba', 'printer_rif' => 'J-00000000-0',
            'authorization' => 'DEMO', 'authorization_date' => '2026-01-01', 'prepared_at' => '2026-02-01',
        ]);
    }

    public function test_reserves_independent_document_and_control_numbers_and_retries_idempotently(): void
    {
        $sequence = $this->repository->createSequence('01', 'CAJA-A', 150, 1);
        $lot = $this->lot();
        $first = $this->repository->reserve($sequence, 'sale_1', hash('sha256', 'sale'), 1, $lot);
        $retry = $this->repository->reserve($sequence, 'sale_1', hash('sha256', 'sale'), 1, $lot);
        $this->assertSame($first->id, $retry->id);
        $this->assertSame(150, (int) $first->document_number);
        $this->assertSame('00-00000001', $first->control_number);
        $this->assertSame(151, (int) $this->db->table('fiscal_sequences')->value('next_number'));
        $this->assertSame(2, (int) $this->db->table('fiscal_control_lots')->value('next_ordinal'));
        $this->assertSame(1, (int) $this->db->table('companies')->value('fiscal_environment_locked'));
        $this->assertSame(1, $this->db->table('fiscal_number_reservations')->count());
    }

    public function test_idempotency_key_cannot_be_reused_for_changed_payload(): void
    {
        $sequence = $this->repository->createSequence('01', '', 1, null);
        $this->repository->reserve($sequence, 'sale', hash('sha256', 'a'), 1);
        $this->expectException(\DomainException::class);
        $this->repository->reserve($sequence, 'sale', hash('sha256', 'b'), 1);
    }

    public function test_lots_cannot_overlap_even_in_another_establishment(): void
    {
        $this->lot('00-10', '00-20');
        $this->expectException(\DomainException::class);
        $this->lot('00-20', '00-30', 2);
    }

    public function test_exhaustion_does_not_advance_document_sequence(): void
    {
        $sequence = $this->repository->createSequence('01', '', 1, null);
        $lot = $this->lot('00-1', '00-1');
        $this->repository->reserve($sequence, 'first', hash('sha256', 'a'), 1, $lot);
        try {
            $this->repository->reserve($sequence, 'second', hash('sha256', 'b'), 1, $lot);
            $this->fail('Debe rechazar el lote agotado');
        } catch (\DomainException $e) {
            $this->assertSame(2, (int) $this->db->table('fiscal_sequences')->value('next_number'));
            $this->assertSame(1, $this->db->table('fiscal_number_reservations')->count());
        }
    }

    public function test_cannot_rewind_sequence_after_use(): void
    {
        $sequence = $this->repository->createSequence('01', '', 1, null);
        $this->repository->changeInitialNumber($sequence, 10);
        $result = $this->repository->reserve($sequence, 'first', hash('sha256', 'a'), 1);
        $this->assertSame(10, (int) $result->document_number);
        $this->expectException(\DomainException::class);
        $this->repository->changeInitialNumber($sequence, 1);
    }

    public function test_cannot_consume_another_establishments_lot(): void
    {
        $sequence = $this->repository->createSequence('01', '', 1, null);
        $lot = $this->lot();
        $this->expectException(\DomainException::class);
        $this->repository->reserve($sequence, 'first', hash('sha256', 'a'), 2, $lot);
    }

    public function test_document_types_share_the_issuers_control_sequence(): void
    {
        $invoice = $this->repository->createSequence('01', '', 1, null);
        $note = $this->repository->createSequence('07', '', 1, null);
        $lot = $this->lot();
        $a = $this->repository->reserve($invoice, 'invoice', hash('sha256', 'a'), 1, $lot);
        $b = $this->repository->reserve($note, 'note', hash('sha256', 'b'), 1, $lot);
        $this->assertSame(1, (int) $b->document_number);
        $this->assertNotSame($a->control_number, $b->control_number);
        $this->assertSame('00-00000002', $b->control_number);
    }

    public function test_authorized_range_crosses_identifier_boundary_without_using_zero(): void
    {
        $sequence = $this->repository->createSequence('01', '', 1, null);
        $lot = $this->lot('00-99999999', '01-1');
        $this->repository->reserve($sequence, 'first', hash('sha256', 'a'), 1, $lot);
        $second = $this->repository->reserve($sequence, 'second', hash('sha256', 'b'), 1, $lot);
        $this->assertSame('01-00000001', $second->control_number);
    }

    public function test_sequence_for_other_establishment_is_rejected(): void
    {
        $sequence = $this->repository->createSequence('01', 'A', 1, 1);
        $this->expectException(\DomainException::class);
        $this->repository->reserve($sequence, 'first', hash('sha256', 'a'), 2);
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
