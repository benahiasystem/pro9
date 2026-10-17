<?php

namespace Tests\Unit;

use App\Imports\StockImport;
use Maatwebsite\Excel\Events\BeforeSheet;
use PhpOffice\PhpSpreadsheet\{IOFactory, Spreadsheet};
use Tests\TestCase;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
class ExactStockWorkbookTest extends TestCase
{
    public function test_stock_import_restores_the_numeric_ooxml_lexeme_before_reading_rows(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pro9-stock-');
        try {
            $book = new Spreadsheet(); $sheet = $book->getActiveSheet();
            $sheet->fromArray([['Código','Stock'],['ITEM','1']], null, 'A1');
            IOFactory::createWriter($book, 'Xlsx')->save($path);
            $zip = new \ZipArchive(); $zip->open($path);
            $xml = preg_replace('~<c\b[^>]*\br="B2"[^>]*>.*?</c>~s', '<c r="B2" t="n"><v>123456789012345678.123456789012</v></c>', $zip->getFromName('xl/worksheets/sheet1.xml'));
            $zip->addFromString('xl/worksheets/sheet1.xml', $xml); $zip->close();
            $loaded = IOFactory::load($path); $import = new StockImport($path);
            $event = new BeforeSheet(new \Maatwebsite\Excel\Sheet($loaded->getActiveSheet()), $import);
            $import->registerEvents()[BeforeSheet::class]($event);
            self::assertSame('123456789012345678.123456789012', $loaded->getActiveSheet()->getCell('B2')->getValue());
            self::assertSame('s', $loaded->getActiveSheet()->getCell('B2')->getDataType());
            $loaded->getActiveSheet()->getCell('B2')->setValueExplicit('=1/3', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
            $zip->open($path);
            $xml = preg_replace('~<c\b[^>]*\br="B2"[^>]*>.*?</c>~s', '<c r="B2"><f>1/3</f><v>0.3333</v></c>', $zip->getFromName('xl/worksheets/sheet1.xml'));
            $zip->addFromString('xl/worksheets/sheet1.xml', $xml); $zip->close();
            $this->expectException(\InvalidArgumentException::class);
            $import->registerEvents()[BeforeSheet::class]($event);
        } finally { unlink($path); }
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
