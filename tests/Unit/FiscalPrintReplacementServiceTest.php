<?php

namespace Tests\Unit;

use App\Services\Fiscal\FiscalEmissionService;
use App\Services\Fiscal\FiscalPrintReplacementService;
use App\Services\Fiscal\FiscalReservation;
use App\Services\FiscalProfileService;
use Tests\Support\FiscalDatabaseTestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalPrintReplacementServiceTest extends FiscalDatabaseTestCase
{
    private object $root;
    private int $profileId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->table('users')->insert(['id' => 1, 'name' => 'Admin', 'type' => 'admin', 'active' => 1, 'establishment_id' => 1]);
        $this->db->statement('CREATE TABLE documents (id INTEGER PRIMARY KEY, establishment_id INTEGER, document_type_id TEXT, fiscal_environment TEXT, state_type_id TEXT)');
        $this->db->statement('CREATE TABLE document_items (id INTEGER PRIMARY KEY, document_id INTEGER)');
        $this->db->table('documents')->insert(['id' => 1, 'establishment_id' => 1, 'document_type_id' => '01', 'fiscal_environment' => 'demo', 'state_type_id' => '01']);
        $this->db->table('document_items')->insert(['id' => 1, 'document_id' => 1]);
        $lot = $this->repository->createLot(['establishment_id' => 1, 'start' => '00-1', 'end' => '00-10',
            'printer_name' => 'Test', 'printer_rif' => 'J-00000000-0', 'authorization' => 'DEMO',
            'authorization_date' => '2026-01-01', 'prepared_at' => '2026-02-01']);
        $profile = (new FiscalProfileService($this->db))->save(1, [
            'name' => 'Forma libre', 'channel' => 'presential', 'document_type_id' => '01', 'mode' => 'free_form',
            'sequence_id' => $this->repository->createSequence('01', 'F', 1, 1), 'provider' => 'none',
            'control_lot_id' => $lot, 'configuration' => ['page_capacity' => 10], 'active' => true,
        ], 1);
        $this->profileId = $profile['id'];
        $this->root = $this->repository->reserveForProfile($this->profileId, 'sale', hash('sha256', 'sale'), 1, 'presential');
        $this->db->table('fiscal_number_reservations')->where('id', $this->root->id)->update(['document_id' => 1]);
        $emission = new FiscalEmissionService($this->db);
        $emission->process($this->root->id);
        $emission->invalidatePrint($this->root->id, 1, 'Papel dañado');
    }

    private function replace(?callable $validator = null): object
    {
        return (new FiscalPrintReplacementService($this->db))->replace(
            $this->root->id, $this->profileId, 1, $validator ?? static function () {}
        );
    }

    public function test_replacement_preserves_one_sale_and_retry_does_not_consume_more_numbers(): void
    {
        $checks = 0;
        $replacement = $this->replace(function ($physical, $subject) use (&$checks) {
            $checks++;
            self::assertSame(1, (int) $subject->id);
            self::assertSame('awaiting_print', $physical->status);
        });
        $retry = $this->replace();
        self::assertSame($replacement->id, $retry->id);
        self::assertSame(1, $checks);
        self::assertSame(1, $this->db->table('documents')->count());
        self::assertSame(1, $this->db->table('document_items')->count());
        self::assertSame(2, $this->db->table('fiscal_number_reservations')->count());
        self::assertSame(3, (int) $this->db->table('fiscal_sequences')->value('next_number'));
        self::assertSame(3, (int) $this->db->table('fiscal_control_lots')->value('next_ordinal'));
        self::assertSame('00-00000002', $replacement->control_number);
        self::assertSame('Papel dañado', json_decode($replacement->fiscal_snapshot, true)['print_replacement']['invalidation_reason']);
        self::assertSame(1, $this->db->table('fiscal_numbering_audits')->where('action', 'replace_print')->count());
        $root = $this->db->table('fiscal_number_reservations')->find($this->root->id);
        self::assertSame($replacement->id, FiscalReservation::effective($this->db, $root)->id);
        $emission = new FiscalEmissionService($this->db);
        self::assertSame('issued', $emission->confirmPrinted($root->id, 1)->status);
        self::assertSame(1, $this->db->table('fiscal_numbering_audits')->where('action', 'confirm_print')->count());
    }

    public function test_second_damaged_form_creates_a_chain_without_reusing_controls(): void
    {
        $first = $this->replace();
        (new FiscalEmissionService($this->db))->invalidatePrint($this->root->id, 1, 'Segundo papel dañado');
        $second = $this->replace();
        self::assertNotSame($first->id, $second->id);
        self::assertSame($first->id, (int) $second->parent_reservation_id);
        self::assertSame('00-00000003', $second->control_number);
        self::assertSame('inutilized', $this->db->table('fiscal_number_reservations')->where('id', $first->id)->value('status'));
        self::assertSame(1, $this->db->table('documents')->count());
        self::assertSame(3, $this->db->table('fiscal_number_reservations')->count());
        self::assertSame($second->id, FiscalReservation::effective($this->db, $this->db->table('fiscal_number_reservations')->find($this->root->id))->id);
    }

    public function test_render_failure_rolls_back_new_control_and_document_number(): void
    {
        try {
            $this->replace(static function () { throw new \DomainException('No cabe en la hoja'); });
            self::fail('Debe revertir el reemplazo.');
        } catch (\DomainException $exception) {
            self::assertSame('No cabe en la hoja', $exception->getMessage());
        }
        self::assertSame(1, $this->db->table('fiscal_number_reservations')->count());
        self::assertSame(2, (int) $this->db->table('fiscal_sequences')->value('next_number'));
        self::assertSame(2, (int) $this->db->table('fiscal_control_lots')->value('next_ordinal'));
        self::assertSame(0, $this->db->table('fiscal_numbering_audits')->where('action', 'replace_print')->count());
    }

    public function test_only_active_branch_administrator_can_replace_print(): void
    {
        $this->db->table('users')->where('id', 1)->update(['type' => 'seller']);
        $this->expectException(\DomainException::class);
        $this->replace();
    }

    public function test_confirmed_document_cannot_receive_a_replacement(): void
    {
        $this->db->table('fiscal_number_reservations')->where('id', $this->root->id)->update(['status' => 'issued']);
        $this->expectException(\DomainException::class);
        $this->replace();
    }

    public function test_exhausted_lot_leaves_the_inutilized_record_unchanged(): void
    {
        $this->db->table('fiscal_control_lots')->update(['next_ordinal' => 11]);
        try {
            $this->replace();
            self::fail('Debe requerir otro lote.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString('lote de controles disponible', $exception->getMessage());
        }
        self::assertSame('inutilized', $this->db->table('fiscal_number_reservations')->where('id', $this->root->id)->value('status'));
        self::assertSame(1, $this->db->table('fiscal_number_reservations')->count());
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
