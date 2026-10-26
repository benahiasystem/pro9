<?php

namespace Tests\Unit;

use Maatwebsite\Excel\Excel;
use Modules\Account\Exports\ReportAccountingEjbExport;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Tests\TestCase;

class RetiredBagTaxExportTest extends TestCase
{
    public function test_ejb_workbook_keeps_columns_and_identifiers_aligned_without_the_tax(): void
    {
        $export = (new ReportAccountingEjbExport())->data(['records' => collect([$this->ejbRow()])]);
        $path = tempnam(sys_get_temp_dir(), 'pro9-ejb-export-');
        try {
            file_put_contents($path, app('excel')->raw($export, Excel::XLSX));
            $book = IOFactory::load($path);
            $sheet = $book->getActiveSheet();
            self::assertSame('AB', $sheet->getHighestDataColumn());
            self::assertSame('Cuenta de venta', $sheet->getCell('M1')->getValue());
            self::assertSame(70121, $sheet->getCell('M2')->getValue());
            foreach (['A2' => 'J000000001', 'D2' => '00000042', 'Q2' => '00000007', 'S2' => '0010'] as $cell => $value) {
                self::assertSame($value, $sheet->getCell($cell)->getValue(), $cell);
                self::assertSame(DataType::TYPE_STRING, $sheet->getCell($cell)->getDataType(), $cell);
                self::assertSame(NumberFormat::FORMAT_TEXT, $sheet->getStyle($cell)->getNumberFormat()->getFormatCode(), $cell);
            }
            self::assertSame('cuenta por Cobrar', $sheet->getCell('T1')->getValue());
            self::assertSame(NumberFormat::FORMAT_TEXT, $sheet->getStyle('T2')->getNumberFormat()->getFormatCode());
            self::assertSame('Importe de Pago Automatico', $sheet->getCell('AB1')->getValue());
            self::assertEquals(116, $sheet->getCell('AB2')->getValue());
            self::assertStringNotContainsString('bolsa', strtolower(json_encode($sheet->toArray())));
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }

    public function test_ejb_blade_has_the_same_column_alignment_as_the_workbook(): void
    {
        $html = view('tenant.account.accounting.templates.excel_ejb', ['records' => [$this->ejbRow()]])->render();
        $xpath = $this->tableXpath($html);
        $headings = $xpath->query('//tr/th');
        $cells = $xpath->query('//tr/td');
        self::assertCount(28, $headings);
        self::assertCount(28, $cells);
        self::assertSame('Cuenta de venta', trim($headings->item(12)->textContent));
        self::assertSame('70121', trim($cells->item(12)->textContent));
        self::assertSame('00000007', trim($cells->item(16)->textContent));
        self::assertSame('0010', trim($cells->item(18)->textContent));
    }

    public function test_adsoft_header_and_data_no_longer_have_an_extra_tax_cell(): void
    {
        $keys = ['serie', 'numero', 'fecfac', 'fecven', 'nro_ruc', 'nombre', 'tipdoc', 'tipmon',
            'detrac', 'imp_vta', 'isc', 'imp_ina', 'imp_exo', 'imp_exp', 'recargo', 'imp_igv',
            'imp_tot', 'st', 'ser_dqm', 'nro_dqm', 'fec_dqm', 'tip_dqm', 'serie_fin', 'numero_fin',
            'nro_dni', 'pasaporte', 'cta_vta', 'tip_cam'];
        $row = array_combine($keys, $keys);
        $xpath = $this->tableXpath(view('account::accounting.templates.excel_adsoft', ['records' => [$row]])->render());
        $headings = $xpath->query('//thead/tr[2]/th');
        $cells = $xpath->query('//tr/td');
        self::assertCount(28, $headings);
        self::assertCount(28, $cells);
        self::assertSame('28', $xpath->query('//thead/tr[1]/th')->item(0)->getAttribute('colspan'));
        foreach ($keys as $index => $key) {
            self::assertSame(strtoupper($key), trim($headings->item($index)->textContent));
            self::assertSame($key, trim($cells->item($index)->textContent));
        }
    }

    public function test_the_retired_configuration_endpoint_and_writers_are_absent(): void
    {
        $uris = array_map(static fn ($route) => $route->uri(), iterator_to_array(app('router')->getRoutes()));
        self::assertNotContains('configurations/icbper', $uris);
        self::assertFalse(method_exists(\App\Http\Controllers\Tenant\ConfigurationController::class, 'icbper'));
        // This legacy copy declares the tenant class; loading it would redeclare that class.
        self::assertStringNotContainsString('function icbper', file_get_contents(base_path('modules/Restaurant/Models/ConfigurationController.php')));
    }

    private function tableXpath(string $html): \DOMXPath
    {
        $document = new \DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        return new \DOMXPath($document);
    }

    private function ejbRow(): array
    {
        return [
            'customer_number' => 'J000000001', 'document_type' => '01', 'series' => 'FF01', 'number' => '00000042',
            'date_of_issue_excel' => '2026-10-24', 'date_of_due_excel' => '2026-10-24', 'currency_type_id' => 'VES',
            'total_igv' => 16, 'total' => 116, 'total_unaffected' => 0, 'others' => 0, 'income_account' => '70121',
            'ref_date_excel' => '2026-10-23', 'ref_document_type' => '01', 'ref_series' => 'FF01', 'ref_number' => '00000007',
            'cost_center' => '01', 'subdiary' => '0010', 'receivable' => '1212', 'gloss' => 'Venta de prueba',
            'automatic_payment_account' => '101', 'automatic_payment_document_number' => '00000042', 'automatic_payment_amount' => 116,
        ];
    }
}
