<?php
namespace App\Services\Fiscal;
use App\Support\Exact\Decimal as D;

use App\Models\Tenant\{Company, Document, DocumentNoteAdjustment, DocumentReceivedRetention, DocumentTax};
use App\Http\Controllers\Tenant\DocumentFiscalController;
use App\Services\ExchangeRates\ExchangeRateMath as Math;

/** The fiscal voucher and the note have independent durable lifecycles. */
final class NoteAdjustment
{
    public static function authorize(Document $note, bool $write=false): void
    {
        DocumentFiscalController::authorizeDocument($note);
        if (!FiscalNote::ordinary($note) || $note->document_type_id !== '07') FiscalAmounts::error('note','Los ajustes relacionados requieren una NC registrada.');
        if ($write) abort_unless(auth()->user()->type === 'admin' || auth()->user()->delete_payment,403);
    }

    public static function register(Document $note,array $input): DocumentNoteAdjustment
    {
        self::authorize($note,true);
        $data=validator($input,['operation_key'=>'required|uuid','kind'=>'required|in:IVA,ISLR,IGTF,fund',
            'source_id'=>'required|integer|min:1','amount'=>'nullable|numeric|gt:0','reason'=>'required|string|max:255'])->validate();
        $hash=hash('sha256',json_encode($data,JSON_THROW_ON_ERROR));
        $record=$note->getConnection()->transaction(function () use ($note,$data,$hash) {
            Company::query()->lockForUpdate()->firstOrFail();
            $invoice=Document::query()->lockForUpdate()->findOrFail($note->note->affected_document_id);
            NoteAnnulment::assertNotBusy($invoice);
            Document::query()->lockForUpdate()->findOrFail($note->id);
            $existing=DocumentNoteAdjustment::where('operation_key',$data['operation_key'])->first();
            if ($existing) { if ($existing->document_id !== $note->id || $existing->request_hash !== $hash) abort(409,'La clave de ajuste pertenece a otra solicitud.'); return $existing; }
            $values=['document_id'=>$note->id,'affected_document_id'=>$invoice->id,'operation_key'=>$data['operation_key'],
                'request_hash'=>$hash,'kind'=>$data['kind'],'actor_id'=>auth()->id(),'reason'=>$data['reason'],'request'=>$data,
                'context'=>['note_key'=>$note->note->creation_key],'status'=>'pending'];
            if (in_array($data['kind'],['IVA','ISLR'],true)) {
                $retention=DocumentReceivedRetention::where('document_id',$invoice->id)->where('tax_kind',$data['kind'])->lockForUpdate()->findOrFail($data['source_id']);
                if (DocumentNoteAdjustment::where('retention_id',$retention->id)->exists()) FiscalAmounts::error('source_id','El comprobante ya tiene un ajuste relacionado.');
                if ($retention->reversal_key || $retention->application_status !== 'applied') FiscalAmounts::error('source_id','Seleccione un comprobante aplicado sin otra reversión en curso.');
                $values['retention_id']=$retention->id; $values['amount']=$retention->exactValue('applied_amount');
                $values['context']['original_voucher']=$retention->only(['id','voucher_number','base','amount','applied_amount','currency_type_id','exchange_rate','attachment']);
                $service=$data['kind'] === 'IVA' ? IvaRetentionService::class : IslrRetentionService::class;
                $record=DocumentNoteAdjustment::create($values);
                $service::reverse($retention,['operation_key'=>$data['operation_key'],'reason'=>$data['reason']]);
                $record->diagnostic='Reversión solicitada. Su efecto se conserva hasta acreditar ausencia en HKA.'; $record->save();
            } elseif ($data['kind'] === 'fund') {
                $fund=$invoice->guarantee_fund()->lockForUpdate()->findOrFail($data['source_id']);
                $amount=isset($data['amount']) ? D::of(D::input($data['amount'],'amount')) : D::of($invoice->guarantee_amount);
                if ($amount->isLessThanOrEqualTo(0) || $amount->isGreaterThan(D::min($invoice->guarantee_amount,$note->exactValue('total')->minus(D::sum(DocumentNoteAdjustment::where('document_id',$note->id)->where('kind','fund')->where('status','applied')->get()->map(fn($r)=>$r->exactValue('amount'))))))) FiscalAmounts::error('amount','El ajuste supera el fondo disponible o la NC.');
                $values+=['guarantee_fund_id'=>$fund->id,'amount'=>$amount];
                $values['status']='applied'; $values['context']['original_fund']=$fund->toArray();
                $values['diagnostic']='Aplicación del fondo reducida con trazabilidad, sin salida de efectivo.';
                $record=DocumentNoteAdjustment::create($values);
                FiscalDocumentPersistence::updateSettlementState($invoice);
            } else {
                $tax=DocumentTax::where('tax_kind','IGTF')->whereNotNull('document_payment_id')->lockForUpdate()->findOrFail($data['source_id']);
                $payment=\App\Models\Tenant\DocumentPayment::whereIn('document_id',FiscalNote::collectionDocumentIds($invoice))->whereNull('receipt_parent_id')->whereNull('reversed_at')->lockForUpdate()->find($tax->document_payment_id);
                if (!$payment) FiscalAmounts::error('source_id','El cargo IGTF debe pertenecer a un cobro real vigente de la factura.');
                $amount=isset($data['amount']) ? D::of(D::input($data['amount'],'amount')) : D::convert($tax->exactValue('amount'),$tax->document->currency_type_id,$invoice->currency_type_id,$payment->exchange_rate);
                $used=D::sum(DocumentNoteAdjustment::where('tax_id',$tax->id)->whereNull('reversed_at')->get()->map(fn($r)=>$r->exactValue('amount')));
                $limit=D::convert($payment->exactValue('tax_amount'),$payment->currency_type_id,$invoice->currency_type_id,$payment->exchange_rate);
                if ($amount->isLessThanOrEqualTo(0) || $amount->isGreaterThan($limit->minus($used))) FiscalAmounts::error('amount','El ajuste supera el IGTF del cobro original aún disponible.');
                $values+=['tax_id'=>$tax->id,'amount'=>$amount];
                $values['context']+=['original_tax'=>$tax->toArray(),'document_payment_id'=>$payment->id,'original_payment'=>$payment->only(['id','operation_key','original_amount','tax_amount','exchange_rate','fiscal_context'])];
                $values['status']='blocked';
                $values['diagnostic']='Ajuste IGTF conservado. Requiere contrato HKA y aceptación DEMO verificable; su reembolso permanece bloqueado.';
                $record=DocumentNoteAdjustment::create($values);
            }
            DocumentEmissionView::invalidate();
            return $record;
        });
        self::resumeInvoice($note->note->affected_document);
        return $record->fresh();
    }

