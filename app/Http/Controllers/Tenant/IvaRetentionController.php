<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\{Document, DocumentReceivedRetention, ReceivedRetentionUpload, User};
use App\Services\Fiscal\{IvaRetentionService, IslrRetentionService, HkaIvaRetention, HkaIslrRetention, FiscalAmounts};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IvaRetentionController extends Controller
{
    public function concepts()
    {
        abort_unless(auth()->user() instanceof User,403);
        return ['data'=>IslrRetentionService::available() ? IslrRetentionService::concepts() : []];
    }

    public function proposal(Request $request)
    {
        abort_unless(auth()->user() instanceof User,403);
        $data = $request->validate(['document_currency_type_id'=>'nullable|in:VES,USD','tax_kind'=>'nullable|in:IVA,ISLR','document_id'=>'nullable|integer','customer_id'=>'nullable|integer',
            'date'=>'required|date_format:Y-m-d','total_igv'=>'required|numeric|min:0','total_taxed'=>'required|numeric|min:0',
            'currency_type_id'=>'required|in:VES,USD','fiscal_context'=>'nullable|array']);
        $document = !empty($data['document_id']) ? Document::findOrFail($data['document_id']) : null;
        if ($document) DocumentFiscalController::authorizeDocument($document);
        $id = $document ? $document->customer_id : ($data['customer_id'] ?? 0);
        if (!$id) FiscalAmounts::error('customer_id','Seleccione un cliente.');
        $company = \App\Models\Tenant\Company::firstOrFail();
        $digitalDemo = ($document->fiscal_emission_mode ?? $company->fiscal_emission_mode) === 'digital' && ($document->fiscal_environment ?? $company->fiscal_environment) === 'demo';
        if (($data['tax_kind'] ?? 'IVA') === 'ISLR') {
            if (!IslrRetentionService::available($document)) FiscalAmounts::error('tax_kind','Esta instalación no tiene habilitado el flujo ISLR.');
            $request->validate(['total_value'=>'required_without:document_id|numeric|min:0','exchange_rate_sale'=>'required_without:document_id|numeric|gt:0']);
            $proposal=IslrRetentionService::proposal($id,$data['date'],
                $document->total_value ?? $request->total_value,$document->currency_type_id ?? ($data['document_currency_type_id'] ?? $data['currency_type_id']),
                $request->all(),$document->exchange_rate_sale ?? $request->exchange_rate_sale);
            return ['hka_net_collection_blocked'=>$digitalDemo && !config('islr_retention.demo_net_collection_verified',false),'hka_net_collection_verified'=>(bool)config('islr_retention.demo_net_collection_verified',false),'concepts'=>IslrRetentionService::concepts(),'data'=>$proposal];
        }
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
        $row = DocumentReceivedRetention::whereIn('tax_kind',['IVA','ISLR'])->findOrFail($id);
        DocumentFiscalController::authorizeDocument($row->document);
        if ($row->tax_kind === 'ISLR' && !IslrRetentionService::available($row->document)) FiscalAmounts::error('document','Esta instalación no tiene habilitado el flujo ISLR.');
        return $row;
    }

    private function service(DocumentReceivedRetention $row): string { return $row->tax_kind === 'ISLR' ? IslrRetentionService::class : IvaRetentionService::class; }
    private function hka(DocumentReceivedRetention $row): string { return $row->tax_kind === 'ISLR' ? HkaIslrRetention::class : HkaIvaRetention::class; }
    private function summary(DocumentReceivedRetention $row): array { $doc=$row->document->fresh(); return ['success'=>true,'iva_retention'=>IvaRetentionService::view($doc),'islr_retention'=>IslrRetentionService::view($doc)]; }

    public function query(int $id)
    {
        $row = $this->retention($id);
        app($this->hka($row))->process($row,true);
        return $this->summary($row);
    }

    public function retry(Request $request, int $id)
    {
        $row = $this->retention($id);
        $op = $row->{$row->tax_kind === 'ISLR' ? 'islr_operations' : 'operations'}()->latest('id')->first();
        ($this->service($row))::authorize($row->document,$op && $op->action === 'remove' ? 'delete_payment' : 'create_payment');
        $request->validate(['operation_key'=>'required|uuid']);
        if (!$op || $op->operation_key !== strtolower($request->operation_key)) IvaRetentionService::operationConflict('Indique la operación HKA vigente.');
        if (in_array($op->status,['waiting','uncertain'],true)) $op->update(['next_attempt_at'=>null]);
        app($this->hka($row))->process($row);
        return $this->summary($row);
    }

    public function reverse(Request $request, int $id)
    {
        $row = $this->retention($id);
        ($this->service($row))::reverse($row,$request->all());
        return $this->summary($row);
    }

    public function attachment(int $id)
    {
        $row = $this->retention($id);
        $path = $row->attachment;
        abort_unless($path && str_starts_with($path,'received_retentions/'.$row->document_id.'/')
            && !str_contains($path,'..') && Storage::disk('tenant')->exists($path),404);
        return Storage::disk('tenant')->download($path,'Retencion-'.$row->tax_kind.'-'.preg_replace('/[^A-Za-z0-9_-]/','_',$row->voucher_number).'.'.pathinfo($path,PATHINFO_EXTENSION),
            ['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }
}
