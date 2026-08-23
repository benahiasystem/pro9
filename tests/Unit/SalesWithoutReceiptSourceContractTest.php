<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

// ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
class SalesWithoutReceiptSourceContractTest extends TestCase
{
    /** @test */
    public function the_common_document_writer_rejects_new_receipts(): void
    {
        $source = $this->source('app/CoreFacturalo/Facturalo.php');

        self::assertStringContainsString('SalesDocumentTypePolicy::assertNewFiscalDocumentAllowed', $source);
    }

    /** @test */
    public function critical_sales_flows_expose_only_their_allowed_types(): void
    {
        $contracts = [
            'modules/Order/Http/Controllers/OrderNoteController.php' => "['01', '80']",
            'modules/Hotel/Http/Controllers/HotelRentController.php' => "['80', '01']",
            'modules/Sale/Http/Controllers/GenerateDocumentController.php' => 'TECHNICAL_SERVICE_DOCUMENT_TYPE_IDS',
            'app/Http/Controllers/Tenant/PosController.php' => "['01', '80']",
        ];

        foreach ($contracts as $file => $expected) {
            self::assertStringContainsString($expected, $this->source($file), $file);
        }
    }

    /** @test */
    public function new_tenant_series_do_not_include_receipt_families(): void
    {
        $source = $this->source('app/Services/SeriesCodeGenerator.php');
        $defaults = $this->between($source, '$keys = [', '];', '$keys = [');

        self::assertStringNotContainsString("'receipt'", $defaults);
        self::assertStringNotContainsString("'credit_note_receipt'", $defaults);
        self::assertStringNotContainsString("'debit_note_receipt'", $defaults);
        self::assertStringContainsString("'sale_note'", $defaults);
    }

    /** @test */
    public function the_maintenance_skill_documents_history_compatibility(): void
    {
        $skill = $this->source('.codex/skills/mantener-facturas-notas-venta-sin-boleta/SKILL.md');
        $contract = $this->source('.codex/skills/mantener-facturas-notas-venta-sin-boleta/references/contrato.md');

        self::assertStringContainsString('SalesDocumentTypePolicy', $skill);
        self::assertStringContainsString('históric', $skill);
        self::assertStringContainsString('BB', $contract);
        self::assertStringContainsString('BC', $contract);
        self::assertStringContainsString('BD', $contract);
    }

    private function source(string $relativePath): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$relativePath);
        self::assertNotFalse($source, $relativePath);

        return $source;
    }

    private function between(string $source, string $start, string $end, string $context): string
    {
        $startPosition = strpos($source, $start);
        self::assertNotFalse($startPosition, $context);
        $endPosition = strpos($source, $end, $startPosition + strlen($start));
        self::assertNotFalse($endPosition, $context);

        return substr($source, $startPosition, $endPosition - $startPosition);
    }
}
// ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA

