<?php
namespace Tests\Support;

use App\CoreFacturalo\Facturalo;
use App\Models\Tenant\Document;
use App\Services\Fiscal\{DocumentEmissionView, HkaCreditInvoice, HkaEmission, HkaEmissionPreparation, FiscalDocumentPersistence};
use Illuminate\Support\Str;

trait CreditInvoicePersistenceCases
{
    private function creditInvoiceInput(array $input): array
    {
        return array_replace($input,['payment_condition_id'=>'02','payments'=>[], 'source_module'=>'WEB',
            'exchange_rate_sale'=>'10.12345678','external_id'=>(string)Str::uuid()]);
    }

    public function test_credit_invoice_confirmation_preserves_full_receivable_and_later_collection(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();$input=$this->creditInvoiceInput($input);
        config(['hka_credit_invoices.demo_no_advance_verified'=>true]);
        $sent=[];$mode='confirmed';$this->fakeSpeHka($db,$app,$sent,$mode);
        foreach (['VES','USD'] as $currency) {
            $input['currency_type_id']=$currency;
            $financialBefore=$db->table('global_payments')->count();
            $doc=(new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh();
            self::assertSame('confirmed',$doc->emission->status,$doc->emission->response['diagnostic'] ?? '');
            self::assertSame(HkaCreditInvoice::VERSION,$doc->emission->response[HkaCreditInvoice::KEY]);
            self::assertSame(0,$doc->payments()->count());
            self::assertSame($financialBefore,$db->table('global_payments')->count());
            self::assertEquals(232,$doc->balance);
            $header=$doc->emission->payload['documentoElectronico']['encabezado'];
            self::assertSame($header['totales']['totalAPagar'],$header['totales']['formasPago'][0]['monto']);
            self::assertSame('10.12345678',$header['totales']['formasPago'][0]['tipoCambio']);
            $frozen=$doc->emission->payload;$stock=$db->table('item_warehouse')->value('stock');
            $emissions=count($sent);
            $payment=FiscalDocumentPersistence::payment($doc,['operation_key'=>(string)Str::uuid(),
                'date_of_payment'=>$input['date_of_issue'],'payment_method_type_id'=>'01','payment'=>40,'reference'=>'Real later collection']);
            self::assertEquals(40,$payment->payment);
            self::assertEquals(192,$doc->fresh()->balance);
            self::assertSame($frozen,$doc->fresh()->emission->payload);
            self::assertSame($stock,$db->table('item_warehouse')->value('stock'));
            app(HkaEmission::class)->send($doc);
            self::assertSame($emissions,count($sent));
        }
    }

    public function test_credit_invoice_marker_only_originate_from_new_authorized_web_creation(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();$input=$this->creditInvoiceInput($input);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        config(['hka_credit_invoices.demo_no_advance_verified'=>false]);
        $http=$app->make(\Illuminate\Http\Client\Factory::class);$http->preventStrayRequests();$http->fake();
        $doc=(new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh();
        self::assertSame(HkaCreditInvoice::VERSION,$doc->emission->response[HkaCreditInvoice::KEY]);
        self::assertFalse(DocumentEmissionView::forDocument($doc)['can_send']);
        self::assertSame(HkaCreditInvoice::UNVERIFIED,app(HkaEmission::class)->send($doc)['diagnostic']);
        $http->assertNothingSent();
        config(['hka_credit_invoices.demo_no_advance_verified'=>true]);
        self::assertTrue(DocumentEmissionView::forDocument($doc->fresh())['can_send']);
        $second=(new Facturalo)->forWebDocuments()->save($input)->getDocument();
        $rows=Document::whereIn('id',[$doc->id,$second->id])->with('emission')->get();
        $db->enableQueryLog();
        HkaCreditInvoice::preload($rows);
        $queries=count($db->getQueryLog());
        foreach ($rows as $row) self::assertNull(HkaCreditInvoice::reason($row));
        self::assertCount($queries,$db->getQueryLog(),'Listing capabilities must reuse batched relations: '.json_encode(array_column(array_slice($db->getQueryLog(),$queries),'query')));
        $db->disableQueryLog();$db->flushQueryLog();
        $legacy=(new Facturalo)->save($input+[HkaCreditInvoice::KEY=>HkaCreditInvoice::VERSION])->getDocument()->fresh();
        self::assertArrayNotHasKey(HkaCreditInvoice::KEY,$legacy->emission->response);
        $payload=(new HkaEmissionPreparation)->prepare($legacy)->payload;
        self::assertSame([],$payload['documentoElectronico']['encabezado']['totales']['formasPago']);
        foreach ([$legacy,$doc] as $candidate) {
            $edit=array_replace($input,['id'=>$candidate->id,'series'=>$candidate->series,'number'=>$candidate->number]);
            (new Facturalo)->forWebDocuments()->update($edit,$candidate->id);
            $marker=$candidate->fresh()->emission->response[HkaCreditInvoice::KEY] ?? null;
            self::assertSame($candidate->id===$doc->id ? HkaCreditInvoice::VERSION : null,$marker);
        }
        $rejected=(new Facturalo)->save($input)->getDocument()->fresh();
        $rejected->forceFill(['number'=>723])->save();
        $rejected->emission->update(['status'=>'rejected','payload'=>['unchanged'=>true],
            'response'=>['code'=>'203','validation_codes'=>['1015']]]);
        $before=$rejected->fresh()->emission->toArray();
        app(HkaEmission::class)->send($rejected);
        self::assertSame($before,$rejected->fresh()->emission->toArray());
        self::assertSame(['unchanged'=>true],$rejected->fresh()->emission->payload);
        $http->assertNothingSent();
    }

    public function test_credit_invoice_abono_before_preparation_blocks_full_credit_declaration(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();$input=$this->creditInvoiceInput($input);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        config(['hka_credit_invoices.demo_no_advance_verified'=>true]);
        $http=$app->make(\Illuminate\Http\Client\Factory::class);$http->preventStrayRequests();$http->fake();
        $doc=(new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh();
        FiscalDocumentPersistence::payment($doc,['operation_key'=>(string)Str::uuid(),
            'date_of_payment'=>$input['date_of_issue'],'payment_method_type_id'=>'01','payment'=>40]);
        $view=app(HkaEmission::class)->send($doc);
        self::assertFalse($view['can_send']);self::assertSame(HkaCreditInvoice::INELIGIBLE,$view['diagnostic']);
        self::assertNull($doc->fresh()->emission->payload);self::assertEquals(192,$doc->fresh()->balance);
        self::assertSame(1,$doc->payments()->count());$http->assertNothingSent();
    }

    public function test_credit_invoice_outer_rollback_and_timeout_recovery_do_not_duplicate_effects(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();$input=$this->creditInvoiceInput($input);
        config(['hka_credit_invoices.demo_no_advance_verified'=>true]);
        $sent=[];$mode='timeout';$this->fakeSpeHka($db,$app,$sent,$mode);
        $stock=$db->table('item_warehouse')->value('stock');
        $db->beginTransaction();
        $rolled=(new Facturalo)->forWebDocuments()->save($input)->getDocument();
        self::assertSame([],$sent);$db->rollBack();
        self::assertNull(Document::find($rolled->id));self::assertSame($stock,$db->table('item_warehouse')->value('stock'));
        $doc=(new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh();
        self::assertSame('uncertain',$doc->emission->status);self::assertCount(1,$sent);
        $payload=$doc->emission->payload;$key=$doc->emission->operation_key;
        self::assertStringNotContainsString('private-demo-token',json_encode($doc->emission->response));
        app(HkaEmission::class)->send($doc);
        self::assertSame('confirmed',$doc->fresh()->emission->status);self::assertCount(1,$sent);
        self::assertSame($payload,$doc->fresh()->emission->payload);self::assertSame($key,$doc->fresh()->emission->operation_key);
        self::assertSame(0,$doc->payments()->count());self::assertEquals(232,$doc->fresh()->balance);
        self::assertEquals($stock-2,$db->table('item_warehouse')->value('stock'));
    }

    public function test_credit_invoice_rejections_and_incoherent_results_do_not_confirm_or_resend(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();$input=$this->creditInvoiceInput($input);
        config(['hka_credit_invoices.demo_no_advance_verified'=>true]);
        $mode='1015';$calls=0;
        $http=$app->make(\Illuminate\Http\Client\Factory::class);$http->preventStrayRequests();
        $http->fake(function($request) use($db,&$mode,&$calls) {
            self::assertSame(0,$db->transactionLevel());
            if(str_ends_with($request->url(),'/Autenticacion'))return \Illuminate\Support\Facades\Http::response(['token'=>'private-credit-token','expiracion'=>date('c',time()+3600)]);
            if(str_ends_with($request->url(),'/EstadoDocumento'))throw new \Illuminate\Http\Client\ConnectionException('private-credit-token');
            self::assertStringEndsWith('/Emision',$request->url());$calls++;
            $id=$request['documentoElectronico']['encabezado']['identificacionDocumento'];
            if(in_array($mode,['1015','1016'],true))return \Illuminate\Support\Facades\Http::response(['codigo'=>'203','validaciones'=>[$mode.' - private-credit-token']]);
            if($mode==='incomplete')return \Illuminate\Support\Facades\Http::response(['codigo'=>'200','resultado'=>$id]);
            $id['numeroDocumento']='999999';
            return \Illuminate\Support\Facades\Http::response(['codigo'=>'200','resultado'=>$id+['numeroControl'=>'00-00000999']]);
        });
        foreach(['1015','1016','incomplete','contradictory'] as $mode) {
            $doc=(new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh();
            $status=in_array($mode,['1015','1016'],true)?'rejected':'uncertain';
            self::assertSame($status,$doc->emission->status);self::assertNull($doc->control_number);
            self::assertSame(0,$doc->payments()->count());self::assertEquals(232,$doc->balance);
            $before=$calls;$payload=$doc->emission->payload;
            app(HkaEmission::class)->send($doc);
            self::assertSame($before,$calls);self::assertSame($status,$doc->fresh()->emission->status);
            self::assertSame($payload,$doc->fresh()->emission->payload);
            self::assertFalse(DocumentEmissionView::forDocument($doc->fresh())['can_send']);
            self::assertStringNotContainsString('private-credit-token',json_encode($doc->fresh()->emission->response));
        }
    }

    public function test_credit_invoice_concurrent_sends_emit_once_without_financial_effects(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();$input=$this->creditInvoiceInput($input);
        config(['hka_credit_invoices.demo_no_advance_verified'=>true]);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $doc=(new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh();
        $stock=$db->table('item_warehouse')->value('stock');$financial=$db->table('global_payments')->count();
        $log=tempnam(sys_get_temp_dir(),'pro9-credit-send-');
        try {
            $data=['log'=>$log,'credit_invoice_verified'=>true];
            $this->runFiscalWorkers($doc->id,'hka',[$data,$data]);
            self::assertSame("emission\n",file_get_contents($log));
            self::assertSame('confirmed',$doc->fresh()->emission->status);
            self::assertSame(0,$doc->payments()->count());self::assertEquals(232,$doc->fresh()->balance);
            self::assertSame($stock,$db->table('item_warehouse')->value('stock'));
            self::assertSame($financial,$db->table('global_payments')->count());
        } finally { unlink($log); }
    }

    public function test_credit_invoice_discount_and_charge_preserve_exact_totals_and_declaration(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();$input=$this->creditInvoiceInput($input);
        config(['hka_credit_invoices.demo_no_advance_verified'=>true]);
        $sent=[];$mode='confirmed';$this->fakeSpeHka($db,$app,$sent,$mode);
        $input['discounts']=[['discount_type_id'=>'02','amount'=>'10']];
        $input['charges']=[['charge_type_id'=>'50','amount'=>'6']];
        foreach(['VES','USD'] as $currency) {
            $input['currency_type_id']=$currency;
            $doc=(new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh();
            self::assertSame('confirmed',$doc->emission->status,$doc->emission->response['diagnostic'] ?? '');
            self::assertTrue($doc->exactBalance()->isEqualTo('226.4'));
            self::assertSame(0,$doc->payments()->count());
            $totals=$doc->emission->payload['documentoElectronico']['encabezado']['totales'];
            self::assertSame($totals['totalAPagar'],$totals['formasPago'][0]['monto']);
            self::assertSame('99',$totals['formasPago'][0]['forma']);
        }
    }
}
