<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\{Document, DocumentReceivedRetention, ReceivedRetentionUpload, User};
use App\Services\Fiscal\{IvaRetentionService, HkaIvaRetention, FiscalAmounts};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IvaRetentionController extends Controller
{
    public function proposal(Request $request)
    {
        abort_unless(auth()->user() instanceof User,403);
        $data = $request->validate(['document_id'=>'nullable|integer','customer_id'=>'nullable|integer',
            'date'=>'required|date_format:Y-m-d','total_igv'=>'required|numeric|min:0','total_taxed'=>'required|numeric|min:0',
            'currency_type_id'=>'required|in:VES,USD','fiscal_context'=>'nullable|array']);
        $document = !empty($data['document_id']) ? Document::findOrFail($data['document_id']) : null;
        if ($document) DocumentFiscalController::authorizeDocument($document);
        $id = $document ? $document->customer_id : ($data['customer_id'] ?? 0);
        if (!$id) FiscalAmounts::error('customer_id','Seleccione un cliente.');
        $company = \App\Models\Tenant\Company::firstOrFail();
        $digitalDemo = ($document->fiscal_emission_mode ?? $company->fiscal_emission_mode) === 'digital' && ($document->fiscal_environment ?? $company->fiscal_environment) === 'demo';
        return ['hka_net_collection_blocked'=>$digitalDemo && !config('iva_retention.demo_net_collection_verified',false), 'hka_net_collection_verified'=>(bool)config('iva_retention.demo_net_collection_verified',false), 'data'=>IvaRetentionService::proposal($id,$data['date'], $document->total_igv ?? $data['total_igv'],
            $document->total_taxed ?? $data['total_taxed'],$document->currency_type_id ?? $data['currency_type_id'],$data['fiscal_context'] ?? [])];
    }

    public function upload(Request $request)
    {
        $user = auth()->user();
        abort_unless($user instanceof User && ($user->type === 'admin' || $user->create_payment),403);
        if (!IvaRetentionService::available()) FiscalAmounts::error('file','Esta instalación no tiene habilitado el flujo IVA.');
        $request->validate(['file'=>'required|file|mimes:pdf,jpg,jpeg,png|max:5120']);
        $token = (string)Str::uuid();
        $path = $request->file('file')->storeAs('received_retentions/drafts',$token.'.'.$request->file('file')->extension(),'tenant');
        try {
            ReceivedRetentionUpload::create(['token'=>$token,'user_id'=>$user->id,'path'=>$path,'expires_at'=>now()->addDay()]);
        } catch (\Throwable $error) { Storage::disk('tenant')->delete($path); throw $error; }
        return ['success'=>true,'upload_id'=>$token,'expires_at'=>now()->addDay()->toIso8601String()];
    }

    private function retention(int $id): DocumentReceivedRetention
    {
        $row = DocumentReceivedRetention::where('tax_kind','IVA')->findOrFail($id);
        DocumentFiscalController::authorizeDocument($row->document);
        return $row;
    }

    public function query(int $id)
    {
        $row = $this->retention($id);
        app(HkaIvaRetention::class)->process($row,true);
        return ['success'=>true,'iva_retention'=>IvaRetentionService::view($row->document->fresh())];
    }

    public function retry(Request $request, int $id)
    {
        $row = $this->retention($id);
        $op = $row->operations()->latest('id')->first();
        IvaRetentionService::authorize($row->document,$op && $op->action === 'remove' ? 'delete_payment' : 'create_payment');
        $request->validate(['operation_key'=>'required|uuid']);
        if (!$op || $op->operation_key !== strtolower($request->operation_key)) IvaRetentionService::operationConflict('Indique la operación IVA pendiente.');
        if (in_array($op->status,['waiting','uncertain'],true)) $op->update(['next_attempt_at'=>null]);
        app(HkaIvaRetention::class)->process($row);
        return ['success'=>true,'iva_retention'=>IvaRetentionService::view($row->document->fresh())];
    }

    public function reverse(Request $request, int $id)
    {
        $row = $this->retention($id);
        IvaRetentionService::reverse($row,$request->all());
        return ['success'=>true,'iva_retention'=>IvaRetentionService::view($row->document->fresh())];
    }

    public function attachment(int $id)
    {
        $row = $this->retention($id);
        $path = $row->attachment;
        abort_unless($path && str_starts_with($path,'received_retentions/'.$row->document_id.'/')
            && !str_contains($path,'..') && Storage::disk('tenant')->exists($path),404);
        return Storage::disk('tenant')->download($path,'Retencion-IVA-'.$row->voucher_number.'.'.pathinfo($path,PATHINFO_EXTENSION),
            ['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }
}
