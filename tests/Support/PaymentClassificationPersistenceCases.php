<?php

namespace Tests\Support;

use App\Models\Tenant\{Cash, BankAccount};
use App\Services\Payments\{CashLedger, Movement};
use Modules\Finance\Models\IncomePayment;
use Modules\Expense\Models\ExpensePayment;

// ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
trait PaymentClassificationPersistenceCases
{
    public function test_classified_cash_mysql_open_close_and_reports_share_the_same_native_balances(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'100']);
        $db->transaction(fn()=>CashLedger::open($cash,['opening_balances'=>['VES'=>'100','USD'=>'10']]));
        self::assertSame(2,$db->table('cash_transactions')->where('cash_id',$cash->id)->count());
        foreach ([['01','VES','50',false],['10','USD','5',false],['11','VES','200',false],['04','USD','20',false],['1','VES','30',true],['1','USD','2',true]] as [$method,$currency,$amount,$expense]) {
            $db->transaction(function() use($db,$input,$cash,$method,$currency,$amount,$expense) {
                if ($expense) {
                    $id=$db->table('expenses')->insertGetId(['user_id'=>$input['user_id'],'expense_type_id'=>$db->table('expense_types')->value('id'),
                        'establishment_id'=>$input['establishment_id'],'supplier_id'=>$input['customer_id'],'supplier'=>json_encode($input['customer']),
                        'currency_type_id'=>$currency,'external_id'=>(string)\Illuminate\Support\Str::uuid(),'date_of_issue'=>'2026-10-26','time_of_issue'=>'09:00:00',
                        'exchange_rate_sale'=>'100','total'=>$amount,'state_type_id'=>'05','number'=>'E-'.$currency,'fiscal_environment'=>'demo']);
                    $payment=ExpensePayment::create(['expense_id'=>$id,'expense_method_type_id'=>1,'date_of_payment'=>'2026-10-26','payment'=>$amount]);
                    $type=Cash::class;$destination=$cash->id;
                } else {
                    $id=$db->table('income')->insertGetId(['user_id'=>$input['user_id'],'income_type_id'=>$db->table('income_types')->value('id'),
                        'income_reason_id'=>$db->table('income_reasons')->value('id'),'establishment_id'=>$input['establishment_id'],'customer'=>'Cliente',
                        'currency_type_id'=>$currency,'external_id'=>(string)\Illuminate\Support\Str::uuid(),'date_of_issue'=>'2026-10-26','time_of_issue'=>'09:00:00',
                        'exchange_rate_sale'=>'100','total'=>$amount,'state_type_id'=>'05','number'=>1,'fiscal_environment'=>'demo']);
                    $payment=IncomePayment::create(['income_id'=>$id,'payment_method_type_id'=>$method,'date_of_payment'=>'2026-10-26','payment'=>$amount]);
                    $type=in_array($method,['01','10'],true) ? Cash::class : BankAccount::class;
                    $destination=$type===Cash::class ? $cash->id : BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'Cuenta '.$currency,'number'=>'TEST-'.$currency,'currency_type_id'=>$currency,'initial_balance'=>'0'])->id;
                }
                $global=$payment->global_payment()->create(['destination_type'=>$type,'destination_id'=>$destination,'user_id'=>$input['user_id'],'fiscal_environment'=>'demo']);
                self::assertSame($cash->id,$global->cash_session_id);
            });
        }
        $report=CashLedger::report($cash);
        self::assertSame('120',$report['balances_by_currency']['VES']['final_balance']);
        self::assertSame('13',$report['balances_by_currency']['USD']['final_balance']);
        $closed=CashLedger::close($cash);
        self::assertSame($report['balances_by_currency'],$closed['balances_by_currency']);
        self::assertSame('120',$cash->fresh()->final_balance);
        foreach (['VES'=>['120','3'],'USD'=>['13','3']] as $currency=>[$physical,$count]) {
            $data=(new \Modules\Finance\Http\Controllers\BalanceController())->records(new \Illuminate\Http\Request([
                'currency_type_id'=>$currency,'period'=>'date','date_start'=>'2026-10-26','date_end'=>'2026-10-26','month_start'=>'2026-10','month_end'=>'2026-10',
            ]));
            self::assertCount((int)$count,$data['classified']);
            self::assertSame($currency,$data['currency_type_id']);
            $cashGroups=array_filter($data['classified'],fn($row)=>$row['method']['collection_kind']==='cash');
            self::assertSame($physical,\App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::sum(array_map(fn($row)=>\App\Support\Exact\Decimal::readRequired($row,'balance'),$cashGroups))));
            self::assertCount(0,$data['records']);
        }
        foreach (['cash_summary','cash_simple','general_with_payments','income_egress','income_summary','payments_associated','summary_daily_operations'] as $type) {
            $payload=(new \Modules\CashReport\Services\ClassifiedCashReport())->build($cash,$type,[]);
            self::assertSame($report['balances_by_currency'],$payload['classified']['balances_by_currency']);
        }
        $app->instance('files',new \Illuminate\Filesystem\Filesystem());
        config(['view.compiled'=>sys_get_temp_dir().'/'.$this->database.'/views','view.paths'=>[]]);
        mkdir(config('view.compiled'),0777,true);
        $app->register(\Illuminate\View\ViewServiceProvider::class);
        $app['view']->addNamespace('cashreport',base_path('modules/CashReport/Resources/views'));
        foreach (['classified_a4','classified_ticket','classified_excel'] as $view) {
            $html=$app['view']->make('cashreport::reports.'.$view,$payload)->render();
            self::assertStringContainsString('120.00',$html);self::assertStringContainsString('13.00',$html);
        }
        if ($directory=getenv('PRO9_PAYMENTS_VISUAL_OUTPUT_DIR')) {
            if (!is_dir($directory)) mkdir($directory,0777,true);
            foreach (['a4','ticket80','ticket58'] as $paper) file_put_contents($directory.'/cash-'.$paper.'.pdf',
                (new \Modules\CashReport\Services\CashReportRenderer())->pdfContent('cash_summary',$cash,['paper'=>$paper]));
        }
    }

    public function test_payment_classification_is_ready_from_the_fresh_tenant_schema(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        self::assertFalse($db->getSchemaBuilder()->hasTable('payment_reporting_settings'));
        self::assertFalse($db->getSchemaBuilder()->hasColumn('cash','report_version'));
        self::assertSame('account',$db->table('payment_method_types')->where('id','11')->value('collection_kind'));
        self::assertSame('USD',$db->table('payment_method_types')->where('id','10')->value('cash_currency_type_id'));
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,['opening_balances'=>['VES'=>'100','USD'=>'10']]));
        self::assertSame('100',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);
        self::assertSame('10',CashLedger::report($cash)['balances_by_currency']['USD']['final_balance']);
    }

    public function test_classified_invoice_receipts_keep_received_currency_and_bank_money_outside_physical_cash(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,[]));
        $input['payments']=[];$input['exchange_rate_sale']='100';
        $document=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument();
        $row=['date_of_payment'=>'2026-10-26','payment_method_type_id'=>'10','payment'=>'100','currency_type_id'=>'USD',
            'original_amount'=>'1','exchange_rate'=>'100.00000000','operation_key'=>(string)\Illuminate\Support\Str::uuid(),'payment_destination_id'=>'cash'];
        $payment=\App\Services\Fiscal\FiscalDocumentPersistence::payment($document,$row);
        self::assertSame('1',Movement::row($payment->global_payment)['amount']);
        $account=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD','number'=>'USD-test','currency_type_id'=>'USD','initial_balance'=>'0']);
        $row=array_replace($row,['payment_method_type_id'=>'04','payment'=>'132','original_amount'=>'1.32','payment_destination_id'=>$account->id,'operation_key'=>(string)\Illuminate\Support\Str::uuid()]);
        $bank=\App\Services\Fiscal\FiscalDocumentPersistence::payment($document,$row);
        self::assertSame($bank->id,\App\Services\Fiscal\FiscalDocumentPersistence::payment($document,$row)->id);
        self::assertSame(2,$db->table('global_payments')->count());
        $report=CashLedger::report($cash);
        self::assertSame('1',$report['balances_by_currency']['USD']['final_balance']);self::assertSame('0',$report['balances_by_currency']['VES']['final_balance']);
        self::assertTrue($document->fresh()->exactBalance()->isZero());
        $groups=\App\Services\Payments\FinancialReport::groups('2026-10-26','2026-10-26');
        self::assertCount(2,$groups);self::assertSame(['USD','USD'],array_column($groups,'currency_type_id'));
        $records=(new \Modules\Finance\Http\Controllers\GlobalPaymentController())->getRecords([
            'payment_type'=>null,'destination_type'=>null,'period'=>'date','date_start'=>'2026-10-26','date_end'=>'2026-10-26','month_start'=>'2026-10','month_end'=>'2026-10',
        ],\Modules\Finance\Models\GlobalPayment::class)->get();
        self::assertCount(2,$records);
        foreach ($records as $global) self::assertNotNull(Movement::row($global));
        $resource=new \Modules\Finance\Http\Resources\GlobalPaymentCollection($records);
        self::assertCount(2,$resource->toArray(new \Illuminate\Http\Request()));
        self::assertSame('2.32',$resource->with(new \Illuminate\Http\Request())['totals_by_version'][0]['total']);
    }

    public function test_classified_sale_note_conversion_reuses_a_receipt_without_second_cash_income(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,[]));
        $controller=new \App\Http\Controllers\Tenant\SaleNoteController();
        $saleInput=array_replace($input,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]]);
        $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($saleInput));
        foreach ($input['items'] as $line) $note->items()->create($line);
        $controller->savePayments($note,[['payment'=>'50','payment_method_type_id'=>'01','payment_destination_id'=>'cash','date_of_payment'=>'2026-10-26']]);
        $source=$note->payments()->firstOrFail();
        $input['sale_note_id']=$note->id;
        $input['payments']=[['payment'=>'50','payment_method_type_id'=>'01','payment_destination_id'=>'cash','date_of_payment'=>'2026-10-26','source_sale_note_payment_id'=>$source->id]];
        $document=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument();
        self::assertSame((string)$source->id,$document->payments->first()->payment_method_snapshot['receipt_origin']['id']);
        self::assertSame(1,$db->table('global_payments')->count());
        self::assertSame('50',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);
    }

    public function test_classified_invoice_change_only_reduces_cash_and_rejects_electronic_excess(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,[]));
        $account=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'VES','number'=>'VES-test','currency_type_id'=>'VES','initial_balance'=>'0']);
        $bank=['payment'=>'200','original_amount'=>'200','currency_type_id'=>'VES','exchange_rate'=>'1','payment_method_type_id'=>'04','payment_destination_id'=>$account->id,'date_of_payment'=>'2026-10-26'];
        $input['payments']=[$bank,array_replace($bank,['payment'=>'50','original_amount'=>'50','payment_method_type_id'=>'01','payment_destination_id'=>'cash'])];
        $document=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument();
        self::assertSame('32',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);
        self::assertTrue($document->fresh()->exactBalance()->isZero());
        self::assertSame('200',Movement::row($document->payments()->where('payment_method_type_id','04')->firstOrFail()->global_payment)['amount']);
        $count=$db->table('documents')->count();
        $input['payments']=[array_replace($bank,['payment'=>'250','original_amount'=>'250'])];
        try {
            (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input);
            self::fail('Un exceso electrónico no puede convertirse en vuelto físico.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('payments',$error->errors());
        }
        self::assertSame($count,$db->table('documents')->count());
        self::assertSame('32',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);
    }

    public function test_classified_cash_report_performance_against_legacy_at_1000_and_10000_receipts(): void
    {
        if (getenv('PRO9_PAYMENT_BENCHMARK') !== '1') $this->markTestSkipped('Medición explícita en MySQL temporal.');
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,[]));
        $parent=$db->table('income')->insertGetId(['user_id'=>$input['user_id'],'income_type_id'=>$db->table('income_types')->value('id'),'income_reason_id'=>$db->table('income_reasons')->value('id'),
            'establishment_id'=>$input['establishment_id'],'customer'=>'Cliente','currency_type_id'=>'VES','external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'date_of_issue'=>'2026-10-26','time_of_issue'=>'09:00:00','exchange_rate_sale'=>'1','total'=>'100','state_type_id'=>'05','number'=>1,'fiscal_environment'=>'demo']);
        $context=json_encode(['version'=>2,'method'=>['catalog'=>'payment_method_types','id'=>'01','description'=>'Efectivo','collection_kind'=>'cash'],
            'currency_type_id'=>'VES','direction'=>'income','opening'=>false,'date'=>'2026-10-26','number'=>'I-1','exchange_rate'=>'1']);
        $results=[];$count=0;$db->setEventDispatcher(new \Illuminate\Events\Dispatcher($app));$db->listen(function() use(&$count){$count++;});
        foreach ([1000,10000] as $target) {
            $current=$db->table('income_payments')->count();
            foreach (array_chunk(range($current+1,$target),250) as $ids) {
                $db->table('income_payments')->insert(array_map(fn($id)=>['id'=>$id,'income_id'=>$parent,'payment_method_type_id'=>'01','date_of_payment'=>'2026-10-26','payment'=>'0.01',
                    'calculation_snapshot'=>json_encode(\App\Support\Exact\Decimal::snapshot(['payment'=>'0.01']))],$ids));
                $db->table('global_payments')->insert(array_map(fn($id)=>['payment_id'=>$id,'payment_type'=>IncomePayment::class,'destination_type'=>Cash::class,'destination_id'=>$cash->id,
                    'cash_session_id'=>$cash->id,'user_id'=>$input['user_id'],'fiscal_environment'=>'demo','report_context'=>$context],$ids));
            }
            $times=[];$queries=[];
            for ($run=0;$run<5;$run++) {
                $count=0;$start=hrtime(true);$report=CashLedger::report($cash);$times[]=(hrtime(true)-$start)/1000000;$queries[]=$count;
                self::assertSame($target===1000 ? '10' : '100',$report['balances_by_currency']['VES']['income']);
            }
            $count=0;$start=hrtime(true);$legacy=(new \Modules\CashReport\Services\Builders\CashSummaryBuilder())->setDataToReport($cash->id);
            $legacyMs=(hrtime(true)-$start)/1000000;$legacyQueries=$count;sort($times);
            self::assertTrue(\App\Support\Exact\Decimal::of($legacy['total_cash_payment_method_type_01'])->isEqualTo($report['balances_by_currency']['VES']['final_balance']));
            $results[]=['receipts'=>$target,'classified_p50_ms'=>$times[2],'classified_p95_ms'=>$times[4],'classified_queries'=>max($queries),
                'legacy_ms'=>$legacyMs,'legacy_queries'=>$legacyQueries,'peak_memory_bytes'=>memory_get_peak_usage(true),'scope'=>'Datos de reporte PHP/MySQL, sin HTTP ni render PDF'];
            self::assertLessThanOrEqual($legacyMs+max($legacyMs*0.2,100),$times[4]);
        }
        file_put_contents(sys_get_temp_dir().'/pro9-payment-benchmark.json',json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    }
}
// ######## FIN CLASIFICACION DE COBROS Y CAJA ########
