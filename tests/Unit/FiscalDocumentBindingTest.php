<?php

namespace Tests\Unit;

use App\Models\Tenant\Document;
use App\Services\Fiscal\FiscalDocumentBinding;
use App\Services\FiscalProfileService;
use Tests\Support\FiscalDatabaseTestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalDocumentBindingTest extends FiscalDatabaseTestCase
{
    private function reservation(): object
    {
        $profile = (new FiscalProfileService($this->db))->save(1, [
            'name' => 'Demo', 'channel' => 'digital', 'document_type_id' => '01', 'mode' => 'digital',
            'sequence_id' => $this->repository->createSequence('01', 'A', 25, 1),
            'provider' => 'simulator', 'configuration' => [], 'active' => true,
        ], 1);
        return $this->repository->reserveForProfile($profile['id'], 'sale', hash('sha256', 'sale'), 1, 'digital');
    }

    private function document(int $reservationId): Document
    {
        $document = $this->getMockBuilder(Document::class)->onlyMethods(['getConnection'])->getMock();
        $document->method('getConnection')->willReturn($this->db);
        $document->setRawAttributes(['document_type_id' => '01', 'establishment_id' => 1, 'series' => 'UNTRUSTED', 'number' => 900, 'fiscal_emission_mode' => 'fiscal_machine']);
        return $document->useFiscalReservation($reservationId);
    }

    public function test_bound_document_receives_snapshot_identifiers_and_mode(): void
    {
        $document = $this->document($this->reservation()->id);
        FiscalDocumentBinding::apply($document, (object) ['fiscal_environment' => 'demo']);
        $this->assertSame('A', $document->series);
        $this->assertSame(25, (int) $document->number);
        $this->assertSame('digital', $document->fiscal_emission_mode);
        $this->assertSame('demo', $document->fiscal_environment);
        $this->assertFalse($document->isFillable('fiscalReservationId'));
    }

    public function test_reservation_from_other_establishment_cannot_be_bound(): void
    {
        $document = $this->document($this->reservation()->id);
        $document->establishment_id = 2;
        $this->expectException(\DomainException::class);
        FiscalDocumentBinding::apply($document, (object) ['fiscal_environment' => 'demo']);
    }

    public function test_linked_reservation_cannot_create_another_document(): void
    {
        $document = $this->document($this->reservation()->id);
        $this->db->table('fiscal_number_reservations')->update(['document_id' => 1]);
        $this->expectException(\DomainException::class);
        FiscalDocumentBinding::apply($document, (object) ['fiscal_environment' => 'demo']);
    }

    public function test_documents_without_series_display_only_document_number(): void
    {
        foreach ([new Document(), new \App\Models\Tenant\Dispatch()] as $document) {
            $document->setRawAttributes(['series' => '', 'number' => 25]);
            $this->assertSame('25', $document->number_full);
            $document->series = 'SUCURSAL-A';
            $this->assertSame('SUCURSAL-A-25', $document->number_full);
        }
    }

    public function test_delivery_order_uses_reserved_identifiers_without_adding_invoice_only_column(): void
    {
        $profile = (new FiscalProfileService($this->db))->save(1, [
            'name' => 'Orden', 'channel' => 'presential', 'document_type_id' => '09', 'mode' => 'digital',
            'sequence_id' => $this->repository->createSequence('09', '', 300, 1), 'provider' => 'simulator', 'configuration' => [], 'active' => true,
        ], 1);
        $reservation = $this->repository->reserveForProfile($profile['id'], 'delivery', hash('sha256', 'delivery'), 1, 'presential');
        $dispatch = $this->getMockBuilder(\App\Models\Tenant\Dispatch::class)->onlyMethods(['getConnection'])->getMock();
        $dispatch->method('getConnection')->willReturn($this->db);
        $dispatch->setRawAttributes(['document_type_id' => '09', 'establishment_id' => 1, 'series' => 'TT01', 'number' => 999]);
        $dispatch->useFiscalReservation($reservation->id);
        FiscalDocumentBinding::apply($dispatch, (object) ['fiscal_environment' => 'demo', 'number' => 'J000000000']);
        $this->assertSame('', $dispatch->series);
        $this->assertSame(300, (int) $dispatch->number);
        $this->assertStringContainsString('300', $dispatch->filename);
        $this->assertArrayNotHasKey('fiscal_emission_mode', $dispatch->getAttributes());
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
