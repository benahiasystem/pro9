<?php
namespace App\Services\Fiscal;

use App\Models\Tenant\{Company, Document, DocumentNoteRefund, DocumentNoteAdjustment, DocumentPayment, PaymentMethodType, BankAccount, Cash};
use App\Services\ExchangeRates\ExchangeRateMath as Math;
use App\Http\Controllers\Tenant\DocumentFiscalController;
use Modules\Finance\Traits\FinanceTrait;

final class NoteRefund
{
    use FinanceTrait;

    public static function eligible(Document $note): void
    {
        DocumentFiscalController::authorizeDocument($note);
        if (!FiscalNote::ordinary($note) || $note->document_type_id !== '07' || $note->isVoidedOrRejected()) FiscalAmounts::error('note','Seleccione una NC vigente registrada desde una factura.');
        if ($note->fiscal_emission_mode !== 'digital' || $note->fiscal_environment !== 'demo' || optional($note->emission)->status !== 'confirmed' || !$note->control_number
            || $note->control_number !== optional($note->emission)->control_number) FiscalAmounts::error('note','Confirme la NC digital DEMO en HKA antes de registrar una devolución de dinero.');
    }

    public static function available(Document $note): float
    {
        $invoice=$note->note->affected_document;
        $cash=$invoice->payments()->whereNull('reversed_at')->whereNull('receipt_parent_id')->sum('payment');
        $refunded=FiscalNote::refunded($invoice);
        $commercial=max(0,$invoice->total-$invoice->taxes->where('tax_kind','IGTF')->sum('amount'));
        $remainingCommercial=max(0,$commercial-FiscalNote::credits($invoice));
        $corrected=DocumentNoteAdjustment::where('affected_document_id',$invoice->id)->whereIn('kind',['IVA','ISLR'])->get()
            ->map(fn ($r)=>$r->context['replacement_id'] ?? null)->filter()->all();
        // Pending vouchers retain their settlement effect, but their credited share is not cash available to refund.
        $retentions=$invoice->received_retentions->sum(function ($r) use ($invoice,$corrected) {
            if (in_array($r->id,$corrected,true)) return $r->applied_amount;
            $field=$r->tax_kind === 'IVA' ? 'total_igv':'total_value';
            $original=(float)$invoice->$field;
            if ($r->tax_kind === 'ISLR' && $original > 0) {
                $base=Math::rational($r->base)->multipliedBy(FiscalNote::remaining($invoice,$field))->dividedBy($original);
                $amount=Math::finalAmount($base->multipliedBy($r->percentage)->dividedBy(100)->minus($r->subtrahend ?? 0));
                return min($r->applied_amount,max(0,FiscalAmounts::convert($amount,$r->currency_type_id,$invoice->currency_type_id,$r->exchange_rate)));
            }
            return $original > 0 ? min($r->applied_amount,round($r->applied_amount*FiscalNote::remaining($invoice,$field)/$original,2)) : 0;
        });
        $fund=min($invoice->guarantee_amount,$commercial > 0 ? round((float)optional($invoice->guarantee_fund)->amount*$remainingCommercial/$commercial,2) : 0);
        $receivable=max(0,$remainingCommercial-$retentions-$fund);
        $remaining=max(0,$cash-$refunded-$receivable);
        $noteRemaining=max(0,$note->total-DocumentNoteRefund::where('document_id',$note->id)->where('component','principal')->whereNull('reversed_at')->sum('amount'));
        return round(min($remaining,$noteRemaining),2);
    }

