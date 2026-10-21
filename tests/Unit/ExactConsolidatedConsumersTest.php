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
    public function test_a_date_selector_cannot_be_used_to_filter_a_projected_monetary_column(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        (new ReportSaleConsolidatedController())->detailRecords(array_replace($this->params(),['date_range_type_id'=>'total']));
    }
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
            foreach (['total','total_value','total_igv','total_other_taxes','total_discount','total_charge','total_prepayment'] as $field) $t->text($field)->default('0');
            $t->json('discounts')->nullable(); $t->json('calculation_snapshot')->nullable();
        });
        foreach (['document_items','sale_note_items'] as $table) Schema::connection('tenant')->create($table, function ($t) use ($table) {
            $t->increments('id'); $t->integer($table === 'document_items' ? 'document_id' : 'sale_note_id');
            $t->integer('item_id'); $t->json('item'); $t->text('quantity'); $t->text('unit_price'); $t->text('total'); $t->json('calculation_snapshot')->nullable();
            $t->string('affectation_igv_type_id')->default('20');
            foreach (['total_value','total_base_igv','total_igv','total_other_taxes','total_discount','total_charge'] as $field) $t->text($field)->default('0');
            $t->json('discounts')->nullable();
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
        foreach (['document_items','sale_note_items'] as $table) foreach (DB::connection('tenant')->table($table)->get() as $row) {
            $amount = Decimal::readRequired($row, 'total');
            $values = ['total'=>$amount, 'quantity'=>Decimal::readRequired($row,'quantity'), 'total_value'=>$amount, 'total_base_igv'=>$amount];
            DB::connection('tenant')->table($table)->where('id',$row->id)->update(['total_value'=>Decimal::represent($amount),
                'total_base_igv'=>Decimal::represent($amount), 'calculation_snapshot'=>json_encode(Decimal::snapshot($values))]);
        }
        foreach (['documents'=>['1900','1/3'],'sale_notes'=>['30','999']] as $table=>$totals) foreach ($totals as $index=>$amount) {
            $values = ['total'=>Decimal::of($amount),'total_value'=>Decimal::of($amount)];
            DB::connection('tenant')->table($table)->where('id',$index+1)->update(['total'=>Decimal::represent($amount),
                'total_value'=>Decimal::represent($amount),'calculation_snapshot'=>json_encode(Decimal::snapshot($values))]);
        }
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
        self::assertLessThanOrEqual(11, count($queries), 'Lecturas por lote de cabeceras y partidas, sin consulta por fila.');
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
            self::assertSame('sale_note:1', $book->getSheet(1)->getCell('A32')->getValue());
            self::assertSame('=1+1', $book->getSheet(0)->getCell('D2')->getValue());
            self::assertSame('s', $book->getSheet(0)->getCell('D2')->getDataType());
        } finally { unlink($path); }
    }

    private function discountHeader(): void
    {
        $values = ['total'=>Decimal::of('1899'), 'total_value'=>Decimal::of('1899'), 'total_discount'=>Decimal::of('1')];
        DB::connection('tenant')->table('documents')->where('id',1)->update(Decimal::values($values) + [
            'discounts'=>json_encode([['discount_type_id'=>'03','amount'=>'1']]),
            'calculation_snapshot'=>json_encode(Decimal::snapshot($values))]);
    }

    public function test_legacy_header_allocation_is_read_only_and_matches_every_page_and_export(): void
    {
        $this->discountHeader();
        $before = DB::connection('tenant')->table('document_items')->get()->toJson();
        $controller = new ReportSaleConsolidatedController();
        $rows = $controller->detailRecords($this->params());
        $summary = SalesConsolidated::summary($rows);
        self::assertSame('1930', $summary['total_sale']);
        self::assertSame('1931', $summary['line_total']);
        self::assertSame('1', $summary['header_discount']);
        $pages = [];
        foreach ([1,2] as $page) $pages[] = $controller->records(new Request($this->params()+['page'=>$page]))->response()->getData(true)['summary'];
        self::assertSame('1930', (string)Decimal::sum(array_map(fn($row)=>Decimal::readRequired($row,'total_sale'),$pages)));
        self::assertSame($summary, SalesConsolidated::summary($controller->totalsByItem(new Request($this->params()))));
        self::assertSame('5/19', (string)Decimal::readRequired(iterator_to_array($rows)[0], 'header_discount'));
        self::assertSame($before, DB::connection('tenant')->table('document_items')->get()->toJson());
        $html = view('report::sales_consolidated.exact_table', ['records'=>$rows,'reportSummary'=>$summary,'detail'=>true,'excel'=>true])->render();
        self::assertStringContainsString('Composición del total documental', $html);
        self::assertStringContainsString('Descuentos de cabecera', $html);
    }

    public function test_frozen_components_are_atomic_and_read_without_reloading_all_original_lines(): void
    {
        $this->discountHeader();
        $db = DB::connection('tenant');
        $freeze = function () {
            $header = \App\Models\Tenant\Document::withoutEagerLoads()->findOrFail(1);
            $header->timestamps = false;
            $items = $header->items()->withoutEagerLoads()->get();
            \App\Support\Exact\HeaderAllocation::freeze($header, $items);
            $header->saveQuietly();
        };
        $before = $db->table('document_items')->where('id',1)->value('calculation_snapshot');
        $db->beginTransaction(); $freeze(); $db->rollBack();
        self::assertSame($before, $db->table('document_items')->where('id',1)->value('calculation_snapshot'));
        $db->transaction($freeze);
        $stored = $db->table('document_items')->where('id',1)->first();
        self::assertSame('5/19', (string)Decimal::readRequired($stored,'header_discount'));
        self::assertSame('9495/19', (string)Decimal::readRequired($stored,'report_total'));
        $db->enableQueryLog(); $db->flushQueryLog();
        $rows = (new ReportSaleConsolidatedController())->detailRecords($this->params());
        $sourceQueries = array_filter($db->getQueryLog(), fn($q)=>str_contains($q['query'],'from "document_items"') && !str_contains($q['query'],'union'));
        // Only the USD document has no allocation; the VES document uses its frozen fractions.
        self::assertCount(1,$sourceQueries);
        self::assertSame('1930', SalesConsolidated::summary($rows)['total_sale']);
        $db->disableQueryLog();
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
