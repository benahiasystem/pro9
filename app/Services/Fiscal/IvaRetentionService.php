<?php

namespace App\Services\Fiscal;

use App\Http\Controllers\Tenant\DocumentFiscalController;
use App\Models\Tenant\{Company, Document, DocumentReceivedRetention, Person, ReceivedRetentionUpload, User};
use App\Services\ExchangeRates\ExchangeRateMath;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Shared commercial lifecycle. A forecast or an unverified voucher never settles a debt. */
final class IvaRetentionService
{
    private static array $schemas = [];

    public static function operationConflict(string $message): void
    {
        throw ValidationException::withMessages(['operation_key'=>$message])->status(409);
    }

    public static function available(?Document $document = null): bool
    {
        $db = ($document ?? new Document)->getConnection();
        $key = $db->getDatabaseName();
        return self::$schemas[$key] ??= $db->getSchemaBuilder()->hasTable('document_iva_retention_operations');
    }

    public static function authorize(Document $document, string $permission = 'create_payment'): void
    {
        DocumentFiscalController::authorizeDocument($document);
        $user = auth()->user();
        abort_unless($user->type === 'admin' || $user->$permission, 403);
    }

    public static function context(array $input): array
    {
        FiscalDocumentPersistence::assertNoSecrets($input);
        return validator($input, [
            'iva_100_basis' => 'nullable|in:'.implode(',', SpecialTaxpayerPolicy::FULL_IVA),
            'evidence_reference' => 'nullable|string|max:255', 'exclusion' => 'nullable|string|max:255',
            'public_operation_scope' => 'nullable|in:subject,excluded', 'public_scope_reference' => 'nullable|string|max:255',
        ])->validate();
    }

    public static function proposal(int $customerId, string $date, $iva, $taxable, string $currency, array $context = []): array
    {
        $context = self::context($context);
        $issuer = FiscalProfileService::at(Company::firstOrFail(), $date);
        $buyer = FiscalProfileService::at(Person::whereType('customers')->findOrFail($customerId), $date);
        $decision = SpecialTaxpayerPolicy::retention($issuer, $buyer, $date, $context);
        if ((float)$iva <= 0) { $decision['eligible']=false; $decision['percentage']=0; }
        $amount = $decision['eligible'] ? ExchangeRateMath::rational($iva)->multipliedBy($decision['percentage'])->dividedBy(100) : 0;
        return ['eligible' => $decision['eligible'], 'percentage' => $decision['percentage'],
            'amount' => ExchangeRateMath::finalAmount($amount), 'iva_amount' => ExchangeRateMath::finalAmount($iva),
            'taxable_base' => ExchangeRateMath::finalAmount($taxable), 'currency_type_id' => $currency,
            'agent_rif' => $buyer['rif'] ?? '', 'date' => $date, 'fiscal_context' => $context,
            'issuer_version_id' => $issuer['version_id'] ?? null, 'buyer_version_id' => $buyer['version_id'] ?? null];
    }

    public static function savePrevision(Document $document, ?array $input): void
    {
        if ($input === null) return;
        if (!self::available($document)) FiscalAmounts::error('iva_retention_prevision', 'Esta instalación no tiene habilitado el flujo IVA.');
        self::authorize($document);
        $data = validator($input, ['enabled' => 'required|boolean', 'fiscal_context' => 'nullable|array'])->validate();
        $prevision = null;
        if ($data['enabled']) {
            if ($document->document_type_id !== '01' || $document->total_igv <= 0 || $document->isVoidedOrRejected()) FiscalAmounts::error('document', 'La previsión IVA requiere una factura vigente con IVA.');
            $prevision = self::proposal($document->customer_id, $document->date_of_issue->format('Y-m-d'), $document->total_igv,
                $document->total_taxed, $document->currency_type_id, $data['fiscal_context'] ?? []);
            if (!$prevision['eligible']) FiscalAmounts::error('customer_id', 'El cliente o la operación no acreditan retención IVA.');
            $prevision += ['actor_id' => auth()->id(), 'saved_at' => now()->toIso8601String()];
        }
        $document->fiscal_data()->update(['iva_retention_prevision' => $prevision === null ? null : json_encode($prevision, JSON_THROW_ON_ERROR)]);
        $document->unsetRelation('fiscal_data');
    }

    public static function store(Document $document, array $input): DocumentReceivedRetention
    {
        self::authorize($document);
        if (!self::available($document)) FiscalAmounts::error('document', 'Esta instalación no tiene habilitado el flujo IVA.');
        $data = validator($input, [
            'operation_key' => 'required|uuid', 'voucher_number' => 'required|string|regex:/^[0-9]{14}$/',
            'voucher_date' => 'required|date_format:Y-m-d', 'agent_rif' => 'required|string|max:12',
            'invoice_number' => 'required|string|max:40', 'invoice_control' => 'required|string|max:32',
            'base' => 'required|numeric|gt:0', 'percentage' => 'required|numeric|in:75,100',
            'amount' => 'required|numeric|gt:0', 'subtrahend' => 'nullable|numeric|in:0',
            'currency_type_id' => 'required|in:VES,USD', 'exchange_rate' => 'required|numeric|gt:0',
            'attachment' => 'nullable|string|max:255', 'upload_id' => 'nullable|uuid', 'fiscal_context' => 'nullable|array',
        ])->validate();
        $data['fiscal_context'] = self::context($data['fiscal_context'] ?? []);
        $data['operation_key'] = strtolower($data['operation_key']);
        $data['exchange_rate'] = ExchangeRateMath::rate($data['exchange_rate']);
        foreach (['base','percentage','amount'] as $field) $data[$field] = ExchangeRateMath::finalAmount($data[$field]);
        if ((float)$data['base'] <= 0 || (float)$data['amount'] <= 0) FiscalAmounts::error('amount','Los importes deben ser positivos a dos decimales.');
        $data['subtrahend'] = '0.00';
        $data['agent_id'] = $document->customer_id;
        $data['tax_kind'] = 'IVA';
        if (isset($input['agent_id']) && (int)$input['agent_id'] !== (int)$document->customer_id) FiscalAmounts::error('agent_id', 'El agente debe ser el cliente de la factura.');
        $hashData = $data; self::sort($hashData);
        $hash = hash('sha256', json_encode($hashData, JSON_THROW_ON_ERROR));
        $result = $document->getConnection()->transaction(function () use ($document, $data, $hash) {
            Company::query()->lockForUpdate()->firstOrFail();
            $document = Document::query()->lockForUpdate()->findOrFail($document->id);
            if ($document->document_type_id !== '01' || $document->isVoidedOrRejected()) FiscalAmounts::error('document', 'Sólo se retiene una factura vigente.');
            $existing = DocumentReceivedRetention::where('operation_key', $data['operation_key'])->first();
            if ($existing) {
                if ((int)$existing->document_id !== (int)$document->id || $existing->input_hash !== $hash) self::operationConflict('La clave ya pertenece a una solicitud diferente.');
                return $existing;
            }
            if (DocumentReceivedRetention::where('reversal_key',$data['operation_key'])->exists()) self::operationConflict('La clave ya pertenece a una reversión.');
            if ($document->received_retentions()->where('tax_kind', 'IVA')->whereIn('application_status', ['pending_validation','applied'])->exists()) FiscalAmounts::error('voucher_number', 'Ya existe un comprobante IVA activo. Reviértalo antes de registrar el reemplazo.');
            $input = $data;
            if (!empty($data['upload_id'])) $input['attachment'] = self::consumeUpload($document, $data['upload_id']);
            self::validatePending($document, $input);
            $retention = $document->received_retentions()->create([
                'tax_kind' => 'IVA', 'operation_key' => $data['operation_key'], 'input_hash' => $hash,
                'application_status' => 'pending_validation', 'voucher_number' => $data['voucher_number'],
                'voucher_date' => $data['voucher_date'], 'agent_id' => $document->customer_id,
                'agent' => Person::findOrFail($document->customer_id)->only(['number','name','address']), 'agent_number' => $data['agent_rif'],
                'base' => $data['base'], 'percentage' => $data['percentage'], 'amount' => $data['amount'],
                'currency_type_id' => $data['currency_type_id'], 'exchange_rate' => $data['exchange_rate'],
                'attachment' => $input['attachment'], 'input_snapshot' => $input, 'applied_amount' => 0,
                'fiscal_context' => ['actor_id' => auth()->id()],
            ]);
            if (self::invoiceReady($document)) self::activate($document, $retention);
            return $retention->fresh();
        });
        HkaIvaRetention::afterCommit($result);
        return $result->fresh();
    }

