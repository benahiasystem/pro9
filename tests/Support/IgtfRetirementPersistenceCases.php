<?php

namespace Tests\Support;

use App\Models\Tenant\{Company, Document};
use App\Services\Fiscal\{FiscalDocumentPersistence, FiscalProfileService};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// ######## INICIO RETIRADA DE EXCEPCION ADMINISTRATIVA IGTF ########
trait IgtfRetirementPersistenceCases
{
    public function test_retired_igtf_exception_is_rejected_across_entrypoints_without_partial_writes(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $company=Company::firstOrFail();$company->forceFill(['fiscal_emission_mode'=>'free_form','igtf_enabled'=>true,'igtf_rate'=>3])->save();
        FiscalProfileService::store($company,SpeProfiles::profile());
        $input['currency_type_id']='USD';$input['exchange_rate_sale']='10';$input['payments']=[];$input['payment_condition_id']='02';
        $document=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
        if (!\Illuminate\Http\Request::hasMacro('validate')) {
            \Illuminate\Http\Request::macro('validate', function (array $rules) { return validator($this->all(),$rules)->validate(); });
        }
        $row=['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'10','payment'=>'232', 'currency_type_id'=>'USD',
            'original_amount'=>'232','exchange_rate'=>'10','payment_destination_id'=>'cash','operation_key'=>(string)Str::uuid()];
        $counts=fn()=>[$db->table('documents')->count(),$db->table('document_payments')->count(),$db->table('global_payments')->count(),
            $db->table('document_taxes')->count(),$db->table('inventory_kardex')->count()];
        $before=$counts();
        foreach (['admin','seller'] as $role) {
            auth()->user()->type=$role;
            foreach ([['igtf_status'=>'exempt','exemption_reason'=>'Anterior','exception_basis'=>'Anterior'],
                ['exemption_reason'=>'Anterior'],['exception_basis'=>'Anterior']] as $retired) {
                $payment=array_replace($row,$retired,['operation_key'=>(string)Str::uuid()]);
                $attempt=array_replace($input,['external_id'=>(string)Str::uuid(),'payments'=>[$payment]]);
                $api=new \ReflectionMethod(\App\CoreFacturalo\Requests\Api\Transform\DocumentTransform::class,'payments');$api->setAccessible(true);
                $apiRows=$api->invoke(null,['codigo_tipo_documento'=>'01','codigo_tipo_moneda'=>'USD','tipo_cambio_venta'=>'10',
                    'fecha_de_emision'=>'2026-09-10','pagos'=>[['codigo_metodo_pago'=>'10','codigo_destino_pago'=>'cash','monto'=>'232',
                        'currency_type_id'=>'USD','original_amount'=>'232','exchange_rate'=>'10','operation_key'=>(string)Str::uuid()]+$retired]]);
                foreach ([
                    fn()=>FiscalDocumentPersistence::payment($document,$payment),
                    fn()=>(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($attempt),
                    fn()=>(new \App\CoreFacturalo\Facturalo())->save(array_replace($attempt,['payments'=>$apiRows])),
                    function () use ($payment) {
                        $preview=new Document(['currency_type_id'=>'USD','exchange_rate_sale'=>'10','total'=>'232','date_of_issue'=>'2026-09-10']);
                        $preview->setRelation('payments',new \Illuminate\Database\Eloquent\Collection());
                        (new \App\CoreFacturalo\Facturalo())->setPaymentsPreview($preview,[$payment]);
                    },
                    fn()=>(new \App\Http\Controllers\Tenant\DocumentPaymentQuoteController())(\Illuminate\Http\Request::create('/', 'POST',
                        ['currency_type_id'=>'USD','exchange_rate_sale'=>'10','principal_due'=>'232','payments'=>[$payment]])),
                ] as $operation) {
                    try { $operation();self::fail('Se aceptó una nueva excepción retirada.'); }
                    catch (ValidationException $error) { self::assertSame(422,$error->status); }
                    self::assertSame($before,$counts());self::assertEquals('232',$document->fresh()->balance);
                }
            }
        }
        $general=(new \App\Http\Controllers\Tenant\FiscalProfileController())->evaluate(\Illuminate\Http\Request::create('/', 'POST',
            ['date'=>'2026-09-10','currency_type_id'=>'VES','exemption_reason'=>null,'exception_basis'=>'']))['data'];
        self::assertArrayNotHasKey('can_authorize_exemption',$general);
        self::assertArrayNotHasKey('authorized_by',$general['payment']);
    }

    public function test_retired_igtf_history_survives_retries_preview_and_reversal_without_reclassification(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $company=Company::firstOrFail();$company->forceFill(['fiscal_emission_mode'=>'free_form','igtf_enabled'=>false,'igtf_rate'=>3])->save();
        $row=['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'10','payment'=>'233.36','currency_type_id'=>'USD',
            'original_amount'=>'0.26','exchange_rate'=>'897.53830000','payment_destination_id'=>'cash','operation_key'=>(string)Str::uuid()];
        $input['exchange_rate_sale']='897.53830000';$input['payments']=[$row];
        $document=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
        $receipt=$document->payments()->whereNull('receipt_parent_id')->firstOrFail();
        // Seed an already recorded legacy fixture; production creation cannot produce it.
        $context=$receipt->fiscal_context;
        $context['issuer']=SpeProfiles::profile();
        $context['decision']=['igtf_status'=>'exempt','financial_intermediation'=>'none','exception_basis'=>'LEGACY-BASIS','authorized_by'=>auth()->id()];
        $db->table('document_payments')->where('id',$receipt->id)->update(['igtf_status'=>'exempt','exemption_reason'=>'LEGACY-REASON','fiscal_context'=>json_encode($context)]);
        $receipt=$receipt->fresh();$context=$receipt->fiscal_context;$before=$receipt->getAttributes();$movements=$db->table('global_payments')->count();
        FiscalProfileService::store($company,SpeProfiles::profile());
        $company->forceFill(['igtf_enabled'=>true,'igtf_rate'=>4])->save();
        $db->table('payment_method_types')->where('id','10')->update(['is_active'=>false,'collection_kind'=>'account']);
        self::assertSame($receipt->id,FiscalDocumentPersistence::payment($document,$row)->id);
        self::assertSame($before,$receipt->fresh()->getAttributes());
        $save=new \ReflectionMethod(\App\CoreFacturalo\Facturalo::class,'savePayments');$save->setAccessible(true);
        $db->transaction(fn()=>$save->invoke(new \App\CoreFacturalo\Facturalo(),$document,[$row]));
        self::assertSame($before,$receipt->fresh()->getAttributes());
        foreach ([['igtf_status'=>'not_applicable'],['exemption_reason'=>'Changed'],['exception_basis'=>'Changed'],['original_amount'=>'0.25']] as $changes) {
            try { FiscalDocumentPersistence::payment($document,array_replace($row,$changes));self::fail('Cambió el cobro histórico.'); }
            catch (ValidationException $error) { self::assertArrayHasKey('operation_key',$error->errors()); }
            self::assertSame($before,$receipt->fresh()->getAttributes());
        }
        self::assertSame($movements,$db->table('global_payments')->count());
        self::assertSame(0,$db->table('document_taxes')->where('tax_kind','IGTF')->count());
        $preview=new Document(['currency_type_id'=>'VES','exchange_rate_sale'=>'897.53830000','total'=>'232','date_of_issue'=>'2026-09-10']);
        $preview->setRelation('payments',new \Illuminate\Database\Eloquent\Collection());
        (new \App\CoreFacturalo\Facturalo())->setPaymentsPreview($preview,[['id'=>$receipt->id]+$row],$document->fresh());
        self::assertSame('exempt',$preview->payments->first()->igtf_status);
        self::assertSame($context,$preview->payments->first()->fiscal_context);
        FiscalDocumentPersistence::reverse($receipt,'Reversión de fixture histórico');
        self::assertNotNull($receipt->fresh()->reversed_at);self::assertSame($context,$receipt->fresh()->fiscal_context);
        self::assertEquals('232',$document->fresh()->balance);
    }
}
// ######## FIN RETIRADA DE EXCEPCION ADMINISTRATIVA IGTF ########
