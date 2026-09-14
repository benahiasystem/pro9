<?php

namespace Tests\Unit;

use App\Services\Fiscal\FiscalPdfData;
use App\Services\FiscalProfileService;
use Tests\Support\FiscalDatabaseTestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalPdfDataTest extends FiscalDatabaseTestCase
{
    private function reservation(): object
    {
        $this->db->statement('ALTER TABLE companies ADD name TEXT');
        $this->db->table('companies')->update(['name' => 'Emisor original']);
        $lot = $this->repository->createLot([
            'establishment_id' => 1, 'start' => '00-10', 'end' => '00-20', 'printer_name' => 'Imprenta original',
            'printer_rif' => 'J-00000000-0', 'authorization' => 'DEMO', 'authorization_date' => '2026-01-01', 'prepared_at' => '2026-02-01',
        ]);
        $profile = (new FiscalProfileService($this->db))->save(1, [
            'name' => 'Forma libre', 'channel' => 'presential', 'document_type_id' => '01', 'mode' => 'free_form',
            'sequence_id' => $this->repository->createSequence('01', '', 500, 1),
            'control_lot_id' => $lot, 'provider' => 'none', 'configuration' => ['page_capacity' => 10], 'active' => true,
        ], 1);
        return $this->repository->reserveForProfile($profile['id'], 'sale', hash('sha256', 'sale'), 1, 'presential');
    }

    public function test_print_data_preserves_issuer_and_printer_snapshot_after_configuration_changes(): void
    {
        $reservation = $this->reservation();
        $this->db->table('companies')->update(['name' => 'Emisor modificado']);
        $this->db->table('fiscal_control_lots')->update(['printer_name' => 'Imprenta modificada']);
        $fiscal = FiscalPdfData::fromReservation($reservation);
        $this->assertSame('Emisor original', $fiscal['issuer']['name']);
        $this->assertSame('Imprenta original', $fiscal['printer']['name']);
        $this->assertSame('500', $fiscal['document_number']);
        $this->assertSame('00-00000010', $fiscal['control_number']);
        $this->assertSame('00-00000010', $fiscal['printer']['start']);
        $this->assertSame('00-00000020', $fiscal['printer']['end']);
        $company = (object) ['name' => 'Emisor modificado'];
        $copy = FiscalPdfData::issuerForPrint($company, $fiscal);
        $this->assertSame('Emisor original', $copy->name);
        $this->assertSame('Emisor modificado', $company->name);
    }

    public function test_rendered_identity_distinguishes_control_document_and_demo_and_escapes_printer(): void
    {
        $fiscal = FiscalPdfData::fromReservation($this->reservation());
        $fiscal['printer']['name'] = '<script>alert(1)</script>';
        $html = $this->renderIdentity($fiscal);
        $this->assertStringContainsString('DEMO — SIN VALIDEZ FISCAL', $html);
        $this->assertStringContainsString('N° de documento: <strong>500</strong>', $html);
        $this->assertStringContainsString('N° de control: <strong>00-00000010</strong>', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('Serie:', $html);
    }

    public function test_reprint_does_not_consume_a_new_number(): void
    {
        $reservation = $this->reservation();
        $first = $this->renderIdentity(FiscalPdfData::fromReservation($reservation));
        $second = $this->renderIdentity(FiscalPdfData::fromReservation($reservation));
        $this->assertSame($first, $second);
        $this->assertSame(1, $this->db->table('fiscal_number_reservations')->count());
        $this->assertSame(501, (int) $this->db->table('fiscal_sequences')->value('next_number'));
        $this->assertSame(11, (int) $this->db->table('fiscal_control_lots')->value('next_ordinal'));
    }

    public function test_multi_page_free_form_cannot_be_delivered_under_one_control(): void
    {
        $this->expectException(\DomainException::class);
        FiscalPdfData::assertPageCount(['mode' => 'free_form'], 2);
    }

    public function test_note_pdf_includes_the_captured_invoice_control_and_date(): void
    {
        $reservation = $this->reservation();
        $snapshot = json_decode($reservation->fiscal_snapshot, true);
        $snapshot['affected_document'] = ['series' => '', 'number' => '400', 'date_of_issue' => '2026-01-01', 'control_number' => '00-00000009', 'device_serial' => null];
        $reservation->fiscal_snapshot = json_encode($snapshot);
        $html = $this->renderIdentity(FiscalPdfData::fromReservation($reservation));
        $this->assertStringContainsString('Factura afectada:</strong> 400', $html);
        $this->assertStringContainsString('Fecha de factura: 2026-01-01', $html);
        $this->assertStringContainsString('Control de factura: 00-00000009', $html);
    }

    public function test_configured_line_capacity_accepts_exact_boundary(): void
    {
        FiscalPdfData::assertItemCapacity(['mode' => 'free_form', 'profile' => ['configuration' => ['page_capacity' => 3]]], 3);
        $this->addToAssertionCount(1);
    }

    /** @dataProvider rejectedCapacities */
    public function test_line_capacity_rejects_overflow_or_invalid_configuration($capacity, int $rows): void
    {
        $this->expectException(\DomainException::class);
        FiscalPdfData::assertItemCapacity(['mode' => 'free_form', 'profile' => ['configuration' => ['page_capacity' => $capacity]]], $rows);
    }

    public static function rejectedCapacities(): array
    {
        return [[3, 4], [3, 0], [null, 1], [0, 1], ['3.5', 1], [101, 1]];
    }

    public function test_preprinted_line_limit_does_not_apply_to_digital_documents(): void
    {
        FiscalPdfData::assertItemCapacity(['mode' => 'digital'], 200);
        $this->addToAssertionCount(1);
    }

    private function renderIdentity(array $fiscal): string
    {
        $compiler = new \Illuminate\View\Compilers\BladeCompiler(new \Illuminate\Filesystem\Filesystem(), sys_get_temp_dir());
        $source = file_get_contents(dirname(__DIR__, 2) . '/app/CoreFacturalo/Templates/pdf/partials/fiscal_identity.blade.php');
        ob_start();
        try {
            eval('?>' . $compiler->compileString($source));
            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
