<?php

namespace Tests\Feature;

use App\Models\Tenant\{Cash, BankAccount, PaymentMethodType};
use App\Services\Payments\{CashLedger, Classification, Movement};
use App\Support\Exact\Decimal as D;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;
use Modules\Finance\Models\IncomePayment;
use Tests\TestCase;

// ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
class PaymentClassificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::purge('tenant');
        config(['fiscal_emission.operation_tables'=>[]]);
        config(['database.connections.tenant'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'']]);
        $schema=Schema::connection('tenant');
        $schema->create('companies',function(Blueprint $t) {$t->increments('id');$t->string('fiscal_environment')->default('demo');$t->string('fiscal_emission_mode')->default('digital');});
        DB::connection('tenant')->table('companies')->insert(['id'=>1]);
        $schema->create('users',fn(Blueprint $t)=>$t->increments('id'));
        DB::connection('tenant')->table('users')->insert(['id'=>1]);
        $schema->create('payment_method_types',function(Blueprint $t) {
            $t->string('id')->primary();$t->string('description');$t->string('hka_code')->default('99');
            $t->string('collection_kind')->default('unclassified');$t->string('cash_currency_type_id')->nullable();
            foreach(['is_cash','is_credit'] as $f)$t->boolean($f)->default(false);$t->boolean('is_active')->default(true);
        });
        $schema->create('cash',function(Blueprint $t) {
            $t->increments('id');$t->integer('user_id');$t->boolean('state')->default(true);
            $t->date('date_opening')->nullable();$t->string('time_opening')->nullable();$t->date('date_closed')->nullable();$t->string('time_closed')->nullable();
            foreach(['beginning_balance','income','final_balance'] as $f)$t->string($f)->default('0');$t->json('calculation_snapshot')->nullable();$t->timestamps();
        });
        $schema->create('cash_transactions',function(Blueprint $t) { $t->increments('id');$t->integer('cash_id');$t->string('payment_method_type_id');$t->string('currency_type_id')->nullable();$t->string('date');$t->string('description');$t->string('payment');$t->json('calculation_snapshot')->nullable(); });
        $schema->create('cash_currency_balances',function(Blueprint $t) { $t->integer('cash_id');$t->string('currency_type_id');foreach(['beginning_balance','income','egress','final_balance'] as $f)$t->string($f)->default('0');$t->json('calculation_snapshot');$t->primary(['cash_id','currency_type_id']); });
        $schema->create('bank_accounts',function(Blueprint $t) {$t->increments('id');$t->string('currency_type_id');$t->integer('status')->default(1);});
        $schema->create('income',function(Blueprint $t) {$t->increments('id');$t->integer('user_id');$t->string('currency_type_id');$t->string('exchange_rate_sale')->default('1');$t->string('state_type_id')->default('05');$t->integer('number')->default(1);$t->date('date_of_issue')->default('2026-10-26');});
        $schema->create('income_payments',function(Blueprint $t) {$t->increments('id');$t->integer('income_id');$t->string('payment_method_type_id');$t->date('date_of_payment');$t->string('payment');$t->json('calculation_snapshot')->nullable();$t->dateTime('reversed_at')->nullable();});
        $schema->create('document_payments',function(Blueprint $t) {$t->increments('id');foreach(['currency_type_id','original_amount','tax_amount','exchange_rate'] as $field)$t->string($field);$t->json('payment_method_snapshot')->nullable();$t->dateTime('reversed_at')->nullable();});
        $schema->create('global_payments',function(Blueprint $t) {$t->increments('id');$t->integer('payment_id');$t->string('payment_type');$t->integer('destination_id');$t->string('destination_type');$t->integer('user_id')->nullable();$t->string('fiscal_environment');$t->integer('cash_session_id')->nullable();$t->json('report_context')->nullable();$t->timestamps();});
        $initial=(require database_path('seeders/data/tenant_initial_data.php'))['tables']['payment_method_types']['rows'];
        foreach($initial as $row) DB::connection('tenant')->table('payment_method_types')->insert(array_intersect_key($row,array_flip(['id','description','hka_code','collection_kind','cash_currency_type_id','is_cash','is_credit','is_active'])));
    }

    private function cash(): Cash { return Cash::create(['user_id'=>1,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','state'=>true,'beginning_balance'=>'100']); }

    private function receipt(string $method,string $amount,string $currency,$destination): \Modules\Finance\Models\GlobalPayment
    {
        return DB::connection('tenant')->transaction(function() use($method,$amount,$currency,$destination) {
            $id=DB::connection('tenant')->table('income')->insertGetId(['user_id'=>1,'currency_type_id'=>$currency,'exchange_rate_sale'=>'100']);
            $payment=IncomePayment::create(['income_id'=>$id,'payment_method_type_id'=>$method,'date_of_payment'=>'2026-10-26','payment'=>$amount]);
            return $payment->global_payment()->create(['destination_id'=>$destination,'destination_type'=>in_array($method,['01','10'],true) ? Cash::class : BankAccount::class,'user_id'=>1,'fiscal_environment'=>'demo']);
        });
    }

    public function test_physical_balances_exclude_electronic_money_and_keep_currencies_separate(): void
    {
        $cash=$this->cash();
        $bank=BankAccount::create(['currency_type_id'=>'VES']);
        $physical=$this->receipt('01','50','VES',$cash->id);
        $this->receipt('10','5','USD',$cash->id);
        $electronic=$this->receipt('11','200','VES',$bank->id);
        self::assertSame($cash->id,$electronic->cash_session_id);
        self::assertSame('account',$electronic->report_context['method']['collection_kind']);
        $rows=CashLedger::rows($cash);
        foreach([['VES','30'],['USD','2']] as [$currency,$amount])$rows[]=['opening'=>false,'method'=>['collection_kind'=>'cash'],'currency_type_id'=>$currency,'direction'=>'egress','amount'=>$amount];
        $balances=CashLedger::summarize($rows,['VES'=>'100','USD'=>'10']);
        self::assertSame('120',$balances['VES']['final_balance']);self::assertSame('13',$balances['USD']['final_balance']);
        PaymentMethodType::findOrFail('11')->update(['description'=>'Nombre nuevo']);
        self::assertSame('Pago Móvil',Movement::row($electronic->fresh())['method']['description']);
        self::assertSame('50',Movement::row($physical)['amount']);
    }

    public function test_account_currency_and_closed_cash_reject_the_entire_operation(): void
    {
        $cash=$this->cash();$account=BankAccount::create(['currency_type_id'=>'USD']);
        foreach([fn()=>$this->receipt('04','5','VES',$account->id),fn()=>$this->receipt('10','5','VES',$cash->id)] as $operation) {
            try {$operation();self::fail('Debe rechazar el movimiento incompatible.');}catch(ValidationException $expected){}
        }
        $cash->state=false;$cash->save();
        try {$this->receipt('01','5','VES',$cash->id);self::fail('Caja cerrada.');}catch(ValidationException $expected){}
        self::assertSame(0,DB::connection('tenant')->table('income_payments')->count());self::assertSame(0,DB::connection('tenant')->table('global_payments')->count());
    }

    public function test_credit_and_unclassified_methods_do_not_create_receipts(): void
    {
        $cash=$this->cash();
        PaymentMethodType::create(['id'=>'07','description'=>'Sin clasificar','collection_kind'=>'unclassified','is_active'=>false]);
        foreach(['05','07'] as $method)try{$this->receipt($method,'5','VES',$cash->id);self::fail('No representa recepción.');}catch(ValidationException $expected){}
        self::assertSame(0,DB::connection('tenant')->table('income_payments')->count());
    }

    public function test_flags_are_derived_and_unclassified_active_methods_are_rejected(): void
    {
        $method=PaymentMethodType::create(['id'=>'27','description'=>'Nuevo','collection_kind'=>'account','is_cash'=>true,'is_credit'=>true,'is_active'=>true]);
        self::assertFalse($method->fresh()->is_cash);self::assertFalse($method->fresh()->is_credit);
        $this->expectException(ValidationException::class);
        $method->update(['collection_kind'=>'unclassified']);
    }

    public function test_new_installation_enforces_one_open_cash_without_activation(): void
    {
        self::assertFalse(Schema::connection('tenant')->hasTable('payment_reporting_settings'));
        self::assertFalse(Schema::connection('tenant')->hasColumn('cash','report_version'));
        $cash=$this->cash();
        try { $this->cash(); self::fail('El usuario ya tiene una caja abierta.'); }
        catch (ValidationException $error) { self::assertArrayHasKey('cash',$error->errors()); }
        $cash->state=false;$cash->save();
        self::assertTrue($this->cash()->state);
        self::assertFalse(PaymentMethodType::findOrFail('11')->is_cash);
    }

    public function test_rational_sums_and_opening_are_counted_once(): void
    {
        $rows=[];for($i=0;$i<3;$i++)$rows[]=['opening'=>false,'method'=>['collection_kind'=>'cash'],'currency_type_id'=>'VES','direction'=>'income','amount'=>'0.333333333333','calculation_snapshot'=>D::snapshot(['amount'=>'1/3'])];
        $rows[]=['opening'=>true,'method'=>['collection_kind'=>'cash'],'currency_type_id'=>'VES','direction'=>'income','amount'=>'100'];
        $summary=CashLedger::summarize($rows,['VES'=>'100']);self::assertSame('101',$summary['VES']['final_balance']);
    }

    public function test_actual_receipt_and_igtf_are_separate_from_documentary_principal(): void
    {
        $payment=new \App\Models\Tenant\DocumentPayment();
        $payment->forceFill(['payment'=>'100','original_amount'=>'1','tax_amount'=>'0.03','currency_type_id'=>'USD','receipt_parent_id'=>null]);
        $parent=new \App\Models\Tenant\Document();$parent->forceFill(['state_type_id'=>'01','currency_type_id'=>'VES']);
        $payment->setRelation('associated_record_payment',$parent);
        $global=new \Modules\Finance\Models\GlobalPayment();
        $global->report_context=['version'=>2,'method'=>['catalog'=>'payment_method_types','id'=>'10','description'=>'Efectivo USD','collection_kind'=>'cash'],
            'currency_type_id'=>'USD','direction'=>'income','opening'=>false,'date'=>'2026-10-26','number'=>'FF-1','exchange_rate'=>'100'];
        $global->setRelation('payment',$payment);
        $row=Movement::row($global);
        self::assertSame('1.03',$row['amount']);self::assertSame('1',$row['principal']);self::assertSame('0.03',$row['tax']);
        $payment->receipt_parent_id=1;self::assertNull(Movement::row($global));
        $payment->receipt_parent_id=null;$payment->reversed_at='2026-10-26';self::assertNull(Movement::row($global));
    }

    public function test_reclassifying_an_initial_method_changes_only_future_movements(): void
    {
        $cash=$this->cash();$bank=BankAccount::create(['currency_type_id'=>'VES']);
        $method=PaymentMethodType::findOrFail('04');
        $global=$this->receipt('04','5','VES',$bank->id);
        $method->update(['collection_kind'=>'cash','cash_currency_type_id'=>'VES']);
        self::assertSame('account',Movement::row($global->fresh())['method']['collection_kind']);
        self::assertSame('0',CashLedger::summarize(CashLedger::rows($cash),[])['VES']['income']);
        $this->expectException(ValidationException::class);
        $global->payment->update(['payment'=>'10']);
    }

    

    public function test_excel_contains_text_amounts_and_their_exact_numerators_and_denominators(): void
    {
        view()->addNamespace('cashreport',base_path('modules/CashReport/Resources/views'));
        $row=['id'=>1,'party'=>'Cliente','number'=>'I-1','date'=>'2026-10-26','opening'=>false,'direction'=>'income','currency_type_id'=>'VES',
            'method'=>['description'=>'Efectivo','collection_kind'=>'cash'],'amount'=>'0.333333333333','principal'=>'0.333333333333','tax'=>'0',
            'calculation_snapshot'=>D::snapshot(['amount'=>'1/3','principal'=>'1/3','tax'=>'0'])];
        $payload=['title'=>'Reporte de caja','header'=>['company_name'=>'Prueba','company_number'=>'J123456789','cash_user_name'=>'Operador',
            'cash_date_opening'=>'2026-10-26','cash_time_opening'=>'09:00:00','cash_date_closed'=>null,'cash_time_closed'=>null,'cash_state'=>true],
            'classified'=>['balances_by_currency'=>CashLedger::summarize([$row],[]),'rows'=>[$row],'groups'=>[],'show_detail'=>true]];
        $export=(new \Modules\CashReport\Services\ClassifiedCashExport())->view_name('cashreport::reports.classified_excel')->data($payload);
        $bytes=\Maatwebsite\Excel\Facades\Excel::raw($export,\Maatwebsite\Excel\Excel::XLSX);
        $path=tempnam(sys_get_temp_dir(),'classified-xlsx-');file_put_contents($path,$bytes);
        try {
            $book=\PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx')->load($path);
            $fractions=$book->getSheetByName('Fracciones exactas');self::assertNotNull($fractions);
            $found=false;
            foreach ($fractions->toArray() as $cells) if ($cells[0]==='rows:0:amount') {
                self::assertSame('1',$cells[3]);self::assertSame('3',$cells[4]);$found=true;
            }
            self::assertTrue($found);
            $textAmounts=0;
            foreach ($book->getSheet(0)->getRowIterator() as $sheetRow) foreach ($sheetRow->getCellIterator() as $cell) {
                if ($cell->getValue()==='0.33') {self::assertSame('s',$cell->getDataType());$textAmounts++;}
            }
            self::assertGreaterThan(0,$textAmounts);
        } finally {unlink($path);}
    }
}
// ######## FIN CLASIFICACION DE COBROS Y CAJA ########
