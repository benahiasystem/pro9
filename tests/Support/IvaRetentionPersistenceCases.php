<?php

namespace Tests\Support;

use App\CoreFacturalo\Facturalo;
use App\Models\Tenant\{Company, Person, DocumentReceivedRetention, ReceivedRetentionUpload};
use App\Services\Fiscal\{FiscalProfileService, IvaRetentionService, HkaIvaRetention};
use Illuminate\Support\Facades\{Http, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait IvaRetentionPersistenceCases
{
    private function bootIvaFixture(): array
    {
        [$db,$app,$input] = $this->bootHkaFixture();
        config(['iva_retention.demo_net_collection_verified'=>true]); // Mocked transport acceptance, never a production default.
        FiscalProfileService::store(Company::firstOrFail(),SpeProfiles::profile());
        FiscalProfileService::store(Person::findOrFail($input['customer_id']),SpeProfiles::profile(['rif'=>'V-12345678-9','nature'=>'natural','personal_firm'=>true]));
        $input['payments'][0]['payment'] = 208;
        $input['payments'][0]['financial_intermediation'] = 'financial_institution';
        $input['iva_retention_prevision'] = ['enabled'=>true];
        $state = (object)['remote'=>[], 'calls'=>[], 'mode'=>'normal'];
        $remote = &$state->remote; $calls = &$state->calls; $mode = &$state->mode;
        Http::preventStrayRequests();
        $app->make(\Illuminate\Http\Client\Factory::class)->fake(function ($request) use ($db,&$remote,&$calls,&$mode) {
            self::assertSame(0,$db->transactionLevel(),'No HKA HTTP inside a commercial transaction');
            $calls[] = [$request->method(),$request->url(),$request->data()];
            if (str_ends_with($request->url(),'/Autenticacion')) return Http::response(['token'=>'private-fixture-token','expiracion'=>date('c',time()+3600)]);
            if (str_ends_with($request->url(),'/DescargaArchivo')) {
                $pdf = new \FPDF();$pdf->AddPage();$pdf->SetFont('Helvetica','',12);$pdf->Text(10,15,'HKA test');
                return Http::response(['codigo'=>'200','archivo'=>base64_encode($pdf->Output('S'))]);
            }
            if (str_ends_with($request->url(),'/AplicarRetencion')) {
                $data = $request->data(); $control = $data['numeroControl'];
                if ($request->method() === 'GET') {
                    if ($mode === 'unknown') return Http::response(['codigo'=>'200']);
                    return Http::response(['codigo'=>'200','infoRetencion'=>$remote[$control] ?? null]);
                }
                if ($mode === 'rejected') return Http::response(['codigo'=>'203','validaciones'=>['invalid']]);
                if ($request->method() === 'DELETE') $remote[$control] = null;
                else $remote[$control] = array_intersect_key($data,array_flip(['serie','tipoDocumento','numeroDocumento','numeroControl'])) + ['totalesRetencion'=>$data['totalRetencion']];
                if ($mode === 'timeout') throw new \Illuminate\Http\Client\ConnectionException('private-fixture-token');
                return Http::response(['codigo'=>'200']);
            }
            $id = $request['documentoElectronico']['encabezado']['identificacionDocumento'];
            return Http::response(['codigo'=>'200','resultado'=>$id+['numeroControl'=>'00-'.str_pad($id['numeroDocumento'],8,'0',STR_PAD_LEFT)]]);
        });
        return [$db,$app,$input,$state];
    }

    private function ivaVoucher($doc, array $changes = []): array
    {
        return array_replace(SpeProfiles::voucher($doc), ['tax_kind'=>'IVA','operation_key'=>(string)Str::uuid(),
            'voucher_number'=>'20260900000001','voucher_date'=>'2026-09-10','base'=>32,'percentage'=>75,'amount'=>24,
            'currency_type_id'=>'VES','exchange_rate'=>'1.00000000'], $changes);
    }

    public function test_iva_net_forecast_idempotence_hka_and_audited_replacement(): void
    {
        [$db,$app,$input] = $this->bootIvaFixture();
        $cashId=$db->table('cash')->insertGetId(['user_id'=>auth()->id(),'date_opening'=>'2026-09-10','time_opening'=>'12:00:00','state'=>1]);
        foreach (['WEB','POS','VENDEYA'] as $source) foreach (['VES','USD'] as $currency) {
            $case = array_replace($input,['source_module'=>$source,'external_id'=>(string)Str::uuid(),'currency_type_id'=>$currency,'exchange_rate_sale'=>'10.12345678']);
            $doc = (new Facturalo)->save($case)->getDocument()->fresh();
            self::assertEquals(24,$doc->balance);self::assertEquals(208,$doc->payments->sum('payment'));self::assertEquals(232,$doc->total);
            self::assertEquals(24,$doc->fiscal_data->iva_retention_prevision['amount']);
            self::assertSame('confirmed',$doc->emission->status);
            $credit=\App\Models\Tenant\CashDocumentCredit::create(['cash_id'=>$cashId,'document_id'=>$doc->id,'status'=>'PENDING']);
            $cashRows=$db->table('cash_document_payments')->count();
            $payload = $doc->emission->payload;
            $inputVoucher = $this->ivaVoucher($doc,['currency_type_id'=>$currency,'exchange_rate'=>'10.12345678']);
            $retention = IvaRetentionService::store($doc,$inputVoucher);
            self::assertSame('applied',$retention->application_status);self::assertEquals(200,$retention->taxable_base);
            self::assertEquals(0,$doc->fresh()->balance);self::assertTrue((bool)$doc->fresh()->total_canceled);
            self::assertSame('confirmed',$retention->operations()->first()->status);
            self::assertSame('PROCESSED',$credit->fresh()->status);self::assertNull($credit->fresh()->cash_id_processed);
            self::assertSame($cashRows,$db->table('cash_document_payments')->count());
            self::assertSame($retention->id,IvaRetentionService::store($doc,$inputVoucher)->id);
            try { IvaRetentionService::store($doc,array_replace($inputVoucher,['voucher_number'=>'20260900000002']));self::fail('Reused request with different data'); }
            catch (ValidationException $e) { self::assertSame(409,$e->status); }
            try { IvaRetentionService::reverse($retention,['operation_key'=>$inputVoucher['operation_key'],'reason'=>'Clave de registro reutilizada']);self::fail('Reused key across actions'); }
            catch (ValidationException $e) { self::assertSame(409,$e->status); }
            self::assertSame($payload,$doc->fresh()->emission->payload);
            try { IvaRetentionService::store($doc,array_replace($inputVoucher,['operation_key'=>(string)Str::uuid(),'voucher_number'=>'20260900000002']));self::fail('Second active IVA'); }
            catch (ValidationException $e) { self::assertArrayHasKey('voucher_number',$e->errors()); }
            $reversal = ['operation_key'=>(string)Str::uuid(),'reason'=>'Comprobante sustituido'];
            IvaRetentionService::reverse($retention,$reversal);IvaRetentionService::reverse($retention,$reversal);
            self::assertSame('reversed',$retention->fresh()->application_status);self::assertEquals(24,$doc->fresh()->balance);
            self::assertEquals(24,$retention->fresh()->fiscal_context['reversal']['applied_amount']);
            self::assertSame('PENDING',$credit->fresh()->status);self::assertSame($cashRows,$db->table('cash_document_payments')->count());
            self::assertEquals(208,$doc->fresh()->payments->sum('payment'));
            try { IvaRetentionService::store($doc,$this->ivaVoucher($doc,['operation_key'=>$reversal['operation_key']]));self::fail('Reversal key reused to register'); }
            catch (ValidationException $e) { self::assertSame(409,$e->status); }
            $replacement = IvaRetentionService::store($doc,$this->ivaVoucher($doc,['currency_type_id'=>$currency,'exchange_rate'=>'10.12345678']));
            self::assertSame('confirmed',$replacement->operations()->first()->status);self::assertEquals(0,$doc->fresh()->balance);
            self::assertCount(2,$doc->fresh()->received_retentions);
        }
    }

    public function test_iva_voucher_staged_during_creation_consumes_upload_only_after_commit(): void
    {
        [$db,$app,$input] = $this->bootIvaFixture();
        $token = (string)Str::uuid(); $path = 'received_retentions/drafts/'.$token.'.pdf';
        Storage::disk('tenant')->put($path,'%PDF-private-fixture');
        ReceivedRetentionUpload::create(['token'=>$token,'user_id'=>auth()->id(),'path'=>$path,'expires_at'=>now()->addDay()]);
        $voucher = ['tax_kind'=>'IVA','operation_key'=>(string)Str::uuid(),'voucher_number'=>'20260900000001','voucher_date'=>'2026-09-10',
            'agent_rif'=>'V-12345678-9','invoice_number'=>'FF01-00000100','invoice_control'=>'00-00000100',
            'base'=>32,'percentage'=>75,'amount'=>24,'currency_type_id'=>'VES','exchange_rate'=>1,'upload_id'=>$token];
        $input['received_retentions'] = [$voucher];
        $db->beginTransaction();
        $doc = (new Facturalo)->save($input)->getDocument();
        self::assertEquals(24,$doc->fresh()->balance);self::assertSame('pending_validation',$doc->received_retentions()->first()->application_status);
        $db->rollBack();self::assertNull(ReceivedRetentionUpload::where('token',$token)->first()->document_id);
        self::assertTrue(Storage::disk('tenant')->exists($path));
        $doc = (new Facturalo)->save($input)->getDocument()->fresh();
        self::assertSame('applied',$doc->received_retentions->first()->application_status);self::assertEquals(0,$doc->balance);
        self::assertSame('confirmed',$doc->received_retentions->first()->operations()->first()->status);
        self::assertArrayNotHasKey('input_snapshot',$doc->received_retentions->first()->toArray());
        $other = (new Facturalo)->save(array_replace($input,['received_retentions'=>[],'external_id'=>(string)Str::uuid()]))->getDocument()->fresh();
        try { IvaRetentionService::store($other,array_replace($voucher,['operation_key'=>(string)Str::uuid()]));self::fail('Reused upload'); }
        catch (ValidationException $e) { self::assertArrayHasKey('upload_id',$e->errors()); }
        ReceivedRetentionUpload::where('token',$token)->update(['expires_at'=>now()->subMinute()]);
        $consumedPath=$doc->received_retentions->first()->attachment;
        $command = new \App\Console\Commands\RecoverIvaRetentions;
        $command->setLaravel(app());
        $command->run(new \Symfony\Component\Console\Input\ArrayInput([]),new \Symfony\Component\Console\Output\NullOutput);
        self::assertTrue(Storage::disk('tenant')->exists($consumedPath),'Expiry cleanup retains consumed support');
        self::assertFalse(Storage::disk('tenant')->exists($path));
        self::assertFalse(ReceivedRetentionUpload::where('token',$token)->exists());
    }
    public function test_iva_ambiguous_write_removal_conflicts_and_definitive_rejections(): void
    {
        [$db,$app,$input,$state] = $this->bootIvaFixture();
        $doc = (new Facturalo)->save($input)->getDocument()->fresh();
        $state->mode = 'timeout';
        $row = IvaRetentionService::store($doc,$this->ivaVoucher($doc));
        self::assertEquals(0,$doc->fresh()->balance); self::assertSame('uncertain',$row->operations()->first()->status);
        $writes = fn() => count(array_filter($state->calls,fn($c)=>$c[0]==='POST' && str_ends_with($c[1],'/AplicarRetencion')));
        self::assertSame(1,$writes());
        $state->mode = 'normal'; app(HkaIvaRetention::class)->process($row,true);
        self::assertSame('confirmed',$row->operations()->first()->status); self::assertSame(1,$writes());
        $state->mode = 'unknown';
        IvaRetentionService::reverse($row,['operation_key'=>(string)Str::uuid(),'reason'=>'Corrección']);
        self::assertSame('applied',$row->fresh()->application_status); self::assertEquals(0,$doc->fresh()->balance);
        $remove = $row->operations()->where('action','remove')->first(); self::assertSame('uncertain',$remove->status);
        $remove->update(['next_attempt_at'=>null]); $state->mode = 'timeout'; app(HkaIvaRetention::class)->process($row);
        self::assertSame('applied',$row->fresh()->application_status);
        $row->operations()->where('action','apply')->update(['started_at'=>now()->subSeconds(91)]);
        $state->mode = 'normal'; app(HkaIvaRetention::class)->process($row,true);
        self::assertSame('reversed',$row->fresh()->application_status); self::assertEquals(24,$doc->fresh()->balance);
        self::assertCount(1,array_filter($state->calls,fn($c)=>$c[0]==='DELETE'));
        $state->mode = 'rejected';
        $replacement = IvaRetentionService::store($doc,$this->ivaVoucher($doc,['voucher_number'=>'20260900000002']));
        self::assertSame('rejected',$replacement->operations()->first()->status);
        app(HkaIvaRetention::class)->process($replacement,true);
        self::assertSame('rejected',$replacement->operations()->first()->status,'Reading absence must not retry a definitive rejection');
        $state->mode = 'normal';
        IvaRetentionService::reverse($replacement,['operation_key'=>(string)Str::uuid(),'reason'=>'Nuevo soporte']);
        $adoptedInput = $this->ivaVoucher($doc,['voucher_number'=>'20260900000004']);
        $foreign = new DocumentReceivedRetention;
        $foreign->setRawAttributes(['voucher_number'=>'20260900000004','voucher_date'=>'2026-09-10','currency_type_id'=>'VES','exchange_rate'=>1,'taxable_base'=>200,'base'=>32,'amount'=>24]);
        $prepared = \App\Services\Fiscal\HkaIvaRetentionPayload::build($doc,$foreign);
        $state->remote[$doc->control_number] = \App\Services\Fiscal\HkaIvaRetentionPayload::identity($prepared)+['totalesRetencion'=>$prepared['totalRetencion']];
        $adopted = IvaRetentionService::store($doc,$adoptedInput);
        self::assertSame('confirmed',$adopted->operations()->first()->status);self::assertSame(0,(int)$adopted->operations()->first()->attempts);
        IvaRetentionService::reverse($adopted,['operation_key'=>(string)Str::uuid(),'reason'=>'Retención ya existente en HKA']);
        self::assertSame('reversed',$adopted->fresh()->application_status);self::assertNull($state->remote[$doc->control_number]);
        self::assertCount(2,array_filter($state->calls,fn($c)=>$c[0]==='DELETE'));
        $payload = $doc->emission->payload;
        $remoteKey = $doc->control_number;
        $state->remote[$remoteKey] = ['serie'=>$doc->series,'tipoDocumento'=>'01','numeroDocumento'=>(string)$doc->number,'numeroControl'=>$remoteKey,
            'totalesRetencion'=>['numeroCompRetencion'=>'20260999999999']];
        $conflict = IvaRetentionService::store($doc,$this->ivaVoucher($doc,['voucher_number'=>'20260900000003']));
        self::assertSame('conflict',$conflict->operations()->first()->status); self::assertSame($payload,$doc->fresh()->emission->payload);
        self::assertSame('20260999999999',$state->remote[$remoteKey]['totalesRetencion']['numeroCompRetencion']);
        $state->remote[$remoteKey] = null;
        $beforeWrites = $writes();
        app(HkaIvaRetention::class)->process($conflict,true);
        self::assertSame('conflict',$conflict->operations()->first()->status,'An external conflict cannot automatically become another POST');
        self::assertSame($beforeWrites,$writes());
        IvaRetentionService::reverse($conflict,['operation_key'=>(string)Str::uuid(),'reason'=>'Conflicto externo corregido']);
        self::assertSame('reversed',$conflict->fresh()->application_status);self::assertEquals(24,$doc->fresh()->balance);
    }

    public function test_iva_staged_reference_mismatch_permissions_and_expired_support(): void
    {
        [$db,$app,$input,$state] = $this->bootIvaFixture();
        $token = (string)Str::uuid(); $path = 'received_retentions/drafts/'.$token.'.pdf';
        Storage::disk('tenant')->put($path,'%PDF-private-fixture');
        $upload = ReceivedRetentionUpload::create(['token'=>$token,'user_id'=>auth()->id(),'path'=>$path,'expires_at'=>now()->addDay()]);
        $voucher = ['tax_kind'=>'IVA','operation_key'=>(string)Str::uuid(),'voucher_number'=>'20260900000001','voucher_date'=>'2026-09-10',
            'agent_rif'=>'V-12345678-9','invoice_number'=>'FF01-00000100','invoice_control'=>'00-WRONG',
            'base'=>32,'percentage'=>75,'amount'=>24,'currency_type_id'=>'VES','exchange_rate'=>1,'upload_id'=>$token];
        $doc = (new Facturalo)->save(array_replace($input,['received_retentions'=>[$voucher]]))->getDocument()->fresh();
        $row = $doc->received_retentions->first();self::assertSame('invalid',$row->application_status);self::assertEquals(24,$doc->balance);
        self::assertEquals(0,$row->applied_amount);self::assertCount(0,$row->operations);
        $user = auth()->user(); $before = count($state->calls);
        $user->type = 'seller';$user->create_payment=false;
        try { IvaRetentionService::store($doc,$this->ivaVoucher($doc));self::fail('Unauthorized seller'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { self::assertSame(403,$e->getStatusCode()); }
        $user->type='admin';self::assertSame($before,count($state->calls));
        $upload->update(['document_id'=>null,'expires_at'=>now()->subMinute()]);
        try { IvaRetentionService::store($doc,array_replace($voucher,['operation_key'=>(string)Str::uuid()]));self::fail('Expired upload'); }
        catch (ValidationException $e) { self::assertArrayHasKey('upload_id',$e->errors()); }
        foreach ([['amount'=>999],['agent_rif'=>'J-99999999-9'],['attachment'=>null],['percentage'=>100,'amount'=>32,'fiscal_context'=>[]]] as $changes) {
            try { IvaRetentionService::store($doc,array_replace($this->ivaVoucher($doc),$changes));self::fail('Invalid voucher'); }
            catch (ValidationException $e) { self::assertNotEmpty($e->errors()); }
        }
        self::assertCount(1,$doc->received_retentions()->get());
        $payload=$doc->emission->payload;
        $malformed=$payload;$malformed['documentoElectronico']['encabezado']['identificacionDocumento']['moneda']='EUR';
        $doc->emission->update(['status'=>'prepared','payload'=>$malformed]);
        $pending=IvaRetentionService::store($doc->fresh(),$this->ivaVoucher($doc));
        self::assertSame('pending_validation',$pending->application_status);
        $doc->emission->update(['status'=>'confirmed']);
        IvaRetentionService::resume($doc->fresh());
        self::assertSame('invalid',$pending->fresh()->application_status);self::assertEquals(0,$pending->fresh()->applied_amount);
        self::assertEquals(24,$doc->fresh()->balance);self::assertEquals(24,$doc->fresh()->fiscal_data->iva_retention_prevision['amount']);
    }

    public function test_iva_two_processes_register_one_active_voucher(): void
    {
        [$db,$app,$input] = $this->bootIvaFixture();
        $doc = (new Facturalo)->save($input)->getDocument()->fresh();
        $voucher = $this->ivaVoucher($doc);
        $results = $this->runFiscalWorkers($doc->id,'retention',[$voucher,array_replace($voucher,['operation_key'=>(string)Str::uuid(),'voucher_number'=>'20260900000002'])]);
        self::assertSame(1,count(array_filter($results,fn($r)=>isset($r['id']))));
        self::assertCount(1,$doc->received_retentions()->get());self::assertEquals(24,$doc->received_retentions()->sum('applied_amount'));
        self::assertEquals(0,$doc->fresh()->balance);
        $row=$doc->received_retentions()->first();
        $reverse=['action'=>'reverse','retention_id'=>$row->id,'input'=>['operation_key'=>(string)Str::uuid(),'reason'=>'Corrección concurrente']];
        $results=$this->runFiscalWorkers($doc->id,'iva-mutations',[$reverse,$reverse]);
        self::assertCount(2,array_filter($results,fn($r)=>isset($r['id'])));
        self::assertSame('applied',$row->fresh()->application_status,'Reversal must wait for the uncertain apply attempt');
        self::assertEquals(0,$doc->fresh()->balance);
        $row->operations()->where('action','apply')->update(['started_at'=>now()->subSeconds(91)]);
        app(HkaIvaRetention::class)->process($row->fresh(),true);
        self::assertSame('reversed',$row->fresh()->application_status);self::assertEquals(24,$doc->fresh()->balance);
        self::assertEquals(208,$doc->fresh()->payments->sum('payment'));
        $next=(new Facturalo)->save(array_replace($input,['external_id'=>(string)Str::uuid()]))->getDocument()->fresh();
        $collect=['action'=>'collect','input'=>['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'01','payment'=>24]];
        $results=$this->runFiscalWorkers($next->id,'iva-mutations',[['action'=>'register','input'=>$this->ivaVoucher($next)],$collect]);
        self::assertCount(1,array_filter($results,fn($r)=>isset($r['id'])));
        self::assertEquals(0,$next->fresh()->balance);
        self::assertEquals(232,$next->fresh()->payments->sum('payment')+$next->fresh()->received_retentions->sum('applied_amount'));
        $last=(new Facturalo)->save(array_replace($input,['external_id'=>(string)Str::uuid()]))->getDocument()->fresh();
        $retained=IvaRetentionService::store($last,$this->ivaVoucher($last));
        $results=$this->runFiscalWorkers($last->id,'iva-mutations',[['action'=>'reverse','retention_id'=>$retained->id,'input'=>['operation_key'=>(string)Str::uuid(),'reason'=>'Corrección durante cobro']],$collect]);
        self::assertArrayHasKey('id',$results[0]);self::assertSame('reversed',$retained->fresh()->application_status);
        self::assertEquals(0,$retained->fresh()->applied_amount);
        self::assertGreaterThanOrEqual(0,$last->fresh()->balance);
        self::assertEquals(232-$last->fresh()->payments->sum('payment'),$last->fresh()->balance);
    }

    public function test_iva_fiscal_ves_is_independent_from_usd_settlement_rate(): void
    {
        [$db,$app,$input] = $this->bootIvaFixture();
        $input['currency_type_id']='USD';$input['exchange_rate_sale']='10.00000000';
        $doc=(new Facturalo)->save($input)->getDocument()->fresh();
        $row=IvaRetentionService::store($doc,$this->ivaVoucher($doc,['base'=>320,'amount'=>240,'currency_type_id'=>'VES','exchange_rate'=>'20.00000000']));
        self::assertEquals(12,$row->applied_amount);self::assertEquals(12,$doc->fresh()->balance);
        self::assertEquals(2000,$row->taxable_base);
        $payload=$row->operations()->first()->payload;
        self::assertSame('320.00',$payload['totalRetencion']['totalIVA']);
        self::assertSame('240.00',$payload['totalRetencion']['totalRetenido']);
        self::assertSame('2000.00',$payload['totalRetencion']['totalBaseImponible']);
        self::assertSame('20.00000000',$row->exchange_rate);self::assertSame('10.00000000',$doc->exchange_rate_sale);
    }

}