    public static function resumeInvoice(Document $invoice): void
    {
        foreach (DocumentNoteAdjustment::where('affected_document_id',$invoice->id)->whereIn('kind',['IVA','ISLR'])->where('status','pending')->get() as $adjustment) {
            $retention=DocumentReceivedRetention::find($adjustment->retention_id);
            if ($retention && $retention->application_status === 'reversed') {
                $remaining=FiscalNote::remaining($invoice,$adjustment->kind === 'IVA' ? 'total_igv':'total_value');
                $adjustment->update(['status'=>$remaining > 0 ? 'awaiting_support':'applied',
                    'diagnostic'=>$remaining > 0 ? 'Reversión conciliada. Registre el comprobante corregido real con su soporte.':'Reversión completa conciliada; la factura no conserva base para un reemplazo.']);
            }
        }
    }

    public static function replace(Document $note,DocumentNoteAdjustment $adjustment,array $input): void
    {
        self::authorize($note); abort_unless(auth()->user()->type === 'admin' || auth()->user()->create_payment,403);
        if ($adjustment->document_id !== $note->id || !in_array($adjustment->kind,['IVA','ISLR'],true)) abort(404);
        if (($input['tax_kind'] ?? $adjustment->kind) !== $adjustment->kind) FiscalAmounts::error('tax_kind','El comprobante corregido debe conservar el tipo de retención original.');
        self::resumeInvoice($note->note->affected_document);
        $note->getConnection()->transaction(function () use ($note,$adjustment,$input) {
            Company::query()->lockForUpdate()->firstOrFail();
            $invoice=Document::query()->lockForUpdate()->findOrFail($note->note->affected_document_id);
            NoteAnnulment::assertNotBusy($invoice);
            $adjustment=DocumentNoteAdjustment::query()->lockForUpdate()->findOrFail($adjustment->id);
            $service=$adjustment->kind === 'IVA' ? IvaRetentionService::class : IslrRetentionService::class;
            if ($adjustment->status === 'applied') {
                if (($adjustment->context['replacement_key'] ?? null) !== ($input['operation_key'] ?? null)) abort(409,'El ajuste ya tiene otro comprobante corregido.');
                $service::withNoteReplacement($note,fn()=> $service::store($invoice,$input)); return;
            }
            if ($adjustment->status !== 'awaiting_support') FiscalAmounts::error('adjustment','Conciliar la reversión es necesario antes de registrar un reemplazo.');
            $retention=$service::withNoteReplacement($note,fn()=> $service::store($invoice,$input));
            $context=$adjustment->context; $context['replacement_key']=$input['operation_key']; $context['replacement_id']=$retention->id;
            $adjustment->update(['status'=>'applied','context'=>$context,'diagnostic'=>'Comprobante corregido registrado; su estado HKA se consulta de forma independiente.']);
            DocumentEmissionView::invalidate();
        });
    }

