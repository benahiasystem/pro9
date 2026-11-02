<?php

namespace Tests\Unit;

use App\Exports\PaymentExport;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

// ######## INICIO MONEDA DEL MEDIO DE COBRO ########
class PaymentWorkbookExportTest extends TestCase
{
    public function test_real_workbook_keeps_money_as_text_and_preserves_exact_fractions(): void
    {
        $record=(object)[
            'id'=>1,'ruc'=>'V12345678','date'=>'10/09/2026','invoice'=>'FF01-00000001',
            'comercial_name'=>'Prueba','business_name'=>'Prueba','zone'=>'','person_type'=>'','department'=>'','district'=>'',
            'status'=>'Vigente','currency_type_id'=>'VES','total'=>'9007199254740993.01','balance'=>'0.43',
            'exact_snapshot'=>['values'=>['total'=>['n'=>'900719925474099301','d'=>'100'],'balance'=>['n'=>'3','d'=>'7']]],
            'payments'=>collect([['id'=>2,'payment_method_type_description'=>'Zelle','kind'=>'Cobro','currency_type_id'=>'USD',
                'payment'=>'10.01','applied_payment'=>'4004','exchange_rate'=>'400.12345678','reference'=>'=2+2',
                'exact_snapshot'=>['values'=>['received'=>['n'=>'1001','d'=>'100'],'applied'=>['n'=>'4004','d'=>'1'],
                    'rate'=>['n'=>'20006172839','d'=>'50000000']]]]]),
        ];
        $export=(new PaymentExport())->records(collect([$record]))->payment_count(1);
        $path=tempnam(sys_get_temp_dir(),'pro9-payment-exact-');
        try {
            file_put_contents($path,ExcelFacade::raw($export,Excel::XLSX));
            $book=IOFactory::load($path);
            self::assertSame(['Pagos','Fracciones exactas'],$book->getSheetNames());
            self::assertSame('Pagos',$book->getActiveSheet()->getTitle());
            $sheet=$book->getSheetByName('Pagos');
            $found=[]; $formulaLikeCell=null;
            foreach ($sheet->getRowIterator() as $row) foreach ($row->getCellIterator() as $cell) {
                if ((string)$cell->getValue() === '=2+2') $formulaLikeCell=$cell;
                if (in_array((string)$cell->getValue(),['9007199254740993.01','400.12345678','10.01'],true)) {
                    $found[(string)$cell->getValue()]=$cell->getDataType();
                }
            }
            self::assertSame(DataType::TYPE_STRING,$found['9007199254740993.01'] ?? null);
            self::assertSame(DataType::TYPE_STRING,$found['400.12345678'] ?? null);
            self::assertSame(DataType::TYPE_STRING,$found['10.01'] ?? null);
            self::assertNotNull($formulaLikeCell);
            self::assertSame(DataType::TYPE_STRING,$formulaLikeCell->getDataType());
            self::assertTrue($formulaLikeCell->getStyle()->getQuotePrefix());
            $fractions=$book->getSheetByName('Fracciones exactas')->toArray(null,true,false,false);
            self::assertContains(['1','FF01-00000001',null,'balance','VES','3','7'],$fractions);
            self::assertContains(['1','FF01-00000001','2','rate','VES/USD','20006172839','50000000'],$fractions);
            $book->disconnectWorksheets();
            $record->payments[0] = array_replace($record->payments[0], ['payment'=>null, 'exchange_rate'=>null,
                'exact_snapshot'=>['values'=>['applied'=>['n'=>'4004','d'=>'1']]]]);
            file_put_contents($path, ExcelFacade::raw($export, Excel::XLSX));
            $legacy=IOFactory::load($path);
            $legacyFractions=$legacy->getSheetByName('Fracciones exactas')->toArray(null,true,false,false);
            self::assertContains(['1','FF01-00000001','2','applied','VES','4004','1'], $legacyFractions);
            self::assertNotContains(['1','FF01-00000001','2','received','USD','0','1'], $legacyFractions);
            self::assertNotContains(['1','FF01-00000001','2','rate','VES/USD','0','1'], $legacyFractions);
            $legacy->disconnectWorksheets();
        } finally { if (is_file($path)) unlink($path); }
    }
}
// ######## FIN MONEDA DEL MEDIO DE COBRO ########
