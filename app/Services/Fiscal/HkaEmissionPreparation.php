<?php
namespace App\Services\Fiscal;
use App\Models\Tenant\{Company,Document};
// ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
final class HkaEmissionPreparation
{
    public function prepare(Document $document)
    {
        return $document->getConnection()->transaction(function () use ($document) {
            Company::query()->lockForUpdate()->firstOrFail();
            $document=Document::query()->lockForUpdate()->findOrFail($document->id);
            if($document->fiscal_emission_mode!=='digital' || $document->isVoidedOrRejected()) FiscalAmounts::error('document','El documento debe estar vigente y usar Medios digitales.');
            $emission=$document->emission()->firstOrFail();
            if($emission->status==='prepared') return $emission;
            if($emission->status!=='not_requested') FiscalAmounts::error('emission','La operación fiscal ya está en curso.');
            $document->load(['items','payments','taxes','currency_totals','fiscal_data','note','invoice']);
            $snapshot=json_decode(json_encode($document->toArray(), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            $snapshot['operation_key']=$emission->operation_key;
            if($document->note) {
                $affected=Document::find($document->note->affected_document_id);
                $snapshot['reference']=$document->note->data_affected_document ? (array)$document->note->data_affected_document : ($affected ? $affected->only(['series','number','date_of_issue','total','control_number']) : []);
            }
            if (isset($snapshot['reference']) && empty($snapshot['reference']['control_number']) && $affected) {
                $snapshot['reference']['control_number'] = $affected->control_number;
                $document->note->data_affected_document = $snapshot['reference'];
                $document->note->save();
            }
            $payload=(new HkaPayloadBuilder())->build($snapshot);
            $emission->update(['status'=>'prepared','contract_version'=>HkaPayloadBuilder::contractVersion(),'payload'=>$payload]);
            return $emission->fresh();
        });
    }
    public function setControl(Document $document,string $control): void
    {
        $document->getConnection()->transaction(function () use ($document,$control) {
            Company::query()->lockForUpdate()->firstOrFail();
            $document=Document::query()->lockForUpdate()->findOrFail($document->id);
            $canonical=(string)new \App\Services\FiscalControlNumber($control);
            if($document->control_number && $document->control_number!==$canonical) FiscalAmounts::error('control_number','El control ya está asignado.');
            if (Document::where('fiscal_environment',$document->fiscal_environment)->where('fiscal_emission_mode',$document->fiscal_emission_mode)->where('control_number',$canonical)->where('id','!=',$document->id)->exists()) FiscalAmounts::error('control_number','El control ya pertenece a otro documento del ambiente.');
            $document->forceFill(['control_number'=>$canonical])->save();
            $document->emission()->update(['control_number'=>$canonical]);
        });
    }
}
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