    public static function view(Document $note): array
    {
        if (!FiscalNote::ordinary($note) || $note->document_type_id !== '07') return [];
        $invoice=$note->note->affected_document; $u=auth()->user();
        $can=$u instanceof \App\Models\Tenant\User && ($u->type === 'admin' || $u->establishment_id == $note->establishment_id);
        if (!$can) return [];
        $sources=[];
        foreach ($invoice->received_retentions()->where('application_status','applied')->get() as $r) $sources[]=['kind'=>$r->tax_kind,'id'=>$r->id,'description'=>$r->tax_kind.' '.$r->voucher_number,'amount'=>$r->applied_amount];
        if ($invoice->guarantee_fund) $sources[]=['kind'=>'fund','id'=>$invoice->guarantee_fund->id,'description'=>'Fondo de garantía','amount'=>$invoice->guarantee_amount];
        $payments=\App\Models\Tenant\DocumentPayment::whereIn('document_id',FiscalNote::collectionDocumentIds($invoice))->whereNull('reversed_at')->whereNull('receipt_parent_id')->pluck('id');
        foreach (DocumentTax::where('tax_kind','IGTF')->whereIn('document_payment_id',$payments)->get() as $t) $sources[]=['kind'=>'IGTF','id'=>$t->id,'description'=>'IGTF del cobro '.$t->document_payment_id,'amount'=>$t->amount];
        return ['can_create'=>$u->type === 'admin' || $u->delete_payment,'sources'=>$sources,
            'records'=>DocumentNoteAdjustment::where('document_id',$note->id)->get()->map(fn ($r)=>[
                'id'=>$r->id,'kind'=>$r->kind,'amount'=>$r->amount,'status'=>$r->status,'diagnostic'=>$r->diagnostic,
                'created_at'=>$r->created_at,'actor_id'=>$r->actor_id,'reason'=>$r->reason,
                'origin'=>['id'=>$r->retention_id ?? $r->guarantee_fund_id ?? $r->tax_id,
                    'description'=>$r->retention_id ? ($r->kind.' '.($r->context['original_voucher']['voucher_number'] ?? '')) : ($r->kind === 'fund' ? 'Fondo de garantía #'.$r->guarantee_fund_id : 'IGTF del cobro #'.($r->context['document_payment_id'] ?? ''))],
                'support_url'=>$r->retention_id && !empty($r->context['original_voucher']['attachment']) ? '/documents/retention/'.$r->retention_id.'/attachment' : null,
                'replacement_id'=>$r->context['replacement_id'] ?? null,
                'retention_id'=>$r->retention_id,'can_replace'=>$r->status === 'awaiting_support' && ($u->type === 'admin' || $u->create_payment)])->all()];
    }
}
