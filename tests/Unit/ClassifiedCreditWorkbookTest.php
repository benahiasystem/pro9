<?php
namespace Tests\Unit;

use App\Support\Exact\Decimal;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Modules\CashReport\Services\{ClassifiedCashExport,ClassifiedCashReport};
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

final class ClassifiedCreditWorkbookTest extends TestCase
{
    private function payload(): array
    {
        $ves=Decimal::of(['n'=>'10049999999999999','d'=>'10000000000000000']);
        $usd=Decimal::of(1)->dividedBy(3);
        return ['title'=>'Crédito pendiente de prueba','header'=>[
            'company_name'=>'Empresa temporal','company_number'=>'J123456789','cash_user_name'=>'Caja temporal',
            'cash_date_opening'=>'2026-11-11','cash_time_opening'=>'09:00','cash_state'=>true,
            'cash_date_closed'=>null,'cash_time_closed'=>null,'printed_at'=>'2026-11-11','printed_by'=>'Prueba',
            'establishment_address'=>'Sede temporal','establishment_department_description'=>'Caracas',
            'establishment_district_description'=>'Libertador'],
            'classified'=>['balances_by_currency'=>[],'groups'=>[],'rows'=>[],'show_detail'=>false,
                'credit_pending'=>['VES'=>Decimal::represent($ves),'USD'=>Decimal::represent($usd)],
                'credit_pending_snapshot'=>Decimal::snapshot(['VES'=>$ves,'USD'=>$usd])]];
    }
    public function test_final_views_round_from_fractions_and_keep_the_existing_workbook_format(): void
    {
        $this->app['view']->addNamespace('cashreport',base_path('modules/CashReport/Resources/views'));
        $payload=$this->payload();$before=$payload;
        foreach (['cashreport::reports.classified_a4','cashreport::reports.classified_ticket','cashreport::reports.classified_excel'] as $view) {
            $html=view($view,$payload)->render();
            $text=preg_replace('/\s+/u',' ',html_entity_decode(strip_tags($html)));
            self::assertStringContainsString('1.00',$text);
            self::assertStringContainsString('0.33',$text);
            self::assertStringNotContainsString('1.01',$text);
            if ($view!=='cashreport::reports.classified_excel') {
                $format=$view==='cashreport::reports.classified_ticket' ? [58,160] : 'A4';
                $pdf=new \Mpdf\Mpdf(['tempDir'=>sys_get_temp_dir(),'format'=>$format]);
                $pdf->WriteHTML($html);$bytes=$pdf->Output('','S');
                self::assertStringStartsWith('%PDF-',$bytes);
                $temporary=tempnam(sys_get_temp_dir(),'pro9-credit-pdf-');
                try {
                    file_put_contents($temporary,$bytes);
                    $extract=new \Symfony\Component\Process\Process(['pdftotext','-raw',$temporary,'-']);
                    $extract->setTimeout(30);$extract->mustRun();
                    $plain=preg_replace('/\s+/u','',$extract->getOutput());
                    self::assertStringContainsString('1.00',$plain);
                    self::assertStringContainsString('0.33',$plain);
                    self::assertStringNotContainsString('1.01',$plain);
                    if ($run=getenv('PRO9_POSTGRES_RUN_ID')) {
                        $directory=base_path('informes/evidencias/postgresql_17_implementacion/resultados/creditos-'.$run);
                        if (!is_dir($directory)) mkdir($directory,0755,true);
                        file_put_contents($directory.'/'.($format==='A4' ? 'a4' : 'ticket_58').'.pdf',$bytes);
                    }
                } finally {if (is_file($temporary)) unlink($temporary);}
                unset($pdf);
            }
        }
        self::assertSame($before,$payload);
        self::assertSame(['n'=>'10049999999999999','d'=>'10000000000000000'],Decimal::fraction(ClassifiedCashReport::pendingCredit($payload['classified'],'VES')));
        $export=(new ClassifiedCashExport())->view_name('cashreport::reports.classified_excel')->data($payload);
        $path=tempnam(sys_get_temp_dir(),'pro9-credit-xlsx-');
        try {
            file_put_contents($path,ExcelFacade::raw($export,Excel::XLSX));
            $book=IOFactory::load($path);
            $fractions=$book->getSheetByName('Fracciones exactas');
            self::assertNotNull($fractions);
            $rows=$fractions->toArray(null,true,false,false);
            // Credit summaries were not rows of the original auxiliary sheet.
            self::assertCount(1,$rows);
            self::assertSame(['Registro','Campo','Representación decimal (12)','Numerador','Denominador'],$rows[0]);
            $book->disconnectWorksheets();
        } finally {if (is_file($path)) unlink($path);}
        self::assertSame($before,$payload);
    }
    public function test_scalar_legacy_credit_remains_readable_and_unknown_or_missing_currency_fails(): void
    {
        self::assertTrue(ClassifiedCashReport::pendingCredit(['credit_pending'=>['VES'=>'1.25']],'VES')->isEqualTo('1.25'));
        try {ClassifiedCashReport::pendingCredit(['credit_pending'=>[]],'VES');self::fail('No se inventa un importe ausente.');}
        catch (\UnexpectedValueException $expected) {self::assertStringContainsString('VES',$expected->getMessage());}
        $this->expectException(\InvalidArgumentException::class);
        ClassifiedCashReport::pendingCredit(['credit_pending'=>['EUR'=>'1']],'EUR');
    }
}
