<?php

namespace Tests\Unit;

use App\Support\Exact\{Decimal, ReportDataset, ReportProjection, SalesConsolidated};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
class ExactReportTicketRenderingTest extends TestCase
{
    /** @dataProvider ticketWidths */
    public function test_local_ticket_retains_the_exact_total_and_renders_a_pdf(int $width, string $view): void
    {
        DB::purge('tenant');
        config(['database.connections.tenant'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'']]);
        view()->addNamespace('report', base_path('modules/Report/Resources/views'));
        $rows = [];
        for ($id=1; $id<=4; $id++) {
            $amount = $id === 1 ? '500' : '1/3';
            $rows[] = SalesConsolidated::line(['id'=>$id,'item_id'=>$id,'currency_type_id'=>$id === 1 ? 'VES' : 'USD',
                'exchange_rate_sale'=>'3.00000000','quantity'=>'1','total'=>Decimal::represent($amount),
                'calculation_snapshot'=>Decimal::snapshot(['quantity'=>'1','total'=>$amount]),
                'item'=>['internal_id'=>'QA-'.$id,'description'=>$id === 1 ? 'Producto de prueba Bs. 500' : 'Servicio en moneda extranjera '.$id,'unit_type_id'=>$id === 1 ? 'UND' : 'SERV']]);
        }
        $records = SalesConsolidated::groupedDataset(new ReportDataset($rows));
        $reportSummary = SalesConsolidated::summary($records);
        self::assertSame('503', $reportSummary['total_sale']);
        self::assertSame('1', $reportSummary['currency_totals']['USD']);
        $records = ReportProjection::money($records, ['total_sale','total_ves','total_usd']);
        $company = (new \App\Models\Tenant\Company())->forceFill(['name'=>'DEMOSTRACIÓN DE CÁLCULOS', 'trade_name'=>'Pro9 - Prueba local','number'=>'J000000000']);
        $establishment = (new \App\Models\Tenant\Establishment())->forceFill(['address'=>'Sucursal de prueba']);
        $establishment->setRelation('department', new \Illuminate\Support\Fluent(['description'=>'Caracas']));
        $establishment->setRelation('district', new \Illuminate\Support\Fluent(['description'=>'Libertador']));
        $params = ['person_id'=>null];
        $html = view($view, compact('records','reportSummary','company','establishment','params'))->render();
        self::assertStringContainsString('503.00', $html);
        self::assertStringContainsString('500.00', $html);
        self::assertStringNotContainsString('499.99', $html);
        $pdf = Pdf::loadHTML($html)->setPaper([0,0,$width/25.4*72,1440])->output();
        self::assertStringStartsWith('%PDF-', $pdf);
        if ($directory = getenv('PRO9_EXACT_PDF_OUTPUT_DIR')) {
            if (!is_dir($directory)) mkdir($directory,0775,true);
            file_put_contents($directory.'/calculos-exactos-ticket-'.$width.'mm.pdf', $pdf);
        }
    }

    public static function ticketWidths(): array
    {
        return [[58,'report::sales_consolidated.report_pdf_totals_ticket'], [80,'report::sales_consolidated.report_pdf_totals_ticket_80']];
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
