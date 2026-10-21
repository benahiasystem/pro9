<?php

namespace Tests\Unit;

use App\Models\Tenant\User;
use App\Support\Exact\{Decimal as D, Receivables, OutstandingSummary};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};
use Modules\Finance\Http\Controllers\UnpaidController;
use Tests\TestCase;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
class ExactReceivablesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp(); DB::purge('tenant');
        config(['database.connections.tenant'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>''], 'tenant.items_per_page'=>50]);
        auth()->setUser((new User())->forceFill(['id'=>1,'type'=>'admin','establishment_id'=>1]));
        Carbon::setTestNow('2026-10-19 12:00:00');
        Schema::connection('tenant')->create('persons',fn($t)=>[$t->increments('id'),$t->string('name')]);
        Schema::connection('tenant')->create('users',fn($t)=>[$t->increments('id'),$t->string('name')]);
        Schema::connection('tenant')->create('configurations',fn($t)=>[$t->increments('id'),$t->text('finances')->nullable()]);
        DB::connection('tenant')->table('configurations')->insert(['finances'=>'{"apply_arrears":true,"arrears_amount":0.123456789012}']);
        DB::connection('tenant')->table('persons')->insert(['id'=>1,'name'=>'Cliente']);
        DB::connection('tenant')->table('users')->insert([['id'=>1,'name'=>'Uno'],['id'=>2,'name'=>'Dos']]);
        foreach (['documents','sale_notes'] as $table) Schema::connection('tenant')->create($table,function ($t) {
            $t->increments('id');$t->integer('customer_id')->default(1);$t->integer('user_id')->default(1);$t->integer('establishment_id')->default(1);
            $t->string('document_type_id')->default('01');$t->string('state_type_id')->default('01');
            $t->string('control_number')->nullable();$t->string('fiscal_emission_mode')->nullable();
            $t->string('currency_type_id')->default('VES');$t->text('exchange_rate_sale')->default('3.00000000');
            $t->text('total');$t->text('total_payable')->nullable();$t->json('calculation_snapshot')->nullable();
            $t->date('date_of_issue')->default('2026-10-01');$t->date('due_date')->nullable();
            $t->string('series')->default('F');$t->integer('number')->default(1);$t->string('purchase_order')->nullable();
            $t->string('payment_method_type_id')->nullable();$t->boolean('changed')->default(false);$t->boolean('total_canceled')->default(false);
        });
        Schema::connection('tenant')->create('invoices',fn($t)=>[$t->increments('id'),$t->integer('document_id'),$t->date('date_of_due')]);
        foreach (['document_payments'=>'document_id','sale_note_payments'=>'sale_note_id'] as $table=>$foreign) Schema::connection('tenant')->create($table,function ($t) use ($foreign) {
            $t->increments('id');$t->integer($foreign);$t->text('payment');$t->date('date_of_payment')->default('2026-10-17');
            $t->dateTime('reversed_at')->nullable();$t->json('calculation_snapshot')->nullable();
        });
        Schema::connection('tenant')->create('notes',fn($t)=>[$t->increments('id'),$t->integer('document_id'),$t->integer('affected_document_id')]);
        foreach (['document_received_retentions'=>['document_id','applied_amount'], 'document_guarantee_funds'=>['document_id','amount'],
            'document_note_adjustments'=>['affected_document_id','amount'], 'document_note_refunds'=>['affected_document_id','amount']] as $table=>[$foreign,$field]) {
            Schema::connection('tenant')->create($table,function ($t) use ($foreign,$field) {
                $t->increments('id');$t->integer($foreign);$t->text($field);$t->json('calculation_snapshot')->nullable();
                $t->string('kind')->default('fund');$t->string('status')->default('applied');$t->string('component')->default('principal');$t->dateTime('reversed_at')->nullable();
            });
        }
        Schema::connection('tenant')->create('dispatches',fn($t)=>[$t->increments('id'),$t->integer('reference_document_id')]);
        Schema::connection('tenant')->create('items',fn($t)=>[$t->increments('id'),$t->integer('web_platform_id')->nullable()]);
        Schema::connection('tenant')->create('web_platforms',fn($t)=>[$t->increments('id'),$t->string('name')]);
        foreach (['document_items'=>'document_id','sale_note_items'=>'sale_note_id'] as $table=>$foreign) Schema::connection('tenant')->create($table,fn($t)=>[$t->increments('id'),$t->integer($foreign),$t->integer('item_id')]);
    }

    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }

    public function test_credit_expiry_checks_exact_balances_of_only_the_customer_and_requested_source(): void
    {
        $db=DB::connection('tenant');
        $db->table('persons')->insert(['id'=>2,'name'=>'Otro cliente']);
        $db->table('documents')->insert([['id'=>1,'customer_id'=>1,'total'=>'1','total_payable'=>'1'],
            ['id'=>2,'customer_id'=>2,'total'=>'500','total_payable'=>'500']]);
        $db->table('invoices')->insert([['document_id'=>1,'date_of_due'=>'2026-10-01'],['document_id'=>2,'date_of_due'=>'2025-01-01']]);
        $paid=D::of('9999999999999/10000000000000');
        $db->table('document_payments')->insert(['document_id'=>1,'payment'=>D::represent($paid),'calculation_snapshot'=>json_encode(D::snapshot(['payment'=>$paid]))]);
        $db->table('sale_notes')->insert(['id'=>1,'customer_id'=>1,'total'=>'500','total_payable'=>'500','changed'=>true,'due_date'=>'2025-01-01']);
        $controller=new UnpaidController();
        self::assertSame(18,$controller->customerExpiredDays(1,new Request(['model'=>'document']))->getData(true)['max_expired_days']);
        self::assertSame(0,$controller->customerExpiredDays(1,new Request(['model'=>'sale_note']))->getData(true)['max_expired_days']);
        self::assertSame(400,$controller->customerExpiredDays(1,new Request(['model'=>'unknown']))->getStatusCode());
    }

    public function test_balances_match_documents_without_fake_cash_or_duplicate_reversals(): void
    {
        $db = DB::connection('tenant');
        $db->table('documents')->insert([
            ['id'=>1,'total'=>'500','total_payable'=>'500','document_type_id'=>'01'],
            ['id'=>2,'total'=>'100','total_payable'=>'100','document_type_id'=>'07'],
        ]);
        $db->table('invoices')->insert(['document_id'=>1,'date_of_due'=>'2026-10-17']);
        $db->table('notes')->insert(['document_id'=>2,'affected_document_id'=>1]);
        $db->table('document_payments')->insert([['document_id'=>1,'payment'=>'100','reversed_at'=>null],['document_id'=>1,'payment'=>'999','reversed_at'=>'2026-10-18']]);
        $db->table('document_received_retentions')->insert(['document_id'=>1,'applied_amount'=>'75']);
        $db->table('document_guarantee_funds')->insert(['document_id'=>1,'amount'=>'50']);
        $db->table('document_note_adjustments')->insert(['affected_document_id'=>1,'amount'=>'20']);
        $db->table('document_note_refunds')->insert([['affected_document_id'=>1,'amount'=>'10','reversed_at'=>null],['affected_document_id'=>1,'amount'=>'999','reversed_at'=>'2026-10-18']]);
        $row = iterator_to_array(Receivables::dataset([]))[0];
        self::assertSame('205',$row['total_to_pay']);
        self::assertSame('100',$row['total_credit_notes']);
        self::assertSame('75',$row['retention_amount']);
        self::assertSame('30',$row['guarantee_amount']);
        self::assertSame('0.246913578024',$row['arrears']);
        self::assertSame('205',$row['balance_ves_equivalent']);
    }

    public function test_batches_summary_pagination_currencies_and_thirds_use_the_same_exact_rows(): void
    {
        $db = DB::connection('tenant');
        for ($id=1;$id<=502;$id++) $db->table('documents')->insert(['id'=>$id,'total'=>'500','total_payable'=>'500']);
        $db->table('documents')->insert(['id'=>503,'total'=>'0.333333333333','total_payable'=>'0.33',
            'calculation_snapshot'=>json_encode(D::snapshot(['total'=>'1/3','total_payable'=>'0.33']))]);
        $db->table('document_payments')->insert(['document_id'=>503,'payment'=>'0.33']);
        $db->table('sale_notes')->insert(['id'=>1,'total'=>'10','total_payable'=>'10','currency_type_id'=>'USD']);
        $db->enableQueryLog();$db->flushQueryLog();
        $rows = Receivables::dataset([]);
        self::assertCount(503,$rows);
        self::assertLessThanOrEqual(32,count($db->getQueryLog()),'Consultas acotadas por lote, sin consultas por fila.');
        $db->disableQueryLog();
        $summary = OutstandingSummary::summarize($rows);
        self::assertSame('251030',$summary['consolidated_balance_ves']);
        self::assertSame('10',$summary['USD_balance']);
        $response = (new UnpaidController())->records(new Request(['page'=>2,'limit'=>50]))->response()->getData(true);
        self::assertCount(50,$response['data']);self::assertSame(503,$response['meta']['total']);
        self::assertSame($summary,$response['summary']);
        self::assertSame(503,count(array_unique(array_column(iterator_to_array($rows),'report_key'))));
    }

    public function test_positive_fraction_smaller_than_storage_projection_is_not_filtered_out(): void
    {
        $db = DB::connection('tenant');
        $db->table('documents')->insert(['id'=>1,'total'=>'1','total_payable'=>'1']);
        $payment = D::of('9999999999999/10000000000000');
        $db->table('document_payments')->insert(['document_id'=>1,'payment'=>D::represent($payment),
            'calculation_snapshot'=>json_encode(D::snapshot(['payment'=>$payment]))]);
        $rows = Receivables::dataset([]);
        self::assertCount(1,$rows);
        self::assertSame('1/10000000000000',(string)D::readRequired(iterator_to_array($rows)[0],'total_to_pay'));
    }

    public function test_seller_scope_and_converted_sale_notes_remain_excluded(): void
    {
        $db = DB::connection('tenant');
        $db->table('documents')->insert([['id'=>1,'total'=>'500','user_id'=>1],['id'=>2,'total'=>'500','user_id'=>2]]);
        $db->table('sale_notes')->insert(['id'=>1,'total'=>'500','changed'=>true,'user_id'=>1]);
        auth()->setUser((new User())->forceFill(['id'=>1,'type'=>'seller','establishment_id'=>1]));
        self::assertCount(1,Receivables::dataset([]));
    }

    public function test_real_xlsx_export_has_text_amounts_composite_identity_and_original_fractions(): void
    {
        DB::connection('tenant')->table('documents')->insert(['id'=>1,'currency_type_id'=>'USD','total'=>'0.333333333333','total_payable'=>'0.33',
            'calculation_snapshot'=>json_encode(D::snapshot(['total'=>'1/3','total_payable'=>'0.33']))]);
        DB::connection('tenant')->table('persons')->where('id',1)->update(['name'=>'=1+1']);
        $records = Receivables::dataset([]);
        $export = (new \Modules\Report\Exports\NoPaidExport())->records($records)
            ->company((new \App\Models\Tenant\Company())->forceFill(['name'=>'QA','trade_name'=>'QA','number'=>'J000000000']));
        $path = tempnam(sys_get_temp_dir(),'exact-receivables-');
        try {
            file_put_contents($path,\Maatwebsite\Excel\Facades\Excel::raw($export,\Maatwebsite\Excel\Excel::XLSX));
            $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            self::assertSame(2,$book->getSheetCount());
            self::assertSame('document:1',$book->getSheet(1)->getCell('A2')->getValue());
            self::assertSame('1',$book->getSheet(1)->getCell('D2')->getValue());
            self::assertSame('3',$book->getSheet(1)->getCell('E2')->getValue());
            $found = false;
            foreach ($book->getSheet(0)->getRowIterator() as $row) foreach ($row->getCellIterator() as $cell) {
                self::assertNotSame('f',$cell->getDataType());
                if (in_array($cell->getValue(),['0.333333333333','=1+1'],true)) { $found = true; self::assertSame('s',$cell->getDataType()); }
            }
            self::assertTrue($found);
            $visible = $book->getSheet(0)->toArray(null,true,true,false);
            $texts = array_merge(...$visible);
            self::assertContains('Saldo equivalente (Bs.)',$texts);
            self::assertContains('Total documental equivalente (Bs.)',$texts);
            self::assertContains('0.99',$texts);
        } finally { unlink($path); }
    }

    public function test_pdf_displays_documentary_total_and_settled_balance_from_the_same_exact_rows(): void
    {
        DB::connection('tenant')->table('documents')->insert([
            ['id'=>1,'total'=>'500','total_payable'=>'500','currency_type_id'=>'VES','calculation_snapshot'=>null],
            ['id'=>2,'total'=>'0.333333333333','total_payable'=>'0.33','currency_type_id'=>'USD',
                'calculation_snapshot'=>json_encode(D::snapshot(['total'=>'1/3','total_payable'=>'0.33']))],
        ]);
        view()->addNamespace('finance',base_path('modules/Finance/Resources/views'));
        $rows = Receivables::dataset([]);
        $reportSummary = OutstandingSummary::summarize($rows);
        self::assertSame('501',$reportSummary['consolidated_total_ves']);
        self::assertSame('500.99',$reportSummary['consolidated_balance_ves']);
        $records = \App\Support\Exact\ReportProjection::money($rows,['total','total_to_pay','total_ves_equivalent','balance_ves_equivalent']);
        $company = (new \App\Models\Tenant\Company())->forceFill(['name'=>'Demostración Pro9','trade_name'=>'Demostración Pro9','number'=>'J000000000']);
        $html = view('finance::unpaid.reports.report_pdf',compact('records','reportSummary','company'))->render();
        self::assertStringContainsString('500.99',$html);
        self::assertStringContainsString('<small>Bs. 0.99</small>',$html);
        self::assertStringContainsString('<small>Bs. 1.00</small>',$html);
        self::assertSame('1/3',(string)D::readRequired(iterator_to_array($rows)[1],'total'));
        $bytes = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->output();
        self::assertStringStartsWith('%PDF-',$bytes);
        if ($path = getenv('PRO9_EXACT_RECEIVABLE_PDF_PATH')) file_put_contents($path,$bytes);
    }

    public function test_payables_pdf_shows_original_currency_and_historical_equivalents(): void
    {
        view()->addNamespace('finance',base_path('modules/Finance/Resources/views'));
        $row=\App\Support\Exact\Payables::row((object)['id'=>1,'supplier_id'=>1,'supplier_name'=>'Proveedor de demostración',
            'series'=>'F','number'=>1,'date_of_issue'=>'2026-10-19','currency_type_id'=>'USD','exchange_rate_sale'=>'3.00000000',
            'total'=>'0.333333333333','total_payable'=>'0.33','calculation_snapshot'=>D::snapshot(['total'=>'1/3','total_payable'=>'0.33'])],
            'purchase',D::of('0'),null);
        $rows=new \App\Support\Exact\ReportDataset([$row]);
        $reportSummary=OutstandingSummary::summarize($rows);
        $records=\App\Support\Exact\ReportProjection::money($rows,['total','total_to_pay','total_ves_equivalent','balance_ves_equivalent']);
        $company=(new \App\Models\Tenant\Company())->forceFill(['name'=>'Demostración Pro9','trade_name'=>'Demostración Pro9','number'=>'J000000000']);
        $html=view('finance::to_pay.report_pdf',compact('records','reportSummary','company'))->render();
        self::assertStringContainsString('USD 0.33',$html);
        self::assertStringContainsString('<small>Bs. 0.99</small>',$html);
        self::assertStringContainsString('<small>Bs. 1.00</small>',$html);
        self::assertSame('1/3',(string)D::readRequired($row,'total'));
        $bytes=\Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->output();
        self::assertStringStartsWith('%PDF-',$bytes);
        if ($path=getenv('PRO9_EXACT_PAYABLE_PDF_PATH')) file_put_contents($path,$bytes);
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
