<?php
namespace Tests\Support;

use App\Models\Tenant\{Company, Person, Document, User};
use App\Services\Fiscal\{FiscalProfileService, FiscalDocumentPersistence, HkaEmission, HkaPdf, HkaIgtfNote, SpecialTaxpayerPolicy};
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

trait SpePersistenceCases
{
    // ######## INICIO INTERMEDIACION AUTOMATICA DEL COBRO ########
    public function test_automatic_intermediation_matches_evaluation_quote_preview_and_initial_persistence(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $company=Company::firstOrFail();
        $company->forceFill(['fiscal_emission_mode'=>'free_form','igtf_enabled'=>true,'igtf_rate'=>3])->save();
        FiscalProfileService::store($company,SpeProfiles::profile());
        $account=\App\Models\Tenant\BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),
            'description'=>'Cobros USD','number'=>'AUTOMATIC-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
        $db->table('payment_method_types')->where('id','13')->update(['bank_account_id'=>$account->id,'is_active'=>1]);
        if (!\Illuminate\Http\Request::hasMacro('validate')) {
            \Illuminate\Http\Request::macro('validate', function (array $rules) { return validator($this->all(), $rules)->validate(); });
        }
        $input['currency_type_id']='USD';$input['exchange_rate_sale']='10';
        foreach (['10'=>['none','subject','6.96','cash'], '13'=>['financial_institution','not_applicable','0.00',$account->id]] as $method=>$expected) {
            [$intermediation,$status,$tax,$destination]=$expected;
            $row=['date_of_payment'=>'2026-09-10','payment_method_type_id'=>(string)$method,'payment'=>'232',
                'currency_type_id'=>'USD','original_amount'=>'232','exchange_rate'=>'10','payment_destination_id'=>$destination,
                'operation_key'=>(string)Str::uuid()];
            $evaluation=(new \App\Http\Controllers\Tenant\FiscalProfileController())->evaluate(\Illuminate\Http\Request::create('/', 'POST',
                ['customer_id'=>$input['customer_id'],'date'=>'2026-09-10','currency_type_id'=>'USD','original_amount'=>'232','payment_method_type_id'=>(string)$method]))['data'];
            self::assertSame($intermediation,$evaluation['payment']['financial_intermediation']);
            self::assertSame($status,$evaluation['payment']['igtf_status']);self::assertFalse($evaluation['needs_intermediation']);
            $quote=(new \App\Http\Controllers\Tenant\DocumentPaymentQuoteController())(\Illuminate\Http\Request::create('/', 'POST',
                ['currency_type_id'=>'USD','exchange_rate_sale'=>'10','principal_due'=>'232','payments'=>[$row]]))['data'];
            self::assertSame($tax,$quote['payments'][0]['tax_amount']);
            $preview=new Document(['currency_type_id'=>'USD','exchange_rate_sale'=>'10','total'=>'232','date_of_issue'=>'2026-09-10']);
            $preview->setRelation('payments',new \Illuminate\Database\Eloquent\Collection());
            (new \App\CoreFacturalo\Facturalo())->setPaymentsPreview($preview,[$row]);
            self::assertTrue(\App\Support\Exact\Decimal::of($tax)->isEqualTo($preview->payments->first()->tax_amount));
            $before=$db->table('global_payments')->count();
            $input['external_id']=(string)Str::uuid();$input['payments']=[$row];
            $document=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
            $receipt=$document->payments()->whereNull('receipt_parent_id')->firstOrFail();
            self::assertSame($intermediation,$receipt->fiscal_context['decision']['financial_intermediation']);
            self::assertSame($status,$receipt->igtf_status);self::assertTrue($document->exactBalance()->isZero());
            self::assertSame($before+1,$db->table('global_payments')->count());
            self::assertTrue(\App\Support\Exact\Decimal::of($tax)->isEqualTo($receipt->tax_amount));
            $frozen=$receipt->fiscal_context;
            $db->table('payment_method_types')->where('id',$method)->update(['is_active'=>0,'collection_kind'=>$method===10 ? 'account' : 'cash']);
            self::assertSame($receipt->id,FiscalDocumentPersistence::payment($document,$row)->id);
            $repeat=new \ReflectionMethod(\App\CoreFacturalo\Facturalo::class,'savePayments');$repeat->setAccessible(true);
            $db->transaction(fn()=>$repeat->invoke(new \App\CoreFacturalo\Facturalo(),$document,[$row]));
            self::assertSame($frozen,$receipt->fresh()->fiscal_context);self::assertSame($before+1,$db->table('global_payments')->count());
            $db->table('payment_method_types')->where('id',$method)->update(['is_active'=>1,'collection_kind'=>$method===10 ? 'cash' : 'account']);
        }
        // Generic profile/retention proposals still accept requests without a payment method.
        $general=(new \App\Http\Controllers\Tenant\FiscalProfileController())->evaluate(\Illuminate\Http\Request::create('/', 'POST',
            ['customer_id'=>$input['customer_id'],'date'=>'2026-09-10','currency_type_id'=>'VES']))['data'];
        self::assertArrayHasKey('retention',$general);
    }

    public function test_automatic_intermediation_rejects_contradictions_without_effects_and_freezes_later_retries(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $company=Company::firstOrFail();$company->forceFill(['fiscal_emission_mode'=>'free_form','igtf_enabled'=>true,'igtf_rate'=>3])->save();
        FiscalProfileService::store($company,SpeProfiles::profile());
        $input['payments']=[];$input['payment_condition_id']='02';$input['exchange_rate_sale']='10';
        $document=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
        self::assertSame(0,$document->payments()->count());self::assertSame(0,$db->table('global_payments')->count());
        $row=['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'10','currency_type_id'=>'USD',
            'original_amount'=>'5','payment'=>'50','exchange_rate'=>'10','payment_destination_id'=>'cash','operation_key'=>(string)Str::uuid()];
        $before=[$db->table('documents')->count(),$db->table('document_payments')->count(),$db->table('global_payments')->count()];
        try {
            FiscalDocumentPersistence::payment($document,$row+['financial_intermediation'=>'financial_institution']);
            self::fail('El efectivo no admite intermediación bancaria.');
        } catch (ValidationException $error) { self::assertArrayHasKey('financial_intermediation',$error->errors()); }
        self::assertSame($before,[$db->table('documents')->count(),$db->table('document_payments')->count(),$db->table('global_payments')->count()]);
        $invalid=array_replace($input,['external_id'=>(string)Str::uuid(),'payment_condition_id'=>'01',
            'payments'=>[$row+['financial_intermediation'=>'financial_institution']]]);
        $stock=$db->table('item_warehouse')->value('stock');
        try {
            (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($invalid);
            self::fail('La factura no puede guardarse con intermediación contradictoria.');
        } catch (ValidationException $error) { self::assertArrayHasKey('financial_intermediation',$error->errors()); }
        self::assertSame($before,[$db->table('documents')->count(),$db->table('document_payments')->count(),$db->table('global_payments')->count()]);
        self::assertSame($stock,$db->table('item_warehouse')->value('stock'));
        $payment=FiscalDocumentPersistence::payment($document,$row);
        self::assertSame('none',$payment->fiscal_context['decision']['financial_intermediation']);
        self::assertSame('subject',$payment->igtf_status);self::assertEquals('0.15',$payment->tax_amount);
        $snapshot=$payment->fresh()->fiscal_context;$count=$db->table('global_payments')->count();
        $db->table('payment_method_types')->where('id','10')->update(['collection_kind'=>'account','is_active'=>0]);
        $company->forceFill(['igtf_enabled'=>false,'igtf_rate'=>4])->save();
        self::assertSame($payment->id,FiscalDocumentPersistence::payment($document,$row)->id);
        self::assertSame($snapshot,$payment->fresh()->fiscal_context);self::assertSame($count,$db->table('global_payments')->count());
        self::assertEquals('182',$document->fresh()->balance);
    }
    // ######## FIN INTERMEDIACION AUTOMATICA DEL COBRO ########

    public function test_web_invoice_dollar_rounding_closes_single_and_mixed_cash_with_one_real_movement_per_receipt(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $company=Company::firstOrFail();
        $company->forceFill(['fiscal_emission_mode'=>'free_form','igtf_enabled'=>true,'igtf_rate'=>'3'])->save();
        FiscalProfileService::store($company,SpeProfiles::profile());
        $db->table('payment_method_types')->where('id','10')->update(['iva_rate_type_id'=>'IGTF']);
        $input['currency_type_id']='VES';$input['exchange_rate_sale']='897.53830000';
        $input['items'][0]['quantity']='1';$input['items'][0]['unit_price']='5000';
        $usd=['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'10','currency_type_id'=>'USD',
            'original_amount'=>'5.57','payment'=>'4999.29','exchange_rate'=>'897.53830000',
            'financial_intermediation'=>'none','payment_destination_id'=>'cash','igtf_status'=>'subject','quoted_tax_amount'=>'0.17'];
        $ves=array_replace($usd,['payment_method_type_id'=>'01','currency_type_id'=>'VES','original_amount'=>'1000',
            'payment'=>'1000','igtf_status'=>'not_applicable','quoted_tax_amount'=>'0.00']);
        $mixed=array_replace($usd,['original_amount'=>'4.46','payment'=>'4003.02','quoted_tax_amount'=>'0.13']);
        $stock=\App\Support\Exact\Decimal::of($db->table('item_warehouse')->where('item_id',$input['items'][0]['item_id'])->value('stock'));
        foreach ([[$usd],[$ves,$mixed],[$mixed,$ves]] as $index=>$rows) {
            $input['external_id']=(string)Str::uuid();
            $input['payments']=array_map(fn($row)=>$row+['operation_key'=>(string)Str::uuid()],$rows);
            $before=$db->table('global_payments')->count();
            $document=(new \App\CoreFacturalo\Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh();
            $receipt=$document->payments->whereNull('receipt_parent_id')->firstWhere('currency_type_id','USD');
            $expected=$index===0 ? '5000' : '4000';
            self::assertTrue($receipt->exactValue('payment')->isEqualTo($expected));
            self::assertTrue($document->exactBalance()->isZero());
            self::assertTrue((bool)$document->total_canceled);
            self::assertSame($index===0 ? '0.711669' : '-3.020818',\App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::readRequired($receipt,'payment_adjustment')));
            self::assertSame($index===0 ? '5.74' : '4.59',\App\Services\Payments\Movement::row($receipt->global_payment)['amount']);
            self::assertSame($before+count($rows),$db->table('global_payments')->count());
            self::assertSame('IGTF',$receipt->payment_method_snapshot['iva_rate_type_id']);
            self::assertTrue(\App\Support\Exact\Decimal::of($receipt->payment_method_snapshot['iva_rate_percentage'])->isEqualTo('3'));
            self::assertSame('not_requested',$document->emission->status);
            self::assertTrue(\App\Support\Exact\Decimal::of($db->table('item_warehouse')->where('item_id',$input['items'][0]['item_id'])->value('stock'))->isEqualTo($stock->minus((string)($index+1))));
            $count=$db->table('document_payments')->count();
            $savedRow=collect($input['payments'])->firstWhere('currency_type_id','USD');
            self::assertSame($receipt->id,FiscalDocumentPersistence::payment($document,$savedRow)->id);
            self::assertSame($count,$db->table('document_payments')->count());
            self::assertSame($before+count($rows),$db->table('global_payments')->count());
        }
        $before=[$db->table('documents')->count(),$db->table('document_payments')->count(),$db->table('global_payments')->count(),
            $db->table('item_warehouse')->where('item_id',$input['items'][0]['item_id'])->value('stock')];
        $input['external_id']=(string)Str::uuid();
        $partial=array_replace($usd,['original_amount'=>'5.56','payment'=>'4990.31','_settlement_payment'=>'5000','operation_key'=>(string)Str::uuid()]);
        $input['payments']=[$partial];
        try {
            (new \App\CoreFacturalo\Facturalo)->forWebDocuments()->save($input);
            self::fail('El abono insuficiente no puede cerrar una factura al contado.');
        } catch (ValidationException $exception) { self::assertArrayHasKey('payments',$exception->errors()); }
        self::assertSame($before,[$db->table('documents')->count(),$db->table('document_payments')->count(),$db->table('global_payments')->count(),
            $db->table('item_warehouse')->where('item_id',$input['items'][0]['item_id'])->value('stock')]);
        $input['external_id']=(string)Str::uuid();$input['payment_condition_id']='02';
        $input['fee']=[['date'=>'2026-11-26','currency_type_id'=>'VES','amount'=>'9.69']];
        $input['payments'][0]['operation_key']=(string)Str::uuid();
        $credit=(new \App\CoreFacturalo\Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh();
        self::assertSame('9.69',\App\Support\Exact\Decimal::represent($credit->exactBalance()));
        self::assertSame('9.69',\App\Support\Exact\Decimal::represent($credit->fee->first()->exactValue('amount')));
    }

    public function test_web_invoice_dollar_change_preserves_net_igtf_and_one_cash_movement(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $company=Company::firstOrFail();
        $company->forceFill(['fiscal_emission_mode'=>'free_form','igtf_enabled'=>true,'igtf_rate'=>'3'])->save();
        FiscalProfileService::store($company,SpeProfiles::profile());
        $input['currency_type_id']='VES';$input['items'][0]['quantity']='1';
        if (!\Illuminate\Http\Request::hasMacro('validate')) {
            \Illuminate\Http\Request::macro('validate', function (array $rules) { return validator($this->all(), $rules)->validate(); });
        }
        $usd=['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'10','currency_type_id'=>'USD',
            'financial_intermediation'=>'none','payment_destination_id'=>'cash'];
        $ves=array_replace($usd,['payment_method_type_id'=>'01','currency_type_id'=>'VES',
            'original_amount'=>'1000','payment'=>'1000','exchange_rate'=>'897.53830000']);
        foreach ([
            ['54266.91','904.44850000','70','60','10','1.80','54266.91','0',[]],
            ['500','897.53830000','0.57','0.56','0.01','0.02','500','-2.621448',[]],
            ['5000','897.53830000','6','4.46','1.54','0.13','4000','-3.020818',[$ves]],
        ] as [$due,$rate,$gross,$net,$change,$tax,$applied,$adjustment,$otherRows]) {
            $input['exchange_rate_sale']=$rate;$input['items'][0]['unit_price']=$due;
            $row=$usd+['original_amount'=>$gross,'payment'=>$gross,'exchange_rate'=>$rate];
            $rows=array_merge($otherRows,[$row]);
            $quote=(new \App\Http\Controllers\Tenant\DocumentPaymentQuoteController())(\Illuminate\Http\Request::create('/', 'POST',
                ['currency_type_id'=>'VES','exchange_rate_sale'=>$rate,'principal_due'=>$due,'payments'=>$rows]))['data'];
            $quoted=collect($quote['payments'])->firstWhere('currency_type_id','USD');
            self::assertTrue(\App\Support\Exact\Decimal::of($net)->isEqualTo($quoted['original_amount']));
            self::assertTrue(\App\Support\Exact\Decimal::of($change)->isEqualTo($quoted['change']));
            self::assertSame($tax,$quoted['tax_amount']);
            $preview=new Document(['currency_type_id'=>'VES','exchange_rate_sale'=>$rate,'total'=>$due,'date_of_issue'=>'2026-09-10']);
            $preview->setRelation('payments',new \Illuminate\Database\Eloquent\Collection());
            (new \App\CoreFacturalo\Facturalo())->setPaymentsPreview($preview,$rows);
            self::assertTrue(\App\Support\Exact\Decimal::of($net)->isEqualTo($preview->payments->firstWhere('currency_type_id','USD')->original_amount));
            $input['external_id']=(string)Str::uuid();
            $input['payments']=array_map(function($payment) use ($tax) {
                return $payment+['operation_key'=>(string)Str::uuid(),
                    'quoted_tax_amount'=>$payment['currency_type_id']==='USD' ? $tax : '0.00'];
            },$rows);
            $before=$db->table('global_payments')->count();
            $document=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
            $receipt=$document->payments->whereNull('receipt_parent_id')->firstWhere('currency_type_id','USD');
            self::assertTrue($receipt->exactValue('original_amount')->isEqualTo($net));
            self::assertTrue($receipt->exactValue('payment')->isEqualTo($applied));
            self::assertTrue($receipt->exactValue('tax_amount')->isEqualTo($tax));
            self::assertTrue(\App\Support\Exact\Decimal::of($receipt->change)->isEqualTo($change));
            self::assertTrue(\App\Support\Exact\Decimal::readRequired($receipt,'tendered_original_amount')->isEqualTo($gross));
            self::assertTrue(\App\Support\Exact\Decimal::readRequired($receipt,'received_change')->isEqualTo($change));
            self::assertTrue(\App\Support\Exact\Decimal::readRequired($receipt,'payment_adjustment')->isEqualTo($adjustment));
            self::assertSame($quote['total_with_tax'],\App\Support\Exact\Decimal::represent($document->exactValue('total_payable'),2,true));
            self::assertTrue($document->exactBalance()->isZero());
            $movement=\App\Services\Payments\Movement::row($receipt->global_payment);
            self::assertTrue(\App\Support\Exact\Decimal::of($quote['received_by_currency']['USD'])->isEqualTo($movement['amount']));
            self::assertTrue(\App\Support\Exact\Decimal::of($movement['principal'])->isEqualTo($net));
            self::assertTrue(\App\Support\Exact\Decimal::of($tax)->isEqualTo($movement['tax']));
            self::assertSame($before+count($rows),$db->table('global_payments')->count());
            self::assertSame('not_requested',$document->emission->status);
        }
    }

    public function test_invoice_total_tendered_includes_igtf_and_preserves_the_actual_eight_twenty_change(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $company=Company::firstOrFail();
        $company->forceFill(['fiscal_emission_mode'=>'free_form','igtf_enabled'=>true,'igtf_rate'=>'3'])->save();
        FiscalProfileService::store($company,SpeProfiles::profile());
        if (!\Illuminate\Http\Request::hasMacro('validate')) {
            \Illuminate\Http\Request::macro('validate', function (array $rules) { return validator($this->all(), $rules)->validate(); });
        }
        foreach (['USD'=>'60','VES'=>'54266.91'] as $currency=>$due) {
            $input['currency_type_id']=$currency;$input['exchange_rate_sale']='904.44850000';
            $input['items'][0]['quantity']='1';$input['items'][0]['unit_price']=$due;
            $row=['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'10','currency_type_id'=>'USD',
                'exchange_rate'=>'904.44850000','original_amount'=>'999','payment'=>'999','tendered_amount'=>'70',
                'payment_destination_id'=>'cash','operation_key'=>(string)Str::uuid()];
            $quote=(new \App\Http\Controllers\Tenant\DocumentPaymentQuoteController())(\Illuminate\Http\Request::create('/', 'POST',
                ['currency_type_id'=>$currency,'exchange_rate_sale'=>'904.44850000','principal_due'=>$due,'payments'=>[$row]]))['data'];
            self::assertSame('8.20',$quote['payments'][0]['change']);
            self::assertSame('61.80',$quote['received_by_currency']['USD']);
            self::assertSame('70.00',$quote['tendered_by_currency']['USD']);
            $preview=new Document(['currency_type_id'=>$currency,'exchange_rate_sale'=>'904.44850000','total'=>$due,'date_of_issue'=>'2026-09-10']);
            $preview->setRelation('payments',new \Illuminate\Database\Eloquent\Collection());
            (new \App\CoreFacturalo\Facturalo())->setPaymentsPreview($preview,[$row]);
            self::assertTrue(\App\Support\Exact\Decimal::of($preview->payments->first()->change)->isEqualTo('8.20'));
            $input['external_id']=(string)Str::uuid();$input['payments']=[$row+['quoted_tax_amount'=>'1.80']];
            $before=$db->table('global_payments')->count();
            $document=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
            $receipt=$document->payments->whereNull('receipt_parent_id')->first();
            self::assertTrue($receipt->exactValue('original_amount')->isEqualTo('60'));
            self::assertTrue($receipt->exactValue('tax_amount')->isEqualTo('1.80'));
            self::assertTrue(\App\Support\Exact\Decimal::of($receipt->change)->isEqualTo('8.20'));
            foreach (['total_tendered'=>'70','requested_total_tendered'=>'70','tendered_original_amount'=>'68.20','received_change'=>'8.20'] as $key=>$amount) {
                self::assertTrue(\App\Support\Exact\Decimal::readRequired($receipt,$key)->isEqualTo($amount));
            }
            self::assertTrue($document->exactBalance()->isZero());
            self::assertTrue(\App\Support\Exact\Decimal::of(\App\Services\Payments\Movement::row($receipt->global_payment)['amount'])->isEqualTo('61.80'));
            self::assertSame($before+1,$db->table('global_payments')->count());
            self::assertSame($receipt->id,FiscalDocumentPersistence::payment($document,$row)->id);
            try {
                FiscalDocumentPersistence::payment($document,array_replace($row,['tendered_amount'=>'70.01']));
                self::fail('El mismo UUID no permite otro total entregado.');
            } catch (ValidationException $exception) { self::assertArrayHasKey('operation_key',$exception->errors()); }
            self::assertSame($before+1,$db->table('global_payments')->count());
        }
    }

    public function test_inclusive_forty_dollars_preserve_exact_tax_and_cash_in_a_mixed_invoice(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();$D=\App\Support\Exact\Decimal::class;
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $company=Company::firstOrFail();
        $company->forceFill(['fiscal_emission_mode'=>'free_form','igtf_enabled'=>true,'igtf_rate'=>'3'])->save();
        FiscalProfileService::store($company,SpeProfiles::profile());
        if (!\Illuminate\Http\Request::hasMacro('validate')) {
            \Illuminate\Http\Request::macro('validate', function (array $rules) { return validator($this->all(), $rules)->validate(); });
        }
        $input['currency_type_id']='USD';$input['exchange_rate_sale']='904.44850000';
        $input['items'][0]['quantity']='1';$input['items'][0]['unit_price']='60';
        $usd=['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'10','currency_type_id'=>'USD',
            'exchange_rate'=>'904.44850000','original_amount'=>'999','payment'=>'999','tendered_amount'=>'40',
            'payment_destination_id'=>'cash','quoted_tax_amount'=>'1.17'];
        $ves=array_replace($usd,['payment_method_type_id'=>'01','currency_type_id'=>'VES',
            'original_amount'=>'19147.17','payment'=>'19147.17','tendered_amount'=>'19147.17','quoted_tax_amount'=>'0.00']);
        foreach ([[$usd,$ves],[$ves,$usd]] as $rows) {
            $rows=array_map(fn($row)=>$row+['operation_key'=>(string)Str::uuid()],$rows);
            $quote=(new \App\Http\Controllers\Tenant\DocumentPaymentQuoteController())(\Illuminate\Http\Request::create('/', 'POST',
                ['currency_type_id'=>'USD','exchange_rate_sale'=>'904.44850000','principal_due'=>'60','payments'=>$rows]))['data'];
            self::assertSame('40.00',$quote['received_by_currency']['USD']);
            self::assertSame('19147.17',$quote['received_by_currency']['VES']);
            self::assertSame('61.17',$quote['total_with_tax']);self::assertSame('0.00',$quote['balance']);
            $preview=new Document(['currency_type_id'=>'USD','exchange_rate_sale'=>'904.44850000','total'=>'60','date_of_issue'=>'2026-09-10']);
            $preview->setRelation('payments',new \Illuminate\Database\Eloquent\Collection());
            (new \App\CoreFacturalo\Facturalo())->setPaymentsPreview($preview,$rows);
            self::assertTrue($D::of($preview->payments->firstWhere('currency_type_id','USD')->tax_amount)->isEqualTo('1.17'));
            $input['external_id']=(string)Str::uuid();$input['payments']=$rows;
            $before=$db->table('global_payments')->count();
            $document=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
            $receipt=$document->payments->whereNull('receipt_parent_id')->firstWhere('currency_type_id','USD');
            self::assertTrue($receipt->exactValue('original_amount')->isEqualTo('38.83'));
            self::assertTrue($receipt->exactValue('tax_amount')->isEqualTo('1.17'));
            self::assertTrue($D::of($receipt->change)->isZero());
            self::assertTrue($D::readRequired($receipt,'total_tendered')->isEqualTo('40'));
            self::assertTrue($D::readRequired($receipt,'tax_base_exact')->isEqualTo($D::of('4000')->dividedBy('103')));
            self::assertTrue($D::readRequired($receipt,'tax_exact')->isEqualTo($D::of('120')->dividedBy('103')));
            self::assertTrue($D::readRequired($receipt,'principal_settlement_adjustment')->plus($D::readRequired($receipt,'tax_settlement_adjustment'))->isZero());
            $tax=$document->taxes()->where('document_payment_id',$receipt->id)->where('tax_kind','IGTF')->firstOrFail();
            self::assertTrue($D::readRequired($tax,'base')->isEqualTo($D::of('4000')->dividedBy('103')));
            self::assertTrue($D::readRequired($tax,'amount')->isEqualTo('1.17'));
            self::assertTrue($document->exactBalance()->isZero());
            self::assertTrue($D::of(\App\Services\Payments\Movement::row($receipt->global_payment)['amount'])->isEqualTo('40'));
            self::assertSame($before+2,$db->table('global_payments')->count());
            self::assertSame($receipt->id,FiscalDocumentPersistence::payment($document,collect($rows)->firstWhere('currency_type_id','USD'))->id);
            self::assertSame($before+2,$db->table('global_payments')->count());
        }
    }

    public function test_invoice_settles_single_cross_currency_cash_subcent_remainder(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();$sent=[];$mode='confirmed';$this->fakeSpeHka($db,$app,$sent,$mode);
        $company=Company::firstOrFail();$company->forceFill(['igtf_enabled'=>true,'igtf_rate'=>3])->save();
        FiscalProfileService::store($company,SpeProfiles::profile());
        if (!\Illuminate\Http\Request::hasMacro('validate')) {
            \Illuminate\Http\Request::macro('validate', function (array $rules) { return validator($this->all(), $rules)->validate(); });
        }
        $row=['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'10','payment'=>'233.36',
            'currency_type_id'=>'USD','original_amount'=>'0.26','exchange_rate'=>'897.53830000',
            'financial_intermediation'=>'none','operation_key'=>(string)Str::uuid()];
        $quoteInput=['currency_type_id'=>'VES','exchange_rate_sale'=>'897.53830000','principal_due'=>'232','payments'=>[$row]];
        $quote=(new \App\Http\Controllers\Tenant\DocumentPaymentQuoteController())(\Illuminate\Http\Request::create('/documents/payment-quote','POST',$quoteInput))['data'];
        self::assertSame('232.00',$quote['principal_applied']);
        self::assertSame('0.00',$quote['payments'][0]['change']);
        self::assertSame('0.01',$quote['payments'][0]['tax_amount']);
        $input['currency_type_id']='VES';$input['exchange_rate_sale']='897.53830000';
        $input['payments']=[array_replace($row,['igtf_status'=>'subject','quoted_tax_amount'=>'0.01'])];
        $document=$db->transaction(fn()=>(new \App\CoreFacturalo\Facturalo)->save($input)->getDocument())->fresh();
        $receipt=$document->payments->whereNull('receipt_parent_id')->first();
        self::assertEquals('0',$document->balance);
        self::assertEquals('232',$receipt->payment);
        self::assertEquals('0.26',$receipt->original_amount);
        self::assertEquals('0.01',$receipt->tax_amount);
        self::assertFalse(\App\Support\Exact\Decimal::readRequired($receipt,'payment_adjustment')->isZero());
        self::assertSame('0.27',\App\Services\Payments\Movement::row($receipt->global_payment)['amount']);
    }

    public function test_payment_quote_matches_persisted_invoice_and_rejects_stale_tax(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();$sent=[];$mode='confirmed';$this->fakeSpeHka($db,$app,$sent,$mode);
        $company=Company::firstOrFail();$company->forceFill(['igtf_enabled'=>true,'igtf_rate'=>3])->save();
        FiscalProfileService::store($company,SpeProfiles::profile());
        if (!\Illuminate\Http\Request::hasMacro('validate')) {
            \Illuminate\Http\Request::macro('validate', function (array $rules) { return validator($this->all(), $rules)->validate(); });
        }
        $row=['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'10','payment'=>'250',
            'currency_type_id'=>'USD','original_amount'=>'250','exchange_rate'=>'10',
            'financial_intermediation'=>'none','operation_key'=>(string)Str::uuid()];
        $quoteInput=['currency_type_id'=>'USD','exchange_rate_sale'=>'10','principal_due'=>'232','payments'=>[$row]];
        $request=\Illuminate\Http\Request::create('/documents/payment-quote','POST',$quoteInput);
        $before=$db->table('documents')->count();
        $quote=(new \App\Http\Controllers\Tenant\DocumentPaymentQuoteController())($request)['data'];
        self::assertSame($before,$db->table('documents')->count());
        self::assertSame('6.96',$quote['payments'][0]['tax_amount']);
        self::assertSame('18.00',$quote['payments'][0]['change']);
        self::assertSame('238.96',$quote['received_by_currency']['USD']);
        self::assertSame('256.96',$quote['tendered_by_currency']['USD']);
        $db->table('payment_method_types')->where('id','10')->update(['iva_rate_type_id'=>'IGTF']);
        $associated=(new \App\Http\Controllers\Tenant\DocumentPaymentQuoteController())($request)['data'];
        self::assertSame($quote['payments'][0]['tax_amount'],$associated['payments'][0]['tax_amount']);
        $db->table('payment_method_types')->where('id','10')->update(['iva_rate_type_id'=>null]);
        $input['currency_type_id']='USD';$input['exchange_rate_sale']='10';
        $input['payments']=[array_replace($row,['igtf_status'=>'subject','quoted_tax_amount'=>'6.95'])];
        try {
            $db->transaction(fn()=>(new \App\CoreFacturalo\Facturalo)->save($input));
            self::fail('A stale quote cannot be saved.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('quoted_tax_amount',$exception->errors());
        }
        self::assertSame($before,$db->table('documents')->count());
        $input['payments'][0]['quoted_tax_amount']=$quote['payments'][0]['tax_amount'];
        $document=$db->transaction(fn()=>(new \App\CoreFacturalo\Facturalo)->save($input)->getDocument())->fresh();
        self::assertEquals('238.96',$document->total);
        self::assertTrue(\App\Support\Exact\Decimal::of('6.96')->isEqualTo(
            \App\Support\Exact\Decimal::of($document->taxes()->where('tax_kind','IGTF')->sum('amount'))));
        self::assertEquals('0',$document->balance);
        self::assertTrue(\App\Support\Exact\Decimal::of('6.96')->isEqualTo(
            \App\Support\Exact\Decimal::of($document->payments->whereNull('receipt_parent_id')->first()->tax_amount)));
        $receipt=$document->payments->whereNull('receipt_parent_id')->first();
        self::assertEquals('18',$receipt->change);
        $movement=\App\Services\Payments\Movement::row($receipt->global_payment);
        self::assertSame('238.96',$movement['amount']);
        self::assertSame('232',$movement['principal']);
        self::assertSame('6.96',$movement['tax']);
        $header=$document->emission->payload['documentoElectronico']['encabezado'];
        self::assertSame('69.60',$header['totales']['totalIGTF']);
        self::assertSame('238.96',$header['totales']['formasPago'][0]['monto']);
    }

    public function test_later_quote_preserves_balance_and_creates_one_debit_note(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();$sent=[];$mode='confirmed';$this->fakeSpeHka($db,$app,$sent,$mode);
        config(['hka_credit_invoices.demo_no_advance_verified'=>true]);
        $company=Company::firstOrFail();$company->forceFill(['igtf_enabled'=>true,'igtf_rate'=>3])->save();
        FiscalProfileService::store($company,SpeProfiles::profile());
        if (!\Illuminate\Http\Request::hasMacro('validate')) {
            \Illuminate\Http\Request::macro('validate', function (array $rules) { return validator($this->all(), $rules)->validate(); });
        }
        $input['payments']=[];$input['payment_condition_id']='02';$input['exchange_rate_sale']='10';
        $document=$db->transaction(fn()=>(new \App\CoreFacturalo\Facturalo)->save($input)->getDocument())->fresh();
        self::assertEquals('232',$document->balance);
        $row=['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'10','payment'=>'50',
            'currency_type_id'=>'USD','original_amount'=>'5','exchange_rate'=>'10',
            'financial_intermediation'=>'none','operation_key'=>(string)Str::uuid()];
        $request=\Illuminate\Http\Request::create('/documents/payment-quote','POST',['document_id'=>$document->id,'payments'=>[$row]]);
        $quote=(new \App\Http\Controllers\Tenant\DocumentPaymentQuoteController())($request)['data'];
        self::assertSame('0.15',$quote['payments'][0]['tax_amount']);
        self::assertSame('5.15',$quote['received_by_currency']['USD']);
        self::assertSame('182.00',$quote['balance']);
        $payment=array_replace($row,['igtf_status'=>'subject','quoted_tax_amount'=>'0.15']);
        $company->forceFill(['igtf_rate'=>4])->save();
        try {
            FiscalDocumentPersistence::payment($document,$payment);
            self::fail('A changed rate cannot silently change the quoted tax.');
        } catch (ValidationException $exception) { self::assertArrayHasKey('quoted_tax_amount',$exception->errors()); }
        self::assertEquals('232',$document->fresh()->balance);
        $company->forceFill(['igtf_rate'=>3])->save();
        $receipt=$db->transaction(fn()=>FiscalDocumentPersistence::payment($document,$payment));
        self::assertEquals('182',$document->fresh()->balance);
        $tax=\App\Models\Tenant\DocumentTax::where('document_payment_id',$receipt->id)->firstOrFail();
        $note=Document::findOrFail($tax->document_id);
        self::assertSame('IGTF',$note->note->note_debit_type_id);
        self::assertSame('1.5',\App\Support\Exact\Decimal::represent($note->total));
        self::assertSame($receipt->id,FiscalDocumentPersistence::payment($document,$payment)->id);
        self::assertSame(1,\App\Models\Tenant\DocumentTax::where('document_payment_id',$receipt->id)->count());
    }

    public function test_spe_invoice_reuses_a_sale_note_receipt_without_new_igtf(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $company=Company::firstOrFail();$company->forceFill(['igtf_enabled'=>true,'igtf_rate'=>3])->save();
        FiscalProfileService::store($company,SpeProfiles::profile());
        $controller=new \App\Http\Controllers\Tenant\SaleNoteController();
        $input['exchange_rate_sale']='10';
        $saleInput=array_replace($input,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]]);
        $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($saleInput));
        // This fixture boots warehouse observers but not the global Kardex provider.
        // Supply both movements of the original sale before testing receipt reuse.
        $ledger=new class { use \App\Traits\KardexTrait; };
        $db->transaction(function () use ($input,$note,$ledger) {
            foreach ($input['items'] as $line) {
                $note->items()->create($line);
                $ledger->saveKardex('sale',$line['item_id'],$note->id,$line['quantity'],'sale_note');
                $ledger->updateStock($line['item_id'],$line['quantity'],true);
            }
        });
        $controller->savePayments($note,[['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'10',
            'payment_destination_id'=>'cash','currency_type_id'=>'USD','original_amount'=>'5',
            'exchange_rate'=>'10','payment'=>'50']]);
        $source=$note->payments()->firstOrFail();
        $input['sale_note_id']=$note->id;$input['payment_condition_id']='02';
        $input['fee']=[['date'=>'2026-10-10','currency_type_id'=>'VES','amount'=>'182']];
        $input['payments']=[\App\Http\Resources\Tenant\SaleNoteResource::getTransformPayments(collect([$source]))->first()];
        $document=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
        $receipt=$document->payments()->whereNull('receipt_parent_id')->firstOrFail();
        self::assertSame('not_applicable',$receipt->igtf_status);
        self::assertTrue($receipt->exactValue('tax_amount')->isZero());
        self::assertSame((string)$source->id,$receipt->payment_method_snapshot['receipt_origin']['id']);
        self::assertSame(0,$document->taxes()->where('tax_kind','IGTF')->count());
        self::assertSame(1,$db->table('global_payments')->count());
        self::assertEquals('182',$document->balance);
    }

    private function fakeSpeHka($db, $app, &$sent, &$mode): void
    {
        $app->make(\Illuminate\Http\Client\Factory::class)->preventStrayRequests();
        $app->make(\Illuminate\Http\Client\Factory::class)->fake(function ($request) use ($db, &$sent, &$mode) {
            self::assertSame(0, $db->transactionLevel());
            if (str_ends_with($request->url(), '/Autenticacion')) return Http::response(['token'=>'private-demo-token','expiracion'=>date('c',time()+3600)]);
            if (str_ends_with($request->url(), '/DescargaArchivo')) {
                $pdf=new \FPDF();$pdf->AddPage();$pdf->SetFont('Helvetica','',12);$pdf->Text(10,15,'Synthetic HKA PDF');
                return Http::response(['codigo'=>'200','archivo'=>base64_encode($pdf->Output('S'))]);
            }
            if (str_ends_with($request->url(), '/EstadoDocumento')) {
                $identity=$request->data();
                $stored=\App\Models\Tenant\DocumentEmission::whereRaw("REPLACE(operation_key, '-', '') = ?",[$identity['transaccionId']])->firstOrFail();
                $identity=$stored->payload['documentoElectronico']['encabezado']['identificacionDocumento'];
                return Http::response(['codigo'=>'200','estado'=>$identity+['estadoDocumento'=>'Procesado','numeroControl'=>'00-'.str_pad((string)(10000+(int)($identity['numeroDocumento'] ?? '0')+(($identity['tipoDocumento'] ?? '01')==='03'?20000:0)),8,'0',STR_PAD_LEFT)]]);
            }
            if (str_ends_with($request->url(), '/AplicarRetencion')) return Http::response(['codigo'=>'200','infoRetencion'=>null]);
            $identity=$request['documentoElectronico']['encabezado']['identificacionDocumento'];$sent[]=$identity;
            if ($mode==='timeout') throw new \Illuminate\Http\Client\ConnectionException('private-demo-token');
            if ($mode==='rejected') return Http::response(['codigo'=>'203','validaciones'=>['1015']]);
            return Http::response(['codigo'=>'200','resultado'=>$identity+['numeroControl'=>'00-'.str_pad((string)(10000+(int)($identity['numeroDocumento'] ?? '0')+(($identity['tipoDocumento'] ?? '01')==='03'?20000:0)),8,'0',STR_PAD_LEFT), 'urlConsulta'=>'https://example.test/qa/'.$identity['transaccionId']]]);
        });
    }

    public function test_spe_buyer_and_commercial_issuer_matrix_in_common_web_pos_garage_persistence(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();$sent=[];$mode='confirmed';$this->fakeSpeHka($db,$app,$sent,$mode);
        config(['hka_credit_invoices.demo_no_advance_verified'=>true]);
        $company=Company::firstOrFail();$company->forceFill(['igtf_enabled'=>true,'igtf_rate'=>3])->save();
        FiscalProfileService::store($company,SpeProfiles::profile());
        $profiles=[
            'natural'=>SpeProfiles::profile(['rif'=>'V-23456789-0','nature'=>'natural','iva_agent_basis'=>'none']),
            'personal_firm'=>SpeProfiles::profile(['rif'=>'V-23456789-1','nature'=>'natural','personal_firm'=>true]),
            'legal'=>SpeProfiles::profile(['rif'=>'J-23456789-2']),
        ];
        foreach(SpecialTaxpayerPolicy::PUBLIC_KINDS as $i=>$kind) $profiles[$kind]=SpeProfiles::profile(['rif'=>'G-23456789-'.($i+3),'nature'=>'public','public_kind'=>$kind,'iva_agent_basis'=>'SNAT/2013/0029']);
        $buyers=[];
        foreach($profiles as $name=>$profile){
            $person=Person::create(['type'=>'customers','identity_document_type_id'=>$profile['nature']==='natural'?'1':($profile['nature']==='public'?'G':'6'),
                'number'=>(string)(24000000+count($buyers)),'name'=>'DEMO '.$name,'country_id'=>'VE','address'=>'DEMO Caracas','email'=>'qa@example.test']);
            FiscalProfileService::store($person,$profile);$buyers[$name]=$person;
        }
        foreach($buyers as $name=>$buyer) foreach(['WEB','POS','GARAGE'] as $channel) foreach(['VES','USD'] as $currency) foreach(['01','02'] as $condition){
            $case=array_replace($input,['source_module'=>$channel,'customer_id'=>$buyer->id,'customer'=>$buyer->toArray(),'currency_type_id'=>$currency,'exchange_rate_sale'=>'40.12345678',
                'payment_condition_id'=>$condition,'external_id'=>(string)Str::uuid(), 'payments'=>$condition==='02'?[]:[['payment'=>232,'date_of_payment'=>'2026-09-10','payment_method_type_id'=>$currency==='USD'?'10':'01','financial_intermediation'=>'none']],
                'fee'=>$condition==='02'?[['date'=>'2026-10-10','currency_type_id'=>$currency,'amount'=>232]]:[]]);
            $db->beginTransaction();$before=count($sent);$engine=new \App\CoreFacturalo\Facturalo();if($channel==='WEB')$engine->forWebDocuments();
            $doc=$engine->save($case)->getDocument()->fresh();self::assertSame($before,count($sent));$db->commit();$doc=$doc->fresh();
            self::assertSame('confirmed',$doc->emission->status,$name.' '.$channel.' '.$currency.' '.$condition);
            self::assertSame($profiles[$name]['rif'],$doc->customer->fiscal_profile->rif);
            $payload=$doc->emission->payload['documentoElectronico'];self::assertArrayNotHasKey('regimenEspTributacion',$payload['encabezado']['identificacionDocumento']);
            self::assertSame($profiles[$name]['rif'],$payload['encabezado']['comprador']['numeroIdentificacion']);
            self::assertSame($condition==='02'?'Crédito':'Contado',$payload['encabezado']['identificacionDocumento']['tipoDePago']);
            self::assertEquals($condition==='02'?232:0,$doc->balance);
            self::assertCount($condition==='02'?0:1,$doc->payments->whereNull('receipt_parent_id'));
            self::assertEquals($condition==='01'&&$currency==='USD'?6.96:0,$doc->taxes()->where('tax_kind','IGTF')->sum('amount'));
            self::assertCount($condition==='02'?1:0,$doc->fee);
        }
        self::assertCount(108,$sent);
        $control=Person::findOrFail($input['customer_id']);
        foreach(['natural','personal_firm','legal'] as $nature){
            $company->number=($nature==='legal'?'J':'V').'123456789';$company->save();
            FiscalProfileService::store($company,SpeProfiles::profile(['rif'=>($nature==='legal'?'J':'V').'-12345678-9','nature'=>$nature==='legal'?'legal':'natural','personal_firm'=>$nature==='personal_firm','iva_agent_basis'=>$nature==='natural'?'none':'SNAT/2025/000054']));
            foreach(['WEB','POS','GARAGE'] as $channel)foreach(['VES','USD']as$currency)foreach(['01','02']as$condition){
                $case=array_replace($input,['source_module'=>$channel,'currency_type_id'=>$currency,'payment_condition_id'=>$condition,'external_id'=>(string)Str::uuid(),
                    'payments'=>$condition==='02'?[]:[['date_of_payment'=>'2026-09-10','payment_method_type_id'=>$currency==='USD'?'10':'01','payment'=>232,'financial_intermediation'=>'none']]]);
                $doc=(new \App\CoreFacturalo\Facturalo)->save($case)->getDocument()->fresh();self::assertSame('confirmed',$doc->emission->status);self::assertSame($nature==='legal'?'legal':'natural',$doc->issuer['fiscal_profile']['nature']);
            }
        }
        self::assertCount(144,$sent);
        $before=count($sent);$db->beginTransaction();(new \App\CoreFacturalo\Facturalo)->save($case);$db->rollBack();self::assertSame($before,count($sent));
        $frozen=$doc->emission->payload;FiscalProfileService::store($company,SpeProfiles::profile(['effective_from'=>'2026-09-11','spe'=>false,'iva_agent_basis'=>'none']));
        self::assertSame($frozen,$doc->fresh()->emission->payload);self::assertTrue($doc->fresh()->issuer['fiscal_profile']['spe']);
        self::assertFalse(FiscalProfileService::at($company,'2026-09-11')['spe']);self::assertTrue(FiscalProfileService::at($company,'2026-09-10')['spe']);
        foreach(['rejected','timeout']as$failure){$mode=$failure;$doc=(new \App\CoreFacturalo\Facturalo)->save($case)->getDocument()->fresh();self::assertSame($failure==='timeout'?'uncertain':'rejected',$doc->emission->status);self::assertEquals(232,$doc->balance);}
    }

    public function test_spe_real_collection_context_retentions_notes_freeze_and_recovery(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();$sent=[];$mode='confirmed';$this->fakeSpeHka($db,$app,$sent,$mode);
        $company=Company::firstOrFail();FiscalProfileService::store($company,SpeProfiles::profile());
        $buyer=Person::findOrFail($input['customer_id']);FiscalProfileService::store($buyer,SpeProfiles::profile(['rif'=>'V-12345678-9','nature'=>'natural','personal_firm'=>true]));
        $company->forceFill(['igtf_enabled'=>true,'igtf_rate'=>3])->save();
        $input['payments']=[];$input['payment_condition_id']='02';
        $doc=(new \App\CoreFacturalo\Facturalo)->save($input)->getDocument()->fresh();self::assertEquals(232,$doc->balance);
        $voucher=SpeProfiles::voucher($doc)+['tax_kind'=>'IVA','voucher_number'=>'20260900000001','voucher_date'=>'2026-09-10','agent_id'=>$buyer->id,'base'=>32,'percentage'=>75,'amount'=>24,'currency_type_id'=>'VES','exchange_rate'=>1];
        $retention=FiscalDocumentPersistence::retention($doc,$voucher);self::assertEquals(208,$doc->fresh()->balance);self::assertCount(0,$doc->payments);
        foreach(['duplicate'=>['operation_key'=>(string)Str::uuid()], 'wrong_rate'=>['voucher_number'=>'20260900000002','percentage'=>50,'amount'=>16], 'wrong_rif'=>['voucher_number'=>'20260900000003','agent_rif'=>'J-99999999-9'], 'wrong_control'=>['invoice_control'=>'00-00000001'], 'missing_support'=>['attachment'=>null], 'excess_75'=>['voucher_number'=>'20260900000004','base'=>8,'amount'=>6]]as$changes){
            try{FiscalDocumentPersistence::retention($doc,array_replace($voucher,$changes));self::fail('Invalid voucher persisted');}catch(ValidationException $e){self::assertNotEmpty($e->errors());}
        }
        $pay=['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'10','currency_type_id'=>'USD','exchange_rate'=>'10.00000001','original_amount'=>5,'financial_intermediation'=>'none','operation_key'=>(string)Str::uuid()];
        $before=count($sent);$db->beginTransaction();$payment=FiscalDocumentPersistence::payment($doc,$pay);self::assertSame($before,count($sent));$db->commit();
        $tax=\App\Models\Tenant\DocumentTax::where('document_payment_id',$payment->id)->firstOrFail();$note=Document::findOrFail($tax->document_id);
        self::assertSame('confirmed',$note->emission->status);self::assertTrue(HkaIgtfNote::applies($note));self::assertCount(0,$note->items);self::assertEquals(0,$note->total_igv);self::assertSame('1.5000000015',$note->total);self::assertEquals(158,$doc->fresh()->balance);
        // ######## INICIO CALCULOS EXACTOS SAB ########
        self::assertSame('1.5',$note->total_payable);
        self::assertTrue($note->exactBalance()->isZero());
        self::assertSame('-3/2000000000',(string)$note->payments->first()->exactValue('payment_adjustment'));
        $receiptRows=(new \App\Http\Resources\Tenant\DocumentPaymentCollection($note->payments))->toArray(\Illuminate\Http\Request::create('/'));
        self::assertSame($payment->id,$receiptRows->first()['receipt_parent_id']);
        $reportDetail=\App\CoreFacturalo\Helpers\Template\TemplateHelper::getDetailedPayment($note);
        self::assertSame('Aplicación IGTF',$reportDetail['PAGOS'][0]['kind']);
        self::assertStringContainsString('Aplicación IGTF',$reportDetail['PAGOS'][0]['display']);
        self::assertStringContainsString('Aplicado VES 1.5',$reportDetail['PAGOS'][0]['display']);
        self::assertStringNotContainsString('Recibido',$reportDetail['PAGOS'][0]['display']);
        $currentRows=(new \Modules\Report\Http\Resources\DocumentCollection(new \Illuminate\Database\Eloquent\Collection([$note->fresh()])))
            ->toArray(\Illuminate\Http\Request::create('/'));
        self::assertSame('1.50',$currentRows[0]['total']);
        self::assertSame(['n'=>'3000000003','d'=>'2000000000'],$currentRows[0]['report_total_fractions']['total']);
        $rejectedNote=clone $note; $rejectedNote->state_type_id='09';
        $rejectedRows=(new \Modules\Report\Http\Resources\DocumentCollection(new \Illuminate\Database\Eloquent\Collection([$rejectedNote])))
            ->toArray(\Illuminate\Http\Request::create('/'));
        foreach (['total','total_taxed','total_igv','total_charge'] as $field) self::assertSame('0.00',$rejectedRows[0][$field]);
        config(['view.compiled'=>sys_get_temp_dir().'/'.$this->database.'/igtf-report-views', 'view.paths'=>[base_path('resources/views')]]);
        mkdir(config('view.compiled'),0777,true);
        $app->register(\Illuminate\View\ViewServiceProvider::class);
        $app['view']->addNamespace('report',base_path('modules/Report/Resources/views'));
        $report=new \Modules\Report\Http\Controllers\ReportDocumentController();
        $filters=['period'=>'between_dates','date_start'=>'2026-01-01','date_end'=>'2027-01-01',
            'document_type_id'=>'08','seller_id'=>null,'person_id'=>null,'user_type'=>'admin'];
        $records=$report->getRecords($filters,Document::class)->where('id',$note->id)->get();
        $establishment=\App\Models\Tenant\Establishment::findOrFail($note->establishment_id);
        $categories=collect(); $categories_services=collect();
        $reportView='report::documents.report_pdf';
        $templateSource=file_get_contents(base_path('modules/Report/Resources/views/documents/report_pdf.blade.php'));
        preg_match_all('/\$columns->([a-zA-Z_]+)/',$templateSource,$matches);
        $columns=new \stdClass();
        foreach (array_unique($matches[1]) as $name) $columns->$name=(object)['title'=>$name,'visible'=>in_array($name,['payment_form','payment_method'],true)];
        $html=view($reportView,compact('records','company','establishment','filters','columns','categories','categories_services'))->render();
        self::assertStringContainsString('Aplicación IGTF',$html);
        self::assertStringContainsString('Aplicado VES 1.5',$html);
        self::assertStringNotContainsString('Recibido USD',$html);
        $pdf=new \Mpdf\Mpdf(['tempDir'=>sys_get_temp_dir(),'format'=>'A4-L']);
        $pdf->WriteHTML($html);
        $reportPdf=$pdf->Output('','S');
        self::assertStringStartsWith('%PDF-',$reportPdf);
        if ($dir=getenv('SAB_TICKET_EVIDENCE_DIR')) file_put_contents($dir.'/igtf-document-report.pdf',$reportPdf);
        foreach (['report_pdf_standard','report_pdf_simple'] as $viewName) {
            $rejectedRecords=new \Illuminate\Database\Eloquent\Collection([$rejectedNote]);
            $rejectedHtml=view('report::documents.'.$viewName,['records'=>$rejectedRecords,'company'=>$company,
                'establishment'=>$establishment,'filters'=>$filters])->render();
            $dom=new \DOMDocument(); @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$rejectedHtml);
            $xpath=new \DOMXPath($dom);
            $rows=$xpath->query('//tbody/tr[td[contains(normalize-space(.), "'.$rejectedNote->series.'")]]');
            self::assertSame(1,$rows->length,$viewName);
            $cells=$xpath->query('./td',$rows->item(0));
            self::assertSame('0.00',trim($cells->item($cells->length-3)->textContent),$viewName.' base');
            self::assertSame('0.00',trim($cells->item($cells->length-2)->textContent),$viewName.' IVA');
            self::assertSame('0.00',trim($cells->item($cells->length-1)->textContent),$viewName.' total');
        }
        $changed=clone $note;$changed->total=$note->exactValue('total')->plus('0.000000000001');
        try { HkaIgtfNote::assertCollection($changed);self::fail('Una diferencia subcéntimo no acredita el impuesto original.'); }
        catch (ValidationException $error) { self::assertArrayHasKey('payment',$error->errors()); }
        // ######## FIN CALCULOS EXACTOS SAB ########
        self::assertSame('03',$note->emission->payload['documentoElectronico']['encabezado']['identificacionDocumento']['tipoDocumento']);
        self::assertSame('98',$note->emission->payload['documentoElectronico']['encabezado']['identificacionDocumento']['tipoTransaccion']);
        self::assertSame($doc->control_number,$note->note->data_affected_document->control_number);
        self::assertEquals(0,$note->payments->first()->cash_received_amount);
        self::assertStringStartsWith('%PDF-',app(HkaPdf::class)->download($note,'a4'));self::assertStringStartsWith('%PDF-',app(HkaPdf::class)->download($note,'a5'));
        $frozen=$payment->fresh()->fiscal_context;FiscalProfileService::store($company,SpeProfiles::profile(['effective_from'=>'2026-09-11','revoked_at'=>'2026-09-11']));
        self::assertSame($payment->id,FiscalDocumentPersistence::payment($doc,$pay)->id);self::assertSame($frozen,$payment->fresh()->fiscal_context);
        try{FiscalDocumentPersistence::reverse($payment,'Must require fiscal adjustment');self::fail('Confirmed note was reversed');}catch(ValidationException $e){self::assertArrayHasKey('document',$e->errors());}
        self::assertNull($payment->fresh()->reversed_at);
        $noTax=FiscalDocumentPersistence::payment($doc,array_replace($pay,['operation_key'=>(string)Str::uuid(),'date_of_payment'=>'2026-09-11','original_amount'=>1]));self::assertEquals(0,$noTax->tax_amount);
        // ######## INICIO INTERMEDIACION AUTOMATICA DEL COBRO ########
        $account=\App\Models\Tenant\BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD','number'=>'SPE-BANK','currency_type_id'=>'USD','initial_balance'=>'0']);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1,'bank_account_id'=>$account->id]);
        $bank=FiscalDocumentPersistence::payment($doc,array_replace($pay,['operation_key'=>(string)Str::uuid(),'payment_method_type_id'=>'13','payment_destination_id'=>$account->id,'original_amount'=>1,'financial_intermediation'=>'financial_institution']));self::assertEquals(0,$bank->tax_amount);
        // ######## FIN INTERMEDIACION AUTOMATICA DEL COBRO ########
        $ves=FiscalDocumentPersistence::payment($doc,['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'01','payment'=>10]);self::assertEquals(0,$ves->tax_amount);
        // ######## INICIO RETIRADA DE EXCEPCION ADMINISTRATIVA IGTF ########
        $beforeException=$db->table('document_payments')->count();
        try { FiscalDocumentPersistence::payment($doc,array_replace($pay,['operation_key'=>(string)Str::uuid(),'original_amount'=>1,'igtf_status'=>'exempt','exception_basis'=>'DEMO reviewed provision','exemption_reason'=>'DEMO exemption evidence']));self::fail('La excepción retirada fue aceptada.'); }
        catch (ValidationException $error) { self::assertArrayHasKey('igtf_status',$error->errors()); }
        self::assertSame($beforeException,$db->table('document_payments')->count());
        // ######## FIN RETIRADA DE EXCEPCION ADMINISTRATIVA IGTF ########
        $company->fiscal_emission_mode='free_form';$company->save();
        $modePayment=FiscalDocumentPersistence::payment($doc,array_replace($pay,['operation_key'=>(string)Str::uuid(),'original_amount'=>1]));
        $modeTax=\App\Models\Tenant\DocumentTax::where('document_payment_id',$modePayment->id)->firstOrFail();$modeNote=Document::findOrFail($modeTax->document_id);
        self::assertSame($doc->fiscal_emission_mode,$modeNote->fiscal_emission_mode);self::assertSame($doc->fiscal_environment,$modeNote->fiscal_environment);self::assertSame('not_requested',$modeNote->emission->status);
        $company->fiscal_emission_mode='digital';$company->save();HkaIgtfNote::resume($doc);self::assertSame('confirmed',$modeNote->fresh()->emission->status);
        // Defer a note while its parent lacks confirmation; later resume automatically.
        $company->fiscal_credentials=null;$company->save();$later=(new \App\CoreFacturalo\Facturalo)->save($input)->getDocument()->fresh();
        $pendingPayment=FiscalDocumentPersistence::payment($later,array_replace($pay,['operation_key'=>(string)Str::uuid()]));
        $pendingTax=\App\Models\Tenant\DocumentTax::where('document_payment_id',$pendingPayment->id)->firstOrFail();$pendingNote=Document::findOrFail($pendingTax->document_id);
        self::assertSame('not_requested',$pendingNote->emission->status);self::assertEquals(182,$later->fresh()->balance);
        $company->fiscal_credentials=json_encode(['usuario'=>'test-user','clave'=>'test-secret']);$company->save();app(HkaEmission::class)->send($later);
        self::assertSame('confirmed',$pendingNote->fresh()->emission->status);
        $snapshot=$retention->fresh()->fiscal_context;FiscalProfileService::store($buyer,SpeProfiles::profile(['rif'=>'V-12345678-9','nature'=>'natural','personal_firm'=>true,'effective_from'=>'2026-09-11','spe'=>false,'iva_agent_basis'=>'none']));self::assertSame($snapshot,$retention->fresh()->fiscal_context);
        $full=(new \App\CoreFacturalo\Facturalo)->save($input)->getDocument()->fresh();
        $v=array_replace(SpeProfiles::voucher($full)+$voucher,['voucher_number'=>'20260900000009','percentage'=>100,'amount'=>32,'fiscal_context'=>['iva_100_basis'=>'portal_100','evidence_reference'=>'DEMO reviewed portal']]);
        self::assertEquals(32,FiscalDocumentPersistence::retention($full,$v)->applied_amount);
        self::assertEquals(200,$full->fresh()->balance);
    }

    public function test_spe_profile_admin_history_and_transaction_rollback(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();$company=Company::firstOrFail();$buyer=Person::findOrFail($input['customer_id']);
        $profile=SpeProfiles::profile();$db->beginTransaction();FiscalProfileService::store($company,$profile);$db->rollBack();self::assertSame(0,$db->table('fiscal_profile_versions')->count());self::assertNull($company->fresh()->fiscal_profile);
        FiscalProfileService::store($company,$profile);self::assertSame(1,$db->table('fiscal_profile_versions')->count());
        $user=auth()->user();$user->type='seller';
        try{FiscalProfileService::store($buyer,$profile);self::fail('Non-admin modified fiscal facts');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){self::assertSame(403,$e->getStatusCode());}
        $user->type='admin';self::assertSame(1,$db->table('fiscal_profile_versions')->count());
        try{FiscalProfileService::store($company,array_replace($profile,['commercial_activity'=>false]));self::fail('Agent-only issuer was enabled');}catch(ValidationException $e){self::assertArrayHasKey('commercial_activity',$e->errors());}
    }
}
