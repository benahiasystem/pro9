<?php

namespace Tests\Unit;

use App\Models\Tenant\User;
use App\Support\Exact\{Decimal, SalesConsolidated};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};
use Modules\Report\Http\Controllers\ReportSaleConsolidatedController;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
class ExactConsolidatedConsumersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::purge('tenant');
        config(['database.connections.tenant'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>''], 'tenant.items_per_page'=>2]);
        auth()->setUser((new User())->forceFill(['id'=>1, 'type'=>'admin']));
        view()->addNamespace('report', base_path('modules/Report/Resources/views'));
        foreach (['documents','sale_notes'] as $table) Schema::connection('tenant')->create($table, function ($t) {
            $t->increments('id'); $t->string('series')->default(''); $t->integer('number')->default(1);
            $t->string('document_type_id')->default('01'); $t->string('state_type_id')->default('01');
            $t->string('currency_type_id')->default('VES'); $t->text('exchange_rate_sale')->default('1.00000000');
            $t->date('date_of_issue')->default('2026-10-17'); $t->integer('establishment_id')->default(1);
            $t->integer('customer_id')->default(1); $t->integer('user_id')->default(1); $t->integer('seller_id')->default(1);
            $t->boolean('changed')->default(false);
        });
        foreach (['document_items','sale_note_items'] as $table) Schema::connection('tenant')->create($table, function ($t) use ($table) {
            $t->increments('id'); $t->integer($table === 'document_items' ? 'document_id' : 'sale_note_id');
            $t->integer('item_id'); $t->json('item'); $t->text('quantity'); $t->text('unit_price'); $t->text('total'); $t->json('calculation_snapshot')->nullable();
        });
        Schema::connection('tenant')->create('items', function ($t) {
            $t->increments('id'); $t->string('internal_id'); $t->string('unit_type_id'); $t->integer('brand_id')->nullable(); $t->integer('category_id')->nullable();
        });
        foreach (['brands','categories'] as $table) Schema::connection('tenant')->create($table, function ($t) { $t->increments('id'); $t->string('name'); });
        DB::connection('tenant')->table('items')->insert(['id'=>1, 'internal_id'=>'A', 'unit_type_id'=>'UND']);
        DB::connection('tenant')->table('documents')->insert([['id'=>1, 'currency_type_id'=>'VES', 'exchange_rate_sale'=>'1.00000000'], ['id'=>2, 'currency_type_id'=>'USD', 'exchange_rate_sale'=>'3.00000000']]);
        DB::connection('tenant')->table('sale_notes')->insert([['id'=>1, 'changed'=>false], ['id'=>2, 'changed'=>true]]);
        $item = json_encode(['internal_id'=>'A','description'=>'=1+1','unit_type_id'=>'UND','unit_price'=>'999']);
        DB::connection('tenant')->table('document_items')->insert([
            ['id'=>1, 'document_id'=>1, 'item_id'=>1, 'item'=>$item, 'quantity'=>'1', 'unit_price'=>'500', 'total'=>'500', 'calculation_snapshot'=>null],
            ['id'=>2, 'document_id'=>1, 'item_id'=>1, 'item'=>$item, 'quantity'=>'2', 'unit_price'=>'700', 'total'=>'1400', 'calculation_snapshot'=>null],
            ['id'=>3, 'document_id'=>2, 'item_id'=>1, 'item'=>$item, 'quantity'=>'0.333333333333', 'unit_price'=>'1', 'total'=>'0.333333333333', 'calculation_snapshot'=>json_encode(Decimal::snapshot(['quantity'=>'1/3', 'total'=>'1/3']))],
        ]);
        DB::connection('tenant')->table('sale_note_items')->insert([
            ['id'=>1, 'sale_note_id'=>1, 'item_id'=>1, 'item'=>$item, 'quantity'=>'3', 'unit_price'=>'10', 'total'=>'30'],
            ['id'=>2, 'sale_note_id'=>2, 'item_id'=>1, 'item'=>$item, 'quantity'=>'1', 'unit_price'=>'999', 'total'=>'999'],
        ]);
    }

    private function params(): array
    {
        return ['document_type_id'=>'[]', 'date_range_type_id'=>'date_of_issue', 'date_start'=>'2026-10-01', 'date_end'=>'2026-10-31', 'person_id'=>null];
    }

    public function test_union_batches_api_groups_and_rendered_table_share_saved_values(): void
    {
        $controller = new ReportSaleConsolidatedController();
        DB::connection('tenant')->enableQueryLog();
        $records = $controller->detailRecords($this->params());
        self::assertCount(4, $records);
        $summary = SalesConsolidated::summary($records);
        self::assertSame('1931', $summary['total_sale']);
        self::assertSame('19/3', (string)Decimal::readRequired($summary, 'quantity'));
        $queries = DB::connection('tenant')->getQueryLog();
        self::assertLessThanOrEqual(5, count($queries));
        $groups = $controller->totalsByItem(new Request($this->params()));
        self::assertSame($summary, SalesConsolidated::summary($groups));
        $response = $controller->records(new Request($this->params()))->response()->getData(true);
        self::assertCount(2, $response['data']);
        self::assertSame(4, $response['meta']['total']);
        self::assertArrayHasKey('summary', $response);
        $secondPage = $controller->records(new Request($this->params()+['page'=>2]))->response()->getData(true);
        self::assertCount(4, array_unique(array_column(array_merge($response['data'], $secondPage['data']), 'report_key')));
        $html = view('report::sales_consolidated.exact_table', ['records'=>\App\Support\Exact\ReportProjection::money($records, ['total_sale','total_ves','total_usd']), 'reportSummary'=>$summary, 'detail'=>true, 'excel'=>false])->render();
        self::assertStringContainsString('1,931.00', $html);
        self::assertStringContainsString('Moneda original', $html);
        self::assertStringContainsString('=1+1', $html);
    }

    public function test_xlsx_preserves_text_and_fraction_sheet_without_formulas(): void
    {
        $records = (new ReportSaleConsolidatedController())->detailRecords($this->params());
        $html = view('report::sales_consolidated.exact_table', ['records'=>$records, 'reportSummary'=>SalesConsolidated::summary($records), 'detail'=>true, 'excel'=>true])->render();
        $path = tempnam(sys_get_temp_dir(), 'exact-report-');
        try {
            file_put_contents($path, $html);
            $book = IOFactory::createReader('Html')->load($path);
            \App\Support\Exact\FractionSheet::append($book->getActiveSheet(), $records, ['quantity','total_sale','total_original']);
            self::assertSame('Fracciones exactas', $book->getSheet(1)->getTitle());
            self::assertSame('document:1', $book->getSheet(1)->getCell('A2')->getValue());
            self::assertSame('sale_note:1', $book->getSheet(1)->getCell('A11')->getValue());
            $found = false;
            foreach ($book->getActiveSheet()->getRowIterator() as $row) foreach ($row->getCellIterator() as $cell) {
                self::assertNotSame('f', $cell->getDataType());
                if ($cell->getValue() === '=1+1') { $found = true; self::assertSame('s', $cell->getDataType()); }
            }
            self::assertTrue($found);
            self::assertContains('3', $book->getSheet(1)->toArray()[9]);
        } finally { unlink($path); }
    }

    public function test_real_xlsx_writer_keeps_the_second_sheet_and_composite_identifiers(): void
    {
        $records = (new ReportSaleConsolidatedController())->detailRecords($this->params());
        $export = new class extends \Modules\Report\Exports\SaleConsolidatedExport {
            public function view(): \Illuminate\Contracts\View\View
            {
                return view('report::sales_consolidated.exact_table', ['records'=>$this->records,
                    'reportSummary'=>SalesConsolidated::summary($this->records), 'detail'=>true, 'excel'=>true]);
            }
        };
        $path = tempnam(sys_get_temp_dir(), 'pro9-final-xlsx-');
        try {
            file_put_contents($path, \Maatwebsite\Excel\Facades\Excel::raw($export->records($records), \Maatwebsite\Excel\Excel::XLSX));
            $book = IOFactory::load($path);
            self::assertSame(2, $book->getSheetCount());
            self::assertSame('document:1', $book->getSheet(1)->getCell('A2')->getValue());
            self::assertSame('sale_note:1', $book->getSheet(1)->getCell('A11')->getValue());
            self::assertSame('=1+1', $book->getSheet(0)->getCell('D2')->getValue());
            self::assertSame('s', $book->getSheet(0)->getCell('D2')->getDataType());
        } finally { unlink($path); }
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