    public static function view(Document $note): array
    {
        if (!FiscalNote::ordinary($note) || $note->document_type_id !== '07') return ['available'=>false];
        $reason=null;
        try { self::eligible($note); } catch (\Illuminate\Validation\ValidationException $e) { $reason=collect($e->errors())->flatten()->first(); }
        $u=auth()->user();
        $scope=$u instanceof \App\Models\Tenant\User && ($u->type === 'admin' || $u->establishment_id == $note->establishment_id);
        $can=$scope && ($u->type === 'admin' || $u->create_payment);
        return ['available'=>true,'can_create'=>$can && !$reason && self::available($note)>0,'block_reason'=>$reason,
            'amount_available'=>$scope ? self::available($note) : 0,'currency_type_id'=>$note->currency_type_id,
            'records'=>$scope ? DocumentNoteRefund::where('document_id',$note->id)->orderBy('id')->get()->map(fn ($r)=>[
                'id'=>$r->id,'component'=>$r->component,'date'=>$r->date_of_payment->format('Y-m-d'),'amount'=>$r->amount,
                'original_amount'=>$r->original_amount,'currency_type_id'=>$r->currency_type_id,'exchange_rate'=>$r->exchange_rate,
                'exchange_rate_source'=>$r->exchange_rate_source,'source_payment_id'=>$r->document_payment_id,
                'registered_at'=>$r->created_at,'actor_id'=>$r->actor_id,'reversed_by'=>$r->reversed_by,'reversal_reason'=>$r->reversal_reason,
                'payment_method'=>$r->context['payment_method']['description'] ?? null,'destination'=>optional($r->global_payment)->getDestinationDescriptionPayment(),
                'reference'=>$r->reference,'reversed_at'=>$r->reversed_at,'can_reverse'=>!$r->reversed_at && ($u->type === 'admin' || $u->delete_payment)])->all() : [],
            'sources'=>$scope ? DocumentPayment::whereIn('document_id',FiscalNote::collectionDocumentIds($note->note->affected_document))->whereNull('reversed_at')->whereNull('receipt_parent_id')->get()->map(fn ($p)=>[
                'id'=>$p->id,'date'=>$p->date_of_payment->format('Y-m-d'),'principal'=>$p->payment,'original_amount'=>$p->original_amount,
                'currency_type_id'=>$p->currency_type_id,'tax_amount'=>$p->tax_amount,'principal_eligible'=>(int)$p->document_id === (int)$note->note->affected_document_id])->all() : []];
    }

