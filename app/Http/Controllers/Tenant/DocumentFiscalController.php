<?php
namespace App\Http\Controllers\Tenant;
use App\Http\Controllers\Controller;
use App\Models\Tenant\{Company,Document};
use App\Services\Fiscal\{FiscalDocumentPersistence,HkaEmissionPreparation};
use Illuminate\Http\Request;
// ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
class DocumentFiscalController extends Controller
{
    public static function authorizeDocument(Document $document): void
    {
        $user=auth()->user();
        abort_unless($user instanceof \App\Models\Tenant\User && ($user->type==='admin' || (int)$user->establishment_id===(int)$document->establishment_id),403);
    }
    public function retentions($id)
    {
        $document=Document::findOrFail($id);self::authorizeDocument($document);
        return ['success'=>true,'data'=>$document->received_retentions,'document'=>[
            'id'=>$document->id,'customer_id'=>$document->customer_id,'currency_type_id'=>$document->currency_type_id,
            'exchange_rate_sale'=>$document->exchange_rate_sale,'balance'=>$document->balance]];
    }
    public function retentionStore(Request $request)
    {
        $document=Document::findOrFail($request->input('document_id'));self::authorizeDocument($document);
        abort_unless(auth()->user()->type==='admin' || auth()->user()->create_payment,403);
        return ['success'=>true,'data'=>FiscalDocumentPersistence::retention($document,$request->all()),'message'=>'Comprobante de retención registrado.'];
    }
    public function upload(Request $request)
    {
        $request->validate(['document_id'=>'required|integer','file'=>'required|file|mimes:pdf,jpg,jpeg,png|max:5120']);
        $document=Document::findOrFail($request->document_id);self::authorizeDocument($document);
        abort_unless(auth()->user()->type==='admin' || auth()->user()->create_payment,403);
        $path=$request->file('file')->store('received_retentions/'.$document->id,'tenant');
        return ['success'=>true,'attachment'=>$path];
    }
    public function prepare($id)
    {
        $document=Document::findOrFail($id);self::authorizeDocument($document);
        abort_unless(auth()->user()->type==='admin',403);
        (new HkaEmissionPreparation())->prepare($document);
        $view = \App\Services\Fiscal\DocumentEmissionView::forDocument($document->fresh());
        return ['success' => true, 'data' => $view, 'fiscal_emission' => $view];
    }
    public function sendHka($id)
    {
        $document = Document::findOrFail($id);
        self::authorizeDocument($document);
        abort_unless(auth()->user()->type === 'admin', 403);
        return ['success' => true, 'message' => 'Venta guardada.',
            'fiscal_emission' => app(\App\Services\Fiscal\HkaEmission::class)->send($document)];
    }
    public function queryHka($id)
    {
        $document = Document::findOrFail($id);
        self::authorizeDocument($document);
        return ['success' => true,
            'fiscal_emission' => app(\App\Services\Fiscal\HkaEmission::class)->query($document)];
    }
    public function queryHkaEmail($id)
    {
        $document = Document::findOrFail($id);
        self::authorizeDocument($document);
        $delivery = app(\App\Services\Fiscal\HkaMail::class)->query($document);
        return ['success' => true, 'message' => $delivery['message'], 'email_delivery' => $delivery];
    }
    public function settings()
    {
        $company=Company::firstOrFail();
        return ['data'=>['enabled'=>(bool)$company->igtf_enabled,'rate'=>$company->igtf_rate]];
    }
    public function settingsStore(Request $request)
    {
        abort_unless(auth()->user() instanceof \App\Models\Tenant\User && auth()->user()->type==='admin',403);
        $data=$request->validate(['enabled'=>'required|boolean','rate'=>'nullable|numeric|gt:0|max:100']);
        if(isset($data['rate'])) $data['rate']=round((float)$data['rate'],2);
        if($data['enabled'] && empty($data['rate'])) \App\Services\Fiscal\FiscalAmounts::error('rate','Indique la tasa operativa.');
        \DB::connection('tenant')->transaction(function () use ($data) {
            $company=Company::query()->lockForUpdate()->firstOrFail();
            $previous=$company->only(['igtf_enabled','igtf_rate']);
            $company->forceFill(['igtf_enabled'=>$data['enabled'],'igtf_rate'=>$data['rate'] ?? null])->save();
            $company->getConnection()->table('fiscal_configuration_audits')->insert(['company_id'=>$company->id,'actor_type'=>'tenant',
                'actor_id'=>auth()->id(),'changed_values'=>json_encode(['before'=>$previous,'after'=>$company->fresh()->only(['igtf_enabled','igtf_rate'])]),'changed_fields'=>json_encode(['igtf_enabled','igtf_rate']),
                'fiscal_emission_mode'=>$company->fiscal_emission_mode,'fiscal_environment'=>$company->fiscal_environment,'created_at'=>now()]);
        });
        return ['success'=>true,'message'=>'Configuración IGTF guardada.'];
    }
}
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
