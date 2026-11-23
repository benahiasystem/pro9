<?php
namespace Tests\Support;

use App\CoreFacturalo\Facturalo;
use App\Models\Tenant\Document;
use App\Services\Fiscal\{FiscalNote, NoteRefund, NoteAdjustment};
use Illuminate\Support\Str;

trait NotePersistenceCases
{
    public function test_retention_proposals_use_exact_remaining_ves_invoice_amounts_after_credit_note(): void
    {
        [$db, , $input] = $this->bootHkaFixture(false);
        $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
        $db->table('configurations')->update(['auto_send_hka' => false]);
        SpeProfiles::versions($db, 1, $input['customer_id']);
        $input['payment_condition_id'] = '02';
        $input['payments'] = [];
        $invoice = (new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh();
        self::assertSame('VES', $invoice->currency_type_id);

        $credit = (new Facturalo)->forWebDocuments()->save($this->noteInput($input, $invoice, '07', '09', [[
            'affected_document_item_id' => $invoice->items->first()->id,
            'adjustment_amount' => '1',
        ]]))->getDocument()->fresh();
        $remainingNet = $invoice->exactValue('total_value')->minus($credit->exactValue('total_value'));
        $remainingIva = $invoice->exactValue('total_igv')->minus($credit->exactValue('total_igv'));
        $remainingTaxed = $invoice->exactValue('total_taxed')->minus($credit->exactValue('total_taxed'));
        self::assertSame('4/29', (string) $credit->exactValue('total_igv'));
        $decimal = \App\Support\Exact\Decimal::class;
        $document = (new \App\Http\Controllers\Tenant\DocumentFiscalController())->retentions($invoice->id)['document'];
        self::assertSame('VES', $document['currency_type_id']);
        self::assertSame($decimal::represent($remainingNet), $document['effective_total_value']);
        self::assertSame($decimal::represent($remainingIva), $document['effective_total_igv']);
        self::assertSame($decimal::represent($remainingTaxed), $document['effective_total_taxed']);

        \Illuminate\Http\Request::macro('validate', function ($rules) {
            return validator($this->all(), $rules)->validate();
        });
        $controller = new \App\Http\Controllers\Tenant\IvaRetentionController();
        $proposal = static function (array $fields) use ($controller, $invoice): array {
            $request = \Illuminate\Http\Request::create('/documents/retention/proposal', 'POST', $fields + [
                'document_id' => $invoice->id, 'date' => '2026-09-10', 'currency_type_id' => 'VES',
                'total_value' => '200', 'total_taxed' => '200', 'total_igv' => '32',
            ]);
            return $controller->proposal($request)['data'];
        };
        $iva = $proposal(['tax_kind' => 'IVA']);
        $islrInput = [
            'tax_kind' => 'ISLR', 'concept_id' => '055', 'percentage' => '3',
            'exchange_rate' => '1',
            'fiscal_context' => ['concept_basis' => 'Concepto de prueba', 'evidence_reference' => 'Soporte de prueba'],
        ];
        $islr = $proposal($islrInput + ['base_mode' => 'full_net']);
        self::assertTrue($iva['eligible']);
        self::assertTrue($islr['eligible']);
        self::assertTrue($decimal::read($iva, 'iva_amount')->isEqualTo($remainingIva));
        self::assertTrue($decimal::read($iva, 'taxable_base')->isEqualTo($remainingTaxed));
        self::assertTrue($decimal::read($iva, 'amount')->isEqualTo($remainingIva->multipliedBy('0.75')));
        self::assertTrue($decimal::read($islr, 'base')->isEqualTo($remainingNet));
        self::assertTrue($decimal::read($islr, 'voucher_amount')->isEqualTo($remainingNet->multipliedBy('0.03')));
        try {
            $proposal($islrInput + ['base_mode' => 'declared', 'base' => '200']);
            self::fail('La base original supera el saldo fiscal después de la nota de crédito.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('base', $error->errors());
        }
    }

    public function test_note_context_and_series_preserve_bigint_branch_ids_on_postgresql(): void
    {
        [$db, , , $invoice] = $this->noteFixture();
        $controller = new \App\Http\Controllers\Tenant\NoteController();
        $small = $controller->record($invoice->id);
        self::assertSame($invoice->establishment_id, $small['establishment_id']);
        self::assertIsInt($small['establishment_id']);

        $branches = ['9007199254740992', '9007199254740993'];
        $seriesIds = ['9007199254741002', '9007199254741003'];
        foreach ($branches as $index => $branch) {
            $db->table('establishments')->insert([
                'id' => $branch, 'description' => 'Sucursal de prueba '.$index, 'country_id' => 'VE',
                'department_id' => '14', 'province_id' => '0229', 'district_id' => '000619',
                'address' => 'Test', 'telephone' => '04121234567', 'code' => '010'.$index,
            ]);
            $db->table('series')->insert([
                'id' => $seriesIds[$index], 'establishment_id' => $branch, 'document_type_id' => '07',
                'number' => 'FN0'.$index,
            ]);
        }
        $db->table('documents')->where('id', $invoice->id)->update(['establishment_id' => $branches[1]]);

        $catalog = \App\Models\Tenant\Series::whereIn('id', $seriesIds)->orderBy('id')->get()
            ->map(static fn ($row) => $row->getCollectionData())->all();
        $payload = json_decode(json_encode([
            'record' => $controller->record($invoice->id), 'series' => $catalog,
        ], JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($branches[1], $payload['record']['establishment_id']);
        self::assertSame($branches, array_column($payload['series'], 'establishment_id'));
        self::assertSame($seriesIds, array_column($payload['series'], 'id'));
    }

    public function test_note_adjustments_persist_fractional_fund_and_igtf_amounts(): void
    {
        [$db,$app,$input,$invoice]=$this->noteFixture(true);
        $note=(new Facturalo)->forWebDocuments()->save($this->noteInput($input,$invoice,'07','09',[[
            'affected_document_item_id'=>$invoice->items->first()->id,'adjustment_amount'=>'23.20']]))->getDocument()->fresh();
        $fund=$invoice->guarantee_fund()->create(['base'=>'100','percentage'=>'10','amount'=>'10']);
        $request=['kind'=>'fund','source_id'=>$fund->id,'operation_key'=>(string)Str::uuid(),'amount'=>'0.50','reason'=>'Prueba de centavos'];
        $adjustment=NoteAdjustment::register($note,$request);
        self::assertSame('0.5',\App\Support\Exact\Decimal::represent($adjustment->exactValue('amount')));
        self::assertSame('9.5',\App\Support\Exact\Decimal::represent($invoice->fresh()->exactGuaranteeAmount()));
        $payment=$invoice->payments->whereNull('receipt_parent_id')->first();
        $tax=$invoice->taxes()->where('tax_kind','IGTF')->where('document_payment_id',$payment->id)->firstOrFail();
        $igtf=NoteAdjustment::register($note,['kind'=>'IGTF','source_id'=>$tax->id,'operation_key'=>(string)Str::uuid(),'amount'=>'0.50','reason'=>'Prueba de centavos']);
        self::assertSame('0.5',\App\Support\Exact\Decimal::represent($igtf->exactValue('amount')));
        self::assertSame('blocked',$igtf->status);
    }

    public function test_free_form_note_default_fund_adjustment_preserves_exact_fraction(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $input['payment_condition_id']='02'; $input['payments']=[];
        $invoice=(new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh();
        $note=(new Facturalo)->forWebDocuments()->save($this->noteInput($input,$invoice,'07','09',[[
            'affected_document_item_id'=>$invoice->items->first()->id,'adjustment_amount'=>'23.20']]))->getDocument()->fresh();
        $fund=$invoice->guarantee_fund()->create(['base'=>'100','percentage'=>'1','amount'=>\App\Support\Exact\Decimal::of('1/3')]);
        $adjustment=NoteAdjustment::register($note,['kind'=>'fund','source_id'=>$fund->id,'operation_key'=>(string)Str::uuid(),'reason'=>'Ajuste exacto']);
        self::assertSame(['n'=>'1','d'=>'3'],\App\Support\Exact\Decimal::fraction($adjustment->exactValue('amount')));
        self::assertTrue($invoice->fresh()->exactGuaranteeAmount()->isZero());
    }

    public function test_note_retention_reversal_detects_subprecision_remaining_base(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $input['payment_condition_id']='02'; $input['payments']=[];
        $invoice=(new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh();
        $note=(new Facturalo)->forWebDocuments()->save($this->noteInput($input,$invoice,'07','09',[[
            'affected_document_item_id'=>$invoice->items->first()->id,'adjustment_amount'=>'23.20']]))->getDocument()->fresh();
        foreach ([[$invoice,'1/3000000000000'],[$note,'0']] as [$document,$remaining]) {
            $snapshot=$document->calculation_snapshot;
            if (is_string($snapshot)) $snapshot=json_decode($snapshot,true,512,JSON_THROW_ON_ERROR);
            foreach (['total_igv','total_value'] as $field) $snapshot['values'][$field]=\App\Support\Exact\Decimal::fraction($remaining);
            $db->table('documents')->where('id',$document->id)->update(['calculation_snapshot'=>json_encode($snapshot)]);
        }
        foreach (['IVA','ISLR'] as $kind) {
            $retention=$db->table('document_received_retentions')->insertGetId(['document_id'=>$invoice->id,'tax_kind'=>$kind,
                'voucher_number'=>'QA-'.$kind,'voucher_date'=>'2026-09-10','agent_id'=>$input['customer_id'],'agent'=>'{}',
                'agent_number'=>'V-12345678-9','currency_type_id'=>'VES','exchange_rate'=>'1','application_status'=>'reversed']);
            $db->table('document_note_adjustments')->insert(['document_id'=>$note->id,'affected_document_id'=>$invoice->id,
                'kind'=>$kind,'retention_id'=>$retention,'amount'=>'0','status'=>'pending','reason'=>'Prueba',
                'request'=>'{}','context'=>'{}','operation_key'=>(string)Str::uuid(),'request_hash'=>str_repeat('a',64),'actor_id'=>$input['user_id']]);
        }
        NoteAdjustment::resumeInvoice($invoice->fresh());
        self::assertSame(['awaiting_support','awaiting_support'],$db->table('document_note_adjustments')->orderBy('id')->pluck('status')->all());
        $snapshot=$invoice->fresh()->calculation_snapshot;
        foreach (['total_igv','total_value'] as $field) $snapshot['values'][$field]=\App\Support\Exact\Decimal::fraction('0');
        $db->table('documents')->where('id',$invoice->id)->update(['calculation_snapshot'=>json_encode($snapshot)]);
        $db->table('document_note_adjustments')->update(['status'=>'pending']);
        NoteAdjustment::resumeInvoice($invoice->fresh());
        self::assertSame(['applied','applied'],$db->table('document_note_adjustments')->orderBy('id')->pluck('status')->all());
        // A previously loaded invoice must not restore a base already removed by another operation.
        $db->table('document_note_adjustments')->update(['status'=>'pending']);
        NoteAdjustment::resumeInvoice($invoice);
        self::assertSame(['applied','applied'],$db->table('document_note_adjustments')->orderBy('id')->pluck('status')->all());
        // MySQL repeatable-read may have an earlier snapshot of credits in the outer transaction.
        foreach ([[$invoice,'1'],[$note,'0']] as [$document,$value]) {
            $snapshot=$document->fresh()->calculation_snapshot;
            foreach (['total_igv','total_value'] as $field) $snapshot['values'][$field]=\App\Support\Exact\Decimal::fraction($value);
            $db->table('documents')->where('id',$document->id)->update(['calculation_snapshot'=>json_encode($snapshot)]);
        }
        $db->table('document_note_adjustments')->update(['status'=>'pending']);
        config(['database.connections.sab_note_concurrent'=>$db->getConfig()]);
        $other=\Illuminate\Support\Facades\DB::connection('sab_note_concurrent');
        $db->beginTransaction();
        try {
            $stale=Document::findOrFail($invoice->id);
            self::assertTrue(FiscalNote::remainingExact($stale,'total_igv')->isEqualTo('1'));
            $snapshot=$note->fresh()->calculation_snapshot;
            foreach (['total_igv','total_value'] as $field) $snapshot['values'][$field]=\App\Support\Exact\Decimal::fraction('1');
            $other->table('documents')->where('id',$note->id)->update(['calculation_snapshot'=>json_encode($snapshot)]);
            NoteAdjustment::resumeInvoice($stale);
            self::assertSame(['applied','applied'],$db->table('document_note_adjustments')->orderBy('id')->pluck('status')->all());
        } finally {
            $db->rollBack();
            \Illuminate\Support\Facades\DB::purge('sab_note_concurrent');
        }
    }

    public function test_free_form_full_credit_preserves_subprecision_remaining_fractions(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $input['payment_condition_id']='02'; $input['payments']=[];
        $input['items'][0]['quantity']='0.000000000001';
        $input['items'][0]['quantity_input']=['amount'=>'0.000000000001','unit_price'=>'3'];
        $input['items'][0]['unit_price']='0.1';
        $input['items'][0]['affectation_igv_type_id']='20';
        $invoice=(new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh();
        $line=$invoice->items->first();
        self::assertSame(['n'=>'1','d'=>'3000000000000'],\App\Support\Exact\Decimal::fraction($line->exactValue('quantity')));
        self::assertSame(['n'=>'1','d'=>'30000000000000'],\App\Support\Exact\Decimal::fraction($invoice->exactValue('total')));
        $context=FiscalNote::context($invoice);
        self::assertSame('0',$context['credit_available']);
        self::assertSame(['n'=>'1','d'=>'30000000000000'],$context['calculation_snapshot']['values']['credit_available']);
        $data=$this->noteInput($input,$invoice,'07','01',[]);
        $note=(new Facturalo)->forWebDocuments()->save($data)->getDocument()->fresh();
        self::assertSame(\App\Support\Exact\Decimal::fraction($invoice->exactValue('total')),\App\Support\Exact\Decimal::fraction($note->exactValue('total')));
        self::assertSame(\App\Support\Exact\Decimal::fraction($line->exactValue('quantity')),\App\Support\Exact\Decimal::fraction($note->items->first()->exactValue('inventory_quantity')));
        self::assertSame($note->id,(new Facturalo)->forWebDocuments()->save($data)->getDocument()->id);
        $duplicate=$this->noteInput($input,$invoice->fresh(),'07','01',[]);
        try { (new Facturalo)->forWebDocuments()->save($duplicate); self::fail('No debe anular dos veces la misma fracción.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('items',$error->errors()); }
    }

    public function test_free_form_full_credit_restores_fractional_invoice_lots(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $input['payment_condition_id']='02'; $input['payments']=[];
        $input['items'][0]['quantity']='0.333333333333';
        $input['items'][0]['quantity_input']=['amount'=>'1','unit_price'=>'3'];
        $itemId=$input['items'][0]['item_id']; $warehouse=$input['items'][0]['warehouse_id'];
        foreach (['array','single'] as $kind) {
            $lotId=$db->table('item_lots_group')->insertGetId(['item_id'=>$itemId,'code'=>'RETURN-'.$kind,'quantity'=>'1','date_of_due'=>'2027-11-30']);
            $case=$input; $case['external_id']=(string)Str::uuid();
            $case['items'][0]['item']['IdLoteSelected']=$kind==='single' ? $lotId : [['id'=>$lotId,'compromise_quantity'=>'0.333333333333']];
            $stockBefore=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$itemId)->where('warehouse_id',$warehouse)->firstOrFail()->exactValue('stock');
            $invoice=$db->transaction(fn()=>(new Facturalo)->forWebDocuments()->save($case)->getDocument()->fresh());
            self::assertSame('2/3',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($lotId)->exactValue('quantity'));
            $context=FiscalNote::context($invoice);
            self::assertSame('0.333333333333',$context['items'][0]['lots'][0]['quantity']);
            $manual=\App\Services\Fiscal\FiscalNoteInventory::snapshot($invoice->items->first(),\App\Support\Exact\Decimal::of('1/3'),
                ['lot_returns'=>[['id'=>$lotId,'compromise_quantity'=>'0.333333333333','compromise_quantity_exact'=>['n'=>'999','d'=>'1']]]]);
            self::assertSame('1/3',(string)\App\Support\Exact\Decimal::of($manual['IdLoteSelected'][0]['compromise_quantity_exact']));
            try {
                \App\Services\Fiscal\FiscalNoteInventory::snapshot($invoice->items->first(),\App\Support\Exact\Decimal::of('1/3'),
                    ['lot_returns'=>[['id'=>$lotId,'compromise_quantity'=>'0.333333333334']]]);
                self::fail('No se debe devolver más de lo disponible en el lote.');
            } catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('items.lot_returns',$error->errors()); }
            $data=$this->noteInput($case,$invoice,'07','01',[]);
            $note=$db->transaction(fn()=>(new Facturalo)->forWebDocuments()->save($data)->getDocument()->fresh());
            self::assertSame('1/3',(string)$note->items->first()->exactValue('inventory_quantity'));
            self::assertSame('1/3',(string)\App\Support\Exact\Decimal::of($note->items->first()->item->IdLoteSelected[0]->compromise_quantity_exact));
            self::assertSame('1',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($lotId)->exactValue('quantity'));
            self::assertTrue(\Modules\Inventory\Models\ItemWarehouse::where('item_id',$itemId)->where('warehouse_id',$warehouse)->firstOrFail()->exactValue('stock')->isEqualTo($stockBefore));
        }
    }

    public function test_free_form_partial_credits_preserve_fractional_lot_remainder(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $input['payment_condition_id']='02'; $input['payments']=[];
        $input['items'][0]['quantity']='0.333333333333';
        $input['items'][0]['quantity_input']=['amount'=>'1','unit_price'=>'3'];
        $input['items'][0]['unit_price']='3'; $input['items'][0]['affectation_igv_type_id']='20';
        $itemId=$input['items'][0]['item_id']; $warehouse=$input['items'][0]['warehouse_id'];
        $lotId=$db->table('item_lots_group')->insertGetId(['item_id'=>$itemId,'code'=>'PARTIAL-LOT','quantity'=>'1','date_of_due'=>'2027-11-30']);
        $input['items'][0]['item']['IdLoteSelected']=[['id'=>$lotId,'compromise_quantity'=>'0.333333333333']];
        $stockBefore=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$itemId)->where('warehouse_id',$warehouse)->firstOrFail()->exactValue('stock');
        $invoice=$db->transaction(fn()=>(new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh());
        $source=$invoice->items->first(); self::assertSame('1/3',(string)$source->exactValue('quantity'));
        foreach (['0.1','0.233333333333'] as $quantity) {
            $data=$this->noteInput($input,$invoice->fresh(),'07','07',[[
                'affected_document_item_id'=>$source->id,'quantity'=>$quantity,
                'lot_returns'=>[['id'=>$lotId,'compromise_quantity'=>$quantity,'compromise_quantity_exact'=>['n'=>'99','d'=>'1']]]]]);
            $note=$db->transaction(fn()=>(new Facturalo)->forWebDocuments()->save($data)->getDocument()->fresh());
            self::assertSame($quantity,\App\Support\Exact\Decimal::represent($note->items->first()->exactValue('inventory_quantity')));
            self::assertSame($quantity,\App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::of($note->items->first()->item->IdLoteSelected[0]->compromise_quantity_exact)));
        }
        $remaining=FiscalNote::entitlements($invoice->fresh())[$source->id];
        self::assertSame('1/3000000000000',(string)\App\Support\Exact\Decimal::read($remaining,'remaining_quantity'));
        $context=FiscalNote::context($invoice->fresh());
        self::assertSame('0',$context['items'][0]['lots'][0]['quantity']);
        self::assertSame(['n'=>'1','d'=>'3000000000000'],$context['items'][0]['lots'][0]['calculation_snapshot']['values']['quantity']);
        $last=$this->noteInput($input,$invoice->fresh(),'07','01',[]);
        $note=$db->transaction(fn()=>(new Facturalo)->forWebDocuments()->save($last)->getDocument()->fresh());
        self::assertSame('1/3000000000000',(string)$note->items->first()->exactValue('inventory_quantity'));
        self::assertSame('1',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($lotId)->exactValue('quantity'));
        self::assertTrue(\Modules\Inventory\Models\ItemWarehouse::where('item_id',$itemId)->where('warehouse_id',$warehouse)->firstOrFail()->exactValue('stock')->isEqualTo($stockBefore));
        $again=$this->noteInput($input,$invoice->fresh(),'07','01',[]);
        try { $db->transaction(fn()=>(new Facturalo)->forWebDocuments()->save($again)); self::fail('No debe devolverse dos veces el mismo lote.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('items',$error->errors()); }
    }

    public function test_free_form_partial_credit_splits_fraction_across_original_lots(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $input['payment_condition_id']='02'; $input['payments']=[];
        $line=&$input['items'][0]; $line['quantity']='0.333333333333';
        $line['quantity_input']=['amount'=>'1','unit_price'=>'3']; $line['unit_price']='3'; $line['affectation_igv_type_id']='20';
        $itemId=$line['item_id']; $warehouse=$line['warehouse_id'];
        $lotA=$db->table('item_lots_group')->insertGetId(['item_id'=>$itemId,'code'=>'PARTIAL-A','quantity'=>'1','date_of_due'=>'2027-11-30']);
        $lotB=$db->table('item_lots_group')->insertGetId(['item_id'=>$itemId,'code'=>'PARTIAL-B','quantity'=>'1','date_of_due'=>'2027-11-30']);
        $line['item']['IdLoteSelected']=[['id'=>$lotA,'compromise_quantity'=>'0.1'],['id'=>$lotB,'compromise_quantity'=>'0.233333333333']];
        unset($line);
        $stockBefore=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$itemId)->where('warehouse_id',$warehouse)->firstOrFail()->exactValue('stock');
        $invoice=$db->transaction(fn()=>(new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh());
        $source=$invoice->items->first();
        self::assertSame('7/30',(string)\App\Support\Exact\Decimal::of($source->item->IdLoteSelected[1]->compromise_quantity_exact));
        $data=$this->noteInput($input,$invoice,'07','07',[[
            'affected_document_item_id'=>$source->id,'quantity'=>'0.2',
            'lot_returns'=>[['id'=>$lotA,'compromise_quantity'=>'0.1'],['id'=>$lotB,'compromise_quantity'=>'0.1']]]]);
        $note=$db->transaction(fn()=>(new Facturalo)->forWebDocuments()->save($data)->getDocument()->fresh());
        self::assertSame('1/5',(string)$note->items->first()->exactValue('inventory_quantity'));
        self::assertSame('1',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($lotA)->exactValue('quantity'));
        self::assertSame('13/15',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($lotB)->exactValue('quantity'));
        $context=FiscalNote::context($invoice->fresh());
        self::assertSame('0.133333333333',$context['items'][0]['lots'][0]['quantity']);
        $remaining=$this->noteInput($input,$invoice->fresh(),'07','01',[]);
        $last=$db->transaction(fn()=>(new Facturalo)->forWebDocuments()->save($remaining)->getDocument()->fresh());
        self::assertSame('2/15',(string)$last->items->first()->exactValue('inventory_quantity'));
        self::assertSame('1',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($lotB)->exactValue('quantity'));
        self::assertTrue(\Modules\Inventory\Models\ItemWarehouse::where('item_id',$itemId)->where('warehouse_id',$warehouse)->firstOrFail()->exactValue('stock')->isEqualTo($stockBefore));
    }

    public function test_api_credit_return_preserves_exact_original_lot_allocations(): void
    {
        [$db, $app, $input] = $this->bootHkaFixture(false);
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $input['payment_condition_id']='02'; $input['payments']=[];
        $line=&$input['items'][0]; $line['quantity']='0.333333333333';
        $line['quantity_input']=['amount'=>'1','unit_price'=>'3']; $line['unit_price']='3'; $line['affectation_igv_type_id']='20';
        $itemId=$line['item_id']; $warehouse=$line['warehouse_id'];
        $lotA=$db->table('item_lots_group')->insertGetId(['item_id'=>$itemId,'code'=>'API-PARTIAL-A','quantity'=>'1','date_of_due'=>'2027-11-30']);
        $lotB=$db->table('item_lots_group')->insertGetId(['item_id'=>$itemId,'code'=>'API-PARTIAL-B','quantity'=>'1','date_of_due'=>'2027-11-30']);
        $line['item']['IdLoteSelected']=[['id'=>$lotA,'compromise_quantity'=>'0.1'],['id'=>$lotB,'compromise_quantity'=>'0.233333333333']];
        unset($line);
        $stockBefore=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$itemId)->where('warehouse_id',$warehouse)->firstOrFail()->exactValue('stock');
        $invoice=$db->transaction(fn()=>(new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh());
        $source=$invoice->items->first();
        $payload=\App\CoreFacturalo\Requests\Inputs\Transform\DocumentWebTransform::transform(array_replace($input,[
            'date_of_issue'=>now()->toDateString(),'date_of_due'=>now()->toDateString(),
            'operation_type_id'=>'0101','payment_condition_id'=>'02','payments'=>[],
        ]));
        $payload['datos_del_cliente_o_receptor']['codigo_pais']='VE';
        $payload['datos_del_cliente_o_receptor']['ubigeo']='000619';
        $payload['source_module']='VENDEYA'; $payload['codigo_tipo_documento']='07'; $payload['serie_documento']='FC01';
        $payload['codigo_tipo_nota']='07'; $payload['motivo_o_sustento_de_nota']='Devolución parcial API';
        $payload['note_creation_key']=(string) \Illuminate\Support\Str::uuid();
        $payload['documento_afectado']=['external_id'=>$invoice->external_id];
        $payload['items'][0]['cantidad']='0.2';
        $payload['items'][0]['affected_document_item_id']=(string) $source->id;
        $payload['items'][0]['lot_returns']=[['id'=>$lotA,'compromise_quantity'=>'0.1'],['id'=>$lotB,'compromise_quantity'=>'0.1']];
        $request=\Illuminate\Http\Request::create('/api/documents','POST',$payload);
        $request->attributes->set('sab.strict_input_exceptions',true);
        $operation=(new \App\CoreFacturalo\InputRequest())->handle($request,
            fn (\Illuminate\Http\Request $request)=>(new \App\Services\Sellnow\ApiSaleCreation())->persistDocument($request->all()),'document','api');
        $note=$operation->getDocument()->fresh();
        self::assertSame('1/5',(string)$note->items->first()->exactValue('inventory_quantity'));
        self::assertSame('1',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($lotA)->exactValue('quantity'));
        self::assertSame('13/15',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($lotB)->exactValue('quantity'));
        self::assertTrue(\Modules\Inventory\Models\ItemWarehouse::where('item_id',$itemId)->where('warehouse_id',$warehouse)->firstOrFail()->exactValue('stock')->isEqualTo($stockBefore->minus('2/15')));
    }

    public function test_free_form_note_pdf_formats_show_frozen_lots_and_amounts(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $input['payment_condition_id']='02'; $input['payments']=[];
        $line=&$input['items'][0]; $line['quantity']='0.333333333333'; $line['quantity_input']=['amount'=>'1','unit_price'=>'3'];
        $line['unit_price']='3'; $line['affectation_igv_type_id']='20';
        $itemId=$line['item_id'];
        $lotA=$db->table('item_lots_group')->insertGetId(['item_id'=>$itemId,'code'=>'PDF-LOT-ONE','quantity'=>'1','date_of_due'=>'2027-11-30']);
        $lotB=$db->table('item_lots_group')->insertGetId(['item_id'=>$itemId,'code'=>'PDF-LOT-TWO','quantity'=>'1','date_of_due'=>'2027-11-30']);
        $line['item']['IdLoteSelected']=[['id'=>$lotA,'compromise_quantity'=>'0.1'],['id'=>$lotB,'compromise_quantity'=>'0.233333333333']];
        unset($line);
        $invoice=$db->transaction(fn()=>(new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh());
        $note=$db->transaction(fn()=>(new Facturalo)->forWebDocuments()->save($this->noteInput($input,$invoice,'07','01',[]))->getDocument()->fresh());
        $db->table('item_lots_group')->whereIn('id',[$lotA,$lotB])->update(['code'=>'CAMBIADO']);
        config(['view.compiled'=>sys_get_temp_dir().'/'.$this->database.'/note-lot-views',
            'view.paths'=>[base_path('resources/views')], 'filesystems.disks.public'=>['driver'=>'local','root'=>sys_get_temp_dir()]]);
        mkdir(config('view.compiled'),0777,true); $app->register(\Illuminate\View\ViewServiceProvider::class);
        $formats=[];
        foreach (['default','Plantilla_personalizable','modern-2027','marca_de_agua'] as $theme) foreach (['a4','a5','ticket'] as $format) $formats[]=[$theme,$format];
        $formats[]=['default','ticket_58'];
        $dir=getenv('SAB_NOTE_LOT_PDF_DIR') ?: sys_get_temp_dir().'/sab-note-lots-'.bin2hex(random_bytes(5));
        if (!is_dir($dir)) mkdir($dir,0700,true);
        try {
            foreach ($formats as [$theme,$format]) {
                $db->table('establishments')->where('id',$input['establishment_id'])->update(['template_pdf'=>$theme,'template_ticket_pdf'=>$theme]);
                $print=$note->fresh();
                $customer=(array)$print->customer; $customer['identity_document_type']=['description'=>'Venezolano'];
                foreach (['district_id','province_id','department_id'] as $field) $customer[$field]='-';
                $print->customer=$customer;
                $establishment=(array)$print->establishment;
                foreach (['district_id','province_id','department_id'] as $field) $establishment[$field]='-';
                $print->establishment=$establishment;
                $pdf=(new Facturalo())->createPdf($print,'credit',$format,'string');
                self::assertStringStartsWith('%PDF-',$pdf,$theme.'/'.$format);
                $name=$theme.'-'.$format; $pdfPath=$dir.'/'.$name.'.pdf'; $textPath=$dir.'/'.$name.'.txt'; file_put_contents($pdfPath,$pdf);
                $process=proc_open(['pdftotext','-layout',$pdfPath,$textPath],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
                self::assertIsResource($process); foreach ($pipes as $pipe) fclose($pipe); self::assertSame(0,proc_close($process));
                $text=file_get_contents($textPath);
                self::assertSame(1,substr_count($text,"\f"),$theme.'/'.$format.' debe caber en una página para esta muestra.');
                self::assertStringContainsString('PDF-LOT-ONE',$text,$theme.'/'.$format);
                self::assertStringContainsString('PDF-LOT-TWO',$text,$theme.'/'.$format);
                self::assertStringNotContainsString('CAMBIADO',$text,$theme.'/'.$format);
                self::assertStringNotContainsString('IGV',$text,$theme.'/'.$format);
                self::assertStringContainsString('1.00',$text,$theme.'/'.$format);
            }
            $responseFactory=$this->createMock(\Illuminate\Contracts\Routing\ResponseFactory::class);
            $responseFactory->method('make')->willReturnCallback(fn($content,$status,$headers)=>new \Illuminate\Http\Response($content,$status,$headers));
            $app->instance(\Illuminate\Contracts\Routing\ResponseFactory::class,$responseFactory);
            foreach (['ticket','ticket_58'] as $format) {
                $preview=(new Facturalo())->previewPdf($print,'credit',$format);
                self::assertSame(200,$preview->getStatusCode()); self::assertStringStartsWith('%PDF-',$preview->getContent());
                $pdfPath=$dir.'/preview-'.$format.'.pdf'; $textPath=$dir.'/preview-'.$format.'.txt'; file_put_contents($pdfPath,$preview->getContent());
                $process=proc_open(['pdftotext','-layout',$pdfPath,$textPath],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
                self::assertIsResource($process); foreach ($pipes as $pipe) fclose($pipe); self::assertSame(0,proc_close($process));
                $text=file_get_contents($textPath);
                self::assertSame(1,substr_count($text,"\f"));
                self::assertStringContainsString('PDF-LOT-ONE',$text); self::assertStringContainsString('PDF-LOT-TWO',$text);
            }
            $longCode='PDF-LONG<&'.str_repeat('0123456789',21);
            $longLot=$db->table('item_lots_group')->insertGetId(['item_id'=>$itemId,'code'=>$longCode,'quantity'=>'1','date_of_due'=>'2027-11-30']);
            $longInput=$input; $longInput['external_id']=(string)Str::uuid(); $longInput['items'][0]['item']['IdLoteSelected']=$longLot;
            $longInvoice=$db->transaction(fn()=>(new Facturalo)->forWebDocuments()->save($longInput)->getDocument()->fresh());
            $longNote=$db->transaction(fn()=>(new Facturalo)->forWebDocuments()->save($this->noteInput($longInput,$longInvoice,'07','01',[]))->getDocument()->fresh());
            foreach ([['default','a4'],['default','a5'],['default','ticket'],['default','ticket_58'],['modern-2027','ticket']] as [$theme,$format]) {
                $db->table('establishments')->where('id',$input['establishment_id'])->update(['template_pdf'=>$theme,'template_ticket_pdf'=>$theme]);
                $longPrint=$longNote->fresh(); $longPrint->customer=$customer; $longPrint->establishment=$establishment;
                $pdf=(new Facturalo())->createPdf($longPrint,'credit',$format,'string');
                $pdfPath=$dir.'/long-'.$theme.'-'.$format.'.pdf'; $textPath=$dir.'/long-'.$theme.'-'.$format.'.txt'; file_put_contents($pdfPath,$pdf);
                $process=proc_open(['pdftotext','-raw',$pdfPath,$textPath],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
                self::assertIsResource($process); foreach ($pipes as $pipe) fclose($pipe); self::assertSame(0,proc_close($process));
                $text=file_get_contents($textPath);
                self::assertStringContainsString($longCode,preg_replace('/\s+/u','',$text),$theme.'/'.$format.' conserva el código completo.');
                self::assertStringContainsString('1.00',$text,$theme.'/'.$format.' conserva el total.');
                if (in_array($format,['ticket','ticket_58'],true)) self::assertSame(1,substr_count($text,"\f"),$theme.'/'.$format.' no debe cortar el ticket de muestra.');
            }
        } finally {
            if (!getenv('SAB_NOTE_LOT_PDF_DIR')) (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($dir);
        }
    }

    public function test_free_form_credit_limit_uses_exact_fractions_at_large_amounts(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $input['payment_condition_id']='02'; $input['payments']=[];
        $invoice=(new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh();
        $data=$this->noteInput($input,$invoice,'07','04',[[
            'affected_document_item_id'=>$invoice->items->first()->id,'adjustment_amount'=>'11.60']]);
        $note=(new Facturalo)->forWebDocuments()->save($data)->getDocument()->fresh();
        $setTotal=function (Document $document,string $total) use ($db) {
            $snapshot=$document->calculation_snapshot;
            if (is_string($snapshot)) $snapshot=json_decode($snapshot,true,512,JSON_THROW_ON_ERROR);
            $snapshot['values']['total']=\App\Support\Exact\Decimal::fraction($total);
            $db->table('documents')->where('id',$document->id)->update(['total'=>$total,'calculation_snapshot'=>json_encode($snapshot)]);
        };
        $setTotal($invoice,'1000000000000000');
        $setTotal($note,'1000000000000000.005');
        FiscalNote::finish($note->fresh());
        $setTotal($note,'1000000000000000.005000000001');
        try { FiscalNote::finish($note->fresh()); self::fail('NC fuera del límite exacto'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('total',$error->errors()); }
    }

    public function test_free_form_debit_service_uses_only_adjustment_amount(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $input['payment_condition_id']='02'; $input['payments']=[];
        $invoice=(new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh();
        $service=\App\Models\Tenant\Item::where('unit_type_id','SERV')->firstOrFail();
        foreach (['01','03'] as $reason) {
            $row=['item_id'=>$service->id,'adjustment_amount'=>'11.60','affectation_igv_type_id'=>'10',
                'price_type_id'=>'01','additional_information'=>'','currency_type_id'=>'VES','exchange_rate_sale'=>'1',
                'quantity_input'=>['amount'=>'2','unit_price'=>'1'],
                'price_input'=>['amount'=>'20','currency_type_id'=>'VES'],
                'calculation_snapshot'=>\App\Support\Exact\Decimal::snapshot(['quantity'=>'2','unit_price'=>'20'])];
            $data=$this->noteInput($input,$invoice,'08',$reason,[$row]);
            $note=(new Facturalo)->forWebDocuments()->save($data)->getDocument()->fresh();
            self::assertSame('1',\App\Support\Exact\Decimal::represent($note->items->first()->exactValue('quantity')));
            self::assertSame('11.6',\App\Support\Exact\Decimal::represent($note->exactValue('total')));
            self::assertSame('0',\App\Support\Exact\Decimal::represent($note->items->first()->exactValue('inventory_quantity')));
        }
    }

    /** @dataProvider debitServiceAdjustments */
    public function test_debit_service_uses_the_exact_adjustment_operand_on_postgresql(
        string $currency,
        string $reason,
        string $amount,
        ?array $fraction,
        string $affectation,
        string $expected,
        string $base,
        string $iva,
    ): void {
        [$db, , $input] = $this->bootHkaFixture(false);
        $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
        $db->table('configurations')->update(['auto_send_hka' => false]);
        if ($currency === 'USD') {
            // Los documentos USD solo se admiten en tenants con el esquema anterior.
            $db->statement('ALTER TABLE documents DROP CONSTRAINT documents_currency_ves_check');
            $db->statement('ALTER TABLE configurations DROP COLUMN sales_documents_ves');
            self::assertFalse(\App\Services\SalesDocumentCurrency::usesBolivars());
        }
        $input['currency_type_id'] = $currency;
        $input['exchange_rate_sale'] = '3';
        $input['payment_condition_id'] = '02';
        $input['payments'] = [];
        $invoice = (new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
        $service = \App\Models\Tenant\Item::where('unit_type_id', 'SERV')->firstOrFail();
        $row = [
            'item_id' => $service->id, 'adjustment_amount' => $amount, 'adjustment_amount_exact' => $fraction,
            'affectation_igv_type_id' => $affectation, 'price_type_id' => '01', 'additional_information' => '',
            'currency_type_id' => $currency === 'VES' ? 'USD' : 'VES', 'exchange_rate_sale' => '99',
            'calculation_snapshot' => \App\Support\Exact\Decimal::snapshot(['total' => 999, 'unit_price' => 999]),
        ];
        $data = $this->noteInput($input, $invoice, '08', $reason, [$row]);
        $data['items'] = \App\CoreFacturalo\Requests\Inputs\DocumentInput::items($data);
        $note = (new Facturalo())->forWebDocuments()->save($data)->getDocument()->fresh();
        $line = $note->items()->firstOrFail();
        self::assertTrue($note->exactValue('total')->isEqualTo($expected));
        self::assertTrue($line->exactValue('unit_price')->isEqualTo($expected));
        self::assertTrue($line->exactValue('total_base_igv')->isEqualTo($base));
        self::assertTrue($line->exactValue('total_igv')->isEqualTo($iva));
        self::assertTrue($line->exactValue('quantity')->isEqualTo(1));
        self::assertTrue($line->exactValue('inventory_quantity')->isZero());
        self::assertSame('3.00000000', $note->exchange_rate_sale);
        self::assertSame($currency, $note->currency_type_id);
        self::assertCount(0, $note->payments);

        $bad = $this->noteInput($input, $invoice, '08', $reason, [array_replace($row, [
            'adjustment_amount_exact' => ['n' => '1', 'd' => '0'],
        ])]);
        try {
            (new Facturalo())->forWebDocuments()->save($bad);
            self::fail('La fracción con denominador cero se debe rechazar.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('items.0.adjustment_amount', $error->errors());
        }
        self::assertSame(1, $db->table('documents')->where('document_type_id', '08')->count());
    }

    public static function debitServiceAdjustments(): array
    {
        return [
            'varias unidades capturadas' => ['VES', '01', '30', ['n' => '30', 'd' => '1'], '20', '30', '30', '0'],
            'convertido a USD' => ['USD', '01', '0.333333333333', ['n' => '1', 'd' => '3'], '20', '1/3', '1/3', '0'],
            'edición con fracción desactualizada' => ['USD', '03', '0.50', ['n' => '1', 'd' => '3'], '20', '0.5', '0.5', '0'],
            'importe manual igual al decimal visible' => ['USD', '01', '0.333333333333', null, '20', '0.333333333333', '0.333333333333', '0'],
            'importe de servicio gravado' => ['VES', '03', '104.4', ['n' => '522', 'd' => '5'], '10', '104.4', '90', '14.4'],
            'el cargo pasa a integrar el importe de la unidad fiscal' => ['VES', '03', '126', ['n' => '126', 'd' => '1'], '10', '126', '3150/29', '504/29'],
            'fracción positiva menor a doce decimales' => ['USD', '01', '0', ['n' => '1', 'd' => '3000000000000'], '20', '1/3000000000000', '1/3000000000000', '0'],
            'fracción exacta en instalación VES' => ['VES', '01', '0.333333333333', ['n' => '1', 'd' => '3'], '20', '1/3', '1/3', '0'],
            'edición de fracción desactualizada en VES' => ['VES', '03', '0.50', ['n' => '1', 'd' => '3'], '20', '0.5', '0.5', '0'],
            'importe manual decimal en VES' => ['VES', '01', '0.333333333333', null, '20', '0.333333333333', '0.333333333333', '0'],
            'fracción mínima positiva en VES' => ['VES', '01', '0', ['n' => '1', 'd' => '3000000000000'], '20', '1/3000000000000', '1/3000000000000', '0'],
        ];
    }

    public function test_contingency_listing_matches_series_branch_type_and_user_scope(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $input['payments']=[]; $input['payment_condition_id']='02';
        $invoice=(new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh();
        $db->table('series')->where('establishment_id',$invoice->establishment_id)
            ->where('document_type_id','01')->where('number','FF01')->update(['contingency'=>true]);
        $branch=$db->table('establishments')->insertGetId(['description'=>'Sucursal de prueba','country_id'=>'VE',
            'department_id'=>'14','province_id'=>'0229','district_id'=>'000619','address'=>'Test','telephone'=>'04121234567','code'=>'0001']);
        $db->table('series')->insert(['establishment_id'=>$branch,'document_type_id'=>'01','number'=>'FF01','contingency'=>false]);
        $row=(array)$db->table('documents')->where('id',$invoice->id)->first(); unset($row['id']);
        $row['external_id']=(string)Str::uuid(); $row['establishment_id']=$branch; $row['number']=101;
        $row['filename']='synthetic-branch-contingency'; $row['unique_filename']='synthetic-branch-contingency';
        $branchInvoice=$db->table('documents')->insertGetId($row);
        $row['external_id']=(string)Str::uuid(); $row['establishment_id']=$invoice->establishment_id;
        $row['document_type_id']='08'; $row['number']=102; $row['filename']='synthetic-type-contingency';
        $row['unique_filename']='synthetic-type-contingency';
        $debit=$db->table('documents')->insertGetId($row);
        config(['tenant.items_per_page'=>20]);
        $controller=new \App\Http\Controllers\Tenant\ContingencyController();
        $request=\Illuminate\Http\Request::create('/contingencies/records','GET',['column'=>'number','value'=>'']);
        $ids=collect($controller->records($request)->resource->items())->pluck('id')->all();
        self::assertSame([$invoice->id],$ids,'Un código idéntico en otra sucursal o tipo no identifica la serie de contingencia.');
        $db->table('series')->insert(['establishment_id'=>$invoice->establishment_id,'document_type_id'=>'08','number'=>'FF01','contingency'=>true]);
        $ids=collect($controller->records($request)->resource->items())->pluck('id')->all();
        self::assertEqualsCanonicalizing([$invoice->id,$debit],$ids);
        self::assertNotContains($branchInvoice,$ids);
        $db->table('series')->insert(['establishment_id'=>$invoice->establishment_id,'document_type_id'=>'01','number'=>'','contingency'=>true]);
        $db->table('series')->insert(['establishment_id'=>$branch,'document_type_id'=>'01','number'=>'','contingency'=>false]);
        $row['external_id']=(string)Str::uuid(); $row['document_type_id']='01'; $row['series']=''; $row['number']=103;
        $row['filename']='synthetic-empty-contingency'; $row['unique_filename']='synthetic-empty-contingency';
        $empty=$db->table('documents')->insertGetId($row);
        $row['external_id']=(string)Str::uuid(); $row['establishment_id']=$branch; $row['number']=104;
        $row['filename']='synthetic-empty-normal'; $row['unique_filename']='synthetic-empty-normal';
        $normalEmpty=$db->table('documents')->insertGetId($row);
        $ids=collect($controller->records($request)->resource->items())->pluck('id')->all();
        self::assertEqualsCanonicalizing([$invoice->id,$debit,$empty],$ids);
        self::assertNotContains($normalEmpty,$ids);
        $otherUser=$db->table('users')->insertGetId(['name'=>'Otro vendedor','email'=>'other-seller@example.test',
            'password'=>'not-a-login-hash','type'=>'seller','establishment_id'=>$branch]);
        $db->table('documents')->where('id',$debit)->update(['user_id'=>$otherUser,'seller_id'=>null]);
        $seller=auth()->user(); $seller->type='seller';
        $ids=collect($controller->records($request)->resource->items())->pluck('id')->all();
        self::assertEqualsCanonicalizing([$invoice->id,$empty],$ids,'El alcance de usuario se mantiene tras aplicar el filtro de serie.');
        $byNumber=\Illuminate\Http\Request::create('/','GET',['column'=>'number','value'=>'100']);
        self::assertSame([$invoice->id],collect($controller->records($byNumber)->resource->items())->pluck('id')->all());
        $byDate=\Illuminate\Http\Request::create('/','GET',['column'=>'date_of_issue','value'=>'2026-09-10']);
        self::assertEqualsCanonicalizing([$invoice->id,$empty],collect($controller->records($byDate)->resource->items())->pluck('id')->all());
        try { $controller->records(\Illuminate\Http\Request::create('/','GET',['column'=>'customer_id'])); self::fail('Debe rechazarse un filtro no ofrecido.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('column',$error->errors()); }
    }

    public function test_free_form_paid_invoice_credit_balance_and_refund(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $http=$app->make(\Illuminate\Http\Client\Factory::class); $http->fake();
        $invoice=(new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh();
        $creditInput=$this->noteInput($input,$invoice,'07','09',[
            ['affected_document_item_id'=>$invoice->items->first()->id,'adjustment_amount'=>'23.20'],
        ]);
        $note=(new Facturalo)->forWebDocuments()->save($creditInput)->getDocument()->fresh();
        \App\Services\Fiscal\FreeFormControl::register($invoice,['control_number'=>'00-1','evidence_reference'=>'Factura física sintética']);
        self::assertFalse(NoteRefund::view($note)['can_create']);
        try { NoteRefund::eligible($note); self::fail('Sin control físico no se admite la devolución.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('note',$error->errors()); }
        \App\Services\Fiscal\FreeFormControl::register($note,['control_number'=>'00-2','evidence_reference'=>'Nota física sintética']);
        $invoice=$invoice->fresh(); $note=$note->fresh();
        self::assertSame('free_form',$note->fiscal_emission_mode);
        self::assertSame('-23.2',\App\Support\Exact\Decimal::represent($invoice->exactBalance()));
        self::assertSame('23.2',\App\Support\Exact\Decimal::represent(NoteRefund::available($note)));
        $batch=\App\Support\Exact\Receivables::balancesForDocuments([$invoice]);
        self::assertTrue($batch[$invoice->id]->isEqualTo($invoice->exactBalance()));
        $sql=\App\Services\Fiscal\FiscalNoteBalance::sql();
        self::assertTrue(\App\Support\Exact\Decimal::of($db->table('documents')->where('id',$invoice->id)
            ->selectRaw($sql.' AS outstanding')->value('outstanding'))->isEqualTo($invoice->exactBalance()));
        self::assertCount(0,\App\Support\Exact\Receivables::dataset(['source_type'=>'document'],true));
        $view=NoteRefund::view($note);
        self::assertTrue($view['can_create']);
        self::assertNull($view['block_reason']);
        $response=$note->emission->response;
        $mismatch=$response; $mismatch['free_form_control']['control_number']='00-999';
        $note->emission->response=$mismatch; $note->emission->save();
        try { NoteRefund::eligible($note->fresh()); self::fail('El control debe coincidir con el registro auditado.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('note',$error->errors()); }
        $note->emission->response=$response; $note->emission->save(); $note=$note->fresh();
        $count=$db->table('global_payments')->count();
        $bank=$db->table('bank_accounts')->insertGetId(['description'=>'Devolución sintética','number'=>'qa','bank_id'=>1,'currency_type_id'=>'VES','establishment_id'=>$invoice->establishment_id]);
        $db->table('payment_method_types')->where('id','04')->update(['bank_account_id'=>$bank,'is_active'=>1]);
        $request=['operation_key'=>(string)Str::uuid(),'component'=>'principal','document_payment_id'=>$invoice->payments->first()->id,
            'date_of_payment'=>'2026-09-10','payment_method_type_id'=>'04','payment_destination_id'=>$bank,
            'currency_type_id'=>'VES','original_amount'=>'23.20','exchange_rate'=>'1.00000000','exchange_rate_source'=>'manual'];
        try { (new NoteRefund)->register($note,array_replace($request,['original_amount'=>'23.21'])); self::fail('No puede devolver más de lo disponible.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('original_amount',$error->errors()); }
        $record=(new NoteRefund)->register($note,$request);
        self::assertSame($record->id,(new NoteRefund)->register($note,$request)->id);
        self::assertSame('free_form',$record->context['fiscal_emission_mode']);
        self::assertSame($note->control_number,$record->context['control_number']);
        self::assertSame('output',$record->global_payment->type_movement);
        self::assertSame($count+1,$db->table('global_payments')->count());
        self::assertSame('0',\App\Support\Exact\Decimal::represent($invoice->fresh()->exactBalance()));
        self::assertSame('0',\App\Support\Exact\Decimal::represent(NoteRefund::available($note->fresh())));
        $reverse=['operation_key'=>(string)Str::uuid(),'reason'=>'Reversión sintética'];
        (new NoteRefund)->reverse($note,$record,$reverse);
        (new NoteRefund)->reverse($note,$record,$reverse);
        self::assertSame(1,$db->table('document_note_refunds')->count());
        self::assertSame('-23.2',\App\Support\Exact\Decimal::represent($invoice->fresh()->exactBalance()));
        self::assertSame('23.2',\App\Support\Exact\Decimal::represent(NoteRefund::available($note->fresh())));
        $usdBank=$db->table('bank_accounts')->insertGetId(['description'=>'Devolución USD sintética','number'=>'qa-usd','bank_id'=>1,'currency_type_id'=>'USD','establishment_id'=>$invoice->establishment_id]);
        $db->table('payment_method_types')->where('id','04')->update(['bank_account_id'=>$usdBank]);
        $usd=array_replace($request,['operation_key'=>(string)Str::uuid(),'payment_destination_id'=>$usdBank,
            'currency_type_id'=>'USD','original_amount'=>'0.50','exchange_rate'=>'40.00000000']);
        $usdRecord=(new NoteRefund)->register($note->fresh(),$usd);
        self::assertSame('USD',$usdRecord->currency_type_id);
        self::assertSame('20',\App\Support\Exact\Decimal::represent($usdRecord->exactValue('amount')));
        self::assertSame('-3.2',\App\Support\Exact\Decimal::represent($invoice->fresh()->exactBalance()));
        self::assertSame('3.2',\App\Support\Exact\Decimal::represent(NoteRefund::available($note->fresh())));
        (new NoteRefund)->reverse($note->fresh(),$usdRecord,['operation_key'=>(string)Str::uuid(),'reason'=>'Reversión de prueba USD']);
        self::assertSame('-23.2',\App\Support\Exact\Decimal::represent($invoice->fresh()->exactBalance()));
        $http->assertNothingSent();
    }

    public function test_full_credit_mobile_refund_preserves_gross_receipt_and_both_change_outflows(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $bank=\App\Models\Tenant\BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),
            'description'=>'Pago Móvil devolución','number'=>'QA-mobile-refund','currency_type_id'=>'VES',
            'initial_balance'=>'0','status'=>1,'establishment_id'=>null]);
        $db->table('payment_method_types')->where('id','11')->update(['collection_kind'=>'account','currency_type_id'=>'VES',
            'is_active'=>1,'available_for_change'=>1,'bank_account_id'=>$bank->id]);
        $input['items'][0]['quantity']='1'; $input['items'][0]['unit_price']='250';
        $input['payments']=[['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'01','currency_type_id'=>'VES',
            'payment'=>'350','original_amount'=>'350','tendered_amount'=>'350','exchange_rate'=>'1','payment_destination_id'=>'cash',
            'operation_key'=>(string)Str::uuid(),'change_disbursements'=>[
                ['payment_method_type_id'=>'01','amount'=>'50'], ['payment_method_type_id'=>'11','amount'=>'50']]]];
        $invoice=(new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh();
        $receipt=$invoice->payments()->whereNull('receipt_parent_id')->firstOrFail();
        $before=$receipt->getAttributes();
        $changes=$receipt->change_disbursements()->orderBy('position')->get();
        $beforeChanges=$changes->map->getAttributes()->all();
        $note=(new Facturalo)->forWebDocuments()->save($this->noteInput($input,$invoice,'07','01',[]))->getDocument()->fresh();
        \App\Services\Fiscal\FreeFormControl::register($note,['control_number'=>'99-1','evidence_reference'=>'Control sintético de prueba']);
        $request=['operation_key'=>(string)Str::uuid(),'component'=>'principal','document_payment_id'=>$receipt->id,
            'date_of_payment'=>'2026-09-10','payment_method_type_id'=>'11','payment_destination_id'=>$bank->id,
            'currency_type_id'=>'VES','original_amount'=>'250','exchange_rate'=>'1','exchange_rate_source'=>'manual','reference'=>'QA-DEVOLUCION-MOVIL'];
        $branch=(array)$db->table('establishments')->where('id',$input['establishment_id'])->first();
        unset($branch['id']); $branch['description']='Otra sucursal de prueba'; $branch['code']='9999';
        $other=$db->table('establishments')->insertGetId($branch);
        $beforeGlobals=$db->table('global_payments')->count();
        try { (new NoteRefund)->register($note->fresh(),$request); self::fail('Una cuenta sin sucursal es ambigua cuando existen varias.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment_destination_id',$error->errors()); }
        self::assertSame($beforeGlobals,$db->table('global_payments')->count());
        self::assertSame(0,$db->table('document_note_refunds')->count());
        $db->table('establishments')->where('id',$other)->delete();
        $bank->status=0; $bank->save();
        try { (new NoteRefund)->register($note->fresh(),$request); self::fail('Una cuenta inactiva no puede devolver dinero.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment_destination_id',$error->errors()); }
        $bank->status=1; $bank->save();
        $refund=(new NoteRefund)->register($note->fresh(),$request);
        $movement=\App\Services\Payments\Movement::row($refund->global_payment);
        self::assertSame('egress',$movement['direction']);
        self::assertSame('250',$movement['amount']);
        self::assertSame(\App\Models\Tenant\BankAccount::class,$refund->global_payment->destination_type);
        self::assertSame((int)$bank->id,(int)$refund->global_payment->destination_id);
        self::assertSame($before,$receipt->fresh()->getAttributes());
        self::assertSame($beforeChanges,$receipt->change_disbursements()->orderBy('position')->get()->map->getAttributes()->all());
        self::assertSame('350',\App\Services\Payments\Movement::row($receipt->fresh()->global_payment)['amount']);
        self::assertSame(['50','50'],$changes->map(fn($change)=>\App\Services\Payments\Movement::row($change->fresh()->global_payment)['amount'])->all());
        $finance=new class { use \Modules\Finance\Traits\FinanceTrait; };
        self::assertSame('-300',$finance->getBalanceByBankAcounts(collect([$bank->fresh()]),'VES')->first()['balance']);
        $cash=\App\Models\Tenant\Cash::findOrFail($receipt->global_payment->destination_id);
        self::assertSame('300',\App\Services\Payments\CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);
        self::assertTrue($invoice->fresh()->exactBalance()->isZero());
        self::assertSame('0.00',NoteRefund::available($note->fresh()));
        self::assertSame($refund->id,(new NoteRefund)->register($note->fresh(),$request)->id);
        self::assertSame($beforeGlobals+1,$db->table('global_payments')->count());
    }

    public function test_full_credit_split_mobile_and_cash_refunds_enforce_the_accumulated_limit(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $bank=\App\Models\Tenant\BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),
            'description'=>'Pago Móvil devolución parcial','number'=>'QA-split-refund','currency_type_id'=>'VES',
            'initial_balance'=>'0','status'=>1,'establishment_id'=>null]);
        $db->table('payment_method_types')->where('id','11')->update(['collection_kind'=>'account','currency_type_id'=>'VES',
            'is_active'=>1,'available_for_change'=>1,'bank_account_id'=>$bank->id]);
        $input['items'][0]['quantity']='1'; $input['items'][0]['unit_price']='250';
        $input['payments']=[['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'01','currency_type_id'=>'VES',
            'payment'=>'350','original_amount'=>'350','tendered_amount'=>'350','exchange_rate'=>'1','payment_destination_id'=>'cash',
            'operation_key'=>(string)Str::uuid(),'change_disbursements'=>[
                ['payment_method_type_id'=>'01','amount'=>'50'], ['payment_method_type_id'=>'11','amount'=>'50']]]];
        $invoice=(new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh();
        $receipt=$invoice->payments()->whereNull('receipt_parent_id')->firstOrFail();
        $before=$receipt->getAttributes();
        $changes=$receipt->change_disbursements()->orderBy('position')->get();
        $beforeChanges=$changes->map->getAttributes()->all();
        $cash=\App\Models\Tenant\Cash::findOrFail($receipt->global_payment->destination_id);
        $note=(new Facturalo)->forWebDocuments()->save($this->noteInput($input,$invoice,'07','01',[]))->getDocument()->fresh();
        \App\Services\Fiscal\FreeFormControl::register($note,['control_number'=>'99-1','evidence_reference'=>'Control sintético de devolución parcial']);
        $beforeGlobals=$db->table('global_payments')->count();
        self::assertSame('250.00',NoteRefund::available($note->fresh()));
        $mobileRequest=['operation_key'=>(string)Str::uuid(),'component'=>'principal','document_payment_id'=>$receipt->id,
            'date_of_payment'=>'2026-09-10','payment_method_type_id'=>'11','payment_destination_id'=>$bank->id,
            'currency_type_id'=>'VES','original_amount'=>'100','exchange_rate'=>'1','exchange_rate_source'=>'manual','reference'=>'QA-PARCIAL-MOVIL'];
        $mobile=(new NoteRefund)->register($note->fresh(),$mobileRequest);
        self::assertSame('150.00',NoteRefund::available($note->fresh()));
        self::assertSame($mobile->id,(new NoteRefund)->register($note->fresh(),$mobileRequest)->id);
        $cashRequest=array_replace($mobileRequest,['operation_key'=>(string)Str::uuid(),'payment_method_type_id'=>'01',
            'payment_destination_id'=>'cash','original_amount'=>'150','reference'=>'QA-PARCIAL-CAJA']);
        try { (new NoteRefund)->register($note->fresh(),array_replace($cashRequest,['original_amount'=>'151'])); self::fail('Los Bs. 100 ya devueltos deben contar para el límite acumulado.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('original_amount',$error->errors()); }
        self::assertSame('150.00',NoteRefund::available($note->fresh()));
        self::assertSame(1,$db->table('document_note_refunds')->count());
        self::assertSame($beforeGlobals+1,$db->table('global_payments')->count());
        $cashRefund=(new NoteRefund)->register($note->fresh(),$cashRequest);
        self::assertSame('0.00',NoteRefund::available($note->fresh()));
        self::assertFalse(NoteRefund::view($note->fresh())['can_create']);
        self::assertSame($cashRefund->id,(new NoteRefund)->register($note->fresh(),$cashRequest)->id);
        try { (new NoteRefund)->register($note->fresh(),array_replace($cashRequest,['operation_key'=>(string)Str::uuid(),'original_amount'=>'0.01'])); self::fail('No puede devolverse otro céntimo después de completar los Bs. 250.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('original_amount',$error->errors()); }
        self::assertSame('0.00',NoteRefund::available($note->fresh()));
        self::assertSame(2,$db->table('document_note_refunds')->whereNull('reversed_at')->count());
        self::assertSame($beforeGlobals+2,$db->table('global_payments')->count());
        foreach ([$mobile,$cashRefund] as $refund) {
            self::assertSame('egress',\App\Services\Payments\Movement::row($refund->global_payment)['direction']);
            self::assertSame(1,$refund->global_payment()->count());
        }
        self::assertSame(\App\Models\Tenant\BankAccount::class,$mobile->global_payment->destination_type);
        self::assertSame((int)$bank->id,(int)$mobile->global_payment->destination_id);
        self::assertSame(\App\Models\Tenant\Cash::class,$cashRefund->global_payment->destination_type);
        self::assertSame((int)$cash->id,(int)$cashRefund->global_payment->destination_id);
        self::assertSame($before,$receipt->fresh()->getAttributes());
        self::assertSame($beforeChanges,$receipt->change_disbursements()->orderBy('position')->get()->map->getAttributes()->all());
        self::assertSame('350',\App\Services\Payments\Movement::row($receipt->fresh()->global_payment)['amount']);
        self::assertSame(['50','50'],$changes->map(fn($change)=>\App\Services\Payments\Movement::row($change->fresh()->global_payment)['amount'])->all());
        $finance=new class { use \Modules\Finance\Traits\FinanceTrait; };
        self::assertSame('-150',$finance->getBalanceByBankAcounts(collect([$bank->fresh()]),'VES')->first()['balance']);
        self::assertSame('150',\App\Services\Payments\CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);
        self::assertTrue($invoice->fresh()->exactBalance()->isZero());
    }

    public function test_free_form_concurrent_refunds_preserve_limits_and_idempotency(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $invoice=(new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh();
        $data=$this->noteInput($input,$invoice,'07','09',[
            ['affected_document_item_id'=>$invoice->items->first()->id,'adjustment_amount'=>'23.20'],
        ]);
        $note=(new Facturalo)->forWebDocuments()->save($data)->getDocument()->fresh();
        \App\Services\Fiscal\FreeFormControl::register($note,['control_number'=>'00-2','evidence_reference'=>'Nota física sintética']);
        $bank=$db->table('bank_accounts')->insertGetId(['description'=>'Devolución concurrente USD','number'=>'qa-usd','bank_id'=>1,'currency_type_id'=>'USD','establishment_id'=>$invoice->establishment_id]);
        $db->table('payment_method_types')->where('id','04')->update(['is_active'=>1,'currency_type_id'=>'USD','bank_account_id'=>$bank]);
        $refund=['operation_key'=>(string)Str::uuid(),'component'=>'principal','document_payment_id'=>$invoice->payments->first()->id,
            'date_of_payment'=>'2026-09-10','payment_method_type_id'=>'04','payment_destination_id'=>$bank,
            'currency_type_id'=>'USD','original_amount'=>'1','exchange_rate'=>'10.12345678','exchange_rate_source'=>'manual'];
        $before=$db->table('global_payments')->count();
        $results=$this->runFiscalWorkers($note->id,'note-refund',[$refund,$refund]);
        self::assertSame($results[0]['id'],$results[1]['id']);
        self::assertSame($before+1,$db->table('global_payments')->count());
        self::assertSame('13.08',\App\Support\Exact\Decimal::represent(NoteRefund::available($note->fresh())));
        $first=array_replace($refund,['operation_key'=>(string)Str::uuid()]);
        $second=array_replace($refund,['operation_key'=>(string)Str::uuid()]);
        $results=$this->runFiscalWorkers($note->id,'note-refund',[$first,$second]);
        self::assertCount(1,array_filter($results,fn ($r)=>isset($r['id'])));
        self::assertSame($before+2,$db->table('global_payments')->count());
        self::assertSame(2,$db->table('document_note_refunds')->whereNull('reversed_at')->count());
        self::assertSame('2.96',\App\Support\Exact\Decimal::represent(NoteRefund::available($note->fresh())));
        self::assertSame('-2.96',\App\Support\Exact\Decimal::represent($invoice->fresh()->exactBalance()));
        self::assertSame('free_form',$note->fresh()->fiscal_emission_mode);
    }

    public function test_local_invoice_downloads_and_mail_regenerate_without_replacing_stored_pdf(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form','number'=>'J087897598']);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $http=$app->make(\Illuminate\Http\Client\Factory::class); $http->fake();
        $invoice=(new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
        $customer=(array)$invoice->customer; $customer['identity_document_type']=['description'=>'Venezolano'];
        $establishment=(array)$invoice->establishment;
        foreach (['district_id','province_id','department_id'] as $field) { $customer[$field]='-'; $establishment[$field]='-'; }
        $db->table('documents')->where('id',$invoice->id)->update(['customer'=>json_encode($customer),'establishment'=>json_encode($establishment),'terms_condition'=>'']);
        $db->table('configurations')->update(['terms_condition_sale'=>'<p>Condiciones A4 de prueba</p>']);
        $db->table('establishments')->where('id',$invoice->establishment_id)->update(['template_pdf'=>'default']);
        config(['view.compiled'=>sys_get_temp_dir().'/'.$this->database.'/current-a4-views',
            'view.paths'=>[base_path('resources/views')], 'filesystems.disks.public'=>['driver'=>'local','root'=>sys_get_temp_dir()],
            'tenant.template_document_mail'=>'default','mail.username'=>'test@example.test']);
        mkdir(config('view.compiled'),0777,true); $app->register(\Illuminate\View\ViewServiceProvider::class);
        $path='pdf/'.$invoice->filename.'.pdf';
        \Illuminate\Support\Facades\Storage::disk('tenant')->put($path,'%PDF-stored-before-a4-design');
        $readText=static function (string $bytes): string {
            $file=tempnam(sys_get_temp_dir(),'sab-a4-delivery-');
            try {
                file_put_contents($file,$bytes);
                $process=new \Symfony\Component\Process\Process(['pdftotext','-layout',$file,'-']);
                $process->mustRun();
                return $process->getOutput();
            } finally { unlink($file); }
        };
        foreach (['free_form','fiscal_machine'] as $mode) {
            $db->table('documents')->where('id',$invoice->id)->update(['fiscal_emission_mode'=>$mode]);
            $db->table('companies')->update(['fiscal_emission_mode'=>$mode]);
            $current=$invoice->fresh();
            self::assertNull($current->control_number);
            $before=$current->only(['total','subtotal','currency_type_id','calculation_snapshot']);
            self::assertNull(\App\Services\Fiscal\CurrentLocalInvoicePdf::bytesIfApplicable($current,'ticket'));
            $controller=new \App\Http\Controllers\Tenant\DownloadController();
            $download=$controller->downloadExternal('document','pdf',$current->external_id);
            $print=$controller->toPrint('document',$current->external_id,'a4');
            $mail=(new \App\Mail\Tenant\DocumentEmail(\App\Models\Tenant\Company::active(),$current))->build();
            $a5Download=$controller->downloadExternal('document','pdf',$current->external_id,'a5');
            $a5Print=$controller->toPrint('document',$current->external_id,'a5');
            foreach ([$download->getContent(),$print->getContent(),$mail->rawAttachments[0]['data'],$a5Download->getContent(),$a5Print->getContent()] as $bytes) {
                self::assertStringStartsWith('%PDF-',$bytes);
                self::assertNotSame('%PDF-stored-before-a4-design',$bytes);
                $text=$readText($bytes);
                self::assertStringContainsString('FACTURA',$text);
                self::assertStringContainsString('No asignado',$text);
                self::assertStringContainsString('J-08789759-8',$text);
                self::assertStringContainsString('Tasa de Cambio:',$text);
                self::assertStringNotContainsString('SIN VALIDEZ FISCAL',$text);
                self::assertStringNotContainsString('MONEDA',$text);
            }
            self::assertStringContainsString('Condiciones A4 de prueba',$readText($download->getContent()));
            self::assertStringContainsString('Condiciones A4 de prueba',$current->fresh()->terms_condition);
            self::assertSame($before,$current->fresh()->only(['total','subtotal','currency_type_id','calculation_snapshot']));
            self::assertSame('%PDF-stored-before-a4-design',\Illuminate\Support\Facades\Storage::disk('tenant')->get($path));
        }
        // A reversal committed between payment materialization and balance queries must not mix cuts.
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $db->table('documents')->where('id',$invoice->id)->update(['fiscal_emission_mode'=>'free_form','payment_condition_id'=>'02']);
        // Registered controls and bulk downloads use the same A5 generator.
        $controlled=$invoice->fresh();
        \App\Services\Fiscal\FreeFormControl::register($controlled,['control_number'=>'00-00000034','evidence_reference'=>'Control físico para A5']);
        $controller=new \App\Http\Controllers\Tenant\DownloadController();
        foreach ([$controller->toPrint('document',$controlled->external_id,'a5')->getContent(),
            $controller->downloadExternal('document','pdf',$controlled->external_id,'a5')->getContent()] as $bytes) {
            self::assertStringContainsString('00-00000034',$readText($bytes));
            $reader=new \setasign\Fpdi\Fpdi();
            $reader->setSourceFile(\setasign\Fpdi\PdfParser\StreamReader::createByString($bytes));
            $page=$reader->getTemplateSize($reader->importPage(1));
            self::assertEqualsWithDelta(210,$page['width'],0.01);
            self::assertEqualsWithDelta(148,$page['height'],0.01);
        }
        $report=new class { use \Modules\Report\Traits\MassiveDownloadTrait; };
        $bulk=$report->createPdf(['documents_01'=>[$controlled->fresh(),$controlled->fresh()]],'a5');
        self::assertSame(2,substr_count($readText($bulk),'Tasa de Cambio:'));
        self::assertSame(2,substr_count($readText($bulk),'00-00000034'));
        self::assertSame('%PDF-stored-before-a4-design',\Illuminate\Support\Facades\Storage::disk('tenant')->get($path));
        $configuration=$db->getConfig();
        $writer=new \PDO('pgsql:host='.$configuration['host'].';port='.$configuration['port'].';dbname='.$configuration['database'],
            $configuration['username'],$configuration['password'],[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION]);
        $reversed=false;
        $db->listen(function ($query) use ($db,$writer,$invoice,&$reversed) {
            if ($reversed || $db->transactionLevel() === 0 || !preg_match('/^select .*from "document_payments"/i',$query->sql)) return;
            $reversed=true;
            $statement=$writer->prepare('UPDATE document_payments SET reversed_at = ? WHERE document_id = ? AND receipt_parent_id IS NULL');
            $statement->execute(['2026-11-16 12:00:00',$invoice->id]);
        });
        foreach (['a4','a5'] as $format) {
            $db->table('document_payments')->where('document_id',$invoice->id)->update(['reversed_at'=>null]);
            $reversed=false;
            $before=$invoice->fresh();
            $bytes=\App\Services\Fiscal\CurrentLocalInvoicePdf::bytesIfApplicable($before,$format);
            self::assertTrue($reversed);
            $text=$readText($bytes);
            self::assertMatchesRegularExpression('/Saldo pendiente\s+VES 0\.00/u',$text);
            self::assertStringContainsString('Recibido VES',$text);
            self::assertTrue($invoice->fresh()->exactBalance()->isEqualTo($invoice->exactValue('total')));
            self::assertSame('%PDF-stored-before-a4-design',\Illuminate\Support\Facades\Storage::disk('tenant')->get($path));
        }
        $http->assertNothingSent();
    }

    public function test_free_form_invoices_credit_and_debit_match_cash_products_and_local_pdfs(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        // Forma libre must not contact HKA even if the digital-mode flag is enabled.
        $db->table('configurations')->update(['auto_send_hka'=>true]);
        $http=$app->make(\Illuminate\Http\Client\Factory::class); $http->fake();
        $cash=\App\Models\Tenant\Cash::firstOrFail();
        $brand=\Modules\Item\Models\Brand::create(['name'=>'Marca de prueba']);
        $itemId=$input['items'][0]['item_id'];
        $itemRow=$db->table('items')->where('id',$itemId)->first();
        $itemSnapshot=$itemRow->calculation_snapshot === null
            ? \App\Support\Exact\Decimal::snapshot([])
            : json_decode($itemRow->calculation_snapshot,true,512,JSON_THROW_ON_ERROR);
        $itemSnapshot['values']['purchase_unit_price']=\App\Support\Exact\Decimal::fraction('0.1');
        $db->table('items')->where('id',$itemId)->update(['brand_id'=>$brand->id,'currency_type_id'=>'VES','purchase_unit_price'=>'0.1',
            'calculation_snapshot'=>json_encode($itemSnapshot,JSON_THROW_ON_ERROR)]);
        $documents=[];
        $mixedCurrencies = count($this->invoiceTestCurrencies()) > 1;
        foreach (['VES',$mixedCurrencies ? 'USD' : 'VES'] as $currency) {
            $source=$input;
            $source['external_id']=(string)Str::uuid();
            $source['currency_type_id']=$currency; $source['exchange_rate_sale']='10.12345678';
            $source['payments'][0]['payment_method_type_id']=$currency==='USD'?'10':'01';
            $invoice=(new Facturalo)->forWebDocuments()->save($source)->getDocument()->fresh();
            $line=$invoice->items->first();
            $credit=(new Facturalo)->forWebDocuments()->save($this->noteInput($source,$invoice,'07','09',
                [['affected_document_item_id'=>$line->id,'adjustment_amount'=>'23.20']]))->getDocument()->fresh();
            $debit=(new Facturalo)->forWebDocuments()->save($this->noteInput($source,$invoice,'08','02',
                [['affected_document_item_id'=>$line->id,'adjustment_amount'=>'11.60']]))->getDocument()->fresh();
            foreach ([$invoice,$credit,$debit] as $document) {
                self::assertSame('free_form',$document->fiscal_emission_mode);
                if ($document->emission) {
                    self::assertSame('not_requested',$document->emission->status);
                    self::assertNull($document->emission->payload);
                    self::assertTrue($document->emission->response['auto_send_requested']);
                }
                $emissionView=\App\Services\Fiscal\DocumentEmissionView::forDocument($document);
                self::assertNull($emissionView['status']);
                self::assertFalse($emissionView['show_send']);
                self::assertFalse($emissionView['can_send']);
                \App\Models\Tenant\CashDocument::firstOrCreate(['cash_id'=>$cash->id,'document_id'=>$document->id]);
                $documents[]=$document;
            }
            self::assertSame('23.2',\App\Support\Exact\Decimal::represent($credit->exactValue('total')));
            self::assertSame('11.6',\App\Support\Exact\Decimal::represent($debit->exactValue('total')));
            self::assertSame($invoice->id,$credit->note->affected_document_id);
            self::assertSame($invoice->id,$debit->note->affected_document_id);
        }
        $printedInvoice=collect($documents)->first(fn($row)=>$row->document_type_id==='01' && $row->currency_type_id==='VES');
        $printedCredit=collect($documents)->first(fn($row)=>$row->document_type_id==='07' && $row->currency_type_id==='VES');
        $printedDebit=collect($documents)->first(fn($row)=>$row->document_type_id==='08' && $row->currency_type_id==='VES');
        $fiscalController=new \App\Http\Controllers\Tenant\DocumentFiscalController();
        $controlInputs=[$printedInvoice->id=>'00-1',$printedCredit->id=>'00-2',$printedDebit->id=>'00-3'];
        foreach ([$printedInvoice,$printedCredit,$printedDebit] as $controlled) {
            if ($controlled->document_type_id==='07') {
                try { \App\Services\Fiscal\FreeFormControl::register($controlled,['control_number'=>'00-1','evidence_reference'=>'Nota']); self::fail('Control ya pertenece a factura.'); }
                catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('control_number',$error->errors()); }
                $viewer=auth()->user(); $type=$viewer->type; $viewer->type='seller';
                try { $fiscalController->freeFormControl($controlled->id); self::fail('Sólo administrador.'); }
                catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) { self::assertSame(403,$error->getStatusCode()); }
                $viewer->type=$type;
                $controlled->emission()->update(['status'=>'pending']);
                self::assertFalse($fiscalController->freeFormControl($controlled->id)['data']['can_register']);
                try { \App\Services\Fiscal\FreeFormControl::register($controlled->fresh(),['control_number'=>'00-2','evidence_reference'=>'Nota']); self::fail('Emisión en revisión.'); }
                catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('document',$error->errors()); }
                $controlled->emission()->update(['status'=>'not_requested']); $controlled=$controlled->fresh();
            }
            $reference=$controlled->note->data_affected_document ?? null;
            $amount=$controlled->exactValue('total');
            self::assertTrue($fiscalController->freeFormControl($controlled->id)['data']['can_register']);
            $result=$fiscalController->registerFreeFormControl(\Illuminate\Http\Request::create('/','POST',[
                'control_number'=>$controlInputs[$controlled->id],'evidence_reference'=>'Documento físico de prueba']),$controlled->id);
            self::assertTrue($result['success']); self::assertFalse($fiscalController->freeFormControl($controlled->id)['data']['can_register']);
            self::assertTrue($amount->isEqualTo($controlled->fresh()->exactValue('total')));
            if ($reference) self::assertEquals($reference,$controlled->fresh()->note->data_affected_document);
            self::assertSame('not_requested',$controlled->fresh()->emission->status);
        }
        try { \App\Services\Fiscal\FreeFormControl::register($printedCredit->fresh(),['control_number'=>'00-4','evidence_reference'=>'Otra referencia']); self::fail('El control no se reemplaza.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('control_number',$error->errors()); }
        $comparisonInvoice=$documents[3];
        $comparisonCredit=$documents[4];
        \App\Services\Fiscal\FreeFormControl::register($comparisonInvoice,['control_number'=>'00-4','evidence_reference'=>'Segunda factura física de prueba']);
        self::assertNull($comparisonCredit->fresh()->control_number);
        self::assertEmpty($comparisonCredit->fresh()->note->data_affected_document->control_number ?? null);
        $debitPayment=\App\Services\Fiscal\FiscalDocumentPersistence::payment($printedDebit->fresh(),[
            'operation_key'=>(string)Str::uuid(),'date_of_payment'=>'2026-09-10','payment_method_type_id'=>'10',
            'payment_destination_id'=>'cash','currency_type_id'=>'USD','original_amount'=>'1','exchange_rate'=>'10.12345678',
            'igtf_status'=>'not_applicable','financial_intermediation'=>'none']);
        self::assertSame('10.12',\App\Support\Exact\Decimal::represent($debitPayment->exactValue('payment')));
        self::assertSame('1.48',\App\Support\Exact\Decimal::represent($printedDebit->fresh()->exactBalance()));
        $rows=(new \Modules\CashReport\Services\Builders\ProductsBuilder())->getDataReport($cash->id)['documents'];
        $sold=\Modules\CashReport\Services\ProductsAmounts::sold($rows);
        foreach ($this->invoiceTestCurrencies() as $currency) {
            $sum=\App\Support\Exact\Decimal::sum(collect($sold)->where('currency_type_id',$currency)->pluck('total'));
            $expectedTotal = $mixedCurrencies ? '220.4' : ($currency==='VES' ? '440.8' : '0');
            self::assertSame($expectedTotal,\App\Support\Exact\Decimal::represent($sum));
        }
        config(['view.compiled'=>sys_get_temp_dir().'/'.$this->database.'/free-form-note-views',
            'tenant.pdf_template_footer' => true, 'tenant.public_document_search_enabled' => false,
            'view.paths'=>[base_path('resources/views')], 'filesystems.disks.public'=>['driver'=>'local','root'=>sys_get_temp_dir()]]);
        mkdir(config('view.compiled'),0777,true); $app->register(\Illuminate\View\ViewServiceProvider::class);
        $db->table('establishments')->where('id',$input['establishment_id'])->update(['template_pdf'=>'default']);
        $app['view']->addNamespace('report',base_path('modules/Report/Resources/views'));
        foreach ($documents as $document) {
            $print=$document->fresh();
            $customer=(array)$print->customer; $customer['identity_document_type']=['description'=>'Venezolano'];
            foreach (['district_id','province_id','department_id'] as $field) $customer[$field]='-';
            $print->customer=$customer;
            $establishment=(array)$print->establishment;
            foreach (['district_id','province_id','department_id'] as $field) $establishment[$field]='-';
            $print->establishment=$establishment;
            $type=$print->document_type_id==='01'?'invoice':($print->document_type_id==='07'?'credit':'debit');
            $html=(new \App\CoreFacturalo\Template())->pdf('default',$type,\App\Models\Tenant\Company::active(),$print,'a4',['is_preview'=>false]);
            $pdf=new \Mpdf\Mpdf(['tempDir'=>sys_get_temp_dir()]); $pdf->WriteHTML($html);
            $bytes=$pdf->Output('','S');
            self::assertStringStartsWith('%PDF-',$bytes);
            self::assertStringStartsWith('%PDF-',(new Facturalo())->createPdf($print,$type,'a4','string'));
            $template = new \App\CoreFacturalo\Template();
            $footer = $template->pdfFooter('default', $print);
            self::assertStringNotContainsString('/buscar', $footer);
            self::assertStringContainsString('Representacion impresa de la', $footer);
            if ($print->id === $printedInvoice->id) {
                config(['tenant.public_document_search_enabled' => true]);
                try {
                    self::assertStringContainsString('/buscar', $template->pdfFooter('default', $print));
                } finally {
                    config(['tenant.public_document_search_enabled' => false]);
                }
            }
            self::assertStringContainsString(\App\Support\Exact\Decimal::format($print->exactValue('total'),2,'.',','),$html);
            if ($print->document_type_id==='07') { self::assertStringContainsString('TOTAL NOTA DE CRÉDITO',$html); self::assertStringNotContainsString('TOTAL A PAGAR',$html); }
            if ($print->document_type_id==='08') self::assertStringContainsString('TOTAL NOTA DE DÉBITO',$html);
            if ($print->id===$printedCredit->id || $print->id===$printedDebit->id) {
                self::assertStringContainsString('Control actual de factura afectada',$html);
                self::assertStringContainsString('00-00000001',$html);
                self::assertEmpty($print->note->data_affected_document->control_number ?? null);
            }
            if ($print->id===$printedDebit->id) {
                foreach (['default','modern-2027','Plantilla_personalizable','marca_de_agua'] as $template) {
                    foreach (['a4','a5','ticket','ticket_58'] as $format) {
                        $debitHtml=(new \App\CoreFacturalo\Template())->pdf($template,'debit',\App\Models\Tenant\Company::active(),$print,$format,['is_preview'=>false]);
                        self::assertStringContainsString('Recibido USD 1.00',$debitHtml,"$template $format");
                        self::assertStringContainsString('Aplicado VES 10.12',$debitHtml,"$template $format");
                        self::assertSame(1,substr_count($debitHtml,'Recibido USD 1.00'),"$template $format sin doble detalle");
                    }
                }
                $credit=$printedCredit->fresh(); $credit->customer=$customer; $credit->establishment=$establishment;
                $ticket=(new Facturalo())->createPdf($credit,'credit','ticket_58','string');
                self::assertStringStartsWith('%PDF-',$ticket);
                if ($dir=getenv('SAB_FREE_FORM_NOTE_EVIDENCE')) file_put_contents($dir.'/control-ticket-58.pdf',$ticket);
            }
            if ($print->id===$comparisonCredit->id) {
                self::assertStringContainsString('Control actual de factura afectada',$html);
                self::assertStringContainsString('00-00000004',$html);
                $original=$db->table('documents')->where('id',$print->id)->first(['customer','establishment']);
                $db->table('documents')->where('id',$print->id)->update(['customer'=>json_encode($customer),'establishment'=>json_encode($establishment)]);
                try {
                    self::assertStringStartsWith('%PDF-',\App\Services\Fiscal\CurrentFreeFormPdf::bytesIfRegistered($document));
                    self::assertStringStartsWith('%PDF-',\App\Services\Fiscal\CurrentFreeFormPdf::bytesForFilename($print->filename));
                } finally { $db->table('documents')->where('id',$print->id)->update((array)$original); }
            }
            if (isset($controlInputs[$print->id])) {
                $expectedControl=(string)new \App\Services\FiscalControlNumber($controlInputs[$print->id]);
                self::assertStringContainsString($expectedControl,$html);
                if ($print->id===$printedInvoice->id) self::assertStringNotContainsString('VUELTO:',$html);
                $original=$db->table('documents')->where('id',$print->id)->first(['customer','establishment']);
                $db->table('documents')->where('id',$print->id)->update(['customer'=>json_encode($customer),'establishment'=>json_encode($establishment)]);
                $path='pdf/'.$print->filename.'.pdf';
                \Illuminate\Support\Facades\Storage::disk('tenant')->put($path,'%PDF-original-before-control');
                try {
                    $stale=$document; self::assertNull($stale->control_number);
                    $current=\App\Services\Fiscal\CurrentFreeFormPdf::bytesIfRegistered($stale);
                    self::assertStringStartsWith('%PDF-',$current); self::assertNotSame('%PDF-original-before-control',$current);
                    $controller=new \App\Http\Controllers\Tenant\DownloadController();
                    self::assertStringStartsWith('%PDF-',$controller->downloadExternal('document','pdf',$print->external_id)->getContent());
                    self::assertStringStartsWith('%PDF-',$controller->toPrint('document',$print->external_id,'a4')->getContent());
                    if ($print->document_type_id==='07') {
                        self::assertStringStartsWith('%PDF-',$controller->toPrint('document',$print->external_id,'ticket_58')->getContent());
                        self::assertStringStartsWith('%PDF-',$controller->downloadExternal('document','pdf',$print->external_id,'a5')->getContent());
                    }
                    self::assertStringStartsWith('%PDF-',\App\Services\Fiscal\CurrentFreeFormPdf::bytesForFilename($print->filename));
                    $qrRequest=new class extends \Illuminate\Http\Request {
                        public function validate(array $rules) { return validator($this->all(),$rules)->validate(); }
                    };
                    $qrRequest->merge(['filename_only'=>$print->filename,'extension_only'=>'pdf']);
                    $encoded=(new \Modules\QrApi\Http\Controllers\QrApiController())->encodeBase64($qrRequest);
                    self::assertStringStartsWith('%PDF-',base64_decode($encoded,true));
                    config(['tenant.template_document_mail'=>'default','mail.username'=>'test@example.test']);
                    $mail=(new \App\Mail\Tenant\DocumentEmail(\App\Models\Tenant\Company::active(),$stale))->build();
                    self::assertStringStartsWith('%PDF-',$mail->rawAttachments[0]['data']);
                    self::assertNotSame('%PDF-original-before-control',$mail->rawAttachments[0]['data']);
                    self::assertSame('%PDF-original-before-control',\Illuminate\Support\Facades\Storage::disk('tenant')->get($path));
                    if ($dir=getenv('SAB_FREE_FORM_NOTE_EVIDENCE')) file_put_contents($dir.'/control-current-'.$type.'.pdf',$current);
                } finally { $db->table('documents')->where('id',$print->id)->update((array)$original); }
            }
            if ($dir=getenv('SAB_FREE_FORM_NOTE_EVIDENCE')) file_put_contents($dir.'/'.$type.'-'.$print->currency_type_id.'.pdf',$bytes);
        }
        \App\Services\Fiscal\FiscalDocumentPersistence::reverse($debitPayment,'Reversión sintética');
        self::assertSame('11.6',\App\Support\Exact\Decimal::represent($printedDebit->fresh()->exactBalance()));
        $afterReverse=$printedDebit->fresh(); $afterReverse->customer=$customer; $afterReverse->establishment=$establishment;
        foreach (['a4','a5','ticket','ticket_58'] as $format) {
            $html=(new \App\CoreFacturalo\Template())->pdf('default','debit',\App\Models\Tenant\Company::active(),$afterReverse,$format,['is_preview'=>false]);
            self::assertStringNotContainsString('Recibido USD 1.00',$html,"$format sin cobro revertido");
            self::assertStringNotContainsString('Cobro recibido (USD)',$html,"$format sin resumen revertido");
        }
        // Even a third matching filename with a registered control must block ambiguous legacy lookup.
        $comparisonDebit=$documents[5];
        \App\Services\Fiscal\FreeFormControl::register($comparisonDebit,['control_number'=>'00-5','evidence_reference'=>'Nota física de prueba']);
        $invoiceEmission=$comparisonInvoice->fresh()->emission; $originalResponse=$invoiceEmission->response; $temporaryResponse=$originalResponse;
        unset($temporaryResponse['free_form_control']); $invoiceEmission->update(['response'=>$temporaryResponse]);
        $ambiguous=[$comparisonInvoice,$comparisonCredit,$comparisonDebit]; $names=[];
        try {
            foreach ($ambiguous as $candidate) { $names[$candidate->id]=$candidate->filename; $db->table('documents')->where('id',$candidate->id)->update(['filename'=>'ambiguous-free-form-test']); }
            try { \App\Services\Fiscal\CurrentFreeFormPdf::bytesForFilename('ambiguous-free-form-test'); self::fail('No se debe resolver un nombre ambiguo.'); }
            catch (\UnexpectedValueException $error) { self::assertStringContainsString('ambiguo',$error->getMessage()); }
        } finally {
            foreach ($names as $id=>$name) $db->table('documents')->where('id',$id)->update(['filename'=>$name]);
            $invoiceEmission->update(['response'=>$originalResponse]);
        }
        $filters=['period'=>'date','date_start'=>'2026-09-10','date_end'=>'2026-09-10','brand_id'=>$brand->id];
        $reportController=new \Modules\Report\Http\Controllers\ReportSalesByBrandController();
        $reportMethod=new \ReflectionMethod($reportController,'getRecordsByBrand'); $reportMethod->setAccessible(true);
        $brandRows=$reportMethod->invoke($reportController,$filters);
        self::assertCount(1,$brandRows);
        $expected=\App\Support\Exact\Decimal::of('220.4');
        $expected=$expected->plus($expected->multipliedBy($mixedCurrencies ? '10.12345678' : '1'));
        self::assertTrue(\App\Support\Exact\Decimal::readRequired($brandRows->first(),'total_sale_value')->isEqualTo($expected));
        self::assertTrue(\App\Support\Exact\Decimal::readRequired($brandRows->first(),'total_profit')->isEqualTo($expected->minus('0.4')));
        self::assertSame('4',$brandRows->first()['quantity_sold']);
        self::assertSame('UND',$brandRows->first()['unit_type_id']);
        $db->table('items')->where('id',$itemId)->update(['brand_id'=>null]);
        $withoutBrand=$reportMethod->invoke($reportController,array_replace($filters,['brand_id'=>null]));
        self::assertSame('Sin marca',$withoutBrand->first()['brand_name']);
        self::assertTrue(\App\Support\Exact\Decimal::readRequired($withoutBrand->first(),'total_sale_value')->isEqualTo($expected));
        $db->table('items')->where('id',$itemId)->update(['brand_id'=>$brand->id]);
        $vesCredit=collect($documents)->first(fn($d)=>$d->currency_type_id==='VES' && $d->document_type_id==='07');
        $vesDebit=collect($documents)->first(fn($d)=>$d->currency_type_id==='VES' && $d->document_type_id==='08');
        foreach ([[$vesCredit,'-23.2'],[$vesDebit,'11.6']] as [$adjustment,$expectedProfit]) {
            $adjustmentLines=\App\Models\Tenant\DocumentItem::where('document_id',$adjustment->id)->get();
            $adjustmentGroup=\Modules\Report\Services\BrandSalesAmounts::groups($adjustmentLines,collect())->first();
            self::assertSame('0',$adjustmentGroup['quantity_sold']);
            self::assertSame($expectedProfit,$adjustmentGroup['total_profit']);
        }
        $vesInvoice=collect($documents)->first(fn($d)=>$d->currency_type_id==='VES' && $d->document_type_id==='01');
        $physicalSource=$input; $physicalSource['exchange_rate_sale']='10.12345678';
        $return=(new Facturalo)->forWebDocuments()->save($this->noteInput($physicalSource,$vesInvoice,'07','07',
            [['affected_document_item_id'=>$vesInvoice->items->first()->id,'quantity'=>'1']]))->getDocument()->fresh();
        $returnGroup=\Modules\Report\Services\BrandSalesAmounts::groups(
            \App\Models\Tenant\DocumentItem::where('document_id',$return->id)->get(),collect())->first();
        self::assertSame('-1',$returnGroup['quantity_sold']);
        self::assertSame('104.4',\App\Support\Exact\Decimal::represent($return->exactValue('total')));
        self::assertSame('-104.3',$returnGroup['total_profit']);
        $brandResource=(new \Modules\Report\Http\Resources\ReportSalesByBrandCollection($brandRows))->toArray(null)->first();
        self::assertSame(\App\Support\Exact\Decimal::fraction($expected),$brandResource['calculation_snapshot']['values']['total_sale_value']);
        self::assertTrue($brandResource['cost_is_estimated']);
        $company=\App\Models\Tenant\Company::firstOrFail();
        $establishment=\App\Models\Tenant\Establishment::findOrFail($input['establishment_id']);
        $date_start=$date_end='2026-09-10'; $records=$brandRows;
        $brandHtml=view('report::sales_by_brand.report_pdf',compact('company','establishment','date_start','date_end','records'))->render();
        self::assertStringContainsString('Ganancia estimada (Bs.)',$brandHtml);
        self::assertStringContainsString(\App\Support\Exact\Decimal::format($expected,2,'.',''),$brandHtml);
        $brandPdf=new \Dompdf\Dompdf(); $brandPdf->loadHtml($brandHtml); $brandPdf->render();
        $brandBytes=$brandPdf->output();
        self::assertStringStartsWith('%PDF-',$brandBytes);
        if ($dir=getenv('SAB_BRAND_REPORT_EVIDENCE')) file_put_contents($dir.'/marcas.pdf',$brandBytes);
        config(['excel'=>require base_path('config/excel.php')]);
        $export=(new \Modules\Report\Exports\SalesByBrandExport())->records($records)->company($company)
            ->establishment($establishment)->dateStart($date_start)->dateEnd($date_end);
        $temp=new \Maatwebsite\Excel\Files\TemporaryFileFactory(sys_get_temp_dir());
        $app->instance(\Maatwebsite\Excel\Files\TemporaryFileFactory::class,$temp);
        $file=(new \Maatwebsite\Excel\Writer($temp))->export($export,\Maatwebsite\Excel\Excel::XLSX);
        try {
            if ($dir=getenv('SAB_BRAND_REPORT_EVIDENCE')) copy($file->getLocalPath(),$dir.'/marcas.xlsx');
            $book=(new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($file->getLocalPath());
            $fractions=$book->getSheetByName('Fracciones exactas')->toArray(null,true,false,false);
            $fraction=\App\Support\Exact\Decimal::fraction($expected);
            self::assertContains([$brandRows->first()['report_key'],'total_sale_value',\App\Support\Exact\Decimal::represent($expected),$fraction['n'],$fraction['d']],$fractions);
            $book->disconnectWorksheets();
        } finally { $file->delete(); }
        $accountExport=(new \Modules\Report\Exports\StatusClientExport())->records(collect($documents))->company($company)
            ->establishment($establishment)->filters(['user_type'=>'VENDEDOR'])->categories(collect())->categories_services(collect());
        $accountFile=(new \Maatwebsite\Excel\Writer($temp))->export($accountExport,\Maatwebsite\Excel\Excel::XLSX);
        try {
            if ($dir=getenv('SAB_FREE_FORM_NOTE_EVIDENCE')) copy($accountFile->getLocalPath(),$dir.'/estado-cuenta.xlsx');
            $accountBook=(new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($accountFile->getLocalPath());
            $sheet=$accountBook->getActiveSheet(); $totals=[];
            foreach ($sheet->getRowIterator() as $row) {
                $index=$row->getRowIndex(); $label=(string)$sheet->getCell('O'.$index)->getValue();
                if (in_array($label,['Totales VES','Totales USD'],true)) $totals[$label]=(string)$sheet->getCell('U'.$index)->getValue();
            }
            self::assertSame($mixedCurrencies ? ['Totales VES'=>'220.40','Totales USD'=>'220.40'] : ['Totales VES'=>'440.80'],$totals);
            self::assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING,$sheet->getCell('U7')->getDataType());
            $accountFractions=$accountBook->getSheetByName('Fracciones exactas')->toArray(null,true,false,false);
            self::assertContains(['document:'.$documents[1]->id,'total','-23.2','-116','5'],$accountFractions);
            $accountBook->disconnectWorksheets();
        } finally { $accountFile->delete(); }
        $category=\Modules\Item\Models\Category::create(['name'=>'Repuestos QA']);
        $db->table('items')->where('id',$itemId)->update(['category_id'=>$category->id]);
        $db->table('documents')->where('id',$comparisonInvoice->id)->update(['date_of_issue'=>'2026-09-12']);
        $comparisonLine=$db->table('document_items')->where('document_id',$comparisonInvoice->id)->first();
        $historicItem=json_decode($comparisonLine->item,true); $historicItem['unit_type_id']='KG';
        $db->table('document_items')->where('id',$comparisonLine->id)->update(['item'=>json_encode($historicItem)]);
        $customer=\App\Models\Tenant\Person::findOrFail($input['customer_id']);
        $categories=\Modules\Item\Models\Category::all()->pluck('name')->all();
        $analysis=\Modules\Report\Services\CommercialAnalysisAmounts::row($customer,$categories);
        $analysisVes = $mixedCurrencies ? '116.00' : '336.40';
        self::assertSame($analysisVes,$analysis['total_ves']); self::assertSame($mixedCurrencies ? '220.40' : '0.00',$analysis['total_usd']);
        self::assertSame(2,$analysis['quantity_visit']); self::assertSame('2 KG; 1 UND',$analysis['REPUESTOS QA']);
        self::assertSame('10-09-2026',$analysis['first_document_date']); self::assertSame('12-09-2026',$analysis['last_document_date']);
        self::assertSame('2.00',$analysis['prom_difference_days']); self::assertSame('14-09-2026',$analysis['contact_date']);
        self::assertSame(\App\Support\Exact\Decimal::fraction($analysisVes),$analysis['calculation_snapshot']['values']['total_ves']);
        $analysisResource=(new \Modules\Report\Http\Resources\CommercialAnalysisCollection(collect([$customer->fresh()])))->toArray(new \Illuminate\Http\Request())->first();
        self::assertSame($analysis['REPUESTOS QA'],$analysisResource['REPUESTOS QA']);
        self::assertSame('V-12345678',$analysisResource['customer_number']);
        $app->register(\Illuminate\Translation\TranslationServiceProvider::class); config(['tenant.items_per_page'=>10]);
        $analysisRequest=\Illuminate\Http\Request::create('/reports/commercial-analysis/records','GET',[
            'number'=>$customer->number,'person_type_id'=>'all','category_id'=>null]);
        $analysisPage=(new \Modules\Report\Http\Controllers\ReportCommercialAnalysisController())->records($analysisRequest)->getData(true);
        $analysisRow=collect($analysisPage['data'])->first(fn($row)=>$row['id']===$customer->id);
        self::assertNotNull($analysisRow); self::assertSame($analysisVes,$analysisRow['total_ves']);
        $empty=$customer->replicate(); $empty->number='12345679'; $empty->name='Cliente sin compras'; $empty->save();
        $noPurchases=\Modules\Report\Services\CommercialAnalysisAmounts::row($empty->fresh(),$categories);
        self::assertNull($noPurchases['contact_date']); self::assertNull($noPurchases['last_document_date']);
        $categoryRequest=$analysisRequest->duplicate(array_replace($analysisRequest->query->all(),['number'=>'','category_id'=>$category->id]));
        $analysisController=new \Modules\Report\Http\Controllers\ReportCommercialAnalysisController();
        $matched=collect($analysisController->records($categoryRequest)->getData(true)['data'])->pluck('id');
        self::assertTrue($matched->contains($customer->id)); self::assertFalse($matched->contains($empty->id));
        $db->table('documents')->whereIn('id',[$vesInvoice->id,$comparisonInvoice->id])->update(['state_type_id'=>'11']);
        $voidMatched=collect($analysisController->records($categoryRequest)->getData(true)['data'])->pluck('id');
        self::assertFalse($voidMatched->contains($customer->id));
        $db->table('documents')->whereIn('id',[$vesInvoice->id,$comparisonInvoice->id])->update(['state_type_id'=>'01']);
        $commercial=(new \Modules\Report\Exports\CommercialAnalysisExport())->records(collect([$customer->fresh()]))->company($company)->categories($categories);
        $commercialFile=(new \Maatwebsite\Excel\Writer($temp))->export($commercial,\Maatwebsite\Excel\Excel::XLSX);
        try {
            if ($dir=getenv('SAB_COMMERCIAL_REPORT_EVIDENCE')) copy($commercialFile->getLocalPath(),$dir.'/analisis-comercial.xlsx');
            $book=(new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($commercialFile->getLocalPath());
            $sheet=$book->getSheet(0); $matrix=$sheet->toArray(null,true,false,false);
            $header=null; $headerIndex=null;
            foreach ($matrix as $index=>$row) {
                if (in_array('Cliente',$row,true) && in_array('Documento',$row,true) && in_array('Total VES',$row,true)) {
                    $header=$row; $headerIndex=$index; break;
                }
            }
            self::assertNotNull($header,'Faltan las cabeceras del análisis comercial.');
            $column=static function (string $name) use ($header): int {
                $index=array_search($name,$header,true);
                self::assertNotFalse($index,"Falta la columna {$name}.");
                return $index;
            };
            $documentColumn=$column('Documento'); $customerIndex=null;
            foreach ($matrix as $index=>$row) {
                if ($index>$headerIndex && ($row[$documentColumn] ?? null)==='V-12345678') { $customerIndex=$index; break; }
            }
            self::assertNotNull($customerIndex,'Falta la fila del cliente.');
            $cell=static fn (int $index)=>$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index+1).($customerIndex+1));
            $vesCell=$cell($column('Total VES'));
            self::assertSame($analysisVes,$vesCell->getValue());
            self::assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING,$vesCell->getDataType());
            self::assertSame('2 KG; 1 UND',$cell($column('Repuestos QA'))->getValue());
            if (\App\Services\SalesDocumentCurrency::usesBolivars()) {
                self::assertNotContains('Total USD',$header); self::assertNotContains('Otras monedas',$header);
            } else {
                $usdCell=$cell($column('Total USD'));
                self::assertSame($mixedCurrencies ? '220.40' : '0.00',$usdCell->getValue());
                self::assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING,$usdCell->getDataType());
            }
            $fractions=$book->getSheetByName('Fracciones exactas')->toArray(null,true,false,false);
            self::assertContains(['customer:'.$customer->id,'category:REPUESTOS QA:KG','2','2','1'],$fractions);
            $book->disconnectWorksheets();
        } finally { $commercialFile->delete(); }
        $itemLines=\App\Models\Tenant\DocumentItem::whereIn('document_id',[$vesInvoice->id,$comparisonInvoice->id])->orderByDesc('id')->get();
        $itemReport=\Modules\Report\Services\ItemReportAmounts::presentation($itemLines);
        self::assertSame($mixedCurrencies ? '232' : '464',\App\Support\Exact\Decimal::represent($itemReport['amounts']['VES']));
        self::assertSame($mixedCurrencies ? '232' : '0',\App\Support\Exact\Decimal::represent($itemReport['amounts']['USD'] ?? 0));
        self::assertSame('2',\App\Support\Exact\Decimal::represent($itemReport['quantities']['UND']));
        self::assertSame('2',\App\Support\Exact\Decimal::represent($itemReport['quantities']['KG']));
        $itemResource=(new \Modules\Report\Http\Resources\ItemCollection($itemLines))->toArray(new \Illuminate\Http\Request());
        self::assertEqualsCanonicalizing(['VES',$mixedCurrencies ? 'USD' : 'VES'],$itemResource->pluck('currency_type_id')->all());
        self::assertEqualsCanonicalizing(['KG','UND'],$itemResource->pluck('unit_type_id')->all());
        \Illuminate\Http\Request::macro('validate',function ($rules) { return validator($this->all(),$rules)->validate(); });
        $itemRequest=\Illuminate\Http\Request::create('/reports/items/records','GET',['period'=>'between_dates',
            'date_start'=>'2026-09-10','date_end'=>'2026-09-12','item_id'=>$itemId,'type'=>'sale']);
        $page=(new \Modules\Report\Http\Controllers\ReportItemController())->records($itemRequest)->getData(true);
        self::assertCount(2,$page['data']); self::assertEqualsCanonicalizing(['VES',$mixedCurrencies ? 'USD' : 'VES'],array_column($page['data'],'currency_type_id'));
        $extraRequest=\Illuminate\Http\Request::create('/reports/items-extra/records','GET',['period'=>'between_dates',
            'date_start'=>'2026-09-10','date_end'=>'2026-09-12','item_id'=>$itemId,'extra'=>['CatItemStatus'=>999999]]);
        $extraPage=(new \Modules\Report\Http\Controllers\ReportItemExtraController())->records($extraRequest)->getData(true);
        self::assertCount(0,$extraPage['data']);
        self::assertCount(0,\App\Models\Tenant\ItemMovement::getElementItemIdFromClass(\App\Models\Tenant\PurchaseItem::class,$itemId));
        foreach ([false,true] as $extra) {
            $itemExport=(new \Modules\Report\Exports\ItemExport())->records($itemLines)->company($company)->establishment($establishment)->setWithExtraData($extra);
            $itemFile=(new \Maatwebsite\Excel\Writer($temp))->export($itemExport,\Maatwebsite\Excel\Excel::XLSX);
            try {
                if ($dir=getenv('SAB_ITEM_REPORT_EVIDENCE')) copy($itemFile->getLocalPath(),$dir.'/producto-'.($extra?'extra':'normal').'.xlsx');
                $book=(new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($itemFile->getLocalPath());
                $values=collect($book->getSheet(0)->toArray(null,true,false,false))->flatten()->map(fn($v)=>(string)$v)->all();
                self::assertContains('Total VES',$values);
                if ($mixedCurrencies) self::assertContains('Total USD',$values);
                else self::assertNotContains('Total USD',$values);
                self::assertContains('KG',$values); self::assertContains('UND',$values);
                $fractions=$book->getSheetByName('Fracciones exactas')->toArray(null,true,false,false);
                self::assertContains(['unit:KG','quantity','2','2','1'],$fractions);
                self::assertContains($mixedCurrencies ? ['total:USD','total','232','232','1'] : ['total:VES','total','464','464','1'],$fractions);
                $book->disconnectWorksheets();
            } finally { $itemFile->delete(); }
        }
        $http->assertNothingSent();
    }

    private function noteFixture(bool $withIgtf = false): array
    {
        [$db,$app,$input]=$this->bootHkaFixture(); $calls=0;
        config(['hka_notes.contract_probe'=>true]); // Doubles test protocol, never enable runtime capabilities.
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
        if ($withIgtf) {
            \Tests\Support\SpeProfiles::versions($db,1,$input['customer_id']);
            $db->table('companies')->update(['igtf_enabled'=>1,'igtf_rate'=>3]);
            $input['payments'][0]=array_replace($input['payments'][0],['payment_method_type_id'=>'10','currency_type_id'=>'USD','original_amount'=>'23.20','exchange_rate'=>'10.00000000','financial_intermediation'=>'none']);
        }
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
        $input['payment_condition_id']=$type === '08' ? '02':null;
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

    public function test_hka_note_reference_uses_the_original_invoice_fraction_on_postgresql(): void
    {
        [$db, $app, $input] = $this->noteFixture();
        $input['external_id'] = (string) Str::uuid();
        $input['payment_condition_id'] = '01';
        $input['payments'][0]['payment'] = '10.00';
        $source = '10.0049999999996';
        $decimal = \App\Support\Exact\Decimal::class;
        $input['items'][0] = array_replace($input['items'][0], [
            'quantity' => '1', 'unit_price' => '10.005', 'affectation_igv_type_id' => '20',
            'price_input' => ['amount' => $decimal::fraction($source), 'currency_type_id' => 'VES', 'includes_iva' => true],
        ]);
        $invoice = (new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
        self::assertSame('confirmed', $invoice->emission->status);
        self::assertTrue($invoice->exactValue('total')->isEqualTo($source));
        self::assertSame('10.005000000000', $decimal::represent($invoice->getRawOriginal('total'), 12, true));
        $documentedTotal = $invoice->emission->payload['documentoElectronico']['encabezado']['totales']['totalAPagar'];
        self::assertSame('10.00', $documentedTotal);

        foreach (['07' => '09', '08' => '02'] as $type => $reason) {
            $data = $this->noteInput($input, $invoice, $type, $reason, [[
                'affected_document_item_id' => $invoice->items->first()->id, 'adjustment_amount' => '1.00',
            ]]);
            $note = (new Facturalo())->forWebDocuments()->save($data)->getDocument()->fresh();
            self::assertSame('confirmed', $note->emission->status);
            $reference = $note->emission->payload['documentoElectronico']['encabezado']['identificacionDocumento'];
            self::assertSame($documentedTotal, $reference['montoFacturaAfectada']);
            self::assertTrue($decimal::read((array) $note->note->data_affected_document, 'total')->isEqualTo($source));
        }
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
        self::assertSame('02',$note->payment_condition_id);
        self::assertSame(0,$note->payments()->count());
        $payload=$note->emission->payload;
        $receipt=\App\Services\Fiscal\FiscalDocumentPersistence::payment($note,[
            'operation_key'=>(string)Str::uuid(),'payment'=>5,'date_of_payment'=>'2026-09-10','payment_method_type_id'=>'01']);
        self::assertEquals(6.60,$note->fresh()->balance);
        $receivable=null;
        foreach (\App\Support\Exact\Receivables::dataset(['stablishmentUnpaidAll'=>1]) as $row) {
            if ($row['type']==='document' && $row['id']===$note->id) {$receivable=$row;break;}
        }
        self::assertNotNull($receivable);self::assertEquals(6.60,$receivable['total_to_pay']);
        self::assertEquals($payload,$note->fresh()->emission->payload);
        self::assertEquals(0,$invoice->fresh()->balance);
        self::assertEquals(5,$receipt->payment);
    }

    public function test_note_currency_regime_and_service_matrix(): void
    {
        [$db,$app,$input,$first]=$this->noteFixture();
        $party=['person_id'=>$first->customer_id,'rif'=>'J000000004','name'=>'Tercero de prueba','address'=>'Caracas','country_id'=>'VE','activity'=>'Comercio'];
        foreach ($this->invoiceTestCurrencies() as $currency) {
            foreach ([null,1,2,3,4,5,6,7] as $regime) {
                $source=$input;$source['currency_type_id']=$currency;$source['exchange_rate_sale']='10.12345678';$source['payments'][0]['payment_method_type_id']=$currency==='USD'?'10':'01';
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
        [$db,$app,$input,$invoice]=$this->noteFixture(true);$line=$invoice->items->first();
        $data=$this->noteInput($input,$invoice,'07','09',[['affected_document_item_id'=>$line->id,'adjustment_amount'=>23.20]]);
        $note=(new Facturalo)->forWebDocuments()->save($data)->getDocument()->fresh();
        $fund=$invoice->guarantee_fund()->create(['base'=>100,'percentage'=>10,'amount'=>10]);
        $original=$fund->toArray();
        $request=['kind'=>'fund','source_id'=>$fund->id,'operation_key'=>(string)Str::uuid(),'amount'=>5,'reason'=>'Ajuste de fondo por NC'];
        $adjustment=NoteAdjustment::register($note,$request);
        self::assertSame($adjustment->id,NoteAdjustment::register($note,$request)->id);
        self::assertEquals(5,$invoice->fresh()->guarantee_amount);self::assertEquals($original,$fund->fresh()->toArray());
        // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
        $balanceBatch=\App\Support\Exact\Receivables::balancesForDocuments(collect([$invoice->fresh()]));
        self::assertTrue($balanceBatch[$invoice->id]->isEqualTo($invoice->fresh()->exactBalance()));
        // ######## FIN MONEDA DEL MEDIO DE COBRO ########
        $payment=$invoice->payments->whereNull('receipt_parent_id')->first();
        $tax=$invoice->taxes()->where('tax_kind','IGTF')->where('document_payment_id',$payment->id)->firstOrFail();
        $originalTax=$tax->amount;
        $cash=$db->table('global_payments')->count();
        $igtf=NoteAdjustment::register($note,['kind'=>'IGTF','source_id'=>$tax->id,'operation_key'=>(string)Str::uuid(),'amount'=>3,'reason'=>'Impuesto del cobro devuelto']);
        self::assertSame('blocked',$igtf->status);self::assertSame($cash,$db->table('global_payments')->count());
        self::assertEquals($originalTax,$tax->fresh()->amount);
        config(['hka_notes.contract_probe'=>false,'hka_notes.demo_non_cash_notes_verified'=>false]);
        self::assertSame(\App\Services\Fiscal\HkaNoteCapability::CREDIT_REASON,\App\Services\Fiscal\HkaNoteCapability::reason($note));
        self::assertFalse(\App\Services\Fiscal\NoteRefund::view($note)['can_create'] && $note->emission->status !== 'confirmed');
        \Tests\Support\SpeProfiles::versions($db,1,$invoice->customer_id);$db->table('companies')->update(['igtf_enabled'=>1,'igtf_rate'=>3]);
        $invoice->fiscal_data->update(['special_tax_regime_id'=>7,'catalog_snapshot'=>['special_tax_regime_id'=>['id'=>7,'description'=>\App\Services\SpecialTaxRegimePolicy::NAMES[7]]]]);
        $debitInput=$this->noteInput($input,$invoice->fresh(),'08','02',[['affected_document_item_id'=>$line->id,'adjustment_amount'=>11.60]]);
        $debit=(new Facturalo)->forWebDocuments()->save($debitInput)->getDocument()->fresh();
        $receipt=\App\Services\Fiscal\FiscalDocumentPersistence::payment($debit,['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'10',
            'operation_key'=>(string)Str::uuid(),'currency_type_id'=>'USD','original_amount'=>1.16,'exchange_rate'=>'10.00000000','financial_intermediation'=>'none']);
        self::assertEquals(7,$debit->fresh()->fiscal_data->special_tax_regime_id,'Ordinary ND snapshot retains regime');
        self::assertEquals(0,$debit->fresh()->balance);self::assertEquals(0.03,$receipt->tax_amount);
        $taxNote=Document::whereHas('taxes',fn ($q)=>$q->where('document_payment_id',$receipt->id)->where('tax_kind','IGTF'))->firstOrFail();
        self::assertSame('98',$taxNote->fiscal_data->transaction_type_id);self::assertEquals(7,$taxNote->fiscal_data->special_tax_regime_id);
        self::assertSame(\App\Services\Fiscal\HkaNoteCapability::RELATED_IGTF_REASON,\App\Services\Fiscal\HkaNoteCapability::reason($taxNote));
        self::assertEquals($invoice->id,$taxNote->note->affected_document_id);
        $pending=NoteAdjustment::register($note,['kind'=>'IGTF','source_id'=>$taxNote->taxes->first()->id,'operation_key'=>(string)Str::uuid(),'amount'=>0.30,'reason'=>'IGTF del cargo posterior']);
        self::assertSame('blocked',$pending->status);
        config(['hka_notes.contract_probe'=>true]);
        $initial=$input;$initial['external_id']=(string)Str::uuid();$initial['payments']=[['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'10','currency_type_id'=>'USD',
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
        $db->table('payment_method_types')->where('id','04')->update(['bank_account_id'=>$bankId,'is_active'=>1,'commission_percentage'=>'10']);
        $refund=['operation_key'=>(string)Str::uuid(),'component'=>'principal','document_payment_id'=>$invoice->payments->first()->id,
            'date_of_payment'=>'2026-09-10','payment_method_type_id'=>'04','payment_destination_id'=>$bankId,
            'currency_type_id'=>'VES','original_amount'=>10,'exchange_rate'=>'1.00000000','exchange_rate_source'=>'manual'];
        $before=$invoice->payments->first()->getAttributes();
        $excess=array_replace($refund,['operation_key'=>(string)Str::uuid(),'original_amount'=>24]);
        try {(new NoteRefund)->register($note,$excess);self::fail('Cash refund cannot exceed the NC');}
        catch (\Illuminate\Validation\ValidationException $e) {self::assertArrayHasKey('original_amount',$e->errors());}
        $record=(new NoteRefund)->register($note,$refund);
        self::assertSame(0, $db->table('payment_commissions')->count());
        self::assertSame($record->id,(new NoteRefund)->register($note,$refund)->id);
        self::assertEquals(-13.20,$invoice->fresh()->balance);self::assertEquals(13.20,NoteRefund::available($note));
        // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
        $balanceBatch=\App\Support\Exact\Receivables::balancesForDocuments(collect([$invoice->fresh()]));
        self::assertTrue($balanceBatch[$invoice->id]->isEqualTo($invoice->fresh()->exactBalance()));
        // ######## FIN MONEDA DEL MEDIO DE COBRO ########
        $sql=\App\Services\Fiscal\FiscalNoteBalance::sql();
        self::assertTrue(\App\Support\Exact\Decimal::of($invoice->fresh()->balance)->isEqualTo(\App\Support\Exact\Decimal::of($db->table('documents')->where('id',$invoice->id)->selectRaw($sql.' AS outstanding')->first()->outstanding)));
        self::assertEquals(0,$db->table('documents')->where('id',$note->id)->selectRaw($sql.' AS outstanding')->first()->outstanding);
        self::assertSame('output',$record->global_payment->type_movement);
        self::assertSame($before,$invoice->payments->first()->fresh()->getAttributes());
        $reverse=['operation_key'=>(string)Str::uuid(),'reason'=>'Corrección del registro de devolución'];
        (new NoteRefund)->reverse($note,$record,$reverse);(new NoteRefund)->reverse($note,$record,$reverse);
        self::assertEquals(-23.20,$invoice->fresh()->balance);self::assertEquals(23.20,NoteRefund::available($note));
        $cash=\App\Models\Tenant\Cash::where('user_id',auth()->id())->where('state',true)->firstOrFail();
        $db->table('payment_method_types')->where('id','04')->update(['collection_kind'=>'cash','cash_currency_type_id'=>'VES','is_cash'=>1]);
        $cashRefund=array_replace($refund,['operation_key'=>(string)Str::uuid(),'original_amount'=>5,'payment_destination_id'=>'cash','payment_method_type_id'=>'04']);
        $out=(new NoteRefund)->register($note->fresh(),$cashRefund);
        $db->table('payment_method_types')->where('id','04')->update(['collection_kind'=>'account','cash_currency_type_id'=>null,'is_cash'=>0]);
        self::assertEquals(5,$cash->noteRefundTotal());self::assertSame('egress',$out->getDataCashPaymentReport()['type_transaction']);
        self::assertEquals(-5,$out->getRowIncomeSummaryPayment()['payment_for_calculate']);
        $refundBeforeRead=$out->fresh()->getAttributes();
        // Read the real billing-refund adapter with large exact operands. The
        // model copy is never saved; the registered refund remains five VES.
        foreach (['VES','USD'] as $currency) {
            $copy=clone $out;
            $copy->setRawAttributes(array_replace($copy->getAttributes(),['original_amount'=>'9007199254740993.01',
                'currency_type_id'=>$currency,'exchange_rate'=>'10.12345678','reversed_at'=>null]));
            $expected=\App\Support\Exact\Decimal::convert('9007199254740993.01',$currency,'VES','10.12345678')->negated();
            self::assertSame(\App\Support\Exact\Decimal::represent($expected),$copy->getRowIncomeSummaryPayment()['payment_for_calculate']);
            $copy->reversed_at=now();
            self::assertSame('0',$copy->getRowIncomeSummaryPayment()['payment_for_calculate']);
        }
        self::assertSame($refundBeforeRead,$out->fresh()->getAttributes());
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
        $ids=['9007199254740992','9007199254740993'];
        $serials=[];
        foreach ($ids as $index=>$id) {
            $serial=new \Modules\Item\Models\ItemLot(['item_id'=>$item->id,'warehouse_id'=>$warehouse,
                'series'=>'NOTE-SERIAL-'.$index,'date'=>'2026-09-10','item_loteable_type'=>Document::class,
                'item_loteable_id'=>$invoice->id,'has_sale'=>true]);
            $serial->id=$id;
            $serial->save();
            // Legacy snapshots held these identifiers as JSON numbers.
            $serials[]=['id'=>(int)$id,'series'=>$serial->series,'has_sale'=>true];
        }
        $snapshot=json_decode(json_encode($original->item),true);$snapshot['presentation']=['quantity_unit'=>2];
        $snapshot['inventory_components']=[['item_id'=>$item->id,'quantity'=>'2']];
        $snapshot['IdLoteSelected']=[['id'=>$lot->id,'compromise_quantity'=>2]];$snapshot['lots']=$serials;
        $original->item=$snapshot;$original->save();
        self::assertSame('VES',$invoice->currency_type_id);
        $decode=static fn ($value)=>json_decode(json_encode($value,JSON_THROW_ON_ERROR),true,512,JSON_THROW_ON_ERROR);
        $noteController=new \App\Http\Controllers\Tenant\NoteController();
        $noteRecord=$decode($noteController->record($invoice->id));
        self::assertSame($ids,array_column($noteRecord['note_context']['items'][0]['serials'],'id'));
        $items=$decode(\App\Http\Resources\Tenant\DocumentResource::mapDocumentItems($invoice->fresh()));
        self::assertSame($ids,array_column($items[0]['item']['lots'],'id'));
        $request=\Illuminate\Http\Request::create('/documents/regularize-lots', 'GET', ['document_item_id'=>$original->id]);
        $documentController=new \Modules\Document\Http\Controllers\DocumentController();
        self::assertEqualsCanonicalizing($ids,array_column($decode($documentController->regularizeLots($request)),'id'));
        $collection=new \Modules\Document\Http\Resources\ItemLotCollection(
            \Modules\Item\Models\ItemLot::query()->whereIn('id',$ids)->orderBy('id')->get());
        self::assertSame($ids,array_column($decode($collection->resolve($request)),'id'));
        $stock=$db->table('item_warehouse')->where('item_id',$item->id)->value('stock');
        $returned=$this->noteInput($input,$invoice,'07','07',[['affected_document_item_id'=>$original->id,'quantity'=>1,'serial_ids'=>[$noteRecord['note_context']['items'][0]['serials'][1]['id']]]]);
        $returnedDocument=(new Facturalo)->forWebDocuments()->save($returned)->getDocument()->fresh();
        self::assertEquals($stock+2,$db->table('item_warehouse')->where('item_id',$item->id)->value('stock'));
        self::assertEquals(2,$lot->fresh()->quantity);self::assertFalse(\Modules\Item\Models\ItemLot::find($serials[1]['id'])->has_sale);
        \Modules\Item\Models\ItemLot::find($serials[1]['id'])->update(['has_sale'=>true]);
        $beforeCalls=$app->make(\Illuminate\Http\Client\Factory::class)->recorded()->count();
        try {app(\App\Services\Fiscal\NoteAnnulment::class)->request($returnedDocument,['operation_key'=>(string)Str::uuid(),'reason'=>'Intento con serial ya vendido']);self::fail('Reused serial must block cancellation before HKA');}
        catch (\Illuminate\Validation\ValidationException $e) {self::assertArrayHasKey('inventory',$e->errors());}
        self::assertSame($beforeCalls,$app->make(\Illuminate\Http\Client\Factory::class)->recorded()->count());
        \Modules\Item\Models\ItemLot::find($serials[1]['id'])->update(['has_sale'=>false]);
        $context=FiscalNote::context($invoice->fresh());self::assertSame($ids[0],$context['items'][0]['serials'][0]['id']);
        self::assertSame([$ids[0]],array_column($decode($documentController->regularizeLots($request)),'id'));
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
        $db->table('payment_method_types')->where('id','04')->update(['is_active'=>1,'currency_type_id'=>'USD','bank_account_id'=>$bankId]);
        $refund=['operation_key'=>(string)Str::uuid(),'component'=>'principal','document_payment_id'=>$invoice->payments->first()->id,
            'date_of_payment'=>'2026-09-10','payment_method_type_id'=>'04','payment_destination_id'=>$bankId,
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

    public function test_note_annulment_reverses_stock_and_credit_once_and_cancels_unsent_debit_locally(): void
    {
        [$db,$app,$input,$invoice]=$this->noteFixture();$line=$invoice->items->first();
        $stock=$db->table('item_warehouse')->where('item_id',$line->item_id)->value('stock');
        $data=$this->noteInput($input,$invoice,'07','07',[['affected_document_item_id'=>$line->id,'quantity'=>1]]);
        $note=(new Facturalo)->forWebDocuments()->save($data)->getDocument()->fresh();
        $payload=$note->emission->payload;$control=$note->control_number;$calls=0;
        $app->instance(\Illuminate\Http\Client\Factory::class,new \Illuminate\Http\Client\Factory());\Illuminate\Support\Facades\Facade::clearResolvedInstance(\Illuminate\Http\Client\Factory::class);
        $app->make(\Illuminate\Http\Client\Factory::class)->fake(function($request) use ($db,&$calls) {
            self::assertSame(0,$db->transactionLevel());self::assertStringEndsWith('/Anular',$request->url());$calls++;
            self::assertSame('02',$request['tipoDocumento']);return \Illuminate\Support\Facades\Http::response(['codigo'=>'200','validaciones'=>[]]);
        });
        $request=['operation_key'=>(string)Str::uuid(),'reason'=>'Devolución registrada por error'];
        $service=app(\App\Services\Fiscal\NoteAnnulment::class);$result=$service->request($note,$request);
        self::assertSame('completed',$result['annulment']['status']);self::assertSame('cancelled',$result['status']);
        self::assertEquals($stock,$db->table('item_warehouse')->where('item_id',$line->item_id)->value('stock'));self::assertEquals(0,$invoice->fresh()->balance);
        self::assertEquals(0,FiscalNote::credits($invoice));self::assertSame('11',$note->fresh()->state_type_id);
        self::assertSame($payload,$note->fresh()->emission->payload);self::assertSame($control,$note->fresh()->control_number);
        self::assertSame('completed',$service->request($note->fresh(),$request)['annulment']['status']);self::assertSame(1,$calls);
        self::assertArrayNotHasKey('request_hash',$result['annulment']);self::assertArrayNotHasKey('request',$result['annulment']);
        try {$service->request($note->fresh(),array_replace($request,['reason'=>'Otro motivo']));self::fail('UUID mismatch');}
        catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){self::assertSame(409,$e->getStatusCode());}
        config(['hka_notes.contract_probe'=>false,'hka_notes.demo_non_cash_notes_verified'=>false]);
        $debit=(new Facturalo)->forWebDocuments()->save($this->noteInput($input,$invoice,'08','02',[['affected_document_item_id'=>$line->id,'adjustment_amount'=>11.60]]))->getDocument()->fresh();
        self::assertSame('not_requested',$debit->emission->status);
        self::assertTrue(\App\Services\Fiscal\DocumentEmissionView::forDocument($debit)['show_send']);
        $result=$service->request($debit,['operation_key'=>(string)Str::uuid(),'reason'=>'Cancelación local del cargo no emitido']);
        self::assertSame('completed',$result['annulment']['status']);self::assertSame(1,$calls);self::assertEquals(0,$debit->fresh()->balance);
        $sql=$db->selectOne('SELECT '.\App\Services\Fiscal\FiscalNoteBalance::sql().' AS balance FROM documents WHERE id=?',[$debit->id]);self::assertEquals(0,$sql->balance);
    }

    public function test_note_annulment_preserves_settled_invoice_cash_link_at_half_a_cent(): void
    {
        [$db, $app, $input, $invoice] = $this->noteFixture();
        $payment = $invoice->payments()->firstOrFail();
        $amount = $invoice->exactValue('total_payable')->minus('0.005');
        // Set up the fractional receipt in the temporary fixture without editing a recorded receipt through business APIs.
        $payment->setExactValues(['payment' => $amount, 'original_amount' => $amount])->saveQuietly();
        self::assertTrue($invoice->fresh()->exactBalance()->isEqualTo('0.005'));

        $cashId = $db->table('cash')->where('user_id', auth()->id())->where('state', true)->value('id');
        $creditId = $db->table('cash_document_credits')->insertGetId([
            'document_id' => $invoice->id,
            'cash_id' => $cashId,
            'status' => 'PROCESSED',
        ]);
        $note = (new Facturalo)->forWebDocuments()->save($this->noteInput($input, $invoice, '07', '04', [
            ['affected_document_item_id' => $invoice->items->first()->id, 'adjustment_amount' => '0.01'],
        ]))->getDocument()->fresh();

        $app->instance(\Illuminate\Http\Client\Factory::class, new \Illuminate\Http\Client\Factory());
        \Illuminate\Support\Facades\Facade::clearResolvedInstance(\Illuminate\Http\Client\Factory::class);
        $app->make(\Illuminate\Http\Client\Factory::class)->fake([
            '*/Anular' => \Illuminate\Support\Facades\Http::response(['codigo' => '200', 'validaciones' => []]),
        ]);
        $result = app(\App\Services\Fiscal\NoteAnnulment::class)->request($note, [
            'operation_key' => (string) Str::uuid(),
            'reason' => 'Anulación del ajuste aplicado por error',
        ]);
        self::assertSame('completed', $result['annulment']['status']);
        self::assertTrue($invoice->fresh()->exactBalance()->isEqualTo('0.005'));
        self::assertSame('PROCESSED', $db->table('cash_document_credits')->where('id', $creditId)->value('status'));
    }

    public function test_note_annulment_rejection_preserves_effects_and_audits_codes_without_private_text(): void
    {
        [$db,$app,$input,$invoice]=$this->noteFixture();$line=$invoice->items->first();
        $note=(new Facturalo)->forWebDocuments()->save($this->noteInput($input,$invoice,'07','04',[['affected_document_item_id'=>$line->id,'adjustment_amount'=>23.20]]))->getDocument()->fresh();
        $state=$note->state_type_id;$calls=0;
        $app->instance(\Illuminate\Http\Client\Factory::class,new \Illuminate\Http\Client\Factory());\Illuminate\Support\Facades\Facade::clearResolvedInstance(\Illuminate\Http\Client\Factory::class);
        $app->make(\Illuminate\Http\Client\Factory::class)->fake(function($request) use ($db,&$calls) {
            self::assertSame(0,$db->transactionLevel());self::assertStringEndsWith('/Anular',$request->url());$calls++;
            return \Illuminate\Support\Facades\Http::response(['codigo'=>$calls===1?'400':'203','validaciones'=>['[4012] private provider data']]);
        });
        $service=app(\App\Services\Fiscal\NoteAnnulment::class);$request=['operation_key'=>(string)Str::uuid(),'reason'=>'Rechazo sintético'];
        $result=$service->request($note,$request);
        self::assertSame('rejected',$result['annulment']['status']);self::assertSame($state,$note->fresh()->state_type_id);
        self::assertSame('confirmed',$note->fresh()->emission->status);self::assertEquals(23.20,FiscalNote::credits($invoice));
        self::assertStringContainsString('4012',$result['annulment']['diagnostic']);self::assertStringNotContainsString('private',$result['annulment']['diagnostic']);
        $provider=$note->fresh()->emission->response['annulment']['provider_result'];
        self::assertSame(200,$provider['http_status']);self::assertSame('400',$provider['code']);self::assertSame(['4012'],$provider['validation_codes']);
        $service->request($note->fresh(),$request);self::assertSame(1,$calls);
        $request['operation_key']=(string)Str::uuid();$result=$service->request($note->fresh(),$request);
        self::assertSame('uncertain',$result['annulment']['status']);self::assertSame('13',$note->fresh()->state_type_id);
        self::assertEquals(23.20,FiscalNote::credits($invoice));
        $journal=$note->fresh()->emission->response['annulment'];self::assertCount(1,$journal['history']);
        self::assertSame('400',$journal['history'][0]['provider_result']['code']);self::assertSame('203',$journal['provider_result']['code']);
        self::assertStringNotContainsString('private',json_encode($journal));
        $service->request($note->fresh(),$request);self::assertSame(2,$calls);
    }

    public function test_note_annulment_timeout_preserves_limits_and_uses_paginated_or_uuid_recovery(): void
    {
        [$db,$app,$input,$invoice]=$this->noteFixture();$line=$invoice->items->first();
        $note=(new Facturalo)->forWebDocuments()->save($this->noteInput($input,$invoice,'07','04',[['affected_document_item_id'=>$line->id,'adjustment_amount'=>23.20]]))->getDocument()->fresh();
        $identity=$note->emission->payload['documentoElectronico']['encabezado']['identificacionDocumento'];$calls=0;$listCalls=0;$allow=false;
        $app->instance(\Illuminate\Http\Client\Factory::class,new \Illuminate\Http\Client\Factory());\Illuminate\Support\Facades\Facade::clearResolvedInstance(\Illuminate\Http\Client\Factory::class);
        $app->make(\Illuminate\Http\Client\Factory::class)->fake(function($request) use ($db,$note,$identity,&$calls,&$listCalls,&$allow) {
            self::assertSame(0,$db->transactionLevel());
            if(str_ends_with($request->url(),'/Anular')){$calls++;throw new \Illuminate\Http\Client\ConnectionException('private remote body must not escape');}
            if(str_ends_with($request->url(),'/ListadoDocumentos')) {
                $listCalls++;$page=$request['numPagina'];
                return \Illuminate\Support\Facades\Http::response(['codigo'=>'200','validaciones'=>[],'numeroPagina'=>(string)$page,'totalPaginas'=>'2',
                    'documentos'=>$page===1 ? [['rif'=>'OTHER','serie'=>$note->series,'tipoDocumento'=>'02','numeroDocumento'=>(string)$note->number,'numeroControl'=>$note->control_number,'estadoDocumento'=>'Anulada']] : []]);
            }
            self::assertStringEndsWith('/EstadoDocumento',$request->url());
            return \Illuminate\Support\Facades\Http::response(['codigo'=>'200','estado'=>array_replace($identity,['transaccionId'=>$allow?$identity['transaccionId']:'wrong','estadoDocumento'=>'Anulada','numeroControl'=>$note->control_number])]);
        });
        $service=app(\App\Services\Fiscal\NoteAnnulment::class);$request=['operation_key'=>(string)Str::uuid(),'reason'=>'Anulación con respuesta incierta'];
        $result=$service->request($note,$request);self::assertSame('uncertain',$result['annulment']['status']);self::assertSame('13',$note->fresh()->state_type_id);
        self::assertEquals(23.20,FiscalNote::credits($invoice));self::assertSame(1,$calls);self::assertStringNotContainsString('private',$result['annulment']['diagnostic']);
        $service->request($note->fresh(),$request);self::assertSame(1,$calls);
        try {(new Facturalo)->forWebDocuments()->save($this->noteInput($input,$invoice,'07','04',[['affected_document_item_id'=>$line->id,'adjustment_amount'=>1]]));self::fail('Pending cancellation reserves source');}
        catch(\Illuminate\Validation\ValidationException $e){self::assertArrayHasKey('annulment',$e->errors());}
        $result=$service->query($note->fresh());self::assertSame('uncertain',$result['annulment']['status']);self::assertSame(2,$listCalls);
        $allow=true;$result=$service->query($note->fresh());self::assertSame('completed',$result['annulment']['status']);self::assertSame(4,$listCalls);self::assertSame(1,$calls);
        self::assertEquals(0,FiscalNote::credits($invoice));
    }

    public function test_note_annulment_remote_success_can_finish_commercially_after_stock_race(): void
    {
        [$db,$app,$input,$invoice]=$this->noteFixture();$line=$invoice->items->first();
        $stock=$db->table('item_warehouse')->where('item_id',$line->item_id)->value('stock');
        $note=(new Facturalo)->forWebDocuments()->save($this->noteInput($input,$invoice,'07','07',[['affected_document_item_id'=>$line->id,'quantity'=>1]]))->getDocument()->fresh();
        $calls=0;
        $app->instance(\Illuminate\Http\Client\Factory::class,new \Illuminate\Http\Client\Factory());\Illuminate\Support\Facades\Facade::clearResolvedInstance(\Illuminate\Http\Client\Factory::class);
        $app->make(\Illuminate\Http\Client\Factory::class)->fake(function($request) use ($db,$line,&$calls) {
            self::assertSame(0,$db->transactionLevel());$calls++;
            // ######## INICIO CALCULOS EXACTOS SAB ########
            // A real exact writer changes the stock fraction and its projection together.
            $warehouseStock=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$line->item_id)->firstOrFail();
            $warehouseStock->stock=\App\Support\Exact\Decimal::of(0);$warehouseStock->save();
            // ######## FIN CALCULOS EXACTOS SAB ########
            return \Illuminate\Support\Facades\Http::response(['codigo'=>'200','validaciones'=>[]]);
        });
        $service=app(\App\Services\Fiscal\NoteAnnulment::class);$result=$service->request($note,['operation_key'=>(string)Str::uuid(),'reason'=>'Anulación con consumo concurrente de stock']);
        self::assertSame('remote_confirmed',$result['annulment']['status']);self::assertSame('cancelled',$result['status']);self::assertSame('13',$note->fresh()->state_type_id);
        self::assertEquals($note->total,FiscalNote::credits($invoice));self::assertTrue($result['annulment']['can_complete']);
        // ######## INICIO CALCULOS EXACTOS SAB ########
        $warehouseStock=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$line->item_id)->firstOrFail();
        $warehouseStock->stock=\App\Support\Exact\Decimal::of($stock)->plus(1);$warehouseStock->save();
        // ######## FIN CALCULOS EXACTOS SAB ########
        $result=$service->complete($note->fresh());self::assertSame('completed',$result['annulment']['status']);self::assertSame(1,$calls);
        self::assertEquals($stock,$db->table('item_warehouse')->where('item_id',$line->item_id)->value('stock'));self::assertEquals(0,$invoice->fresh()->balance);
        $service->complete($note->fresh());self::assertSame(1,$calls);
    }

    public function test_note_debit_is_created_on_credit_without_cash_effects_and_collects_later(): void
    {
        [$db,$app,$input,$invoice]=$this->noteFixture();$line=$invoice->items->first();
        $bank=$db->table('bank_accounts')->insertGetId(['description'=>'USD ND','number'=>'cash-nd','bank_id'=>1,'currency_type_id'=>'USD','establishment_id'=>$invoice->establishment_id]);
        $db->table('payment_method_types')->where('id','04')->update(['currency_type_id'=>'USD','is_active'=>1,'bank_account_id'=>$bank]);
        $data=$this->noteInput($input,$invoice,'08','02',[['affected_document_item_id'=>$line->id,'adjustment_amount'=>11.60]]);
        unset($data['payment_condition_id'],$data['payments']);
        $counts=[];
        foreach (['documents','document_payments','global_payments','cash_documents','cash_document_payments'] as $table) $counts[$table]=$db->table($table)->count();
        $calls=$app->make(\Illuminate\Http\Client\Factory::class)->recorded()->count();
        $initialPayment=['operation_key'=>(string)Str::uuid(),'payment'=>11.60,'original_amount'=>'5.80','currency_type_id'=>'USD','exchange_rate'=>'2.00000001',
            'date_of_payment'=>'2026-09-10','payment_method_type_id'=>'04','payment_destination_id'=>$bank,'igtf_status'=>'not_applicable','financial_intermediation'=>'financial_institution'];
        foreach ([['payment_condition_id'=>'01'],['payments'=>[$initialPayment]],['payment_condition_id'=>'02','payments'=>[$initialPayment]]] as $invalid) {
            try {(new Facturalo)->forWebDocuments()->save(array_replace($data,$invalid));self::fail('Initial cash debit or collection must be rejected');}
            catch(\Illuminate\Validation\ValidationException $e){self::assertArrayHasKey(isset($invalid['payments'])?'payments':'payment_condition_id',$e->errors());}
            foreach ($counts as $table=>$count) self::assertSame($count,$db->table($table)->count(),$table);
            self::assertSame($calls,$app->make(\Illuminate\Http\Client\Factory::class)->recorded()->count());
        }
        $note=(new Facturalo)->forWebDocuments()->save($data)->getDocument()->fresh();
        self::assertSame('02',$note->payment_condition_id);self::assertEquals(11.60,$note->balance);self::assertSame(0,$note->payments()->count());
        foreach (['document_payments','global_payments','cash_document_payments'] as $table) self::assertSame($counts[$table],$db->table($table)->count(),$table);
        self::assertSame($counts['cash_documents']+1,$db->table('cash_documents')->count());
        self::assertTrue($db->table('cash_documents')->where('document_id',$note->id)->exists());
        self::assertSame($note->id,(new Facturalo)->forWebDocuments()->save($data)->getDocument()->id);
        self::assertSame($counts['documents']+1,$db->table('documents')->count());
        $payload=$note->emission->payload;
        $forms=$payload['documentoElectronico']['encabezado']['totales']['formasPago'];
        self::assertCount(1,$forms);self::assertSame('99',$forms[0]['forma']);
        self::assertSame('confirmed',$note->emission->status);
        $payment=$db->transaction(function () use ($note,$initialPayment) {
            $payment=\App\Services\Fiscal\FiscalDocumentPersistence::payment($note,$initialPayment);
            $finance=new class { use \Modules\Finance\Traits\FinanceTrait; };
            $finance->createGlobalPayment($payment,$initialPayment);
            return $payment;
        });
        self::assertEquals(0,$note->fresh()->balance);self::assertSame('2.00000001',$payment->exchange_rate);
        self::assertSame($bank,$payment->global_payment->destination_id);self::assertSame('USD',$payment->currency_type_id);
        self::assertSame(1,$note->payments()->count());self::assertEquals($payload,$note->fresh()->emission->payload);
        self::assertFalse(\App\Services\Fiscal\NoteAnnulment::view($note)['can_request']);
        $service=app(\App\Services\Fiscal\NoteAnnulment::class);
        try {$service->request($note,['operation_key'=>(string)Str::uuid(),'reason'=>'No debe revertir cobros automáticamente']);self::fail('Active collections block cancellation');}
        catch(\Illuminate\Validation\ValidationException $e){self::assertArrayHasKey('annulment',$e->errors());}
        \App\Services\Fiscal\FiscalDocumentPersistence::reverse($payment,'Cobro sintético registrado por error');
        $app->instance(\Illuminate\Http\Client\Factory::class,new \Illuminate\Http\Client\Factory());\Illuminate\Support\Facades\Facade::clearResolvedInstance(\Illuminate\Http\Client\Factory::class);
        $app->make(\Illuminate\Http\Client\Factory::class)->fake(function($request)use($db){self::assertSame(0,$db->transactionLevel());return \Illuminate\Support\Facades\Http::response(['codigo'=>'200','validaciones'=>[]]);});
        $result=$service->request($note->fresh(),['operation_key'=>(string)Str::uuid(),'reason'=>'Cargo anulado después de regularizar cobro']);
        self::assertSame('completed',$result['annulment']['status']);self::assertEquals(0,$note->fresh()->balance);self::assertNotNull($payment->fresh()->reversed_at);
        $authBefore=$app->make('auth');$foreign=new \App\Models\Tenant\User(['type'=>'seller','establishment_id'=>$invoice->establishment_id+1]);
        $app->instance('auth',new class($foreign){private $user;public function __construct($u){$this->user=$u;}public function user(){return $this->user;}public function id(){return 1;}public function check(){return true;}});\Illuminate\Support\Facades\Facade::clearResolvedInstance('auth');
        try {$service->query($note->fresh());self::fail('Other branch or unauthorized user cannot reconcile');}
        catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){self::assertSame(403,$e->getStatusCode());}finally{$app->instance('auth',$authBefore);\Illuminate\Support\Facades\Facade::clearResolvedInstance('auth');}
    }

    public function test_note_concurrent_annulment_transmits_and_releases_credit_once(): void
    {
        [$db,$app,$input,$invoice]=$this->noteFixture();$line=$invoice->items->first();
        $note=(new Facturalo)->forWebDocuments()->save($this->noteInput($input,$invoice,'07','04',[['affected_document_item_id'=>$line->id,'adjustment_amount'=>23.20]]))->getDocument()->fresh();
        $log=tempnam(sys_get_temp_dir(),'sab-annul-test-');
        try {
            $request=['operation_key'=>(string)Str::uuid(),'reason'=>'Anulación concurrente de prueba','log'=>$log];
            $results=$this->runFiscalWorkers($note->id,'note-annul',[$request,$request]);
            self::assertSame('completed',\App\Services\Fiscal\NoteAnnulment::journal($note->fresh())['status']);
            self::assertSame("annul\n",file_get_contents($log));self::assertEquals(0,FiscalNote::credits($invoice));self::assertEquals(0,$invoice->fresh()->balance);
            self::assertCount(2,$results);self::assertSame($request['operation_key'],\App\Services\Fiscal\NoteAnnulment::journal($note->fresh())['operation_key']);
        } finally {unlink($log);}
    }
}
