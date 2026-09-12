<?php

// ########### INICIO CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS
namespace Tests\Unit;

use App\Support\ItemImport\ItemImportCatalogs;
use App\Support\ItemImport\ItemImportContract;
use App\Support\ItemImport\ItemImportWorkbookValidator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\TestCase;

class ItemImportWorkbookValidatorTest extends TestCase
{
    /** @var array */
    private $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    /** @test */
    public function it_validates_the_complete_workbook_before_importing(): void
    {
        $path = $this->workbookPath();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(ItemImportContract::HEADERS, null, 'A1');
        $sheet->fromArray($this->validRow('ITEM-001'), null, 'A2');
        $sheet->fromArray($this->validRow('ITEM-001'), null, 'A3');
        $sheet->setCellValue('F3', 'VED');
        $sheet->setCellValue('G3', '=1+1');
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        $result = $this->validator()->validate($path);
        $coordinates = array_map(function (array $error) {
            return $error['row'] . ':' . $error['column'];
        }, $result->errors());

        self::assertFalse($result->passes());
        self::assertSame(2, $result->totalRows());
        self::assertContains('2:2', $coordinates);
        self::assertContains('3:2', $coordinates);
        self::assertContains('3:6', $coordinates);
        self::assertContains('3:7', $coordinates);
    }

    /** @test */
    public function it_accepts_a_valid_workbook_with_the_current_contract(): void
    {
        $path = $this->workbookPath();
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray(ItemImportContract::HEADERS, null, 'A1');
        $spreadsheet->getActiveSheet()->fromArray($this->validRow('ITEM-002'), null, 'A2');
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        $result = $this->validator()->validate($path);

        self::assertTrue($result->passes());
        self::assertSame([], $result->errors());
        self::assertSame(1, $result->totalRows());
    }

    /** @test */
    public function it_rejects_legacy_product_and_service_unit_codes(): void
    {
        foreach (['NIU', 'ZZ'] as $legacyCode) {
            $path = $this->workbookPath();
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray(ItemImportContract::HEADERS, null, 'A1');
            $row = $this->validRow('LEGACY-' . $legacyCode);
            $row[4] = $legacyCode;
            $sheet->fromArray($row, null, 'A2');
            IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

            $result = $this->validator()->validate($path);
            self::assertFalse($result->passes(), $legacyCode);
            self::assertContains(5, array_column($result->errors(), 'column'), $legacyCode);
            $spreadsheet->disconnectWorksheets();
        }
    }

    /** @test */
    public function it_rejects_extra_populated_columns_in_headers_and_rows(): void
    {
        $path = $this->workbookPath();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(ItemImportContract::HEADERS, null, 'A1');
        $sheet->setCellValue('V1', 'Columna inesperada');
        $sheet->setCellValue('V2', 'Dato inesperado');
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        $result = $this->validator()->validate($path);
        $coordinates = array_map(function (array $error) {
            return $error['row'] . ':' . $error['column'];
        }, $result->errors());

        self::assertFalse($result->passes());
        self::assertSame(1, $result->totalRows());
        self::assertContains('1:22', $coordinates);
        self::assertContains('2:22', $coordinates);
    }

    public function test_image_data_requires_the_current_optional_header(): void
    {
        $path = $this->workbookPath();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(ItemImportContract::HEADERS, null, 'A1');
        $sheet->fromArray($this->validRow('WITH-IMAGE'), null, 'A2');
        $sheet->setCellValue('U2', 'https://example.test/product.png');
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        $result = $this->validator()->validate($path);
        self::assertFalse($result->passes());
        self::assertSame([[1, 21]], array_map(static fn (array $error): array => [$error['row'], $error['column']], $result->errors()));

        $sheet->setCellValue('U1', ItemImportContract::OPTIONAL_IMAGE_HEADER);
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);
        self::assertTrue($this->validator()->validate($path)->passes());
        $spreadsheet->disconnectWorksheets();
    }

    /** @test */
    public function it_rejects_damaged_empty_and_multiple_sheet_workbooks(): void
    {
        $damagedPath = $this->workbookPath();
        file_put_contents($damagedPath, 'not-an-xlsx');
        $damaged = $this->validator()->validate($damagedPath);

        self::assertFalse($damaged->passes());
        self::assertSame(0, $damaged->totalRows());
        self::assertStringContainsString('dañado', $damaged->errors()[0]['messages'][0]);

        $emptyPath = $this->workbookPath();
        $emptyWorkbook = new Spreadsheet();
        $emptyWorkbook->getActiveSheet()->fromArray(ItemImportContract::HEADERS, null, 'A1');
        IOFactory::createWriter($emptyWorkbook, 'Xlsx')->save($emptyPath);
        $empty = $this->validator()->validate($emptyPath);

        self::assertFalse($empty->passes());
        self::assertSame(0, $empty->totalRows());
        self::assertStringContainsString('no contiene productos', $empty->errors()[0]['messages'][0]);

        $multiplePath = $this->workbookPath();
        $multipleWorkbook = new Spreadsheet();
        $multipleWorkbook->getActiveSheet()->fromArray(ItemImportContract::HEADERS, null, 'A1');
        $multipleWorkbook->getActiveSheet()->fromArray($this->validRow('ITEM-003'), null, 'A2');
        $multipleWorkbook->createSheet();
        IOFactory::createWriter($multipleWorkbook, 'Xlsx')->save($multiplePath);
        $multiple = $this->validator()->validate($multiplePath);

        self::assertFalse($multiple->passes());
        self::assertStringContainsString('exactamente una hoja', $multiple->errors()[0]['messages'][0]);
    }

    /** @test */
    public function the_downloadable_template_matches_the_effective_pro9_contract(): void
    {
        $result = $this->validator()->validate(__DIR__ . '/../../public/formats/items.xlsx');

        self::assertTrue($result->passes(), json_encode($result->errors(), JSON_UNESCAPED_UNICODE));
        self::assertSame(1, $result->totalRows());
    }

    private function validator(): ItemImportWorkbookValidator
    {
        $catalogs = new class extends ItemImportCatalogs {
            public function snapshot(): array
            {
                return [
                    'unit_type_ids' => ['UND'],
                    'currency_type_ids' => ['VES', 'USD'],
                    'affectation_igv_type_ids' => ['10', '20'],
                    'existing_internal_ids' => [],
                ];
            }
        };

        return new ItemImportWorkbookValidator(new ItemImportContract(), $catalogs);
    }

    private function workbookPath(): string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'items_validator_');
        self::assertNotFalse($temporary);
        unlink($temporary);
        $path = $temporary . '.xlsx';
        $this->temporaryFiles[] = $path;

        return $path;
    }

    private function validRow(string $internalId): array
    {
        return [
            'Producto', $internalId, 'M1', '12345678', 'UND', 'VES', 10, '10', 'SI', 5,
            '10', 1, 1, 'Categoría', 'Marca', 'Nombre', 'Secundario', null, null, 'BAR-1',
        ];
    }
}
// ########### FIN CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS
