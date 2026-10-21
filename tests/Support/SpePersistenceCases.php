<?php
namespace Tests\Support;

use App\Models\Tenant\{Company, Person, Document, User};
use App\Services\Fiscal\{FiscalProfileService, FiscalDocumentPersistence, HkaEmission, HkaPdf, HkaIgtfNote, SpecialTaxpayerPolicy};
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

trait SpePersistenceCases
{
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
                'payment_condition_id'=>$condition,'external_id'=>(string)Str::uuid(), 'payments'=>$condition==='02'?[]:[['payment'=>232,'date_of_payment'=>'2026-09-10','payment_method_type_id'=>'01','financial_intermediation'=>'none']],
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
                    'payments'=>$condition==='02'?[]:[['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'01','payment'=>232,'financial_intermediation'=>'none']]]);
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
        $pay=['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'01','currency_type_id'=>'USD','exchange_rate'=>'10.00000001','original_amount'=>5,'financial_intermediation'=>'none','operation_key'=>(string)Str::uuid()];
        $before=count($sent);$db->beginTransaction();$payment=FiscalDocumentPersistence::payment($doc,$pay);self::assertSame($before,count($sent));$db->commit();
        $tax=\App\Models\Tenant\DocumentTax::where('document_payment_id',$payment->id)->firstOrFail();$note=Document::findOrFail($tax->document_id);
        self::assertSame('confirmed',$note->emission->status);self::assertTrue(HkaIgtfNote::applies($note));self::assertCount(0,$note->items);self::assertEquals(0,$note->total_igv);self::assertSame('1.5000000015',$note->total);self::assertEquals(158,$doc->fresh()->balance);
        // ######## INICIO CALCULOS EXACTOS PRO9 ########
        self::assertSame('1.5',$note->total_payable);
        self::assertTrue($note->exactBalance()->isZero());
        self::assertSame('-3/2000000000',(string)$note->payments->first()->exactValue('payment_adjustment'));
        $receiptRows=(new \App\Http\Resources\Tenant\DocumentPaymentCollection($note->payments))->toArray(\Illuminate\Http\Request::create('/'));
        self::assertSame($payment->id,$receiptRows->first()['receipt_parent_id']);
        $changed=clone $note;$changed->total=$note->exactValue('total')->plus('0.000000000001');
        try { HkaIgtfNote::assertCollection($changed);self::fail('Una diferencia subcéntimo no acredita el impuesto original.'); }
        catch (ValidationException $error) { self::assertArrayHasKey('payment',$error->errors()); }
        // ######## FIN CALCULOS EXACTOS PRO9 ########
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
        $bank=FiscalDocumentPersistence::payment($doc,array_replace($pay,['operation_key'=>(string)Str::uuid(),'original_amount'=>1,'financial_intermediation'=>'financial_institution']));self::assertEquals(0,$bank->tax_amount);
        $ves=FiscalDocumentPersistence::payment($doc,['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'01','payment'=>10]);self::assertEquals(0,$ves->tax_amount);
        $exception=FiscalDocumentPersistence::payment($doc,array_replace($pay,['operation_key'=>(string)Str::uuid(),'original_amount'=>1,'igtf_status'=>'exempt','exception_basis'=>'DEMO reviewed provision','exemption_reason'=>'DEMO exemption evidence']));self::assertEquals(0,$exception->tax_amount);self::assertSame(auth()->id(),$exception->fiscal_context['decision']['authorized_by']);
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
