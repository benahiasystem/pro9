<?php
namespace Tests\Support;

use App\CoreFacturalo\Facturalo;
use App\Models\Tenant\Document;
use App\Services\Fiscal\{FiscalNote, NoteRefund, NoteAdjustment};
use Illuminate\Support\Str;

trait NotePersistenceCases
{
    private function noteFixture(): array
    {
        [$db,$app,$input]=$this->bootHkaFixture(); $calls=0;
        config(['hka_notes.contract_probe'=>true]); // Doubles test protocol, never enable runtime capabilities.
        $owned=$db->getDatabaseName(); $connection=$db->getConfig();
        register_shutdown_function(static function () use ($owned,$connection) {
            if (!preg_match('/^pro9_fiscal_test_[a-f0-9]{12}$/D',$owned)) return;
            $pdo=new \PDO('mysql:host='.$connection['host'].';port='.$connection['port'].';dbname=information_schema',$connection['username'],$connection['password']);
            $pdo->exec('DROP DATABASE IF EXISTS `'.$owned.'`');
        });
        $app->make(\Illuminate\Http\Client\Factory::class)->fake(function ($request) use ($db,&$calls) {
            self::assertSame(0,$db->transactionLevel());
            if (str_ends_with($request->url(),'/Autenticacion')) return \Illuminate\Support\Facades\Http::response(['token'=>'test-private-token','expiracion'=>date('c',time()+3600)]);
            if (str_ends_with($request->url(),'/DescargaArchivo')) {
                $pdf=new \FPDF();$pdf->AddPage();$pdf->SetFont('Helvetica','',12);$pdf->Text(10,15,'Note fiscal test');
                return \Illuminate\Support\Facades\Http::response(['codigo'=>'200','archivo'=>base64_encode($pdf->Output('S'))]);
            }
            $calls++;$id=$request['documentoElectronico']['encabezado']['identificacionDocumento'];
            return \Illuminate\Support\Facades\Http::response(['codigo'=>'200','validaciones'=>[],
                'resultado'=>$id+['numeroControl'=>'00-'.str_pad((string)$calls,8,'0',STR_PAD_LEFT),'urlConsulta'=>'https://example.test/consulta/'.$calls]]);
        });
        $invoice=(new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh();
        self::assertSame('confirmed',$invoice->emission->status);
        return [$db,$app,$input,$invoice];
    }

    private function noteInput(array $input,Document $invoice,string $type,string $reason,array $rows): array
    {
        unset($input['invoice'],$input['fiscal_data'],$input['third_party_id'],$input['iva_retention_prevision'],$input['islr_retention_prevision'],$input['received_retentions'],$input['guarantee_fund']);
        $input['type']=$type === '07' ? 'credit':'debit';$input['document_type_id']=$type;
        $input['series']=$type === '07' ? 'FC01':'FD01';$input['external_id']=(string)Str::uuid();$input['number']='#';
        $input['note_creation_key']=(string)Str::uuid();$input['items']=$rows;$input['payments']=[];
        $input['note']=['note_type'=>$input['type'],'note_credit_type_id'=>$type==='07' ? $reason:null,
            'note_debit_type_id'=>$type==='08' ? $reason:null,'note_description'=>'Ajuste de prueba','affected_document_id'=>$invoice->id,'data_affected_document'=>null];
        return $input;
    }

    public function test_note_credit_limits_inventory_idempotency_and_hka_mapping(): void
    {
        [$db,$app,$input,$invoice]=$this->noteFixture();$line=$invoice->items->first();
        $stock=$db->table('item_warehouse')->where('item_id',$line->item_id)->value('stock');
        $data=$this->noteInput($input,$invoice,'07','04',[['affected_document_item_id'=>$line->id,'adjustment_amount'=>23.20]]);
        $note=(new Facturalo)->forWebDocuments()->save($data)->getDocument()->fresh();
        self::assertEquals(23.20,$note->total);self::assertEquals($stock,$db->table('item_warehouse')->where('item_id',$line->item_id)->value('stock'));
        self::assertSame('confirmed',$note->emission->status);
        self::assertSame('02',$note->emission->payload['documentoElectronico']['encabezado']['identificacionDocumento']['tipoDocumento']);
        self::assertSame('04',$note->fiscal_data->transaction_type_id);
        self::assertEquals(-23.20,$invoice->fresh()->balance);
        self::assertSame($note->id,(new Facturalo)->forWebDocuments()->save($data)->getDocument()->id);
        $collision=$data;$collision['items'][0]['adjustment_amount']=23.21;
        try {(new Facturalo)->forWebDocuments()->save($collision);self::fail('Creation UUID cannot identify different amounts');}
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {self::assertSame(409,$e->getStatusCode());}
        $partial=$this->noteInput($input,$invoice,'07','07',[['affected_document_item_id'=>$line->id,'quantity'=>1]]);
        $returned=(new Facturalo)->forWebDocuments()->save($partial)->getDocument()->fresh();
        self::assertEquals(104.40,$returned->total);self::assertEquals($stock+1,$db->table('item_warehouse')->where('item_id',$line->item_id)->value('stock'));
        $bad=$this->noteInput($input,$invoice,'07','07',[['affected_document_item_id'=>$line->id,'quantity'=>2]]);
        $count=$db->table('documents')->count();
        try {(new Facturalo)->forWebDocuments()->save($bad);self::fail('Returns cannot exceed the source quantity');}
        catch (\Illuminate\Validation\ValidationException $e) {self::assertSame($count,$db->table('documents')->count());}
        $cancel=$this->noteInput($input,$invoice,'07','01',[]);
        $last=(new Facturalo)->forWebDocuments()->save($cancel)->getDocument()->fresh();
        self::assertEquals(104.40,$last->total);self::assertEquals(232,FiscalNote::credits($invoice));
        self::assertEquals($stock+2,$db->table('item_warehouse')->where('item_id',$line->item_id)->value('stock'));
        self::assertSame('03',$last->fiscal_data->transaction_type_id);
    }

    public function test_note_debit_is_a_separate_receivable_without_inventory(): void
    {
        [$db,$app,$input,$invoice]=$this->noteFixture();$line=$invoice->items->first();
        $stock=$db->table('item_warehouse')->where('item_id',$line->item_id)->value('stock');
        $data=$this->noteInput($input,$invoice,'08','02',[['affected_document_item_id'=>$line->id,'adjustment_amount'=>11.60]]);
        $note=(new Facturalo)->forWebDocuments()->save($data)->getDocument()->fresh();
        self::assertEquals(11.60,$note->balance);self::assertEquals(0,$invoice->fresh()->balance);
        self::assertEquals($stock,$db->table('item_warehouse')->where('item_id',$line->item_id)->value('stock'));
        self::assertSame('03',$note->emission->payload['documentoElectronico']['encabezado']['identificacionDocumento']['tipoDocumento']);
        self::assertSame('02',$note->fiscal_data->transaction_type_id);
        $paid=$data;$paid['note_creation_key']=(string)Str::uuid();$paid['external_id']=(string)Str::uuid();
        $paid['payments']=[['payment'=>11.60,'date_of_payment'=>'2026-09-10','payment_method_type_id'=>'01']];
        $collected=(new Facturalo)->forWebDocuments()->save($paid)->getDocument()->fresh();
        self::assertEquals(0,$collected->balance);self::assertSame('confirmed',$collected->emission->status);
    }

    public function test_note_currency_regime_and_service_matrix(): void
    {
        [$db,$app,$input,$first]=$this->noteFixture();
        $party=['person_id'=>$first->customer_id,'rif'=>'J000000004','name'=>'Tercero de prueba','address'=>'Caracas','country_id'=>'VE','activity'=>'Comercio'];
        foreach (['VES','USD'] as $currency) {
            foreach ([null,1,2,3,4,5,6,7] as $regime) {
                $source=$input;$source['currency_type_id']=$currency;$source['exchange_rate_sale']='10.12345678';
                $invoice=(new Facturalo)->forWebDocuments()->save($source)->getDocument()->fresh();
                if ($regime) $invoice->fiscal_data->update(['special_tax_regime_id'=>$regime,'catalog_snapshot'=>['special_tax_regime_id'=>['id'=>$regime,'description'=>\App\Services\SpecialTaxRegimePolicy::NAMES[$regime]]]]);
                $line=$invoice->items->first();
                $noteInput=$this->noteInput($source,$invoice,'07','09',[['affected_document_item_id'=>$line->id,'adjustment_amount'=>11.60]]);
                $noteInput['exchange_rate_sale']='99.00000000';
                $note=(new Facturalo)->forWebDocuments()->save($noteInput)->getDocument()->fresh();
                self::assertSame($invoice->exchange_rate_sale,$note->exchange_rate_sale);
                self::assertSame('confirmed',$note->emission->status);
                self::assertSame('VES',$note->emission->payload['documentoElectronico']['encabezado']['identificacionDocumento']['moneda']);
                if ($regime) self::assertSame(\App\Services\SpecialTaxRegimePolicy::NAMES[$regime],$note->emission->payload['documentoElectronico']['encabezado']['identificacionDocumento']['regimenEspTributacion']);
                $invoice->fiscal_data->update(['third_party'=>$party,'third_party_person_id'=>$first->customer_id,'transaction_type_id'=>'99']);
                $debitInput=$this->noteInput($source,$invoice,'08','02',[['affected_document_item_id'=>$line->id,'adjustment_amount'=>11.60]]);
                $debitInput['exchange_rate_sale']='11.87654321';
                $debit=(new Facturalo)->forWebDocuments()->save($debitInput)->getDocument()->fresh();
                self::assertSame('11.87654321',$debit->exchange_rate_sale);self::assertSame('confirmed',$debit->emission->status);
                self::assertSame('02',$debit->fiscal_data->transaction_type_id);
                self::assertSame('Tercero de prueba',$debit->emission->payload['documentoElectronico']['encabezado']['tercero']['razonSocial']);
            }
        }
        $service=\App\Models\Tenant\Item::where('unit_type_id','SERV')->firstOrFail();
        foreach (['01','03'] as $reason) {
            $data=$this->noteInput($input,$first,'08',$reason,[['item_id'=>$service->id,'adjustment_amount'=>11.60,'affectation_igv_type_id'=>'10','price_type_id'=>'01','additional_information'=>'']]);
            $note=(new Facturalo)->forWebDocuments()->save($data)->getDocument()->fresh();
            self::assertSame('confirmed',$note->emission->status);self::assertEquals(11.60,$note->balance);
            self::assertEquals(0,$note->items->first()->inventory_quantity);
        }
    }

    public function test_note_pending_igtf_and_fund_adjustments_have_no_cash_effect(): void
    {
        [$db,$app,$input,$invoice]=$this->noteFixture();$line=$invoice->items->first();
        $data=$this->noteInput($input,$invoice,'07','09',[['affected_document_item_id'=>$line->id,'adjustment_amount'=>23.20]]);
        $note=(new Facturalo)->forWebDocuments()->save($data)->getDocument()->fresh();
        $fund=$invoice->guarantee_fund()->create(['base'=>100,'percentage'=>10,'amount'=>10]);
        $original=$fund->toArray();
        $request=['kind'=>'fund','source_id'=>$fund->id,'operation_key'=>(string)Str::uuid(),'amount'=>5,'reason'=>'Ajuste de fondo por NC'];
        $adjustment=NoteAdjustment::register($note,$request);
        self::assertSame($adjustment->id,NoteAdjustment::register($note,$request)->id);
        self::assertEquals(5,$invoice->fresh()->guarantee_amount);self::assertEquals($original,$fund->fresh()->toArray());
        $payment=$invoice->payments->first();$payment->forceFill(['tax_amount'=>3])->save();
        $tax=$invoice->taxes()->create(['tax_kind'=>'IGTF','code'=>'IGTF','percentage'=>3,'base'=>100,'amount'=>3,'base_ves'=>100,'amount_ves'=>3,'document_payment_id'=>$payment->id]);
        $cash=$db->table('global_payments')->count();
        $igtf=NoteAdjustment::register($note,['kind'=>'IGTF','source_id'=>$tax->id,'operation_key'=>(string)Str::uuid(),'amount'=>3,'reason'=>'Impuesto del cobro devuelto']);
        self::assertSame('blocked',$igtf->status);self::assertSame($cash,$db->table('global_payments')->count());
        self::assertEquals(3,$tax->fresh()->amount);
        config(['hka_notes.contract_probe'=>false,'hka_notes.demo_credit_without_payment_verified'=>false]);
        self::assertSame(\App\Services\Fiscal\HkaNoteCapability::CREDIT_REASON,\App\Services\Fiscal\HkaNoteCapability::reason($note));
        self::assertFalse(\App\Services\Fiscal\NoteRefund::view($note)['can_create'] && $note->emission->status !== 'confirmed');
        \Tests\Support\SpeProfiles::versions($db,1,$invoice->customer_id);$db->table('companies')->update(['igtf_enabled'=>1,'igtf_rate'=>3]);
        $invoice->fiscal_data->update(['special_tax_regime_id'=>7,'catalog_snapshot'=>['special_tax_regime_id'=>['id'=>7,'description'=>\App\Services\SpecialTaxRegimePolicy::NAMES[7]]]]);
        $debitInput=$this->noteInput($input,$invoice->fresh(),'08','02',[['affected_document_item_id'=>$line->id,'adjustment_amount'=>11.60]]);
        $debit=(new Facturalo)->forWebDocuments()->save($debitInput)->getDocument()->fresh();
        $receipt=\App\Services\Fiscal\FiscalDocumentPersistence::payment($debit,['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'01',
            'operation_key'=>(string)Str::uuid(),'currency_type_id'=>'USD','original_amount'=>1.16,'exchange_rate'=>'10.00000000','financial_intermediation'=>'none']);
        self::assertEquals(0,$debit->fresh()->balance);self::assertEquals(0.03,$receipt->tax_amount);
        $taxNote=Document::whereHas('taxes',fn ($q)=>$q->where('document_payment_id',$receipt->id)->where('tax_kind','IGTF'))->firstOrFail();
        self::assertSame('98',$taxNote->fiscal_data->transaction_type_id);self::assertEquals(7,$taxNote->fiscal_data->special_tax_regime_id);
        self::assertSame(\App\Services\Fiscal\HkaNoteCapability::RELATED_IGTF_REASON,\App\Services\Fiscal\HkaNoteCapability::reason($taxNote));
        self::assertEquals($invoice->id,$taxNote->note->affected_document_id);
        $pending=NoteAdjustment::register($note,['kind'=>'IGTF','source_id'=>$taxNote->taxes->first()->id,'operation_key'=>(string)Str::uuid(),'amount'=>0.30,'reason'=>'IGTF del cargo posterior']);
        self::assertSame('blocked',$pending->status);
        config(['hka_notes.contract_probe'=>true]);
        $initial=$input;$initial['external_id']=(string)Str::uuid();$initial['payments']=[['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'01','currency_type_id'=>'USD',
            'original_amount'=>23.20,'exchange_rate'=>'10.00000000','financial_intermediation'=>'none']];
        $taxed=(new Facturalo)->forWebDocuments()->save($initial)->getDocument()->fresh();
        self::assertEquals(239,$taxed->total);self::assertEquals(0,$taxed->balance);
        $creditInput=$this->noteInput($initial,$taxed,'07','09',[['affected_document_item_id'=>$taxed->items->first()->id,'adjustment_amount'=>46.40]]);
        $credit=(new Facturalo)->forWebDocuments()->save($creditInput)->getDocument()->fresh();
        self::assertEquals(46.40,NoteRefund::available($credit));
        $initialTax=$taxed->taxes->firstWhere('tax_kind','IGTF');
        NoteAdjustment::register($credit,['kind'=>'IGTF','source_id'=>$initialTax->id,'operation_key'=>(string)Str::uuid(),'amount'=>1.40,'reason'=>'IGTF proporcional pendiente']);
        self::assertEquals(46.40,NoteRefund::available($credit),'Pending IGTF blocks only its component, never the real principal refund');
    }

    public function test_note_iva_replacement_preserves_real_vouchers_without_fake_cash(): void
    {
        [$db,$app,$input]=$this->bootIvaFixture(); config(['hka_notes.contract_probe'=>true]);
        $invoice=(new Facturalo)->save($input)->getDocument()->fresh();
        $voucher=$this->ivaVoucher($invoice);
        $original=\App\Services\Fiscal\IvaRetentionService::store($invoice,$voucher);
        self::assertEquals(0,$invoice->fresh()->balance);
        $data=$this->noteInput($input,$invoice,'07','09',[['affected_document_item_id'=>$invoice->items->first()->id,'adjustment_amount'=>46.40]]);
        $data['number']=200;
        $note=(new Facturalo)->forWebDocuments()->save($data)->getDocument()->fresh();
        self::assertSame('confirmed',$note->emission->status);
        $before=$db->table('global_payments')->count();
        self::assertEquals(41.60,NoteRefund::available($note)); // A voucher's credited share cannot be paid out as cash.
        $adjustment=NoteAdjustment::register($note,['kind'=>'IVA','source_id'=>$original->id,'operation_key'=>(string)Str::uuid(),'reason'=>'Comprobante corregido por NC']);
        self::assertSame('awaiting_support',$adjustment->status);
        $replacement=$this->ivaVoucher($invoice,['voucher_number'=>'20260900000002','base'=>25.60,'amount'=>19.20]);
        NoteAdjustment::replace($note,$adjustment,$replacement);
        self::assertSame('applied',$adjustment->fresh()->status);
        self::assertEquals(-41.60,$invoice->fresh()->balance);
        self::assertEquals(41.60,NoteRefund::available($note));
        self::assertSame('reversed',$original->fresh()->application_status);
        self::assertEquals(24,$original->fresh()->amount);
        self::assertSame($before,$db->table('global_payments')->count());
    }

    public function test_note_refund_preserves_collections_and_reverses_once(): void
    {
        [$db,$app,$input,$invoice]=$this->noteFixture();$line=$invoice->items->first();
        $data=$this->noteInput($input,$invoice,'07','09',[['affected_document_item_id'=>$line->id,'adjustment_amount'=>23.20]]);
        $note=(new Facturalo)->forWebDocuments()->save($data)->getDocument()->fresh();
        $bankId=$db->table('bank_accounts')->insertGetId(['description'=>'Refund test','number'=>'test','bank_id'=>1,'currency_type_id'=>'VES','establishment_id'=>$invoice->establishment_id]);
        $refund=['operation_key'=>(string)Str::uuid(),'component'=>'principal','document_payment_id'=>$invoice->payments->first()->id,
            'date_of_payment'=>'2026-09-10','payment_method_type_id'=>'01','payment_destination_id'=>$bankId,
            'currency_type_id'=>'VES','original_amount'=>10,'exchange_rate'=>'1.00000000','exchange_rate_source'=>'manual'];
        $before=$invoice->payments->first()->toArray();
        $excess=array_replace($refund,['operation_key'=>(string)Str::uuid(),'original_amount'=>24]);
        try {(new NoteRefund)->register($note,$excess);self::fail('Cash refund cannot exceed the NC');}
        catch (\Illuminate\Validation\ValidationException $e) {self::assertArrayHasKey('original_amount',$e->errors());}
        $record=(new NoteRefund)->register($note,$refund);
        self::assertSame($record->id,(new NoteRefund)->register($note,$refund)->id);
        self::assertEquals(-13.20,$invoice->fresh()->balance);self::assertEquals(13.20,NoteRefund::available($note));
        $sql=\App\Services\Fiscal\FiscalNoteBalance::sql();
        self::assertEquals($invoice->fresh()->balance,$db->table('documents')->where('id',$invoice->id)->selectRaw($sql.' AS outstanding')->first()->outstanding);
        self::assertEquals(0,$db->table('documents')->where('id',$note->id)->selectRaw($sql.' AS outstanding')->first()->outstanding);
        self::assertSame('output',$record->global_payment->type_movement);
        self::assertEquals($before,$invoice->payments->first()->fresh()->toArray());
        $reverse=['operation_key'=>(string)Str::uuid(),'reason'=>'Corrección del registro de devolución'];
        (new NoteRefund)->reverse($note,$record,$reverse);(new NoteRefund)->reverse($note,$record,$reverse);
        self::assertEquals(-23.20,$invoice->fresh()->balance);self::assertEquals(23.20,NoteRefund::available($note));
        $cash=\App\Models\Tenant\Cash::create(['user_id'=>auth()->id(),'date_opening'=>'2026-09-10','time_opening'=>'12:00:00','state'=>true]);
        $cashRefund=array_replace($refund,['operation_key'=>(string)Str::uuid(),'original_amount'=>5,'payment_destination_id'=>'cash']);
        $out=(new NoteRefund)->register($note->fresh(),$cashRefund);
        self::assertEquals(5,$cash->noteRefundTotal());self::assertSame('egress',$out->getDataCashPaymentReport()['type_transaction']);
        self::assertEquals(-5,$out->getRowIncomeSummaryPayment()['payment_for_calculate']);
        self::assertSame(1,\App\Models\Tenant\Cash::filterDataGeneralCashReport()->find($cash->id)->global_destination->where('payment_type',\App\Models\Tenant\DocumentNoteRefund::class)->count());
        (new NoteRefund)->reverse($note,$out,['operation_key'=>(string)Str::uuid(),'reason'=>'Reversión auditada de salida de caja']);
        self::assertEquals(0,$cash->noteRefundTotal());
        $view=NoteRefund::view($note->fresh());self::assertSame($record->document_payment_id,$view['records'][0]['source_payment_id']);
        self::assertStringNotContainsString('request_hash',json_encode($view));self::assertStringNotContainsString('fiscal_context',json_encode($view));
        $other=new \App\Models\Tenant\User(['id'=>1,'type'=>'seller','establishment_id'=>999,'create_payment'=>true]);
        $app->instance('auth',new class($other) {private $user;public function __construct($u){$this->user=$u;}public function user(){return $this->user;}public function id(){return $this->user->id;}});
        try {(new NoteRefund)->register($note,array_replace($refund,['operation_key'=>(string)Str::uuid()]));self::fail('A different branch cannot refund this note');}
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {self::assertSame(403,$e->getStatusCode());}
    }

    public function test_note_original_packs_presentations_lots_serials_and_stock_rollback(): void
    {
        [$db,$app,$input,$invoice]=$this->noteFixture(); $original=$invoice->items->first();
        $item=\App\Models\Tenant\Item::findOrFail($original->item_id);$warehouse=$original->warehouse_id;
        $pack=$item->replicate();$pack->description='Pack original';$pack->internal_id='NOTE-PACK';$pack->is_set=true;$pack->save();
        $pack->sets()->create(['individual_item_id'=>$item->id,'quantity'=>3]);
        $data=$input;$data['items'][0]['item_id']=$pack->id;$data['items'][0]['item']=$pack->fresh()->toArray();
        $packed=(new Facturalo)->forWebDocuments()->save($data)->getDocument()->fresh();
        $source=$packed->items->first();$stock=$db->table('item_warehouse')->where('item_id',$item->id)->value('stock');
        self::assertEquals(3,$source->item->inventory_components[0]->quantity);
        $pack->sets()->update(['quantity'=>9]);
        $partial=$this->noteInput($data,$packed,'07','07',[['affected_document_item_id'=>$source->id,'quantity'=>1]]);
        (new Facturalo)->forWebDocuments()->save($partial);
        self::assertEquals($stock+3,$db->table('item_warehouse')->where('item_id',$item->id)->value('stock'));
        $lot=\Modules\Item\Models\ItemLotsGroup::create(['item_id'=>$item->id,'code'=>'ORIGINAL','quantity'=>0,'old_quantity'=>2,'date_of_due'=>'2027-01-01']);
        $serials=[];foreach ([1,2] as $n) $serials[]=\Modules\Item\Models\ItemLot::create(['item_id'=>$item->id,'warehouse_id'=>$warehouse,
            'series'=>'NOTE-SERIAL-'.$n,'date'=>'2026-09-10','item_loteable_type'=>Document::class,'item_loteable_id'=>$invoice->id,'has_sale'=>true])->toArray();
        $snapshot=json_decode(json_encode($original->item),true);$snapshot['presentation']=['quantity_unit'=>2];
        $snapshot['inventory_components']=[['item_id'=>$item->id,'quantity'=>'2']];
        $snapshot['IdLoteSelected']=[['id'=>$lot->id,'compromise_quantity'=>2]];$snapshot['lots']=$serials;
        $original->item=$snapshot;$original->save();
        $stock=$db->table('item_warehouse')->where('item_id',$item->id)->value('stock');
        $returned=$this->noteInput($input,$invoice,'07','07',[['affected_document_item_id'=>$original->id,'quantity'=>1,'serial_ids'=>[$serials[1]['id']]]]);
        (new Facturalo)->forWebDocuments()->save($returned);
        self::assertEquals($stock+2,$db->table('item_warehouse')->where('item_id',$item->id)->value('stock'));
        self::assertEquals(2,$lot->fresh()->quantity);self::assertFalse(\Modules\Item\Models\ItemLot::find($serials[1]['id'])->has_sale);
        $context=FiscalNote::context($invoice->fresh());self::assertSame($serials[0]['id'],$context['items'][0]['serials'][0]['id']);
        self::assertEquals(1,$context['items'][0]['lots'][0]['quantity']);
        $bad=$this->noteInput($input,$invoice,'07','07',[['affected_document_item_id'=>$original->id,'quantity'=>1,'serial_ids'=>[$serials[1]['id']]]]);
        $count=$db->table('documents')->count();$httpCalls=$app->make(\Illuminate\Http\Client\Factory::class)->recorded()->count();
        try {(new Facturalo)->forWebDocuments()->save($bad);self::fail('A serial cannot be returned twice');}
        catch (\Illuminate\Validation\ValidationException $e) {self::assertSame($count,$db->table('documents')->count());}
        self::assertSame($httpCalls,$app->make(\Illuminate\Http\Client\Factory::class)->recorded()->count());
        $last=$this->noteInput($input,$invoice,'07','01',[]);(new Facturalo)->forWebDocuments()->save($last);
        self::assertEquals($stock+4,$db->table('item_warehouse')->where('item_id',$item->id)->value('stock'));self::assertEquals(4,$lot->fresh()->quantity);
    }

    public function test_note_concurrent_retention_corrections_preserve_uncertain_effects(): void
    {
        [$db,$app,$input,$state]=$this->bootIslrFixture();config(['hka_notes.contract_probe'=>true]);
        $invoice=(new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh();
        $iva=\App\Services\Fiscal\IvaRetentionService::store($invoice,$this->ivaVoucher($invoice));
        $islr=\App\Services\Fiscal\IslrRetentionService::store($invoice,$this->islrVoucher($invoice));
        $data=$this->noteInput($input,$invoice,'07','09',[['affected_document_item_id'=>$invoice->items->first()->id,'adjustment_amount'=>46.40]]);
        $note=(new Facturalo)->forWebDocuments()->save($data)->getDocument()->fresh();
        $state->mode='unknown';
        $ivaAdjustment=NoteAdjustment::register($note,['kind'=>'IVA','source_id'=>$iva->id,'operation_key'=>(string)Str::uuid(),'reason'=>'Comprobante IVA corregido']);
        self::assertSame('pending',$ivaAdjustment->status);self::assertEquals(24,$iva->fresh()->applied_amount);
        self::assertSame('applied',$islr->fresh()->application_status);
        $state->mode='normal';$iva->operations()->where('action','remove')->update(['next_attempt_at'=>now()->subSecond()]);
        app(\App\Services\Fiscal\HkaIvaRetention::class)->process($iva->fresh());NoteAdjustment::resumeInvoice($invoice);
        NoteAdjustment::replace($note,$ivaAdjustment,$this->ivaVoucher($invoice,['voucher_number'=>'20260900000002','base'=>25.60,'amount'=>19.20]));
        $islrAdjustment=NoteAdjustment::register($note,['kind'=>'ISLR','source_id'=>$islr->id,'operation_key'=>(string)Str::uuid(),'reason'=>'Comprobante ISLR corregido']);
        self::assertSame('awaiting_support',$islrAdjustment->status);
        NoteAdjustment::replace($note,$islrAdjustment,$this->islrVoucher($invoice,['voucher_number'=>'ISLR corregido / 002','base'=>160,'amount'=>4.40]));
        self::assertEquals(23.60,$invoice->fresh()->retention_amount);self::assertEquals(-40,$invoice->fresh()->balance);
        self::assertEquals(40,NoteRefund::available($note));self::assertEquals(202,$invoice->fresh()->payments->sum('payment'));
    }

    public function test_note_concurrent_creation_and_refunds_use_one_effect_and_real_currency(): void
    {
        [$db,$app,$input,$invoice]=$this->noteFixture();$line=$invoice->items->first();
        $data=$this->noteInput($input,$invoice,'07','07',[['affected_document_item_id'=>$line->id,'quantity'=>1]]);
        $stock=$db->table('item_warehouse')->where('item_id',$line->item_id)->value('stock');
        $results=$this->runFiscalWorkers($invoice->id,'note-create',[$data,$data]);
        self::assertSame($results[0]['id'],$results[1]['id']);
        self::assertEquals($stock+1,$db->table('item_warehouse')->where('item_id',$line->item_id)->value('stock'));
        $note=Document::findOrFail($results[0]['id']);
        self::assertSame('not_requested',$note->emission->status); // DEMO capability rejection does not roll back commerce.
        $note->forceFill(['control_number'=>'00-00999999'])->save();$note->emission->update(['status'=>'confirmed','control_number'=>$note->control_number]);
        $bankId=$db->table('bank_accounts')->insertGetId(['description'=>'USD refund','number'=>'usd','bank_id'=>1,'currency_type_id'=>'USD','establishment_id'=>$invoice->establishment_id]);
        $refund=['operation_key'=>(string)Str::uuid(),'component'=>'principal','document_payment_id'=>$invoice->payments->first()->id,
            'date_of_payment'=>'2026-09-10','payment_method_type_id'=>'01','payment_destination_id'=>$bankId,
            'currency_type_id'=>'USD','original_amount'=>1,'exchange_rate'=>'10.12345678','exchange_rate_source'=>'manual'];
        $results=$this->runFiscalWorkers($note->id,'note-refund',[$refund,$refund]);
        self::assertSame($results[0]['id'],$results[1]['id']);
        $receipt=\App\Models\Tenant\DocumentNoteRefund::findOrFail($results[0]['id']);
        self::assertEquals(10.12,$receipt->amount);self::assertSame('10.12345678',$receipt->exchange_rate);
        self::assertEquals(105.88,NoteRefund::available($note->fresh()));
        $refund['operation_key']=(string)Str::uuid();$refund['original_amount']=10;
        $next=array_replace($refund,['operation_key'=>(string)Str::uuid()]);
        $results=$this->runFiscalWorkers($note->id,'note-refund',[$refund,$next]);
        self::assertCount(1,array_filter($results,fn ($r)=>isset($r['id'])));
        self::assertEquals(4.65,NoteRefund::available($note->fresh()));
        self::assertEquals(232,$invoice->fresh()->payments->sum('payment'));
        self::assertEquals(0,$note->items->first()->affected_document_item_id === null ? 1:0);
    }
}
