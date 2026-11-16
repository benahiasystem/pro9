<?php

namespace Modules\Accounting\Tests;

use PHPUnit\Framework\TestCase;
use Modules\Accounting\Domain\ApiProblem;
use Modules\Accounting\Support\{ImportFileReader, ImportSchema};

final class ImportFileReaderTest extends TestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            unlink($file);
        }
        parent::tearDown();
    }

    private function file(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'accounting-import-reader-');
        file_put_contents($path, $bytes);
        $this->files[] = $path;
        return $path;
    }

    private function xlsx(array $extra = [], bool $date1904 = false): string
    {
        $parts = [
            'xl/workbook.xml' => '<?xml version="1.0"?><workbook xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><workbookPr date1904="' . ($date1904 ? '1' : '0') . '"/><sheets><sheet name="Datos" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<Relationships><Relationship Id="rId1" Target="worksheets/sheet1.xml"/></Relationships>',
            'xl/worksheets/sheet1.xml' => '<worksheet><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>empresa_rif</t></is></c><c r="B1" t="inlineStr"><is><t>referencia_importacion</t></is></c><c r="C1" t="inlineStr"><is><t>fecha_contable</t></is></c><c r="D1" t="inlineStr"><is><t>concepto</t></is></c><c r="E1" t="inlineStr"><is><t>cuenta</t></is></c><c r="F1" t="inlineStr"><is><t>debe_ves</t></is></c><c r="G1" t="inlineStr"><is><t>haber_ves</t></is></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>J-00000000-0</t></is></c><c r="B2" t="inlineStr"><is><t>001</t></is></c><c r="C2"><v>46337</v></c><c r="D2" t="inlineStr"><is><t>Exactitud original</t></is></c><c r="E2" t="inlineStr"><is><t>1.1.02</t></is></c><c r="F2"><v>9007199254740993.12</v></c><c r="G2"><v>1e-12</v></c></row></sheetData></worksheet>',
        ];
        $path = $this->file('');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach (array_replace($parts, $extra) as $name => $bytes) {
            $zip->addFromString($name, $bytes);
        }
        $zip->close();
        return $path;
    }

    public function test_raw_xlsx_numbers_and_excel_dates_never_pass_through_floats(): void
    {
        $row = (new ImportFileReader())->read($this->xlsx(), 'entries', 'xlsx')[0];
        $this->assertSame([], $row['errors']);
        $this->assertSame('9007199254740993.12', $row['data']['debe_ves']);
        $this->assertSame('0.000000000001', $row['data']['haber_ves']);
        $this->assertSame('2026-11-11', $row['data']['fecha_contable']);
        $this->assertSame('001', $row['data']['referencia_importacion']);
        $other = (new ImportFileReader())->read($this->xlsx([], true), 'entries', 'xlsx')[0];
        $this->assertSame('2030-11-12', $other['data']['fecha_contable']);
    }

    public function test_numeric_identifiers_are_rejected_instead_of_reconstructing_leading_zeros(): void
    {
        $sheet = '<worksheet><sheetData><row><c r="A1" t="inlineStr"><is><t>empresa_rif</t></is></c><c r="B1" t="inlineStr"><is><t>referencia_importacion</t></is></c><c r="C1" t="inlineStr"><is><t>rif</t></is></c><c r="D1" t="inlineStr"><is><t>nombre</t></is></c><c r="E1" t="inlineStr"><is><t>tipo</t></is></c></row><row><c r="A2" t="inlineStr"><is><t>J-00000000-0</t></is></c><c r="B2"><v>001</v></c><c r="C2" t="inlineStr"><is><t>J-00000001-0</t></is></c><c r="D2" t="inlineStr"><is><t>Cliente</t></is></c><c r="E2" t="inlineStr"><is><t>customer</t></is></c></row></sheetData></worksheet>';
        $row = (new ImportFileReader())->read($this->xlsx(['xl/worksheets/sheet1.xml' => $sheet]), 'third-parties', 'xlsx')[0];
        $this->assertSame('referencia_importacion', $row['errors'][0]['field']);
        $this->assertSame('IMPORT_TEXT_REQUIRED', $row['errors'][0]['code']);
        $this->assertSame('001', $row['data']['referencia_importacion']);
    }

    public function test_csv_preserves_quoted_multiline_fields_and_reports_physical_rows(): void
    {
        $path = $this->file("\xEF\xBB\xBFempresa_rif;referencia_importacion;rif;nombre;tipo;direccion\r\nJ-00000000-0;P01;J-00000001-0;Cliente;customer;\"Piso 1\r\nOficina 2\"\r\nJ-00000000-0;P02;J-00000002-0;Otro;other;\r\n");
        $rows = (new ImportFileReader())->read($path, 'third-parties', 'csv');
        $this->assertSame([2, 4], array_column($rows, 'row'));
        $this->assertSame("Piso 1\r\nOficina 2", $rows[0]['data']['direccion']);
    }

    public function test_csv_comma_decimals_are_exact_and_thousands_are_rejected(): void
    {
        $reader = new ImportFileReader();
        $this->assertSame('9007199254740993.12', $reader->decimal('9007199254740993,120', ','));
        $this->assertSame('0.000000000001', $reader->decimal('1e-12'));
        $this->expectException(ApiProblem::class);
        $reader->decimal('1,234.56');
    }

    public function test_formulas_anywhere_in_the_workbook_are_rejected_without_evaluation(): void
    {
        $this->expectException(ApiProblem::class);
        $this->expectExceptionMessage('fórmulas');
        (new ImportFileReader())->read($this->xlsx(['xl/worksheets/unused.xml' => '<worksheet><c><f>WEBSERVICE("http://127.0.0.1/")</f><v>1</v></c></worksheet>']), 'entries', 'xlsx');
    }

    public function test_external_relationships_are_rejected_without_network_requests(): void
    {
        $this->expectException(ApiProblem::class);
        $this->expectExceptionMessage('externos');
        (new ImportFileReader())->read($this->xlsx(['xl/_rels/other.xml.rels' => '<Relationships><Relationship Id="x" TargetMode="External" Target="file:///etc/passwd"/></Relationships>']), 'entries', 'xlsx');
    }

    public function test_entity_declarations_are_rejected(): void
    {
        $this->expectException(ApiProblem::class);
        (new ImportFileReader())->read($this->xlsx(['docProps/core.xml' => '<!DOCTYPE a [<!ENTITY x SYSTEM "file:///etc/passwd">]><a>&x;</a>']), 'entries', 'xlsx');
    }

    public function test_path_traversal_and_binary_parts_are_rejected_before_extraction(): void
    {
        $this->expectException(ApiProblem::class);
        (new ImportFileReader())->read($this->xlsx(['../payload.xml' => '<a/>']), 'entries', 'xlsx');
    }

    public function test_unknown_headers_and_invalid_utf8_fail_explicitly(): void
    {
        $this->expectException(ApiProblem::class);
        (new ImportFileReader())->read($this->file("empresa_rif;desconocido\nJ-0;x\n"), 'third-parties', 'csv');
    }

    public function test_submitted_paths_are_not_a_supported_format(): void
    {
        $this->expectException(ApiProblem::class);
        (new ImportFileReader())->read($this->file('https://example.invalid/file.csv'), 'entries', 'url');
    }

    public function test_invalid_encoding_and_unterminated_csv_fields_are_not_silently_repaired(): void
    {
        try {
            (new ImportFileReader())->read($this->file("empresa_rif;referencia_importacion;rif;nombre;tipo\nJ0;A;J1;\xFF;customer\n"), 'third-parties', 'csv');
            $this->fail('El CSV debe ser UTF-8 válido.');
        } catch (ApiProblem $e) {
            $this->assertSame('IMPORT_ENCODING', $e->errorCode);
        }
        $this->expectException(ApiProblem::class);
        $this->expectExceptionMessage('incompleto');
        (new ImportFileReader())->read($this->file("empresa_rif;referencia_importacion\n\"incompleto"), 'third-parties', 'csv');
    }
}
