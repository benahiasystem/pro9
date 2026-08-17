<?php

// ########### INICIO CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS
namespace Tests\Unit;

use App\Support\ItemImport\ItemImportContract;
use App\Support\ItemImport\ItemImportCatalogs;
use App\Support\ItemImport\ItemImportValidationWorkbook;
use App\Support\ItemImport\ItemImportWorkbookValidator;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PHPUnit\Framework\TestCase;

class ItemImportValidationWorkbookTest extends TestCase
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
    public function it_marks_invalid_cells_adds_comments_and_neutralizes_formulas(): void
    {
        $source = $this->temporaryPath('items_source_');
        $target = $this->temporaryPath('items_target_');
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray(ItemImportContract::HEADERS, null, 'A1');
        $spreadsheet->getActiveSheet()->setCellValue('F2', '=1+1');
        $spreadsheet->createSheet()->setCellValue('B2', '=2+2');
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($source);

        (new ItemImportValidationWorkbook())->build($source, $target, [[
            'row' => 2,
            'column' => 6,
            'messages' => ['La moneda no existe.', 'Use VES.'],
        ]]);

        $result = IOFactory::load($target);
        $cell = $result->getActiveSheet()->getCell('F2');
        $comment = $result->getActiveSheet()->getComment('F2');

        self::assertSame('FFFFA6A6', $result->getActiveSheet()->getStyle('F2')->getFill()->getStartColor()->getARGB());
        self::assertStringContainsString('La moneda no existe.', $comment->getText()->getPlainText());
        self::assertStringContainsString('Use VES.', $comment->getText()->getPlainText());
        self::assertSame(DataType::TYPE_STRING, $cell->getDataType());
        self::assertSame('=1+1', $cell->getValue());
        self::assertSame(DataType::TYPE_STRING, $result->getSheet(1)->getCell('B2')->getDataType());
        self::assertSame('=2+2', $result->getSheet(1)->getCell('B2')->getValue());
    }

    /** @test */
    public function an_office_resaved_partially_corrected_report_contains_only_pending_errors(): void
    {
        $source = $this->temporaryPath('items_partial_source_');
        $firstReport = $this->temporaryPath('items_partial_first_');
        $partiallyCorrected = $this->temporaryPath('items_partial_corrected_');
        $secondReport = $this->temporaryPath('items_partial_second_');
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(ItemImportContract::HEADERS, null, 'A1');
        $sheet->fromArray($this->validRow(), null, 'A2');
        $sheet->setCellValue('F2', 'VED');
        $sheet->setCellValue('I2', 'PENDIENTE');
        $sheet->getComment('A2')->setAuthor('Usuario');
        $sheet->getComment('A2')->getText()->createText('Nota propia del usuario.');
        $sheet->getStyle('A2')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FFFFFF00');
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($source);

        $validator = $this->validator();
        $workbook = new ItemImportValidationWorkbook();
        $firstValidation = $validator->validate($source);
        self::assertCount(2, $firstValidation->errors());
        $workbook->build($source, $firstReport, $firstValidation->errors());

        $edited = IOFactory::load($firstReport);
        $edited->getActiveSheet()->setCellValue('F2', 'VES');

        foreach ($edited->getActiveSheet()->getComments() as $comment) {
            $officeText = new RichText();
            $officeText->createText(str_replace("\n", "\r\n", $comment->getText()->getPlainText()));
            $comment->setAuthor('Autoría desconocida');
            $comment->setText($officeText);
        }

        IOFactory::createWriter($edited, 'Xlsx')->save($partiallyCorrected);
        $edited->disconnectWorksheets();

        $secondValidation = $validator->validate($partiallyCorrected);
        $pendingCoordinates = array_map(function (array $error) {
            return $error['row'] . ':' . $error['column'];
        }, $secondValidation->errors());
        $workbook->build($partiallyCorrected, $secondReport, $secondValidation->errors());

        self::assertFalse($secondValidation->passes());
        self::assertCount(1, $secondValidation->errors());
        self::assertNotContains('2:6', $pendingCoordinates);
        self::assertContains('2:9', $pendingCoordinates);

        $result = IOFactory::load($secondReport);
        $resultSheet = $result->getActiveSheet();

        self::assertSame('VES', $resultSheet->getCell('F2')->getValue());
        self::assertArrayNotHasKey('F2', $resultSheet->getComments());
        self::assertSame(Fill::FILL_NONE, $resultSheet->getStyle('F2')->getFill()->getFillType());
        self::assertStringContainsString('SI o NO', $resultSheet->getComment('I2')->getText()->getPlainText());
        self::assertSame(
            ItemImportValidationWorkbook::ERROR_FILL_ARGB,
            $resultSheet->getStyle('I2')->getFill()->getStartColor()->getARGB()
        );
        self::assertSame('Autoría desconocida', $resultSheet->getComment('A2')->getAuthor());
        self::assertSame('Nota propia del usuario.', $resultSheet->getComment('A2')->getText()->getPlainText());
        self::assertSame('FFFFFF00', $resultSheet->getStyle('A2')->getFill()->getStartColor()->getARGB());
        $result->disconnectWorksheets();
    }

    private function validator(): ItemImportWorkbookValidator
    {
        $catalogs = new class extends ItemImportCatalogs {
            public function snapshot(): array
            {
                return [
                    'unit_type_ids' => ['NIU'],
                    'currency_type_ids' => ['VES', 'USD'],
                    'affectation_igv_type_ids' => ['10', '20'],
                    'existing_internal_ids' => [],
                ];
            }
        };

        return new ItemImportWorkbookValidator(new ItemImportContract(), $catalogs);
    }

    private function validRow(): array
    {
        return [
            'Producto', 'ITEM-001', 'M1', '12345678', 'NIU', 'VES', 10, '10', 'SI', 5,
            '10', 1, 1, 'Categoría', 'Marca', 'Nombre', 'Secundario', null, null, 'BAR-1',
        ];
    }

    private function temporaryPath(string $prefix): string
    {
        $temporary = tempnam(sys_get_temp_dir(), $prefix);
        self::assertNotFalse($temporary);
        unlink($temporary);
        $path = $temporary . '.xlsx';
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
// ########### FIN CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS
