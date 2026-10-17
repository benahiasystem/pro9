<?php

namespace App\Services\Fiscal;

use App\Http\Controllers\Tenant\DocumentFiscalController;
use App\Models\Tenant\{Company, Document, DocumentReceivedRetention, Person, ReceivedRetentionUpload, User};
use App\Services\ExchangeRates\ExchangeRateMath;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Support\Exact\Decimal as D;
use Illuminate\Validation\ValidationException;

/** Transactional lifecycle shared by received IVA and ISLR; fiscal rules stay in each policy. */
abstract class ReceivedRetentionService
{
    private static array $noteReplacementScope = [];

    /** Only the locked note-adjustment workflow opens this scope, never posted flags. */
    public static function withNoteReplacement(Document $note, callable $work)
    {
        NoteAdjustment::authorize($note);
        $key=$note->getConnection()->getDatabaseName().':'.$note->note->affected_document_id;
        self::$noteReplacementScope[$key]=true;
        try { return $work(); } finally { unset(self::$noteReplacementScope[$key]); }
    }

    public static function settlementLimit(Document $document): \Brick\Math\BigRational
    {
        $key=$document->getConnection()->getDatabaseName().':'.$document->id;
        if (empty(self::$noteReplacementScope[$key])) return $document->exactBalance();
        // A corrected real voucher can coexist with cash pending return after an NC.
        // Its fiscal/base limits still apply; this does not manufacture a collection.
        return D::max(0,$document->exactValue('total')->minus(D::sum(FiscalNote::activeCredits($document)->get()->map(fn($r)=>$r->exactValue('total'))))->minus($document->exactRetentionAmount())->minus($document->exactGuaranteeAmount()));
    }
    private static array $schemas = [];

    abstract protected static function validationRules(): array;
    abstract protected static function validatePending(Document $document, array $input): void;
    abstract protected static function appliedTaxableBase(Document $document, DocumentReceivedRetention $retention): \Brick\Math\BigRational;

    public static function operationConflict(string $message): void
    {
        throw ValidationException::withMessages(['operation_key'=>$message])->status(409);
    }

    public static function available(?Document $document = null): bool
    {
        $db = ($document ?? new Document)->getConnection();
        $key = $db->getDatabaseName().':'.static::KIND;
        return static::$schemas[$key] ??= $db->getSchemaBuilder()->hasTable((new (static::OPERATION_MODEL))->getTable());
    }

    public static function authorize(Document $document, string $permission = 'create_payment'): void
    {
        DocumentFiscalController::authorizeDocument($document);
        $user = auth()->user();
        abort_unless($user->type === 'admin' || $user->$permission, 403);
    }

    public static function store(Document $document, array $input): DocumentReceivedRetention
    {
        static::authorize($document);
        if (!static::available($document)) FiscalAmounts::error('document', 'Esta instalación no tiene habilitado el flujo '.static::KIND.'.');
        $data = validator($input, static::validationRules())->validate();
        $data['fiscal_context'] = static::context($data['fiscal_context'] ?? []);
        $data['operation_key'] = strtolower($data['operation_key']);
        $data['exchange_rate'] = ExchangeRateMath::rate($data['exchange_rate']);
        foreach (['base','percentage','amount'] as $field) $data[$field] = \App\Support\Exact\Decimal::input($data[$field], $field);
        if (D::of($data['base'])->isLessThanOrEqualTo(0) || D::of($data['amount'])->isLessThanOrEqualTo(0)) FiscalAmounts::error('amount','Los importes deben ser positivos.');
        $data['subtrahend'] = \App\Support\Exact\Decimal::input($data['subtrahend'] ?? 0, 'subtrahend');
        $data['agent_id'] = $document->customer_id;
        $data['tax_kind'] = static::KIND;
        if (isset($input['agent_id']) && (int)$input['agent_id'] !== (int)$document->customer_id) FiscalAmounts::error('agent_id', 'El agente debe ser el cliente de la factura.');
        $hashData = $data; static::sort($hashData);
        $hash = hash('sha256', json_encode($hashData, JSON_THROW_ON_ERROR));
        $result = $document->getConnection()->transaction(function () use ($document, $data, $hash) {
            Company::query()->lockForUpdate()->firstOrFail();
            $document = Document::query()->lockForUpdate()->findOrFail($document->id);
            NoteAnnulment::assertNotBusy($document);
            if ($document->document_type_id !== '01' || $document->isVoidedOrRejected()) FiscalAmounts::error('document', 'Sólo se retiene una factura vigente.');
            $existing = DocumentReceivedRetention::where('operation_key', $data['operation_key'])->first();
            if ($existing) {
                if ((int)$existing->document_id !== (int)$document->id || $existing->tax_kind !== static::KIND || $existing->input_hash !== $hash) static::operationConflict('La clave ya pertenece a una solicitud diferente.');
                return $existing;
            }
            if (DocumentReceivedRetention::where('reversal_key',$data['operation_key'])->exists()) static::operationConflict('La clave ya pertenece a una reversión.');
            if ($document->received_retentions()->where('tax_kind', static::KIND)->whereIn('application_status', ['pending_validation','applied'])->exists()) FiscalAmounts::error('voucher_number', 'Ya existe un comprobante '.static::KIND.' activo. Reviértalo antes de registrar el reemplazo.');
            $input = $data;
            if (!empty($data['upload_id'])) $input['attachment'] = static::consumeUpload($document, $data['upload_id']);
            static::validatePending($document, $input);
            $retention = $document->received_retentions()->create([
                'tax_kind' => static::KIND, 'operation_key' => $data['operation_key'], 'input_hash' => $hash,
                'application_status' => 'pending_validation', 'voucher_number' => $data['voucher_number'],
                'voucher_date' => $data['voucher_date'], 'agent_id' => $document->customer_id,
                'agent' => Person::findOrFail($document->customer_id)->only(['number','name','address']), 'agent_number' => $data['agent_rif'],
                'concept_id' => $data['concept_id'] ?? null, 'subtrahend' => $data['subtrahend'],
                'base' => $data['base'], 'percentage' => $data['percentage'], 'amount' => $data['amount'],
                'currency_type_id' => $data['currency_type_id'], 'exchange_rate' => $data['exchange_rate'],
                'attachment' => $input['attachment'], 'input_snapshot' => $input, 'applied_amount' => 0,
                'fiscal_context' => ['actor_id' => auth()->id()],
            ]);
            if (static::invoiceReady($document)) static::activate($document, $retention);
            return $retention->fresh();
        });
        (static::HKA)::afterCommit($result);
        return $result->fresh();
    }

    protected static function sort(array &$data): void
    {
        ksort($data);
        foreach ($data as &$value) if (is_array($value)) static::sort($value);
    }

    public static function invoiceReady(Document $document): bool
    {
        return $document->control_number && ($document->fiscal_emission_mode !== 'digital' || ($document->emission->status ?? '') === 'confirmed');
    }

    public static function activate(Document $document, DocumentReceivedRetention $retention): void
    {
        $field = static::PREVISION;
        $input = $retention->input_snapshot;
        static::validatePending($document, $input);
        FiscalDocumentPersistence::retentionValidated($document, $input, $retention);
        $retention->taxable_base = static::appliedTaxableBase($document,$retention);
        $retention->application_status = 'applied';
        $retention->diagnostic = null;
        $prevision = $document->fiscal_data->$field ?? null;
        if ($prevision) {
            $retention->fiscal_context = array_replace($retention->fiscal_context,['prevision'=>$prevision]);
            $document->fiscal_data()->update([$field=>null]);
            $document->unsetRelation('fiscal_data');
        }
        $retention->save();
        FiscalDocumentPersistence::updateSettlementState($document);
        static::syncCreditBalance($document);
        if ($document->fiscal_emission_mode === 'digital' && $document->fiscal_environment === 'demo') (static::HKA)::enqueue($document, $retention, 'apply', $retention->operation_key);
    }

    public static function resume(Document $document): void
    {
        if (!static::available($document) || !static::invoiceReady($document)) return;
        foreach ($document->received_retentions()->where('tax_kind',static::KIND)->where('application_status','pending_validation')->get() as $candidate) {
            $document->getConnection()->transaction(function () use ($document, $candidate) {
                Company::query()->lockForUpdate()->firstOrFail();
                $locked = Document::query()->lockForUpdate()->findOrFail($document->id);
                NoteAnnulment::assertNotBusy($locked);
                $retention = DocumentReceivedRetention::query()->lockForUpdate()->findOrFail($candidate->id);
                if ($retention->application_status !== 'pending_validation' || !static::invoiceReady($locked)) return;
                try { $locked->getConnection()->transaction(fn()=>static::activate($locked, $retention)); }
                catch (ValidationException $exception) {
                    // A later payload validation may fail after local writes; the savepoint above
                    // rolls them back before preserving the voucher as invalid.
                    $retention->refresh()->update(['application_status' => 'invalid','applied_amount'=>0, 'diagnostic' => implode(' ', array_merge(...array_values($exception->errors()))) ]);
                    FiscalDocumentPersistence::updateSettlementState($locked);
                }
            });
            (static::HKA)::afterCommit($candidate);
        }
    }

    public static function reverse(DocumentReceivedRetention $retention, array $input): void
    {
        abort_unless($retention->tax_kind === static::KIND,422);
        static::authorize($retention->document, 'delete_payment');
        $data = validator($input, ['operation_key'=>'required|uuid','reason'=>'required|string|max:255'])->validate();
        $data['operation_key'] = strtolower($data['operation_key']);
        if (trim($data['reason']) === '') FiscalAmounts::error('reason', 'Indique el motivo de reversión.');
        $retention->getConnection()->transaction(function () use ($retention, $data) {
            Company::query()->lockForUpdate()->firstOrFail();
            $document = Document::query()->lockForUpdate()->findOrFail($retention->document_id);
            NoteAnnulment::assertNotBusy($document);
            $retention = DocumentReceivedRetention::query()->lockForUpdate()->findOrFail($retention->id);
            if ($retention->reversal_key) {
                if ($retention->reversal_key !== $data['operation_key'] || $retention->reversal_reason !== $data['reason']) static::operationConflict('La reversión ya tiene otra operación o motivo.');
                return;
            }
            if (DocumentReceivedRetention::where('operation_key',$data['operation_key'])->orWhere('reversal_key',$data['operation_key'])->exists()) static::operationConflict('La clave ya pertenece a otra solicitud de retención.');
            $retention->update(['reversal_key'=>$data['operation_key'], 'reversal_reason'=>$data['reason'], 'reversed_by'=>auth()->id()]);
            $remote = $retention->{static::RELATION}()->where('action','apply')->where(fn($q)=>$q->where('attempts','>',0)->orWhereIn('status',['confirmed','pending','uncertain','conflict']))->exists();
            if ($remote) (static::HKA)::enqueue($document, $retention, 'remove', $data['operation_key']);
            else {
                $retention->{static::RELATION}()->where('action','apply')->update(['status'=>'cancelled','attempt_id'=>null]);
                static::finishReversal($document, $retention);
            }
        });
        (static::HKA)::afterCommit($retention);
    }

    public static function finishReversal(Document $document, DocumentReceivedRetention $retention): void
    {
        $field = static::PREVISION;
        if ($retention->application_status === 'reversed') return;
        $context = $retention->fiscal_context ?? [];
        $context['reversal'] = ['applied_amount'=>$retention->applied_amount,'actor_id'=>$retention->reversed_by,'reason'=>$retention->reversal_reason];
        $retention->update(['application_status'=>'reversed','applied_amount'=>0,'reversed_at'=>now(),'fiscal_context'=>$context]);
        if (!empty($context['prevision'])) $document->fiscal_data()->update([$field=>json_encode($context['prevision'],JSON_THROW_ON_ERROR)]);
        FiscalDocumentPersistence::updateSettlementState($document);
        static::syncCreditBalance($document);
        $document->getConnection()->afterCommit(fn()=>NoteAdjustment::resumeInvoice($document));
    }

    protected static function syncCreditBalance(Document $document): void
    {
        // Clearing a receivable with a fiscal voucher is not a receipt of cash.
        \App\Models\Tenant\CashDocumentCredit::where('document_id',$document->id)
            ->update(['status'=>$document->fresh()->balance <= 0.005 ? 'PROCESSED' : 'PENDING']);
    }

    public static function consumeUpload(Document $document, string $id): string
    {
        $upload = ReceivedRetentionUpload::query()->where('token',$id)->lockForUpdate()->first();
        if (!$upload || (int)$upload->user_id !== (int)auth()->id() || $upload->expires_at->isPast()
            || $upload->document_id) FiscalAmounts::error('upload_id', 'El adjunto venció o pertenece a otra solicitud.');
        $target = 'received_retentions/'.$document->id.'/'.basename($upload->path);
        if (!Storage::disk('tenant')->exists($upload->path)) FiscalAmounts::error('upload_id','El adjunto no está disponible.');
        // Copy preserves the temporary source if the outer commercial transaction rolls back.
        if (!Storage::disk('tenant')->copy($upload->path, $target)) FiscalAmounts::error('upload_id','No se pudo vincular el adjunto.');
        $upload->update(['document_id'=>$document->id]);
        return $target;
    }

    public static function view(Document $document): array
    {
        $field = static::PREVISION;
        if (!static::available($document)) return ['enabled'=>false,'prevision'=>null,'records'=>[]];
        $document->loadMissing(['fiscal_data','received_retentions.'.static::RELATION]);
        $user = auth()->user();
        $scope = $user instanceof User && ($user->type === 'admin' || (int)$user->establishment_id === (int)$document->establishment_id);
        if ($user && !$scope) return ['enabled'=>false,'prevision'=>null,'records'=>[]];
        $canCreate = $scope && ($user->type === 'admin' || $user->create_payment);
        $canReverse = $scope && ($user->type === 'admin' || $user->delete_payment);
        $digitalDemo = $document->fiscal_emission_mode === 'digital' && $document->fiscal_environment === 'demo';
        $records = $document->received_retentions->where('tax_kind',static::KIND)->map(function ($row) use ($canCreate,$canReverse,$digitalDemo,$scope) {
            $op = $row->{static::RELATION}->sortByDesc('id')->first();
            return ['id'=>$row->id,'operation_key'=>$row->operation_key,'hka_operation_key'=>$op->operation_key ?? null,'voucher_number'=>$row->voucher_number,
                'voucher_date'=>$row->voucher_date->format('Y-m-d'),'agent_rif'=>$row->agent_number,'taxable_base'=>$row->taxable_base,
                (static::KIND === 'IVA' ? 'iva_amount' : 'base')=>$row->base,'concept_id'=>$row->concept_id,'subtrahend'=>$row->subtrahend,'exchange_rate'=>$row->exchange_rate,'percentage'=>$row->percentage,'amount'=>$row->amount,'applied_amount'=>$row->applied_amount,
                'tax_kind'=>static::KIND,'invoice_number'=>$row->input_snapshot['invoice_number'] ?? ($row->fiscal_context['invoice_number'] ?? null),'invoice_control'=>$row->input_snapshot['invoice_control'] ?? ($row->fiscal_context['invoice_control'] ?? null),'currency_type_id'=>$row->currency_type_id,'application_status'=>$row->application_status,'verified_fields'=>static::KIND === 'ISLR' && ($op->status ?? '') === 'confirmed' ? HkaIslrRetentionPayload::VERIFIED_FIELDS : [],
                'hka_status'=>$op->status ?? ($digitalDemo && $row->application_status === 'pending_validation' ? 'waiting_invoice' : 'not_applicable'),'diagnostic'=>$row->diagnostic ?: ($op->diagnostic ?? null),
                'reversal_pending'=>!empty($row->reversal_key) && !$row->reversed_at,
                'can_reverse'=>$canReverse && !$row->reversal_key,
                'can_query'=>$scope && $op !== null,'can_retry'=>$op && ($op->action === 'remove' ? $canReverse : $canCreate) && in_array($op->status,['waiting','uncertain'],true),
                'has_attachment'=>(bool)$row->attachment];
        })->values()->all();
        return ['enabled'=>true,'prevision'=>$document->fiscal_data->$field ?? null,
            'applied_amount'=>D::represent(D::sum($document->received_retentions->where('tax_kind',static::KIND)->map(fn($r)=>$r->exactValue('applied_amount')))),
            'balance'=>$document->balance,'can_create'=>$canCreate,'records'=>$records];
    }

    protected static function validateSupport(Document $document, array $input): void
    {
        $path = $input['attachment'] ?? '';
        if (!$path || !str_starts_with($path, 'received_retentions/'.$document->id.'/') || str_contains($path, '..') || !Storage::disk('tenant')->exists($path)) FiscalAmounts::error('attachment', 'Adjunte un comprobante privado válido para esta factura.');
    }
}