    public static function refreshPrevision(Document $document): void
    {
        if (!self::available($document)) return;
        $saved = $document->fiscal_data->iva_retention_prevision ?? null;
        if (!$saved) return;
        $proposal = self::proposal($document->customer_id,$document->date_of_issue->format('Y-m-d'),$document->total_igv,
            $document->total_taxed,$document->currency_type_id,$saved['fiscal_context'] ?? []);
        $value = $proposal['eligible'] && $document->total_igv > 0 ? array_replace($saved,$proposal) : null;
        $document->fiscal_data()->update(['iva_retention_prevision'=>$value ? json_encode($value,JSON_THROW_ON_ERROR) : null]);
        $document->unsetRelation('fiscal_data');
    }

    private static function sort(array &$data): void
    {
        ksort($data);
        foreach ($data as &$value) if (is_array($value)) self::sort($value);
    }

    private static function validatePending(Document $document, array $input): void
    {
        if ($document->total_igv <= 0) FiscalAmounts::error('document','La factura no tiene IVA sujeto a retención.');
        $date = $input['voucher_date'];
        if ($date < $document->date_of_issue->format('Y-m-d') || $date > now()->format('Y-m-d')
            || substr($input['voucher_number'],0,6) !== str_replace('-', '', substr($date,0,7))) FiscalAmounts::error('voucher_date', 'La fecha y AAAAMM del comprobante deben corresponder a la factura y no ser futuros.');
        $proposal = self::proposal($document->customer_id, $date, $document->total_igv, $document->total_taxed, $document->currency_type_id, $input['fiscal_context']);
        if (!$proposal['eligible'] || (float)$input['percentage'] !== (float)$proposal['percentage'] || $input['agent_rif'] !== $proposal['agent_rif']) FiscalAmounts::error('agent_rif', 'El agente, porcentaje u operación no corresponden al perfil fiscal vigente.');
        $issuer = FiscalProfileService::at(Company::firstOrFail(), $date);
        if ($proposal['percentage'] === 100 && empty($input['fiscal_context']['evidence_reference']) && empty($issuer['evidence_reference'])) FiscalAmounts::error('fiscal_context', 'El 100 % requiere evidencia.');
        $expected = ExchangeRateMath::finalAmount(ExchangeRateMath::rational($input['base'])->multipliedBy($input['percentage'])->dividedBy(100));
        if (!ExchangeRateMath::rational($input['amount'])->isEqualTo(ExchangeRateMath::rational($expected))) FiscalAmounts::error('amount', 'El importe debe corresponder al porcentaje del IVA retenido.');
        $base = FiscalAmounts::convert($input['base'], $input['currency_type_id'], $document->currency_type_id, $input['exchange_rate']);
        $applied = FiscalAmounts::convert($input['amount'], $input['currency_type_id'], $document->currency_type_id, $input['exchange_rate']);
        $fiscalIva = FiscalAmounts::convert($document->total_igv,$document->currency_type_id,'VES',$document->exchange_rate_sale);
        if ($fiscalIva <= 0) FiscalAmounts::error('base','El IVA fiscal en VES debe ser positivo a dos decimales.');
        $voucherIva = FiscalAmounts::convert($input['base'],$input['currency_type_id'],'VES',$input['exchange_rate']);
        $voucherAmount = FiscalAmounts::convert($input['amount'],$input['currency_type_id'],'VES',$input['exchange_rate']);
        if ($voucherIva > $fiscalIva + 0.01 || $voucherAmount > round($fiscalIva * $proposal['percentage'] / 100,2) + 0.01
            || $applied > $document->balance + 0.005) FiscalAmounts::error('amount', 'La retención supera el IVA fiscal conservado o el saldo disponible.');
        self::voucherTaxableBase($document,$input['base'],$input['currency_type_id'],$input['exchange_rate']);
        $path = $input['attachment'] ?? '';
        if (!$path || !str_starts_with($path, 'received_retentions/'.$document->id.'/') || str_contains($path, '..') || !Storage::disk('tenant')->exists($path)) FiscalAmounts::error('attachment', 'Adjunte un comprobante privado válido para esta factura.');
    }

    public static function invoiceReady(Document $document): bool
    {
        return $document->control_number && ($document->fiscal_emission_mode !== 'digital' || ($document->emission->status ?? '') === 'confirmed');
    }