    public function register(Document $note,array $input): DocumentNoteRefund
    {
        self::eligible($note);
        abort_unless(auth()->user()->type === 'admin' || auth()->user()->create_payment,403);
        $data=validator($input,['operation_key'=>'required|uuid','document_payment_id'=>'required|integer|min:1',
            'component'=>'required|in:principal,IGTF','adjustment_id'=>'nullable|integer|min:1','date_of_payment'=>'required|date|after_or_equal:'.$note->date_of_issue->format('Y-m-d'),
            'payment_method_type_id'=>'required|string','payment_destination_id'=>'required','currency_type_id'=>'required|in:VES,USD',
            'original_amount'=>'required|numeric|gt:0','exchange_rate'=>'required','exchange_rate_source'=>'required|string|max:50','reference'=>'nullable|string|max:255'])->validate();
        $data['exchange_rate']=Math::rate($data['exchange_rate']);
        $data['original_amount']=Math::finalAmount($data['original_amount']);
        $hash=hash('sha256',json_encode($data,JSON_THROW_ON_ERROR));
        return $note->getConnection()->transaction(function () use ($note,$data,$hash) {
            Company::query()->lockForUpdate()->firstOrFail();
            $invoice=Document::query()->lockForUpdate()->findOrFail($note->note->affected_document_id);
            $note=Document::query()->lockForUpdate()->findOrFail($note->id); self::eligible($note);
            $existing=DocumentNoteRefund::where('operation_key',$data['operation_key'])->first();
            if ($existing) { if ($existing->document_id !== $note->id || $existing->request_hash !== $hash || $existing->reversed_at) abort(409,'La clave de devolución ya pertenece a otra operación.'); return $existing; }
            if (DocumentNoteRefund::where('reversal_key',$data['operation_key'])->exists()) abort(409,'La clave ya identifica una reversión.');
            $source=DocumentPayment::whereIn('document_id',$data['component'] === 'principal' ? [$invoice->id] : FiscalNote::collectionDocumentIds($invoice))->whereNull('receipt_parent_id')->whereNull('reversed_at')->lockForUpdate()->find($data['document_payment_id']);
            if (!$source) FiscalAmounts::error('document_payment_id','Seleccione un cobro real vigente de la factura original.');
            $method=PaymentMethodType::where('is_active',1)->find($data['payment_method_type_id']);
            if (!$method) FiscalAmounts::error('payment_method_type_id','Seleccione un medio de pago activo.');
            $amount=FiscalAmounts::convert($data['original_amount'],$data['currency_type_id'],$invoice->currency_type_id,$data['exchange_rate']);
            if ($amount <= 0) FiscalAmounts::error('original_amount','El importe convertido debe ser positivo.');
            $used=DocumentNoteRefund::where('document_payment_id',$source->id)->where('component',$data['component'])->whereNull('reversed_at')->sum('amount');
            if ($data['component'] === 'principal') {
                if ($amount > self::available($note)+0.005 || $amount > $source->payment-$used+0.005) FiscalAmounts::error('original_amount','La devolución supera el excedente, la NC o el cobro original disponible.');
            } else {
                $adjustment=DocumentNoteAdjustment::where('document_id',$note->id)->where('kind','IGTF')->where('status','confirmed')->lockForUpdate()->find($data['adjustment_id'] ?? 0);
                if (!$adjustment || ($adjustment->context['document_payment_id'] ?? null) != $source->id) FiscalAmounts::error('adjustment_id','El ajuste IGTF requiere un contrato y confirmación fiscal verificables antes de devolver su importe.');
                $tax=FiscalAmounts::convert($source->tax_amount,$source->currency_type_id,$invoice->currency_type_id,$source->exchange_rate);
                if ($amount > min($tax,$adjustment->amount)-$used+0.005) FiscalAmounts::error('original_amount','La devolución supera el IGTF confirmado disponible.');
            }
            $destination=$this->getDestinationRecord($data);
            if ($destination['destination_type'] === BankAccount::class) {
                $bank=BankAccount::query()->lockForUpdate()->findOrFail($destination['destination_id']);
                if ($bank->currency_type_id !== $data['currency_type_id'] || $bank->establishment_id != $note->establishment_id) FiscalAmounts::error('payment_destination_id','La cuenta debe corresponder a la moneda y sucursal de la devolución.');
            } else {
                $cash=Cash::query()->lockForUpdate()->findOrFail($destination['destination_id']);
                if (!$cash->state || $cash->user->establishment_id != $note->establishment_id) FiscalAmounts::error('payment_destination_id','Seleccione una caja abierta de la sucursal de la nota.');
            }
            $record=DocumentNoteRefund::create(array_diff_key($data,['payment_destination_id'=>true])+[
                'document_id'=>$note->id,'affected_document_id'=>$invoice->id,'request_hash'=>$hash,'amount'=>$amount,'actor_id'=>auth()->id(),
                'context'=>['source_payment'=>$source->only(['id','operation_key','currency_type_id','original_amount','tax_amount','exchange_rate']),
                    'payment_method'=>$method->only(['id','description','hka_code']),'destination'=>$destination,'emission_key'=>$note->emission->operation_key]]);
            $record->global_payment()->create($destination+['user_id'=>auth()->id(),'fiscal_environment'=>$note->fiscal_environment]);
            FiscalDocumentPersistence::updateSettlementState($invoice); DocumentEmissionView::invalidate();
            return $record;
        });
    }

    public function reverse(Document $note,DocumentNoteRefund $refund,array $input): void
    {
        self::eligible($note); abort_unless(auth()->user()->type === 'admin' || auth()->user()->delete_payment,403);
        if ($refund->document_id !== $note->id) abort(404);
        $data=validator($input,['operation_key'=>'required|uuid','reason'=>'required|string|max:255'])->validate();
        $note->getConnection()->transaction(function () use ($note,$refund,$data) {
            Company::query()->lockForUpdate()->firstOrFail();
            $invoice=Document::query()->lockForUpdate()->findOrFail($note->note->affected_document_id);
            Document::query()->lockForUpdate()->findOrFail($note->id);
            $refund=DocumentNoteRefund::query()->lockForUpdate()->findOrFail($refund->id);
            if ($refund->reversal_key) { if ($refund->reversal_key !== $data['operation_key'] || $refund->reversal_reason !== $data['reason']) abort(409,'La devolución ya tiene otra reversión.'); return; }
            if (DocumentNoteRefund::where('operation_key',$data['operation_key'])->orWhere('reversal_key',$data['operation_key'])->exists()) abort(409,'La clave pertenece a otra operación.');
            $refund->forceFill(['reversed_at'=>now(),'reversed_by'=>auth()->id(),'reversal_key'=>$data['operation_key'],'reversal_reason'=>$data['reason']])->save();
            FiscalDocumentPersistence::updateSettlementState($invoice); DocumentEmissionView::invalidate();
        });
    }
}
