<?php

namespace Tests\Unit;

use App\Models\Tenant\User;
use App\Services\Fiscal\FiscalNoteReference;
use App\Services\FiscalProfileService;
use Tests\Support\FiscalDatabaseTestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalNoteReferenceTest extends FiscalDatabaseTestCase
{
    private function actor(string $type = 'admin', int $id = 1): User
    {
        $user = new User();
        $user->setRawAttributes(['id' => $id, 'type' => $type, 'establishment_id' => 1]);
        return $user;
    }

    private function context(): array
    {
        return ['establishment_id' => 1, 'customer_id' => 10, 'currency_type_id' => 'VES', 'fiscal_environment' => 'demo', 'date_of_issue' => '2026-09-10'];
    }

    private function invoice(): void
    {
        $this->db->statement('CREATE TABLE documents (id INTEGER PRIMARY KEY, document_type_id TEXT, state_type_id TEXT, establishment_id INTEGER, customer_id INTEGER, currency_type_id TEXT, fiscal_environment TEXT, date_of_issue TEXT, user_id INTEGER, seller_id INTEGER)');
        $this->db->table('documents')->insert(array_merge($this->context(), ['id' => 1, 'document_type_id' => '01', 'state_type_id' => '01', 'date_of_issue' => '2026-09-09', 'user_id' => 1, 'seller_id' => 2]));
        $profile = (new FiscalProfileService($this->db))->save(1, [
            'name' => 'Demo', 'channel' => 'presential', 'document_type_id' => '01', 'mode' => 'digital',
            'sequence_id' => $this->repository->createSequence('01', 'A', 100, 1),
            'provider' => 'simulator', 'configuration' => [], 'active' => true,
        ], 1);
        $this->repository->reserveForProfile($profile['id'], 'invoice', hash('sha256', 'invoice'), 1, 'presential');
        $this->db->table('fiscal_number_reservations')->update(['document_id' => 1, 'status' => 'issued', 'control_number' => '00-00000025']);
    }

    public function test_reference_captures_document_number_control_and_date_separately(): void
    {
        $this->invoice();
        $reference = FiscalNoteReference::capture($this->db, ['affected_document_id' => 1], $this->context(), $this->actor('seller', 2));
        $this->assertSame('A', $reference['series']);
        $this->assertSame('100', $reference['number']);
        $this->assertSame('00-00000025', $reference['control_number']);
        $this->assertSame('2026-09-09', $reference['date_of_issue']);
        $this->assertFalse($reference['external']);
    }

    /** @dataProvider incompatibleContexts */
    public function test_incompatible_invoice_context_is_rejected(string $field, $value): void
    {
        $this->invoice();
        $context = $this->context();
        $context[$field] = $value;
        $this->expectException(\DomainException::class);
        FiscalNoteReference::capture($this->db, ['affected_document_id' => 1], $context, $this->actor());
    }

    public static function incompatibleContexts(): array
    {
        return [['customer_id', 11], ['establishment_id', 2], ['currency_type_id', 'USD'], ['fiscal_environment', 'production'], ['date_of_issue', '2026-09-08']];
    }

    public function test_unconfirmed_invoice_cannot_be_corrected_by_a_fiscal_note(): void
    {
        $this->invoice();
        $this->db->table('fiscal_number_reservations')->update(['status' => 'uncertain']);
        $this->expectException(\DomainException::class);
        FiscalNoteReference::capture($this->db, ['affected_document_id' => 1], $this->context(), $this->actor());
    }

    public function test_seller_cannot_reference_someone_elses_invoice(): void
    {
        $this->invoice();
        $this->expectException(\DomainException::class);
        FiscalNoteReference::capture($this->db, ['affected_document_id' => 1], $this->context(), $this->actor('seller', 3));
    }

    public function test_external_reference_requires_identifiers_instead_of_inventing_control(): void
    {
        $external = ['document_type_id' => '01', 'series' => '', 'number' => '200', 'control_number' => '01-250', 'date_of_issue' => '2026-09-01'];
        $reference = FiscalNoteReference::capture($this->db, ['data_affected_document' => $external], $this->context(), $this->actor());
        $this->assertTrue($reference['external']);
        $this->assertSame('01-00000250', $reference['control_number']);
        $this->assertSame('200', $reference['number']);
        unset($external['control_number']);
        $this->expectException(\InvalidArgumentException::class);
        FiscalNoteReference::capture($this->db, ['data_affected_document' => $external], $this->context(), $this->actor());
    }

    public function test_external_machine_uses_device_registration_without_inventing_control(): void
    {
        $external = ['document_type_id' => '01', 'number' => '20', 'date_of_issue' => '2026-09-01', 'mode' => 'fiscal_machine', 'device_serial' => 'EQUIPO-REFERENCIA'];
        $reference = FiscalNoteReference::capture($this->db, ['data_affected_document' => $external], $this->context(), $this->actor());
        $this->assertSame('EQUIPO-REFERENCIA', $reference['device_serial']);
        $this->assertNull($reference['control_number']);
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