    public static function voucherTaxableBase(Document $document, $iva, string $currency, $rate): float
    {
        $fiscalIva = FiscalAmounts::convert($document->total_igv,$document->currency_type_id,'VES',$document->exchange_rate_sale);
        if ($fiscalIva <= 0) FiscalAmounts::error('base','La factura no tiene IVA fiscal positivo.');
        $voucherIva = FiscalAmounts::convert($iva,$currency,'VES',$rate);
        $document->loadMissing('taxes');
        $rates=$document->taxes->where('tax_kind','IVA')->where('amount','>',0)->pluck('percentage')->unique();
        if ($rates->count() > 1 && abs($voucherIva-$fiscalIva)>0.01) {
            FiscalAmounts::error('base','Con alícuotas mixtas el comprobante debe cubrir el IVA completo; falta un desglose para determinar una base imponible parcial.');
        }
        $fiscalBase=FiscalAmounts::convert($document->total_taxed,$document->currency_type_id,'VES',$document->exchange_rate_sale);
        $taxable=ExchangeRateMath::rational($fiscalBase)->multipliedBy($voucherIva)->dividedBy($fiscalIva);
        return FiscalAmounts::convert(ExchangeRateMath::finalAmount($taxable),'VES',$currency,$rate);
    }

    public static function activate(Document $document, DocumentReceivedRetention $retention): void
    {
        $input = $retention->input_snapshot;
        self::validatePending($document, $input);
        FiscalDocumentPersistence::retentionValidated($document, $input, $retention);
        $retention->taxable_base = self::voucherTaxableBase($document,$retention->base,$retention->currency_type_id,$retention->exchange_rate);
        $retention->application_status = 'applied';
        $retention->diagnostic = null;
        $prevision = $document->fiscal_data->iva_retention_prevision ?? null;
        if ($prevision) {
            $retention->fiscal_context = array_replace($retention->fiscal_context,['prevision'=>$prevision]);
            $document->fiscal_data()->update(['iva_retention_prevision'=>null]);
            $document->unsetRelation('fiscal_data');
        }
        $retention->save();
        self::syncCreditBalance($document);
        if ($document->fiscal_emission_mode === 'digital' && $document->fiscal_environment === 'demo') HkaIvaRetention::enqueue($document, $retention, 'apply', $retention->operation_key);
    }

    public static function resume(Document $document): void
    {
        if (!self::available($document) || !self::invoiceReady($document)) return;
        foreach ($document->received_retentions()->where('tax_kind','IVA')->where('application_status','pending_validation')->get() as $candidate) {
            $document->getConnection()->transaction(function () use ($document, $candidate) {
                Company::query()->lockForUpdate()->firstOrFail();
                $locked = Document::query()->lockForUpdate()->findOrFail($document->id);
                $retention = DocumentReceivedRetention::query()->lockForUpdate()->findOrFail($candidate->id);
                if ($retention->application_status !== 'pending_validation' || !self::invoiceReady($locked)) return;
                try { $locked->getConnection()->transaction(fn()=>self::activate($locked, $retention)); }
                catch (ValidationException $exception) {
                    // A later payload validation may fail after local writes; the savepoint above
                    // rolls them back before preserving the voucher as invalid.
                    $retention->refresh()->update(['application_status' => 'invalid','applied_amount'=>0, 'diagnostic' => implode(' ', array_merge(...array_values($exception->errors()))) ]);
                    FiscalDocumentPersistence::updateSettlementState($locked);
                }
            });
            HkaIvaRetention::afterCommit($candidate);
        }
    }

