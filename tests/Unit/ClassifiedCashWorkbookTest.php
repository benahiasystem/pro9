<?php

namespace Tests\Unit;

use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Modules\CashReport\Services\ClassifiedCashExport;
use Modules\CashReport\Services\ClassifiedCashReport;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ClassifiedCashWorkbookTest extends TestCase
{
    public function test_product_report_keeps_exact_lines_and_separate_currencies_in_pdf_and_excel(): void
    {
        $this->app['view']->addNamespace('cashreport', base_path('modules/CashReport/Resources/views'));
        $d=\App\Support\Exact\Decimal::class;
        $source=(object)['quantity'=>'0.333333333333','unit_value'=>'3','total'=>'1',
            'calculation_snapshot'=>$d::snapshot(['quantity'=>['n'=>'1','d'=>'3'],'unit_value'=>'3','total'=>'1'])];
        $usd=\Modules\CashReport\Services\ProductsAmounts::line($source,'USD') + [
            'id'=>1,'item_id'=>4,'record_type'=>'document_item','description'=>'Producto','number_full'=>'FF01-1'];
        $usd['calculation_snapshot']['values']['line_total']=['n'=>'4','d'=>'3'];
        $credit=\Modules\CashReport\Services\ProductsAmounts::line($source,'USD',true);
        self::assertSame(['n'=>'-1','d'=>'3'],$credit['calculation_snapshot']['values']['quantity']);
        self::assertSame(['n'=>'-1','d'=>'1'],$credit['calculation_snapshot']['values']['sub_total']);
        self::assertSame(['n'=>'-1','d'=>'1'],$credit['calculation_snapshot']['values']['total']);
        $source->quantity='1'; $source->unit_value='0.1'; $source->total='0.1';
        $source->calculation_snapshot=$d::snapshot(['quantity'=>'1','unit_value'=>'0.1','total'=>'0.1']);
        $ves=\Modules\CashReport\Services\ProductsAmounts::line($source,'VES') + [
            'id'=>2,'item_id'=>4,'record_type'=>'sale_note_item','description'=>'Producto','number_full'=>'NV01-1'];
        $purchase=array_replace($ves,['id'=>3,'record_type'=>'purchase_item','number_full'=>'P001-1']);
        $documents=collect([$usd,$ves,$purchase]);
        self::assertSame(['n'=>'1','d'=>'1'],$usd['calculation_snapshot']['values']['sub_total']);
        $totals=\Modules\CashReport\Services\ProductsAmounts::subtotalsByOperation($documents);
        self::assertSame('1',$d::represent($totals['Ventas']['USD']));
        self::assertSame('0.1',$d::represent($totals['Ventas']['VES']));
        self::assertSame('0.1',$d::represent($totals['Compras']['VES']));
        self::assertCount(2,\Modules\CashReport\Services\ProductsAmounts::sold($documents));
        $unitA=$usd; $unitA['unit_type_id']='UND';
        $unitB=$usd; $unitB['unit_type_id']='KG';
        self::assertCount(2,\Modules\CashReport\Services\ProductsAmounts::sold([$unitA,$unitB]));
        $large=(object)['quantity'=>'0.123456789012','unit_value'=>'123456789012345.123456789012','total'=>'1'];
        $largeLine=\Modules\CashReport\Services\ProductsAmounts::line($large,'VES');
        self::assertSame('15241578753196.091769931893',$largeLine['sub_total']);
        $small=(object)['quantity'=>'1','unit_value'=>'0.004','total'=>'0.004'];
        $smallLine=\Modules\CashReport\Services\ProductsAmounts::line($small,'VES');
        self::assertSame(['0.01','0.00','0.00'],array_values(\Modules\CashReport\Services\ProductsAmounts::display([$smallLine,$smallLine,$smallLine],'sub_total')));
        $smallPurchase=$smallLine + ['record_type'=>'purchase_item'];
        self::assertSame([0=>'0.00',1=>'0.01',2=>'0.00'],\Modules\CashReport\Services\ProductsAmounts::display([$smallLine,$smallPurchase,$smallPurchase],'sub_total'));
        $payload=[
            'header'=>['company_name'=>'Prueba','company_number'=>'V123','cash_user_name'=>'Caja','cash_date_opening'=>'2026-10-26','cash_time_opening'=>'09:00','cash_state'=>true,'cash_date_closed'=>null,'cash_time_closed'=>null,'printed_at'=>'2026-10-26','establishment_address'=>'Sede'],
            'cash'=>(object)['user'=>(object)['establishment'=>(object)[]]], 'documents'=>$documents, 'is_garage'=>false,
            'classified'=>['balances_by_currency'=>[],'groups'=>[],'rows'=>[],'show_detail'=>false],
        ];
        $html=view('cashreport::reports.products_a4',$payload)->render();
        self::assertStringContainsString('Ventas USD',$html);
        self::assertStringContainsString('Ventas VES',$html);
        self::assertStringContainsString('Compras VES',$html);
        $pdf=new \Mpdf\Mpdf(['tempDir'=>sys_get_temp_dir()]);
        $pdf->WriteHTML($html);
        $pdfBytes=$pdf->Output('', 'S');
        self::assertStringStartsWith('%PDF-', $pdfBytes);
        if ($dir=getenv('PRO9_CASH_PRODUCTS_EVIDENCE')) file_put_contents($dir.'/productos-caja.pdf',$pdfBytes);
        $many=$payload;
        $many['documents']=collect(range(1,100))->map(function ($id) use ($usd) {
            return array_replace($usd,['id'=>$id,'description'=>'Producto de prueba '.$id]);
        });
        $manyPdf=new \Mpdf\Mpdf(['tempDir'=>sys_get_temp_dir()]);
        $manyPdf->WriteHTML(view('cashreport::reports.products_a4',$many)->render());
        $manyBytes=$manyPdf->Output('', 'S');
        self::assertGreaterThan(1,preg_match_all('/\/Type\s*\/Page\b/',$manyBytes));
        self::assertSame('100',$d::represent(\Modules\CashReport\Services\ProductsAmounts::subtotals($many['documents'])['USD']));
        if ($dir=getenv('PRO9_CASH_PRODUCTS_EVIDENCE')) file_put_contents($dir.'/productos-caja-extenso.pdf',$manyBytes);
        $export=(new ClassifiedCashExport())->view_name('cashreport::reports.products_excel')->data($payload);
        $path=tempnam(sys_get_temp_dir(),'sab-products-xlsx-');
        try {
            file_put_contents($path,ExcelFacade::raw($export,Excel::XLSX));
            if ($dir=getenv('PRO9_CASH_PRODUCTS_EVIDENCE')) copy($path,$dir.'/productos-caja.xlsx');
            $book=IOFactory::load($path);
            $sheet=$book->getSheet(0);
            $productRow=null;
            foreach ($sheet->getRowIterator() as $row) {
                if ($sheet->getCell('B'.$row->getRowIndex())->getValue()==='Producto' && $sheet->getCell('D'.$row->getRowIndex())->getValue()==='USD') { $productRow=$row->getRowIndex(); break; }
            }
            self::assertNotNull($productRow);
            foreach (['E','F','G'] as $column) self::assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING,$sheet->getCell($column.$productRow)->getDataType());
            $rows=$book->getSheetByName('Fracciones exactas')->toArray(null,true,false,false);
            self::assertContains(['product:document_item:1:quantity','value','0.333333333333','1','3'],$rows);
            self::assertContains(['product:document_item:1:sub_total','value','1','1','1'],$rows);
            self::assertContains(['product:document_item:1:line_total','value','1.333333333333','4','3'],$rows);
            $book->disconnectWorksheets();
        } finally { if (is_file($path)) unlink($path); }
    }

    public function test_registry_selects_classified_financial_reports_and_product_builder(): void
    {
        $this->app['view']->addNamespace('cashreport', base_path('modules/CashReport/Resources/views'));
        $reports = \Modules\CashReport\Services\CashReportRegistry::reports();
        foreach ($reports as $type => $report) {
            self::assertTrue(class_exists($report['builder']), $type);
            foreach ($report['views'] as $view) self::assertTrue(view()->exists($view), $view);
            if (in_array($type, ['products', 'products_garage'], true)) {
                self::assertSame(\Modules\CashReport\Services\Builders\ProductsBuilder::class, $report['builder']);
                continue;
            }
            self::assertSame(\Modules\CashReport\Services\ClassifiedCashReport::class, $report['builder']);
            self::assertSame('cashreport::reports.classified_a4', $report['views']['pdf']);
        }
    }

    public function test_workbook_separates_cash_and_bank_by_currency(): void
    {
        $this->app['view']->addNamespace('cashreport', base_path('modules/CashReport/Resources/views'));
        $payload=[
            'header'=>['company_name'=>'Prueba','company_number'=>'V123','cash_user_name'=>'Caja','cash_date_opening'=>'2026-10-26','cash_time_opening'=>'09:00','cash_state'=>true,'cash_date_closed'=>null,'cash_time_closed'=>null],
            'title'=>'Caja',
            'classified'=>[
                'balances_by_currency'=>[
                    'VES'=>['beginning_balance'=>'100','income'=>'50','egress'=>'30','final_balance'=>'120'],
                    'USD'=>['beginning_balance'=>'10','income'=>'5','egress'=>'2','final_balance'=>'13'],
                ],
                'groups'=>[
                    ['method'=>['description'=>'Efectivo VES','collection_kind'=>'cash'],'destination_label'=>'Caja','currency_type_id'=>'VES','income'=>'50','egress'=>'30','balance'=>'20'],
                    ['method'=>['description'=>'Bancario USD','collection_kind'=>'account'],'destination_label'=>'Cuenta USD','currency_type_id'=>'USD','income'=>'20','egress'=>'0','balance'=>'20'],
                ],
                'rows'=>[], 'show_detail'=>false,
            ],
        ];
        $export=(new ClassifiedCashExport())->view_name('cashreport::reports.classified_excel')->data($payload);
        $path=tempnam(sys_get_temp_dir(),'sab-cash-xlsx-');
        try {
            file_put_contents($path,ExcelFacade::raw($export,Excel::XLSX));
            $book=IOFactory::load($path);
            self::assertContains('Fracciones exactas',$book->getSheetNames());
            $rows=$book->getSheet(0)->toArray(null,true,false,false);
            $flat=array_map('strval',array_merge(...$rows));
            foreach (['Bs.','USD','120.00','13.00','Cuenta USD'] as $value) self::assertContains($value,$flat);
            self::assertNotEmpty(array_filter($flat, fn($value)=>str_contains($value,'Efectivo VES') && str_contains($value,'Efectivo')));
            self::assertNotEmpty(array_filter($flat, fn($value)=>str_contains($value,'Bancario USD') && str_contains($value,'Bancario / electrónico')));
            $book->disconnectWorksheets();
        } finally { if (is_file($path)) unlink($path); }
    }

    public function test_historical_groups_keep_names_classifications_and_destinations_separate(): void
    {
        $base=['opening'=>false,'method'=>['catalog'=>'payment_method_types','id'=>'01','description'=>'Nombre anterior','collection_kind'=>'account'],
            'currency_type_id'=>'USD','destination_type'=>'Bank','destination_id'=>3,'destination_label'=>'Cuenta anterior',
            'direction'=>'income','amount'=>'0.10'];
        $same=$base; $same['amount']='0.20';
        $renamed=$base; $renamed['method']['description']='Nombre nuevo';
        $reclassified=$base; $reclassified['method']['collection_kind']='cash';
        $destination=$base; $destination['destination_label']='Cuenta nueva';
        $opening=$base; $opening['opening']=true;
        $groups=ClassifiedCashReport::groupRows([$base,$same,$renamed,$reclassified,$destination,$opening]);
        self::assertCount(4,$groups);
        self::assertSame(['0.3','0.1','0.1','0.1'],array_column($groups,'income'));
        self::assertSame(['Nombre anterior','Nombre nuevo','Nombre anterior','Nombre anterior'],array_column(array_column($groups,'method'),'description'));
        self::assertSame(['account','account','cash','account'],array_column(array_column($groups,'method'),'collection_kind'));
        self::assertSame(['Cuenta anterior','Cuenta anterior','Cuenta anterior','Cuenta nueva'],array_column($groups,'destination_label'));
        self::assertSame(['n'=>'3','d'=>'10'],$groups[0]['calculation_snapshot']['values']['income']);
    }

    public function test_financial_report_renders_the_historical_kind_beside_the_method(): void
    {
        $this->app['view']->addNamespace('finance',base_path('modules/Finance/Resources/views'));
        $row=['method'=>['description'=>'Efectivo Bolívares','collection_kind'=>'account'],
            'destination_label'=>'Cuenta USD','currency_type_id'=>'USD','opening'=>'0','income'=>'0.05','egress'=>'0','balance'=>'0.05'];
        $html=view('finance::payment_method_types.classified',['records'=>['classified'=>[$row]]])->render();
        self::assertStringContainsString('Efectivo Bolívares',$html);
        self::assertStringContainsString('Bancario / electrónico',$html);
        self::assertStringContainsString('Cuenta USD',$html);
        self::assertStringContainsString('0.05',$html);
    }

    public function test_filtered_cash_reports_group_only_their_visible_movements(): void
    {
        $cash=['opening'=>false,'method'=>['catalog'=>'payment_method_types','id'=>'04','description'=>'Transferencia','collection_kind'=>'cash'],
            'currency_type_id'=>'VES','destination_type'=>'Cash','destination_id'=>1,'destination_label'=>'Caja','direction'=>'income','amount'=>'0.10'];
        $expense=$cash; $expense['direction']='egress'; $expense['amount']='0.03';
        $bank=$cash; $bank['method']['id']='01'; $bank['method']['collection_kind']='account';
        $bank['currency_type_id']='USD'; $bank['destination_type']='Bank'; $bank['destination_label']='Cuenta USD'; $bank['amount']='0.20';
        $opening=$cash; $opening['opening']=true; $opening['amount']='5';
        $rows=[$cash,$expense,$bank,$opening];
        foreach (['general_with_payments','income_egress','payments_associated'] as $type) {
            $visible=ClassifiedCashReport::rowsForReport($rows,$type);
            self::assertCount(3,$visible,$type);
            $groups=ClassifiedCashReport::groupRows($visible);
            self::assertCount(1,$groups,$type);
            self::assertSame('0.1',$groups[0]['income'],$type);
            self::assertSame('0.03',$groups[0]['egress'],$type);
            self::assertSame('0.07',$groups[0]['balance'],$type);
        }
        $visible=ClassifiedCashReport::rowsForReport($rows,'income_summary');
        self::assertCount(2,$visible);
        $groups=ClassifiedCashReport::groupRows($visible);
        self::assertSame(['cash','account'],array_column(array_column($groups,'method'),'collection_kind'));
        self::assertSame(['0','0'],array_column($groups,'egress'));
        self::assertCount(4,ClassifiedCashReport::rowsForReport($rows,'cash_summary'));
    }
}
