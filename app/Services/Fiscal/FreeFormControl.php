<?php

namespace App\Services\Fiscal;

use App\Http\Controllers\Tenant\DocumentFiscalController;
use App\Models\Tenant\{Company, Dispatch, Document, User};
use App\Services\FiscalControlNumber;
use Illuminate\Support\Str;

/** Records the control read by an administrator from a physical form. */
final class FreeFormControl
{
    public static function canRegister(Document $document, $user): bool
    {
        return $user instanceof User && $user->type==='admin' && $document->fiscal_emission_mode==='free_form'
            && in_array($document->document_type_id,['01','07','08'],true) && !$document->isVoidedOrRejected() && !$document->control_number
            && (!$document->emission || $document->emission->status==='not_requested');
    }

    public static function register(Document $document, array $input): Document
    {
        DocumentFiscalController::authorizeDocument($document);
        abort_unless(auth()->user() instanceof User && auth()->user()->type === 'admin',403);
        $data=validator($input,['control_number'=>'required|string|max:11','evidence_reference'=>'required|string|max:255'])->validate();
        $evidence=trim($data['evidence_reference']);
        if ($evidence==='') FiscalAmounts::error('evidence_reference','Indique dónde verificó el control impreso.');
        try { $control=(string)new FiscalControlNumber(trim($data['control_number'])); }
        catch (\InvalidArgumentException $exception) { FiscalAmounts::error('control_number',$exception->getMessage()); }
        $result=$document->getConnection()->transaction(function () use ($document,$control,$evidence) {
            Company::query()->lockForUpdate()->firstOrFail();
            $document=Document::query()->lockForUpdate()->findOrFail($document->id);
            if ($document->fiscal_emission_mode!=='free_form' || !in_array($document->document_type_id,['01','07','08'],true) || $document->isVoidedOrRejected())
                FiscalAmounts::error('document','Sólo puede registrar el control impreso de una factura o nota vigente en Forma libre.');
            $emission=$document->emission()->lockForUpdate()->first();
            if ($emission && $emission->status!=='not_requested')
                FiscalAmounts::error('document','El documento tiene una operación de emisión que requiere revisión antes de registrar el control.');
            if ($document->control_number) {
                if ($document->control_number!==$control) FiscalAmounts::error('control_number','El documento ya tiene un control y no puede reemplazarse.');
                return $document;
            }
            // Match all valid zero-padded spellings, including previously stored noncanonical controls.
            [$prefix,$sequence]=explode('-',$control); $integer=(string)(int)$sequence; $spellings=[];
            for ($length=strlen($integer);$length<=8;$length++) $spellings[]=$prefix.'-'.str_pad($integer,$length,'0',STR_PAD_LEFT);
            $exists=Document::query()->where('fiscal_environment',$document->fiscal_environment)->where('id','!=',$document->id)->whereIn('control_number',$spellings)->exists()
                || Dispatch::query()->where('fiscal_environment',$document->fiscal_environment)->whereIn('control_number',$spellings)->exists();
            if ($exists) FiscalAmounts::error('control_number','El control ya pertenece a otro documento del ambiente.');
            if (!$emission) $emission=$document->emission()->create(['fiscal_environment'=>$document->fiscal_environment,'operation_key'=>(string)Str::uuid(),'status'=>'not_requested']);
            $response=$emission->response ?? [];
            $response['free_form_control']=['control_number'=>$control,'evidence_reference'=>$evidence,'actor_id'=>auth()->id(),'recorded_at'=>now()->toIso8601String()];
            $emission->response=$response; $emission->save();
            $document->forceFill(['control_number'=>$control])->save();
            return $document;
        });
        // The existing recoverers also retry pending vouchers if a worker fails after commit.
        if ($result->document_type_id==='01') $result->getConnection()->afterCommit(function () use ($result) {
            foreach ([IvaRetentionService::class,IslrRetentionService::class] as $service) {
                try { $service::resume($result->fresh()); }
                catch (\Throwable $exception) { \Log::warning('No se pudo reanudar una retención pendiente de Forma libre.',['document_id'=>$result->id,'type'=>$service]); }
            }
        });
        return $result->fresh();
    }
}