    public static function reverse(DocumentReceivedRetention $retention, array $input): void
    {
        self::authorize($retention->document, 'delete_payment');
        $data = validator($input, ['operation_key'=>'required|uuid','reason'=>'required|string|max:255'])->validate();
        $data['operation_key'] = strtolower($data['operation_key']);
        if (trim($data['reason']) === '') FiscalAmounts::error('reason', 'Indique el motivo de reversión.');
        $retention->getConnection()->transaction(function () use ($retention, $data) {
            Company::query()->lockForUpdate()->firstOrFail();
            $document = Document::query()->lockForUpdate()->findOrFail($retention->document_id);
            $retention = DocumentReceivedRetention::query()->lockForUpdate()->findOrFail($retention->id);
            if ($retention->reversal_key) {
                if ($retention->reversal_key !== $data['operation_key'] || $retention->reversal_reason !== $data['reason']) self::operationConflict('La reversión ya tiene otra operación o motivo.');
                return;
            }
            if (DocumentReceivedRetention::where('operation_key',$data['operation_key'])->orWhere('reversal_key',$data['operation_key'])->exists()) self::operationConflict('La clave ya pertenece a otra solicitud IVA.');
            $retention->update(['reversal_key'=>$data['operation_key'], 'reversal_reason'=>$data['reason'], 'reversed_by'=>auth()->id()]);
            $remote = $retention->operations()->where('action','apply')->where(fn($q)=>$q->where('attempts','>',0)->orWhereIn('status',['confirmed','pending','uncertain','conflict']))->exists();
            if ($remote) HkaIvaRetention::enqueue($document, $retention, 'remove', $data['operation_key']);
            else {
                $retention->operations()->where('action','apply')->update(['status'=>'cancelled','attempt_id'=>null]);
                self::finishReversal($document, $retention);
            }
        });
        HkaIvaRetention::afterCommit($retention);
    }

    public static function finishReversal(Document $document, DocumentReceivedRetention $retention): void
    {
        if ($retention->application_status === 'reversed') return;
        $context = $retention->fiscal_context ?? [];
        $context['reversal'] = ['applied_amount'=>$retention->applied_amount,'actor_id'=>$retention->reversed_by,'reason'=>$retention->reversal_reason];
        $retention->update(['application_status'=>'reversed','applied_amount'=>0,'reversed_at'=>now(),'fiscal_context'=>$context]);
        if (!empty($context['prevision'])) $document->fiscal_data()->update(['iva_retention_prevision'=>json_encode($context['prevision'],JSON_THROW_ON_ERROR)]);
        FiscalDocumentPersistence::updateSettlementState($document);
        self::syncCreditBalance($document);
    }

    private static function syncCreditBalance(Document $document): void
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
        if (!self::available($document)) return ['enabled'=>false,'prevision'=>null,'records'=>[]];
        $document->loadMissing(['fiscal_data','received_retentions.operations']);
        $user = auth()->user();
        $scope = $user instanceof User && ($user->type === 'admin' || (int)$user->establishment_id === (int)$document->establishment_id);
        if ($user && !$scope) return ['enabled'=>false,'prevision'=>null,'records'=>[]];
        $canCreate = $scope && ($user->type === 'admin' || $user->create_payment);
        $canReverse = $scope && ($user->type === 'admin' || $user->delete_payment);
        $digitalDemo = $document->fiscal_emission_mode === 'digital' && $document->fiscal_environment === 'demo';
        $records = $document->received_retentions->where('tax_kind','IVA')->map(function ($row) use ($canCreate,$canReverse,$digitalDemo,$scope) {
            $op = $row->operations->sortByDesc('id')->first();
            return ['id'=>$row->id,'operation_key'=>$row->operation_key,'hka_operation_key'=>$op->operation_key ?? null,'voucher_number'=>$row->voucher_number,
                'voucher_date'=>$row->voucher_date->format('Y-m-d'),'agent_rif'=>$row->agent_number,'taxable_base'=>$row->taxable_base,
                'iva_amount'=>$row->base,'percentage'=>$row->percentage,'amount'=>$row->amount,'applied_amount'=>$row->applied_amount,
                'currency_type_id'=>$row->currency_type_id,'application_status'=>$row->application_status,
                'hka_status'=>$op->status ?? ($digitalDemo && $row->application_status === 'pending_validation' ? 'waiting_invoice' : 'not_applicable'),'diagnostic'=>$row->diagnostic ?: ($op->diagnostic ?? null),
                'reversal_pending'=>!empty($row->reversal_key) && !$row->reversed_at,
                'can_reverse'=>$canReverse && !$row->reversal_key,
                'can_query'=>$scope && $op !== null,'can_retry'=>$op && ($op->action === 'remove' ? $canReverse : $canCreate) && in_array($op->status,['waiting','uncertain'],true),
                'has_attachment'=>(bool)$row->attachment];
        })->values()->all();
        return ['enabled'=>true,'prevision'=>$document->fiscal_data->iva_retention_prevision ?? null,
            'applied_amount'=>ExchangeRateMath::finalAmount($document->received_retentions->where('tax_kind','IVA')->sum('applied_amount')),
            'balance'=>ExchangeRateMath::finalAmount($document->balance),'can_create'=>$canCreate,'records'=>$records];
    }
}
